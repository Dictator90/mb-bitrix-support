<?php
namespace MB\Bitrix\EntityView\Parameters\Item;

use Bitrix\Main\Type\Dictionary;

class GridColumn extends Dictionary
{
    /**
     * <code>
     *
     * array(
     *      'id' => string|boolean # Обязательное! Идентификатор колонки
     *      'name' => string # Обязательное! Заголовок колонки, который выводится пользователю.
     *      'type' => struing # Тип инпута для инлайн-редактировани. По умолчанию text. Смотреть - \Bitrix\Main\Grid\Column\Type
     *      'default' => bool # Определяет должна ли отображаться колонка по умолчанию в гриде. По умолчанию колонки не отображаются.
     *      'sort' => string # Идентификатор поля, по которому должна производиться сортировка.
     *      'first_order' => string # Направление первой сортировки колонки. Возможные значения: 'asc' или 'desc'.
     *      'showname' => boolean #Позволяет скрыть заголовок колонки. По умолчанию заголовок выводится.
     *      'width' => int # Ширина колонки в пикселях px.
     *      'align' => string # Выравнивание текста в ячейках колонки. Возможные значения:left/center/justify/right
     *      'class' => string # Пользовательский CSS-класс для заголовки колонки.
     *      'editable' => array # Определяет параметры инлайн-редактирования. Если не указано, то инлайн-редактирование отключено для ячеек колонки. Массив editable должен содержать обязательный параметр TYPE, который в качестве значения принимает константу из \Bitrix\Main\Grid\Editor\Types.
     *      'prevent_default' => boolean # Отменяет выделение строки при клике на ячейку колонки. Может быть полезно, когда в ячейку выводится какой-то интерактивный контент.
     *      'sticked' => boolean # Закрепляет колонку слева, при горизонтальной прокрутке.
     *      'resizeable' => boolean # Позволяет запретить изменять размер колонки. По умолчанию менять размер колонки разрешено.
     *      'color' => string # Фоновый цвет колонки. В качестве значения можно указать CSS-класс, либо цвет в формате hex, rgb или hsl. Также у грида есть набор стандартных цветов -  \Bitrix\Main\Grid\Column\Color.
     * )
     *
     * </code>
     *
     * @see \Bitrix\Main\Grid\Column\Type
     * @see \Bitrix\Main\Grid\Editor\Types
     * @see \Bitrix\Main\Grid\Column\Color
     *
     * @param array $params
     */
    public function __construct(array $params)
    {
        parent::__construct($params);
    }

    public function setColor(string $color)
    {
        $this->values['color'] = $color;
        return $this;
    }

    public function configureEditable($value = true)
    {
        $this->values['editable'] = $value;
        return $this;
    }
}
