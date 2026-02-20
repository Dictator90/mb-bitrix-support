<?php

namespace MB\Core\Settings\Page\Entity;

use Illuminate\Contracts\Support\Renderable;
use MB\Core\Module\ModuleContainer;
use MB\Core\Module\ModuleEntity;

/**
 * Абстрактный базовый класс для страниц настроек системы
 *
 * Предоставляет общий интерфейс и базовую реализацию для страниц,
 * которые могут быть отображены в интерфейсе управления системой.
 * Реализует контракт Renderable для обеспечения совместимости
 * с фреймворком Laravel/Illuminate.
 *
 * @package MB\Core
 * @subpackage Settings\Page\Entity
 * @abstract
 * @implements Renderable
 */
abstract class Base
    implements Renderable
{

    /**
     * Возвращает уникальный идентификатор страницы
     *
     * Используется для идентификации страницы в системе,
     * маршрутизации и построения меню
     *
     * @return string Уникальный идентификатор страницы
     * @abstract
     * @static
     */
    abstract public static function getId(): string;

    /**
     * Возвращает заголовок страницы для отображения в интерфейсе
     *
     * @return string Заголовок страницы
     * @abstract
     * @static
     */
    abstract public static function getTitle(): string;

    public function __construct(protected ModuleEntity $container){}

    /**
     * Определяет, активна ли страница в системе
     *
     * Используется для управления видимостью страницы в меню
     * и доступностью функционала
     *
     * @return bool true если страница активна, false если скрыта
     * @static
     */
    public static function isActive(): bool
    {
        return true;
    }

    /**
     * Определяет, является ли страница системной
     *
     * Системные страницы обычно не могут быть отключены
     * и содержат критически важный функционал
     *
     * @return bool true если страница системная, false если пользовательская
     * @static
     */
    public static function isSystem(): bool
    {
        return false;
    }

    /**
     * Возвращает иконку для отображения в меню
     *
     * Может возвращать название класса иконки, путь к изображению
     * или другую строку, понятную системе отображения меню
     *
     * @return string Строка с классом иконки
     * @static
     */
    public static function getMenuIcon(): string
    {
        return '';
    }

    /**
     * Возвращает порядковый номер для сортировки в меню
     *
     * Меньшие значения отображаются выше в списке.
     * Значение по умолчанию - 500.
     *
     * @return int Числовой приоритет для сортировки
     * @static
     */
    public static function getSort(): int
    {
        return 500;
    }

    /**
     * Возвращает полное имя класса с пространством имен
     *
     * Используется для рефлексии, создания экземпляров
     * и идентификации классов в системе
     *
     * @return string Полное имя класса
     * @static
     */
    public static function getClassName(): string
    {
        return '\\' . get_called_class();
    }

    /**
     * Возвращает класс родительского пункта меню
     *
     * Используется для построения иерархической структуры меню.
     * Если возвращает null, страница отображается в корне меню.
     *
     * @return string|null Имя класса родительского пункта меню или null
     * @static
     */
    public static function getParentMenuClass(): ?string
    {
        return null;
    }
}
