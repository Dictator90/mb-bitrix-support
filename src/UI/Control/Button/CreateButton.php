<?php
namespace MB\Bitrix\UI\Control\Button;

use MB\Bitrix\UI\Base\Button\BitrixButtonAdapter;
use Bitrix\UI\Buttons\CreateButton as BitrixCreateButton;

/**
 * Класс ApplyButton представляет собой обёртку для Bitrix\UI\Buttons\ApplyButton.
 * Предоставляет удобный интерфейс для создания и настройки кнопок в пользовательском интерфейсе.
 *
 * @see \Bitrix\UI\Buttons\CreateButton
 * @see \Bitrix\UI\Buttons\Button
 */
class CreateButton extends BitrixButtonAdapter
{
    /**
     * Конструктор класса Button.
     *
     * @param array $params
     * Массив параметров для инициализации кнопки.
     * Поддерживаемые параметры зависят от Bitrix\UI\Buttons\Button.
     */
    public function __construct(array $params = [])
    {
        parent::__construct(new BitrixCreateButton($params));
    }
}