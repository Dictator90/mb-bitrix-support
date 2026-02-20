<?php
namespace MB\Bitrix\UI\Control\Button;

use MB\Bitrix\UI\Base\Button\BitrixButtonAdapter;
use Bitrix\UI\Buttons\Button as BitrixButton;

/**
 * Класс Button представляет собой обёртку для Bitrix\UI\Buttons\Button.
 * Предоставляет удобный интерфейс для создания и настройки кнопок в пользовательском интерфейсе.
 *
 * @see \Bitrix\UI\Buttons\Button
 */
class Button extends BitrixButtonAdapter
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
        if ($params['click'] || $params['onclick']) {

        }
        parent::__construct(new BitrixButton($params));
    }

    public function getHtml(): string
    {
        return $this->renderer->render();
    }
}