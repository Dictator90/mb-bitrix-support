<?php

namespace MB\Core\Settings\Options;

use MB\Core\UI\Control\Form;
use MB\Core\UI\Reference\Field;
use MB\Core\UI\Reference\Form\Base as FormBase;
use MB\Core\UI\Reference\Row;
use MB\Core\UI\Reference\Tab;

/**
 * Абстрактный базовый класс для опций настроек модуля
 *
 * Предоставляет базовый функционал для создания и управления настройками модуля,
 * включая работу с формами, полями ввода и конфигурациями.
 *
 * @package MB\Core\Settings
 */
abstract class Base
{
    /**
     * Возвращает карту полей настроек
     *
     * Абстрактный метод, который должен быть реализован в потомках.
     * Определяет структуру полей формы настроек.
     *
     * @return array Массив с определением полей формы (вкладки, строки, поля)
     */
    abstract public static function getMap(): array;

    /** @var string Исходный идентификатор опций */
    protected string $rawId;

    /** @var string|null Идентификатор сайта (для мультисайтовости) */
    protected false|string $siteId;

    /** @var FormBase Объект формы настроек */
    protected FormBase $form;

    /** @var array Массив опций */
    protected array $options = [];

    /**
     * Возвращает класс формы по умолчанию
     *
     * @return string Класс формы
     */
    public static function getFormClass(): string
    {
        return Form\Bitrix::class;
    }

    /**
     * Конструктор класса
     *
     * @param string $id Идентификатор опций
     * @param string|bool $siteId Идентификатор сайта (false если не используется)
     */
    public function __construct(string $id, $siteId = false)
    {
        $this->rawId = $id;
        $this->siteId = $siteId;

        $this->form = new (static::getFormClass())($id, $siteId);
        $this->form->setOptions($this);
        $this->form->checkRequest();
    }

    /**
     * Возвращает исходный идентификатор опций
     *
     * @return string Исходный идентификатор
     */
    public function getRawId(): string
    {
        return $this->rawId;
    }

    /**
     * Устанавливает идентификатор сайта
     *
     * @param string $value Идентификатор сайта
     * @return self
     */
    public function setSiteId(string $value): static
    {
        $this->siteId = $value;

        $form = new (static::getFormClass())($this->rawId, $this->siteId);
        $this->setForm($form);

        return $this;
    }

    /**
     * Возвращает идентификатор сайта
     *
     * @return string|null Идентификатор сайта или null если не установлен
     */
    public function getSiteId(): ?string
    {
        return $this->siteId;
    }

    /**
     * Устанавливает объект формы
     *
     * @param FormBase $form Объект формы
     * @return self
     */
    public function setForm(FormBase $form): static
    {
        $this->form = $form;
        $this->form->setOptions($this);
        $this->form->checkRequest();

        return $this;
    }

    /**
     * Возвращает объект формы настроек
     *
     * @return FormBase Объект формы
     */
    public function getForm(): FormBase
    {
        return $this->form;
    }

    /**
     * Возвращает идентификатор формы
     *
     * @return string Идентификатор формы
     */
    public function getId(): string
    {
        return $this->form->getId();
    }

    /**
     * Возвращает все значения настроек
     *
     * @return array Ассоциативный массив всех опций
     */
    public function getAllValues(): array
    {
        return config(siteId: $this->siteId ?: '')->getAll();
    }

    /**
     * Возвращает все поля ввода формы
     *
     * Рекурсивно обходит карту полей и извлекает все поля ввода.
     *
     * @return Field\AbstractInputField[] Массив объектов полей ввода
     */
    public function getInputFields()
    {
        $result = [];
        foreach (static::getMap() as $row) {
            $this->extractFields($result, $row);
        }

        return $result;
    }

    /**
     * Рекурсивно извлекает поля из элемента формы
     *
     * Обрабатывает различные типы элементов: наборы вкладок, вкладки, строки, поля.
     *
     * @param Field\AbstractInputField[] &$result Результирующий массив полей
     * @param mixed &$row Элемент формы для обработки
     * @return void
     */
    protected function extractFields(&$result, &$row)
    {
        if ($row instanceof Tab\Set) {
            foreach ($row->getTabs() as $tab) {
                $this->extractFields($result, $tab);
            }
        } elseif ($row instanceof Tab\Base) {
            foreach ($row->getRows() as $row) {
                $this->extractFields($result, $row);
            }
        } elseif ($row instanceof Row\ChildrenBase) {
            foreach ($row->getChildren() as $fields) {
                $this->extractFields($result, $fields);
            }
        } elseif ($row instanceof Field\AbstractBaseField) {
            $result[] = $row;
        } elseif (is_array($row)) {
            foreach ($row as $i) {
                $this->extractFields($result, $i);
            }
        }
    }
}
