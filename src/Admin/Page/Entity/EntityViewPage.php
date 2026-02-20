<?php

namespace MB\Core\Settings\Page\Entity;

use Bitrix\Main\ORM\Entity;
use MB\Core\EntityView\Builder;

/**
 * Абстрактный класс страницы для отображения ORM-сущностей в виде Bitrix Grid
 *
 * Предоставляет базовую функциональность для создания страниц,
 * отображающих данные ORM-сущностей Bitrix через построитель представлений.
 * Автоматически устанавливает заголовок страницы и создает контейнер для контента.
 *
 * @package MB\Core\Settings\Page\Entity
 * @abstract
 */
abstract class EntityViewPage extends Base
{
    /**
     * Возвращает ORM-сущность для отображения
     *
     * Абстрактный метод, который должен быть реализован в классах-потомках
     * для определения основной сущности, данные которой будут отображаться на странице
     *
     * @return Entity
     * @abstract
     * @static
     */
    abstract public static function getEntity(): Entity;

    /**
     * Подготавливает параметры построителя представления
     *
     * Абстрактный метод для настройки построителя представления сущности.
     * Должен быть реализован в классах-потомках для конфигурации фильтров,
     * полей, сортировки и других параметров отображения данных.
     *
     * @param Builder &$builder
     * @return void
     * @abstract
     * @access protected
     */
    abstract protected function prepareParams(Builder &$builder);

    /**
     * Выполняет рендеринг страницы с данными сущности
     *
     * Устанавливает заголовок страницы, инициализирует построитель представления,
     * применяет параметры и выводит результат в обернутом контейнере.
     *
     * @return void
     * @access public
     */
    final public function render(): void
    {
        global $APPLICATION;

        $APPLICATION->SetTitle(static::getTitle());

        $builder = new Builder(static::getEntity());
        $this->prepareParams($builder);

        echo '<div class="mb-core-settings-container">';
        $builder->render();
        echo '</div>';
    }
}
