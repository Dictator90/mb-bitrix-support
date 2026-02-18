<?php

namespace MB\Bitrix\Module;

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager as BitrixModuleManager;
use MB\Bitrix\Contracts\Migration\Facade;
use MB\Core\Config\ConfigLocator;
use MB\Core\Config\ConfigManager;
use MB\Bitrix\Contracts\Module\Entity as ModuleEntityContract;
use MB\Bitrix\Contracts\Config\Entity as ConfigEntityContract;
use MB\Bitrix\Config\Entity as ConfigEntity;
use MB\Bitrix\Migration\Facade as MigrationFacade;
use MB\Core\Foundation\KernelApplication;
use MB\Core\Page\Asset;
use MB\Core\Settings\Page\PageManager;
use MB\Bitrix\Support\Facades\Filesystem as Fs;
use MB\Support\Str;
use Exception;

/**
 * Менеджер для работы с модулями Битрикс
 *
 * Класс предоставляет функционал для управления модулями, получения информации о модуле,
 * работы с путями, конфигурациями и миграциями.
 *
 * @package MB\Core
 *
 */
class Entity implements ModuleEntityContract
{
    protected string $id;

    /** @var string|null Абсолютный путь к директории модуля */
    protected ?string $modulePath;

    /** @var mixed Класс конфигурации модуля */
    protected $configClass = null;

    /** @var mixed Конфигурация установки модуля */
    protected $installConfig = null;

    protected Facade|null $migrationFacade = null;

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
        $this->fillCommonProperties();
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
     * @return string Путь к директории lib
     */
    public function getLibPath(): string
    {
        return $this->getPath() . '/lib';
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
        return ConfigEntity::class;
    }

    /**
     * @param string $siteId
     * @return Entity|null
     */
    public function getConfig(string $siteId = ''): ?ConfigEntity
    {
        $siteKey = empty($siteId) ? 'none' : $siteId;
        if (!$this->config[$siteKey]) {
            $this->config[$siteKey] = new ($this->getConfigClass())($this, $siteId);
        }

        return $this->config[$siteKey];
    }

    /**
     * Возвращает фасад для работы с миграциями модуля
     *
     * @return Facade Фасад миграций
     */
    public function getMigrationFacade(): MigrationFacade
    {
        //return app()->container($this->id)->migrationFacade();
    }

    public function getPageManager(): PageManager
    {
        return app()->container($this->id)->pageManager();
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
        return message(static::getLangPrefix() . $code, $replaces, $lang) ?: $fallback;
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

        if (!Fs::isDirectory($pathToInclude)) {
            $moduleHolder = Loader::BITRIX_HOLDER;
            $pathToInclude = "{$documentRoot}/{$moduleHolder}/modules/{$this->id}";
            if (!Fs::isDirectory($pathToInclude)) {
                $pathToInclude = null;
            }
        }

        $this->modulePath = $pathToInclude;
    }

    protected function fillConfig(): void
    {
        $this->configClass =
            ConfigLocator::getConfigByPath($this->getLibPath(), $this->getNamespace())
                ?: Entity::class;
    }

    protected function fillInstallConfig()
    {
        $intallJson = $this->getPath() . '/install/config.json';
        if ($this->id !== 'mb.core' && !Fs::isFile($intallJson)) {
            $intallJson = ModuleManager::get('mb.core')->getPath() . '/install/base.config.json';
        }

        if (Fs::isFile($intallJson)) {
            $this->installConfig = Fs::json($intallJson, true, []);
        }
    }

}
