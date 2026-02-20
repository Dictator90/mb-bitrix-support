<?php

namespace MB\Bitrix\UI\Base\Field;

use MB\Bitrix\Contracts\UI\Renderable;
use MB\Bitrix\UI\Traits\RendersWithConditions;
use MB\Bitrix\UI\Traits\HasEnabled;
use MB\Bitrix\UI\Traits\HasId;
use MB\Bitrix\UI\Traits\HasStyle;
use MB\Bitrix\UI\Traits\HasValue;
use MB\Bitrix\UI\Base\Form;
use MB\Bitrix\UI\Base\Row;
use MB\Bitrix\UI\Base\Traits\HasCondition;

/**
 * Абстрактный базовый класс для полей формы.
 *
 * Предоставляет общую функциональность и интерфейс для всех типов полей.
 * Включает поддержку ID, значения, стилей, включения/отключения,
 * условий отображения, а также связь с формой и строкой (row).
 *
 * @package MB\Bitrix\UI\Base\Field
 */
abstract class AbstractBaseField implements Renderable
{
    use HasId;
    use HasValue;
    use HasStyle;
    use HasEnabled;
    use HasCondition;
    use RendersWithConditions;

    /**
     * Ссылка на форму, к которой принадлежит поле.
     *
     * @var Form\Base|null
     */
    protected ?Form\Base $form = null;

    /**
     * Ссылка на строку (row), к которой принадлежит поле.
     *
     * @var Row\Base|null
     */
    protected ?Row\Base $row = null;

    /**
     * Абстрактный метод для получения HTML-представления поля.
     *
     * Должен быть реализован в дочерних классах.
     *
     * @return string HTML-код поля.
     */
    abstract public function getHtml(): string;

    /**
     * Метод, вызываемый перед рендером поля.
     *
     * Может быть переопределён в дочерних классах.
     *
     * @return void
     */
    protected function beforeRender() {}

    /**
     * Метод, вызываемый после рендера поля.
     *
     * Может быть переопределён в дочерних классах.
     *
     * @return void
     */
    protected function afterRender() {}

    /**
     * Метод, вызываемый перед сохранением значения поля.
     *
     * Позволяет модифицировать значение по ссылке.
     *
     * @param mixed $value Значение поля, передаваемое по ссылке.
     * @return void
     */
    public function beforeSave(&$value) {}

    /**
     * Устанавливает форму для поля.
     *
     * @param Form\Base $form Экземпляр формы.
     * @return $this
     */
    public function setForm(Form\Base $form)
    {
        $this->form = $form;
        return $this;
    }

    /**
     * Возвращает форму, к которой привязано поле.
     *
     * @return Form\Base|null
     */
    public function getForm(): ?Form\Base
    {
        return $this->form;
    }

    /**
     * Устанавливает строку (row) для поля.
     *
     * @param Row\Base $row Экземпляр строки.
     * @return $this
     */
    public function setRow(Row\Base $row)
    {
        $this->row = $row;
        return $this;
    }

    /**
     * Возвращает строку (row), к которой привязано поле.
     *
     * @return Row\Base|null
     */
    public function getRow(): ?Row\Base
    {
        return $this->row;
    }

    /**
     * Преобразует поле в массив данных.
     *
     * Включает ID, значение и стили.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'value' => $this->getValue(),
            'style' => $this->getStyleArray()
        ];
    }
}