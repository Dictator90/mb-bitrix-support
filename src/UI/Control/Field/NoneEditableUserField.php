<?php

namespace MB\Bitrix\UI\Control\Field;

use Bitrix\Main\UserTable;
use MB\Bitrix\UI\Base\Field;
use MB\Bitrix\UI\Constants\HasIcon;

class NoneEditableUserField extends NonEditableField
{
    public function getHtml(): string
    {
        $value = null;
        if ($id = $this->getValue()) {
            $user = UserTable::query()->setSelect(['ID', 'NAME', 'LAST_NAME', 'LOGIN'])->where('ID', $id)->fetch();
            $name = $user['NAME'] || $user['LAST_NAME'] ? $user['NAME'] . ' ' . $user['LAST_NAME'] : $user['LOGIN'];
            $value = "{$name} [{$user['ID']}]";
        }

        $lang = LANGUAGE_ID;

        if ($id) {
            return <<<HTML
            <div class="ui-ctl-inline">
                <a href="/bitrix/admin/user_edit.php?lang={$lang}&ID={$id}" target="_blank">{$value}</a>
            </div>
HTML;
        }

        return <<<HTML
            <div class="ui-ctl-inline">
                <span>{$value}</span>
            </div>
HTML;

    }
}
