<?php

namespace MB\Bitrix\UI\Control\Tab;

use Bitrix\Main\Security\Random;

class GroupRightsTab extends BitrixTab
{
    public function __construct()
    {
        parent::__construct('rights_' . Random::getString(5));
    }

    public function getTabContentHtml(): string
    {

        $groupRightsContent = $this->getGroupRightsContent();
        $activeClass = $this->isActive() ? 'mb-tabs-switcher-block-selected' : '';

        return <<<DOC
            <div class="mb-tabs-switcher-block {$activeClass}" data-tab-content="{$this->getId()}">
                <div class="mb-tabs-switcher-block__title">
                    {$this->getDescription()}
               </div>
                <div class="ui-form ui-form-section" style="padding: 20px">
                    <table>
                        {$groupRightsContent}
                    </table>
                </div>
            </div>
DOC;
    }

    protected function getGroupRightsContent()
    {
        global $APPLICATION, $REQUEST_METHOD, $RIGHTS, $SITES, $GROUPS;
        $module_id = defined('ADMIN_MODULE_NAME') ? ADMIN_MODULE_NAME : 'mb.core';
        $Update = !empty($_REQUEST['Update']) ? 'Y' : '';
        $bxsessid = bitrix_sessid();

        ob_start();
        include ($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/admin/group_rights.php');
        $groupRightsContent = ob_get_clean();

        return $groupRightsContent;
    }


    public function beforeGetTab(): void
    {
        if (!$this->label) {
            $this->label = 'Доступы';
        }

        if (!$this->description) {
            $this->description = 'Настройки доступов';
        }
    }

    public function toArray()
    {
        $this->beforeGetTab();
        return array_merge(parent::toArray(), [
            'html' => $this->getGroupRightsContent()
        ]);
    }
}
