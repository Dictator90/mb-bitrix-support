<?php

namespace MB\Core\Settings\Page\Entity;

use Bitrix\UI\Toolbar;
use MB\Core\Module\ModuleContainer;
use MB\Core\Module\ModuleEntity;
use MB\Core\Settings\Builder;
use MB\Core\Settings\Options;
use MB\Core\UI\Reference\Grid;

/**
 * Абстрактный класс страницы настроек с использованием билдера опций
 *
 * Предоставляет базовую функциональность для создания страниц настроек
 * с использованием системы опций, гридов MB UI и тулбара Bitrix UI.
 * Автоматически обрабатывает рендеринг через билдер настроек.
 *
 * @package MB\Core\Settings\Page\Entity
 * @abstract
 */
abstract class OptionsPage extends Base
{
    /**
     * Экземпляр билдера настроек
     *
     * @var Builder|null
     * @access protected
     */
    protected ?Builder $builder = null;

    /**
     * Возвращает карту опций страницы настроек
     *
     * Абстрактный метод, который должен быть реализован в классах-потомках
     * для определения структуры и полей настроек страницы
     *
     * @return Options\Collection|Options\Base
     * @abstract
     * @static
     */
    abstract public static function getMap(): Options\Collection|Options\Base;


    /**
     * Конструктор класса
     *
     * Автоматически инициализирует билдер настроек при создании экземпляра
     *
     * @access public
     */
    public function __construct(protected ModuleEntity $container)
    {
        parent::__construct($container);
        $this->fillBuilder();
    }

    /**
     * Выполняет рендеринг страницы настроек
     *
     * Финальный метод, который делегирует рендеринг билдеру настроек.
     * Не может быть переопределен в классах-потомках.
     *
     * @return void
     * @access public
     * @final
     */
    final public function render(): void
    {
        $this->builder->render();
    }

    /**
     * Подготавливает тулбар страницы
     *
     * Метод для настройки и добавления элементов в тулбар Bitrix UI.
     * Может быть переопределен в классах-потомках для кастомизации тулбара.
     *
     * @param Toolbar\Toolbar &$toolbar
     * @return void
     * @access protected
     * @static
     */
    protected static function prepareToolbar(Toolbar\Toolbar &$toolbar): void
    {}

    /**
     * Определяет поддержку мультисайтовости
     *
     * Указывает, должны ли настройки применяться для всех сайтов
     * или только для текущего. По умолчанию отключено.
     *
     * @return bool
     * @access protected
     * @static
     */
    protected static function multiSiteEnabled(): bool
    {
        return false;
    }

    /**
     * Возвращает грид CSS для отображения данных
     *
     * Может быть переопределен в классах-потомках для использования
     * кастомных гридов. По умолчанию возвращает пустой грид.
     *
     * @return Grid\Base
     * @access protected
     * @static
     */
    protected static function getGrid(): Grid\Base
    {
        return new Grid\EmptyGrid();
    }

    /**
     * Заполняет билдер настройками и конфигурацией
     *
     * Финальный метод, который инициализирует билдер, настраивает опции,
     * грид, мультисайтовость и заголовок страницы.
     *
     * @return void
     * @access protected
     * @final
     */
    final protected function fillBuilder(): void
    {

        $map = static::getMap();
        $options = match (true) {
            $map instanceof Options\Collection => $map,
            $map instanceof Options\Base => new Options\Collection([$map]),
            default => new Options\Collection(),
        };

        $this->builder = new Builder($options, static::getGrid());
        $this->builder->configureMultiSites(static::multiSiteEnabled());

        if ($title = static::getTitle()) {
            $this->builder->setTitle($title);
        }

        $toolbar = $this->builder->getToolBar();
        static::prepareToolbar($toolbar);
    }
}
