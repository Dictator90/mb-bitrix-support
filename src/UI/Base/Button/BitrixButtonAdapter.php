<?php
namespace MB\Bitrix\UI\Base\Button;

use Bitrix\UI\Buttons\Button;
use Bitrix\UI\Buttons\BaseButton;
use MB\Bitrix\UI\Traits\HasEnabled;
use MB\Bitrix\UI\Traits\HasId;
use MB\Bitrix\UI\Base\BitrixAdapter;

/**
 * Адаптер для Bitrix-кнопки, проксирующий все вызовы методов к экземпляру Button через магический метод __call.
 *
 * @method $this setId($id)
 * @method $this setLink(string $link) Устанавливает ссылку, на которую будет вести кнопка.
 * @method $this setText(string $text) Устанавливает текст кнопки.
 * @method $this setTag(string $tag) Устанавливает HTML-тег для кнопки (например, 'a' или 'button').
 * @method $this setCounter($counter) Устанавливает счётчик на кнопке (например, число уведомлений).
 * @method $this addClass(string $className) Добавляет CSS-класс к кнопке.
 * @method $this removeClass(string $className) Удаляет CSS-класс у кнопки.
 * @method $this setDisabled(bool $flag = true) Делает кнопку неактивной (если true).
 * @method $this setMaxWidth(string $width) Устанавливает максимальную ширину кнопки (в пикселях или процентах).
 * @method $this addAttribute(string $name, $value = null) Добавляет произвольный HTML-атрибут кнопке.
 * @method $this removeAttribute(string $name) Удаляет указанный HTML-атрибут у кнопки.
 * @method $this addDataAttribute(string $name, $value = null) Добавляет data-атрибут (например, data-id="123").
 * @method $this setDataRole(string $dataRole) Устанавливает атрибут data-role, используемый в Bitrix для семантики.
 * @method $this setStyles(array $styles) Применяет инлайновые стили к кнопке (ассоциативный массив CSS-свойств).
 * @method $this bindEvent(string $eventName, $fn) Привязывает JavaScript-обработчик к событию (например, click).
 * @method $this bindEvents(array $events) Пакетно привязывает несколько обработчиков событий.
 * @method $this unbindEvent(string $eventName) Удаляет обработчик указанного события.
 * @method $this unbindEvents() Удаляет все обработчики событий с кнопки.
 * @method $this setColor(string $color) Устанавливает цветовое оформление кнопки (например, primary, light).
 * @method $this setIcon(string $icon) Добавляет иконку к кнопке (по имени иконки из библиотеки Bitrix).
 * @method $this setSize(string $size) Устанавливает размер кнопки (sm, md, lg и т.д.).
 * @method $this setState(string $state) Устанавливает состояние кнопки (например, loading, success).
 * @method $this setActive(bool $flag = true) Устанавливает активное состояние кнопки (внешне выделена).
 * @method $this setHovered(bool $flag = true) Имитирует состояние наведения курсора (для тестирования/визуализации).
 * @method $this setWaiting(bool $flag = true) Включает индикатор ожидания (прогресс-бар внутри кнопки).
 * @method $this setClocking(bool $flag = true) Включает анимацию "тикающих" точек (часто используется при ожидании).
 * @method $this setNoCaps(bool $flag = true) Отключает автоматическое приведение текста к верхнему регистру.
 * @method $this setRound(bool $flag = true) Делает углы кнопки скруглёнными.
 * @method $this setDropdown(bool $flag = true) Преобразует кнопку в выпадающее меню (стрелка вниз).
 * @method $this setCollapsed(bool $flag = true) Сворачивает кнопку (часто используется в паре с dropdown).
 * @method $this setMenu(array $options) Настраивает выпадающее меню: передаёт массив пунктов меню.
 *
 * @see Button
 * @see BaseButton
 */
class BitrixButtonAdapter extends BitrixAdapter
{
    use HasEnabled;

    public function __construct(Button $button)
    {
        parent::__construct($button);
    }

    /**
     * Проксирование вызовов к методам Button и BaseButton.
     *
     * @param string $name
     * @param array $arguments
     * @return $this|mixed
     */
    public function __call(string $name, array $arguments)
    {
        if (method_exists($this->renderer, $name)) {
            $result = $this->renderer->$name(...$arguments);
            return $result === $this->renderer ? $this : $result;
        }

        throw new \BadMethodCallException("Method {$name} not found in Button or BaseButton.");
    }
}