<?php

namespace MB\Bitrix\UI\Control\Field;

use Bitrix\Main\UI\Extension;
use Bitrix\Main\Web\Json;
use MB\Bitrix\UI\Base\Field\AbstractEntitySelectorField;

/**
 * Поле выбора с диалоговым окном.
 *
 * Позволяет выбирать элементы из списка, сгруппированных по вкладкам.
 * Поддерживает множественный выбор.
 */
class DialogSelectorField extends AbstractEntitySelectorField
{
    /**
     * Массив элементов для отображения в диалоге.
     * 
     * Формат:
     * <code>
     *     array(
     *       array(
     *         'id' => 1,
     *         'title' => 'Название',
     *         'subtitle' => 'Подзаголовок',
     *         'tabs' => ['tabId']
     *       ),
     *       ...
     *     )
     * </code>
     *
     * @var array
     */
    protected array $items = [];

    /**
     * Массив вкладок диалога.
     *
     * Формат:
     * <code>
     *     array(
     *       array(
     *          'id' => 'tabId',
     *          'title' => 'Название вкладки',
     *       ),
     *       ...
     *     )
     * </code>
     *
     * @var array
     */
    protected array $tabs = [];

    /**
     * Конструктор поля.
     *
     * Инициализирует элементы и вкладки через статические методы.
     *
     * @param string $name Имя поля.
     */
    public function __construct(string $name)
    {
        $this->items = static::getItems();
        $this->tabs = static::getTabs();

        parent::__construct($name);
    }

    /**
     * Генерирует HTML-код поля.
     *
     * Подключает необходимые JS-расширения и рендерит скрипт инициализации.
     *
     * @return string HTML-строка.
     */
    public function getHtml(): string
    {
        Extension::load(['ui', 'mb.ui.dialog-selector']);

        return <<<DOC
            <div id="tag_selector_{$this->getName()}"></div>
            <script>
                (new  MB.UI.DialogSelector.DialogSelector({
                    target: '#tag_selector_{$this->getName()}',
                    name: '{$this->getName()}',
                    dialog: {
                        context: 'MB_CORE_{$this->getName()}',
                        items: {$this->getItemsJson()},
                        tabs: {$this->getTabsJson()},
                        dropdownMode: true,
                    },
                    multiple: {$this->isMultipleJson()},
            
                })).render();
            </script>
DOC;
    }

    /**
     * Возвращает элементы для диалога.
     * 
     * Может быть переопределён в дочерних классах.
     *
     * @return array
     */
    protected static function getItems(): array
    {
        return [];
    }

    /**
     * Возвращает вкладки для диалога.
     *
     * Может быть переопределён в дочерних классах.
     *
     * @return array
     */
    protected static function getTabs(): array
    {
        return [];
    }

    /**
     * Преобразует элементы в JSON, устанавливая флаг selected для выбранных.
     *
     * @return string
     */
    protected function getItemsJson()
    {
        if ($curValue = $this->getValue()) {
            if (!is_array($curValue)) {
                $curValue = [$curValue];
            }
            foreach ($this->items as $i => $item) {
                if (in_array($item['id'], $curValue)) {
                    $this->items[$i]['selected'] = true;
                }
            }
        }

        return Json::encode($this->items);
    }

    /**
     * Преобразует вкладки в JSON.
     *
     * @return string
     */
    protected function getTabsJson()
    {
        return Json::encode($this->tabs);
    }

    /**
     * Устанавливает содержимое вкладок.
     *
     * Автоматически добавляет вкладки и соответствующие элементы.
     *
     * Формат входного массива:
     * <code>
     *     array(
     *        'my-tab' => array(
     *            'title' => 'Заголовок вкладки',
     *            'items' => array(
     *                array(
     *                    'id' => 1,
     *                    'title' => 'Название',
     *                    'subtitle' => 'Подзаголовок'
     *                ),
     *                ...
     *            ),
     *        ),
     *        ...
     *     )
     * </code>
     *
     * @param array $tabsContent Ассоциативный массив с данными вкладок.
     * @return $this
     */
    public function setTabsContent(array $tabsContent)
    {
        foreach ($tabsContent as $tabId => $tabData) {
            $this->addTab([
                'id' => $tabId,
                'title' => $tabData['title']
            ]);

            foreach ($tabData['items'] as $item) {
                $this->addItem([
                    'id' => $item['id'],
                    'entityId' => 'mbDialogEntity',
                    'title' => $item['title'],
                    'subtitle' => $item['subtitle'],
                    'tabs' => $tabId
                ]);
            }
        }

        return $this;
    }

    /**
     * Добавляет новую вкладку.
     *
     * @param array $tab Массив с ключами 'id' и 'title'.
     * @return $this
     */
    public function addTab(array $tab)
    {
        $this->tabs[] = $tab;
        return $this;
    }

    /**
     * Устанавливает список вкладок.
     *
     * @param array $tabs Список вкладок.
     * @return $this
     */
    public function setTabs(array $tabs)
    {
        $this->tabs = $tabs;
        return $this;
    }

    /**
     * Добавляет элемент в список выбора.
     *
     * @param array $item Элемент с данными (id, title, subtitle, tabs и т.д.).
     * @return $this
     */
    public function addItem(array $item)
    {
        $this->items[] = $item;
        return $this;
    }

    /**
     * Устанавливает все элементы.
     *
     * @param array $items Список элементов.
     * @return $this
     */
    public function setItems(array $items)
    {
        $this->items = $items;
        return $this;
    }
}