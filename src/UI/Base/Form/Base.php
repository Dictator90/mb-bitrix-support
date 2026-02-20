<?php

namespace MB\Bitrix\UI\Base\Form;

use Bitrix\Main\Context;
use Bitrix\Main\Event;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\Loader;
use Bitrix\Main\Request;
use Bitrix\Main\UI\Extension;
use Bitrix\Main\Web\Uri;
use MB\Bitrix\Module\ModuleEntity;
use MB\Bitrix\Support\Data\TextString;
use MB\Bitrix\UI\Control\TabSet\BitrixTabSet;
use MB\Bitrix\Settings\Options;
use MB\Bitrix\UI\Base as UiBase;
use MB\Bitrix\UI\Base\Field;
use MB\Bitrix\UI\Base\Row;
use MB\Bitrix\UI\Base\Tab;
use MB\Bitrix\UI\Traits\HasId;
use MB\Bitrix\UI\Traits\HasRequest;
use MB\Bitrix\UI\Traits\HasSiteId;

abstract class Base
{
    use HasId;
    use HasRequest;
    use HasSiteId;

    public const MODE_ACTION_SETTINGS = 'settings';
    public const MODE_ACTION_CUSTOM = 'custom';

    public const EVENT_ON_FORM_SAVE_ACTION = 'onFormSaveActionRequest';

    public bool $isSaved = false;
    //abstract function getHtmlStart(): string;
    //abstract function getHtmlEnd(): string;

    protected Request|HttpRequest $request;

    protected ?ModuleEntity $module = null;

    protected ?Options\Base $optionsEntity = null;

    protected ?array $options = null;

    protected array $optionsValue = [];

    protected Tab\Set $tabSet;

    protected $uri = null;

    protected ?RightsCheckerInterface $rightsChecker = null;

    protected ?ButtonPanelRendererInterface $buttonPanelRenderer = null;

    /**
     * @param string $id Form identifier
     * @param false|string $siteId Site ID or false
     * @param Request|HttpRequest|null $request Optional request (default: Context::getCurrent()->getRequest())
     * @param RightsCheckerInterface|null $rightsChecker Optional (default: BitrixRightsChecker)
     * @param ButtonPanelRendererInterface|null $buttonPanelRenderer Optional (default: BitrixButtonPanelRenderer)
     */
    public function __construct(
        string $id,
        $siteId = false,
        Request|HttpRequest|null $request = null,
        ?RightsCheckerInterface $rightsChecker = null,
        ?ButtonPanelRendererInterface $buttonPanelRenderer = null
    ) {
        $this->setSiteId($siteId);
        $this->request = $request ?? Context::getCurrent()->getRequest();
        $this->uri = new Uri($this->request->getRequestUri());
        $this->tabSet = new BitrixTabSet();
        $this->rightsChecker = $rightsChecker ?? new BitrixRightsChecker();
        $this->buttonPanelRenderer = $buttonPanelRenderer ?? new BitrixButtonPanelRenderer();

        $this->setId($this->buildId($id, $siteId));
    }

    public function setModule(ModuleEntity $module): static
    {
        $this->module = $module;
        return $this;
    }

    public function getModule(): ?ModuleEntity
    {
        if (!$this->module) {
            $this->module = module();
        }

        return $this->module;
    }

    public function checkRequest()
    {
        if ($this->isCurrentActionRequest() && $this->checkPermissions()) {
            if ($this->isSaveActionRequest()) {
                $this->saveSettingsAction();
            } elseif ($this->isResetActionRequest()) {
                $this->resetAction();
            }
        }
    }

    public function callSaveActionRequestEvent()
    {
        $event = new Event(
            $this->getModule()->getId(),
            self::EVENT_ON_FORM_SAVE_ACTION,
            [
                'formId' => $this->getId(),
                'tabSet' => $this->tabSet,
                'request' => $this->request
            ]
        );
        $event->send();
    }

    public function saveSettingsAction()
    {
        $values = $this->request->getPostList()->toArray();
        $config = $this->getModule()?->config(siteId: $this->siteId ?: '');
        $allFields = $this->optionsEntity->getInputFields();

        /** @var Field\AbstractBaseField $field */
        foreach ($allFields as $field) {
            if ($value = $values[$field->getName()]) {
                $this->modifyBeforeSave($field->getName(), $value, $allFields);
                $config->set($field->getName(), $value);
            } else {
                $config->remove($field->getName());
            }
        }

        if ($this->request->isAjaxRequest()) {
            //todo for ajax;
        } else {
            $this->isSaved = true;
//            $uri = new Uri($this->request->getRequestUri());
//            $uri->addParams(['saved' => 1]);
//            LocalRedirect($uri->getUri());
        }

    }

