<?php

namespace MB\Core\Settings\Page\Entity;

/**
 * Абстрактный класс для создания страниц с контентом
 *
 * Предоставляет базовую структуру для рендеринга контента страниц.
 * Классы-потомки должны реализовать метод getContent() для определения
 * конкретного содержимого страницы.
 *
 * @package MB\Core
 * @subpackage Settings\Page\Reference
 * @abstract
 */
abstract class ContentPage extends Base
{
    /**
     * Возвращает содержимое страницы
     *
     * Абстрактный метод, который должен быть реализован в классах-потомках
     * для предоставления конкретного контента страницы (HTML, текст и т.д.)
     *
     * @return string Содержимое страницы для отображения
     * @abstract
     * @access protected
     */
    abstract protected function getContent(): string;

    /**
     * Выполняет рендеринг страницы
     *
     * Выводит содержимое, возвращаемое методом getContent(), напрямую в вывод.
     * Используется для отображения готового контента страницы в браузере.
     *
     * @return void
     * @access public
     * @final
     */
    final public function render()
    {
        echo $this->getContent();
    }
}
