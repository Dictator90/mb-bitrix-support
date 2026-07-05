<?php

declare(strict_types=1);

namespace MB\Bitrix\Module;

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager as BitrixModuleManager;
use Exception;
use MB\Bitrix\AdminKit\Manager\AdminKitManager;
use MB\Bitrix\Config\ConfigLocator;
use MB\Bitrix\Config\Entity as ConfigEntity;
use MB\Bitrix\Contracts\Config\Entity as ConfigEntityContract;
use MB\Bitrix\Contracts\Module\Entity as ModuleEntityContract;
use MB\Bitrix\Filesystem\Filesystem;
use MB\Bitrix\Migration\Facade as MigrationFacade;
use MB\Support\Str;

/**
 * Менеджер для работы с модулями Битрикс
 *
 * Класс предоставляет функционал для управления модулями, получения информации о модуле,
 * работы с путями, конфигурациями и миграциями.
 *
 * @package MB\Bitrix
 *
 */
class Entity implements ModuleEntityContract
{
    /**
     * Во время {@see __construct} сюда кладётся экземпляр, чтобы {@see module()} не шёл в контейнер
     * повторно (иначе {@see \MB\Container\Container::make} ловит re-entrant по тому же abstract и падает с circular).
     *
     * @var array<string, self>
     */
    private static array $constructionStack = [];

    protected string $id;

    /** @var string|null Абсолютный путь к директории модуля */
    protected ?string $modulePath;

    /** @var class-string<ConfigEntityContract> */
    protected string $configClass = ConfigEntity::class;

    /** @var mixed Конфигурация установки модуля */
    protected $installConfig = null;

    protected MigrationFacade|null $migrationFacade = null;

    protected array $config = [];

    /**
     * Конструктор класса
     *
     * @param string $id Идентификатор модуля
     * @throws Exception Если модуль не установлен или не может быть включен
     */
    public function __construct(string $id)
    {
        $this->id = Str::lower(Str::trim($id));
        self::$constructionStack[$this->id] = $this;
        try {
            $this->fillCommonProperties();
        } finally {
            unset(self::$constructionStack[$this->id]);
        }
    }

    /**
     * Экземпляр модуля, который сейчас в процессе создания (для {@see module()} при re-entry из init.php).
     */
    public static function peekDuringConstruction(string $id): ?self
    {
        $id = Str::lower(Str::trim($id));

        return self::$constructionStack[$id] ?? null;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Возвращает абсолютный путь к директории модуля
     *
     * @return string|null Абсолютный путь к модулю или null, если путь не найден
     */
    public function getPath(): ?string
    {
        return $this->modulePath;
    }

    /**
     * Возвращает относительный путь к директории модуля от корня сайта
     *
     * @return string|null Относительный путь к модулю
     */
    public function getLocalPath(): ?string
    {
        return str_replace(Application::getDocumentRoot(), '', $this->getPath());
    }

    /**
     * Возвращает абсолютный путь к директории lib модуля
     *
     * @return string|null Путь к директории lib или null, если путь к модулю неизвестен
     */
    public function getLibPath(): ?string
    {
        $path = $this->getPath();

        return $path !== null ? $path . '/lib' : null;
    }

    /**
     * Возвращает базовое пространство имен модуля
     *
     * Преобразует идентификатор модуля (например: vendor.module) в пространство имен (например: \Vendor\Module)
     *
     * @return string Базовое пространство имен модуля
     */
    public function getNamespace(): string
    {
        $module = explode('.', $this->id);
        return '\\' . Str::ucfirst($module[0]) . '\\' . Str::ucfirst($module[1]);
    }

    /**
     * Возвращает класс конфигурации модуля
     *
     * @return class-string<ConfigEntityContract> Класс конфигурации или null, если не найден
     */
    public function getConfigClass(): string
    {
        return $this->configClass;
    }

    /**
     * @param string $siteId
     */
    public function getConfig(string $siteId = ''): ?ConfigEntity
    {
        $siteKey = empty($siteId) ? 'none' : $siteId;
        if (empty($this->config[$siteKey])) {
            $this->config[$siteKey] = new ($this->getConfigClass())($this, $siteId);
        }

        return $this->config[$siteKey];
    }

    /**
     * Возвращает фасад для работы с миграциями модуля
     *
     * @return MigrationFacade Фасад миграций
     */
    public function getMigrationFacade(): MigrationFacade
    {
        return $this->migrationFacade ??= app()->container($this->id)->migrationFacade();
    }

    public function adminKit(): AdminKitManager
    {
        return app()->container($this->id)->adminKit();
    }

    /**
     * Возвращает конфигурацию установки модуля
     *
     * @return array Конфигурация установки модуля
     */
    public function getInstallConfig(): array
    {
        return $this->installConfig;
    }

    protected function getLangPrefix(): string
    {
        $n = explode('.', $this->id);
        return Str::upper($n[0]) . '_' . Str::upper($n[1]) . '_';
    }

    /**
     * Возвращает языковой флаг с префиксом равным модулю (нап. mb.core => MB_CORE_)
     * В основном нужен для работы внутри модуля и его языковых файлов.
     *
     * @param string $code
     * @param array|null $replaces
     * @param string|null $fallback
     * @param string|null $lang
     * @return string|null
     */
    final public function getLang(string $code, ?array $replaces = null, ?string $fallback = null, ?string $lang = LANGUAGE_ID): ?string
    {
        return __loc(static::getLangPrefix() . $code, $replaces, $lang) ?: $fallback;
    }

    final public function includeLangFile(string $file = 'common')
    {
        Loc::loadMessages($this->getPath() . '/' . $file . '.lang.php');
    }

    /**
     * Заполняет общие свойства модуля
     *
     * @throws Exception Если модуль не установлен или не может быть включен
     */
    protected function fillCommonProperties()
    {
        if (!$this->id) {
            throw new Exception('Module id can\'t be empty');
        }

        if (
            BitrixModuleManager::isModuleInstalled($this->id)
            && Loader::includeModule($this->id)
        ) {
            $this->fillPath();
            $this->fillConfig();
            $this->fillInstallConfig();
        } else {
            throw new Exception("Module `{$this->id}` not installed or not included");
        }
    }

    protected function fillPath()
    {
        $documentRoot = Loader::getDocumentRoot();
        $moduleHolder = Loader::LOCAL_HOLDER;
        $pathToInclude = "{$documentRoot}/{$moduleHolder}/modules/{$this->id}";

        if (!Filesystem::instance()->isDirectory($pathToInclude)) {
            $moduleHolder = Loader::BITRIX_HOLDER;
            $pathToInclude = "{$documentRoot}/{$moduleHolder}/modules/{$this->id}";
            if (!Filesystem::instance()->isDirectory($pathToInclude)) {
                $pathToInclude = null;
            }
        }

        $this->modulePath = $pathToInclude;
    }

    protected function fillConfig(): void
    {
        $libPath = $this->getLibPath();
        $this->configClass = $libPath !== null
            ? (ConfigLocator::getConfigByPath($libPath, $this->getNamespace()) ?: ConfigEntity::class)
            : ConfigEntity::class;
    }

    protected function fillInstallConfig()
    {
        $intallJson = $this->getPath() . '/install/config.json';
        if ($this->id !== 'mb.core' && !Filesystem::instance()->isFile($intallJson)) {
            $intallJson = (new self('mb.core'))->getPath() . '/install/base.config.json';
        }

        if (Filesystem::instance()->isFile($intallJson)) {
            $this->installConfig = Filesystem::instance()->json($intallJson, true, []);
        }
    }

}