    protected function modifyBeforeSave($name, &$value, $allFields)
    {
        foreach ($allFields as $field) {
            if (
                $field instanceof Field\AbstractBaseField
                && TextString::toLower($field->getName()) == TextString::toLower($name)
            ) {
                $field->beforeSave($value);
            }
        }
    }

    public function saveEntityAction()
    {

    }

    public function resetAction()
    {

    }

    protected function checkPermissions()
    {
        global $USER;
        return
            $this->request->isAdminSection()
            && $USER->IsAuthorized()
            && $this->getUserRights() === 'W';
    }

    protected function getUserRights(): string
    {
        return $this->rightsChecker->getGroupRight($this->getModule()->getId());
    }

    public function getJsExtensions(): array
    {
        return [];
    }

    public function getIncludeModules()
    {
        return [];
    }

    public function setTabSet(UiBase\Tab\Set $tabset)
    {
        $this->tabSet = $tabset;
        return $this;
    }

    public function setOptions(Options\Base $options): static
    {
        $this->optionsEntity = $options;
        $this->options = $this->optionsEntity->getMap();
        $this->optionsValue = $this->optionsEntity->getAllValues();
        $this->fillValues();

        return $this;
    }

    protected function fillValues()
    {
        foreach ($this->options as $option) {
            $this->setValue($option);
        }
    }

    protected function setValue($target)
    {
        if ($target instanceof Field\AbstractBaseField) {
            $target->setForm($this);
            if ($this->optionsValue[$target->getName()]) {
                $target->setValue($this->optionsValue[$target->getName()]);
            }
        } elseif ($target instanceof Tab\Base) {
            foreach ($target->getRows() as $row) {
                $this->setValue($row);
            }
        } elseif ($target instanceof Row\ChildrenBase) {
            foreach ($target->getChildren() as $child) {
                $this->setValue($child);
            }
        }
    }

    public function renderButtonPanel(): void
    {
        echo $this->beforeButtonPanel();
        $this->buttonPanelRenderer->render($this->getButtonPanelParams());
        echo $this->afterButtonPanel();
    }

    public function render()
    {
        $this->includeModules();
        Extension::load($this->getJsExtensions());

        echo $this->getStartFormHtml();
        if (!$this->renderTabs()) {
            $this->renderRows();
        }
        $this->renderButtonPanel();
        echo $this->getEndFormHtml();
    }

    protected function renderTabs(): bool
    {
        if ($this->options) {
            foreach ($this->options as $entity) {
                if ($entity instanceof UiBase\Tab\Base) {
                    $this->tabSet->addTab($entity);
                }
            }
        }

        if ($this->tabSet->isEmpty()) {
            return false;
        }

        $this->tabSet->checkActiveTab();
        $this->tabSet->render();
        return true;
    }

    protected function renderRows()
    {
        echo '<div class="mb-tabs-switcher-block mb-tabs-switcher-block-selected"><div class="ui-form ui-form-section">';
        foreach ($this->options as $entity) {
            if ($entity instanceof Row\Base && $entity->isEnabled()) {
                $entity->render();
            }
        }
        echo '</div></div>';
    }

    protected function includeModules()
    {
        foreach ($this->getIncludeModules() as $moduleId) {
            Loader::includeModule($moduleId);
        }
    }

    public static function buildId(string $id, $siteId = null)
    {
        return 'mb_form_' . $id . ($siteId ? "_$siteId" : '');
    }

    public function isCurrentForm(string $id)
    {
        return $this->buildId($id) === $this->getId();
    }

    protected function getButtonPanelParams(): array
    {
        return [
            'ALIGN' => 'left',
            'STICKY_CONTAINER' => true,
            'ID' => 'ui-button-panel-' . $this->getId(),
            'FRAME' => $this->request->isAjaxRequest(),
            'BUTTONS' => [
                [
                    'TYPE' => 'save',
                    'NAME' => 'Update',
                    'VALUE' => 'Y',
                    'ID' => 'saveButton',
                ],
            ],
        ];
    }

    protected function beforeButtonPanel(): string
    {
        return '';
    }

    protected function afterButtonPanel(): string
    {
        return '';
    }

    protected function getStartFormHtml()
    {
        return <<<DOC
            <div class="form-container">
            <form method="POST" id="{$this->getId()}" name="{$this->getId()}" action="{$this->uri->getPath()}?{$this->uri->getQuery()}">
            <input type="hidden" name="{$this->formRequestName}" value="{$this->getId()}" />
DOC;
    }

    protected function getEndFormHtml()
    {
        return <<<DOC
</form></div>
DOC;
    }

    public function toJson()
    {
        return [
            'id' => $this->getId(),
            'buttonPanelParams'=> $this->getButtonPanelParams(),
            'tabset' => $this->tabSet->toArray()
        ];

    }

}
