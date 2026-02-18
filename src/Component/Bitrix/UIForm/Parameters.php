<?php

namespace MB\Bitrix\Component\Bitrix\UIForm;

use MB\Bitrix\Component\Parameters\Base as ParametersBase;

class Parameters extends ParametersBase
{
    public const INITIAL_MODE_VIEW = 'view';
    public const INITIAL_MODE_EDIT = 'edit';

    public function __construct(string|int $id = null)
    {
        parent::__construct();
        if ($id !== null) {
            $this->setGuId($id);
        }
    }

    public function setGuId(string|int $value): static
    {
        return $this->addParam('GUID', $value);
    }

    public function setEntityId(string|int $value): static
    {
        return $this->addParam('ENTITY_ID', $value);
    }

    public function setEntityFields(array $values): static
    {
        return $this->addParam('ENTITY_FIELDS', $values);
    }

    public function setUserFieldEntityId(string $value): static
    {
        return $this->addParam('USER_FIELD_ENTITY_ID', $value);
    }

    public function setUserFieldPrefix(string $value): static
    {
        return $this->addParam('USER_FIELD_PREFIX', $value);
    }

    public function setEntityConfig(array $values): static
    {
        return $this->addParam('ENTITY_CONFIG', $values);
    }

    public function setEntityData(array $values): static
    {
        return $this->addParam('ENTITY_DATA', $values);
    }

    public function setEntityTypeName(string $value): static
    {
        return $this->addParam('ENTITY_TYPE_NAME', $value);
    }

    public function setInitialMode(string $value): static
    {
        return $this->addParam('INITIAL_MODE', $value);
    }

    public function setComponentAjaxData(array $values): static
    {
        return $this->addParam('COMPONENT_AJAX_DATA', $values);
    }

    public function setServiceUrl(string $value): static
    {
        return $this->addParam('SERVICE_URL', $value);
    }

    public function setToolPanelCustomButtons(array $values): static
    {
        return $this->addParam('CUSTOM_TOOL_PANEL_BUTTONS', $values);
    }

    public function setToolPanelButtonsOrder(array $values): static
    {
        return $this->addParam('TOOL_PANEL_BUTTONS_ORDER', $values);
    }

    public function configureAjaxMode(bool $value = true): static
    {
        return $this->addParam('ENABLE_AJAX_FORM', $value);
    }

    public function configureToggleMode(bool $value = true): static
    {
        return $this->addParam('ENABLE_MODE_TOGGLE', $value);
    }

    public function configureVisibilityPolict(bool $value = true): static
    {
        return $this->addParam('ENABLE_VISIBILITY_POLICY', $value);
    }

    public function configureSectionCreation(bool $value = true): static
    {
        return $this->addParam('ENABLE_SECTION_CREATION', $value);
    }

    public function configureSectionEdit(bool $value = true): static
    {
        return $this->addParam('ENABLE_SECTION_EDIT', $value);
    }

    public function configureSectionDragAndDrop(bool $value = true): static
    {
        return $this->addParam('ENABLE_SECTION_DRAG_DROP', $value);
    }

    public function configureFieldDragAndDrop(bool $value = true): static
    {
        return $this->addParam('ENABLE_FIELD_DRAG_DROP', $value);
    }

    public function configureShowAlwaysFeature(bool $value = true): static
    {
        return $this->addParam('ENABLE_SHOW_ALWAYS_FEAUTURE', $value);
    }

    public function configureIdentifiableEntity(bool $value = true): static
    {
        return $this->addParam('IS_IDENTIFIABLE_ENTITY', $value);
    }

    public function configureReadOnly(bool $value = true): static
    {
        return $this->addParam('READ_ONLY', $value);
    }

    public function configureEmbeded(bool $value = true): static
    {
        return $this->addParam('IS_EMBEDDED', $value);
    }

    public function configureToolPanel(bool $value = true): static
    {
        return $this->addParam('ENABLE_TOOL_PANEL', $value);
    }

    public function configureToolPanelAlwaysVisible(bool $value = true): static
    {
        return $this->addParam('IS_TOOL_PANEL_ALWAYS_VISIBLE', $value);
    }

    public function configureBottomPanel(bool $value = true): static
    {
        return $this->addParam('ENABLE_BOTTOM_PANEL', $value);
    }

    public function configureConfigControl(bool $value = true): static
    {
        return $this->addParam('ENABLE_CONFIG_CONTROL', $value);
    }

    public function configureConfigScopeToggle(bool $value = true): static
    {
        return $this->addParam('ENABLE_CONFIG_SCOPE_TOGGLE', $value);
    }

    public function configureConfigUpdate(bool $value = true): static
    {
        return $this->addParam('ENABLE_CONFIGURATION_UPDATE', $value);
    }

    public function configureConfigCommonUpdate(bool $value = true): static
    {
        return $this->addParam('ENABLE_COMMON_CONFIGURATION_UPDATE', $value);
    }

    public function configureConfigPersonalUpdate(bool $value = true): static
    {
        return $this->addParam('ENABLE_PERSONAL_CONFIGURATION_UPDATE', $value);
    }

    public function configureSkipTemplate(bool $value = true): static
    {
        return $this->addParam('SKIP_TEMPLATE', $value);
    }

    public function configureFieldsContextMenu(bool $value = true): static
    {
        return $this->addParam('ENABLE_FIELDS_CONTEXT_MENU', $value);
    }

    public function configereUserFieldCreation(bool $value = true): static
    {
        return $this->addParam('ENABLE_USER_FIELD_CREATION', $value);
    }

    public function setUserFieldCreationSign(string $value): static
    {
        return $this->addParam('USER_FIELD_CREATE_SIGNATURE', $value);
    }

    public function configureForceDefaultSectionName(bool $value = true): static
    {
        return $this->addParam('FORCE_DEFAULT_SECTION_NAME', $value);
    }

    public function configureForceDefaultConfig(bool $value = true): static
    {
        return $this->addParam('FORCE_DEFAULT_CONFIG', $value);
    }
}
