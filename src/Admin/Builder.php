<?php

namespace MB\Core\Settings;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Context;
use Bitrix\Main\SiteTable;
use Bitrix\Main\Web\Uri;
use Bitrix\UI\Toolbar;
use CMain;
use JetBrains\PhpStorm\NoReturn;
use MB\Core\UI\Control\Tab\CustomTab;
use MB\Core\UI\Control\TabSet\BitrixTabSet;
use MB\Core\UI\Reference;

/**
 * Класс для построения интерфейса настроек модуля
 *
 * Предоставляет функционал для создания административного интерфейса настроек
 * с поддержкой множества сайтов, форм и таблиц данных.
 *
 * @package MB\Core\Settings
 */
final class Builder
{
    protected ?Reference\Grid\Base $grid = null;

    /** @var Options\Collection Коллекция опций настроек */
    protected Options\Collection $options;

    /** @var Toolbar\Toolbar|null Панель инструментов */
    protected ?Toolbar\Toolbar $toolbar = null;

    /** @var array Активное меню административного раздела */
    protected array $activeMenu = [];

    /** @var CMain|null Объект приложения */
    protected ?CMain $APPLICATION = null;

    /** @var string|null Заголовок страницы */
    protected string|null $title = null;

    /** @var bool Флаг поддержки множества сайтов */
    protected bool $multiSitesEnabled = false;

    public function __construct(Options\Base|Options\Collection $options, ?Reference\Grid\Base $grid = null)
    {
        global $APPLICATION, $adminMenu;

        if ($options instanceof Options\Base) {
            $options = new Options\Collection([$options]);
        }

        $this->options = $options;

        $this->APPLICATION = $APPLICATION;
        $this->activeMenu = $adminMenu && $adminMenu->aActiveSections ? $adminMenu->aActiveSections['_active'] : [];
        $this->createToolBar();

        if (!$grid) {
            $grid = new Reference\Grid\EmptyGrid();
        }

        $this->grid = $grid;
    }

    /**
     * Создает панель инструментов
     *
     * @return self
     * @throws ArgumentException
     */
    protected function createToolBar()
    {
        $this->toolbar =
            Toolbar\Manager::getInstance()
                ->createToolbar(
                    $this->activeMenu
                        ? md5($this->activeMenu['url'])
                        : 'toolbar',
                    []);
        return $this;
    }

    /**
     * Возвращает панель инструментов
     *
     * @return Toolbar\Toolbar|null
     */
    public function getToolBar(): ?Toolbar\Toolbar
    {
        return $this->toolbar;
    }

    /**
     * Устанавливает заголовок страницы
     *
     * @param string $value Заголовок
     * @return self
     */
    public function setTitle(string $value): static
    {
        $this->title = $value;
        return $this;
    }

    /**
     * Настраивает поддержку множества сайтов
     *
     * @param bool $value Включить/выключить поддержку
     * @return self
     */
    public function configureMultiSites(bool $value = true): static
    {
        $this->multiSitesEnabled = $value;
        return $this;
    }

    /**
     * Рендерит интерфейс настроек
     *
     * @return void
     */
    public function render(): void
    {
        if ($this->activeMenu['text']) {
            $this->APPLICATION->SetTitle($this->activeMenu['text']);
        } else {
            $this->setTitle('Настройки модуля');
        }

        $this->renderTollbar();

        $saved = false;

        $activeTab = null;

        echo '<div class="form-list">';

        if (!$this->multiSitesEnabled) {
            foreach ($this->options->getItems() as $options) {
                $options->getForm()->render();
                if ($options->getForm()->isSaved && !$saved) {
                    $saved = true;
                }
            }
        } else {
            if (!Context::getCurrent()->getRequest()->get('activeTab')) {
                $activeTab = 0;
            }
            $sites
                = SiteTable::query()
                    ->setSelect(['LID', 'SITE_NAME'])
                    ->setOrder(['SORT' => 'ASC'])
                    ->fetchAll();

            $tabSet = new BitrixTabSet();
            foreach ($sites as $i => $site) {

                /** @var Options\Base $options */
                $options = clone $this->options[0];
                $options->setSiteId($site['LID']);
                $form = $options->getForm();

                $tab = new CustomTab($form->getId());
                $tab->setLabel("[{$site['LID']}] {$site['SITE_NAME']}");

                if ($activeTab === 0 && $activeTab === $i) {
                    $tab->configureActive();
                }

                ob_start();
                $options->getForm()->render();
                if ($options->getForm()->isSaved && !$saved) {
                    $activeTab = $tab->getId();
                    $saved = true;
                }
                $html = ob_get_clean();
                $tab->setContent($html);

                $tabSet->addTab($tab);
            }

            $tabSet->render();
        }

        echo '</div>';

        if ($saved) {
            $this->savedRedirect($activeTab);
        }
    }

    /**
     * Выполняет редирект после сохранения настроек
     *
     * @param string|null $activeTab Идентификатор активной вкладки
     * @return void
     */
    #[NoReturn] protected function savedRedirect(string $activeTab = null): void
    {
        $uri = new Uri(Context::getCurrent()->getRequest()->getRequestUri());
        $params = ['saved' => 1];
        if ($activeTab) {
            $params['activeTab'] = $activeTab;
        }
        $uri->addParams($params);
        LocalRedirect($uri->getUri());
    }

    /**
     * Рендерит панель инструментов
     *
     * @return void
     */
    protected function renderTollbar()
    {
        $this->APPLICATION->IncludeComponent(
            'mb:admin.ui.toolbar',
            '',
            [
                'TOOLBAR_ID' => $this->getToolBar()->getId(),
                'TOOLBAR_TITLE' => $this->title ?: null,
            ]
        );
    }

    /**
     * @return Reference\Grid\Base
     */
    public function getGrid(): Reference\Grid\Base
    {
        return $this->grid;
    }

    /**
     * Возвращает форму настроек
     *
     * @return Reference\Form\Base
     */
    public function getForm(): Reference\Form\Base
    {
        return $this->options->getForm();
    }
}
