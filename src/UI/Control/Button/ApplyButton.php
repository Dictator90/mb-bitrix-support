<?php
namespace MB\Bitrix\UI\Control\Button;

use MB\Bitrix\UI\Base\Button\BitrixButtonAdapter;
use Bitrix\UI\Buttons\ApplyButton as BitrixApplyButton;

/**
 * Класс ApplyButton представляет собой обёртку для Bitrix\UI\Buttons\ApplyButton.
 * Предоставляет удобный интерфейс для создания и настройки кнопок в пользовательском интерфейсе.
 *
 * @see \Bitrix\UI\Buttons\ApplyButton
 * @see \Bitrix\UI\Buttons\Button
 */
class ApplyButton extends BitrixButtonAdapter
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
        dump($params);
        dump($this->isEnabled());
        parent::__construct(new BitrixApplyButton($params));
    }
}