<?php

namespace MB\Bitrix\EntityView\Grid\Row\Assembler\Field;

use Bitrix\Main\UserTable;
use MB\Bitrix\UI\Control\Field\UserSelectorField;
use MB\Bitrix\EntityView\Grid\Row\Assembler\BaseFieldAssembler;

class UserFieldAssembler extends BaseFieldAssembler
{
    protected array $userCache = [];
    protected string $type = 'custom';

    protected function loadUserName(int $userId): string
    {
        $nameFormat = \CSite::GetNameFormat();

        $row = UserTable::getRow([
            'select' => [
                'ID',
                'LOGIN',
                'NAME',
                'LAST_NAME',
                'SECOND_NAME',
                'EMAIL',
                'TITLE',
            ],
            'filter' => [
                '=ID' => $userId,
            ],
        ]);
        if ($row)
        {
            return \CUser::FormatName($nameFormat, $row, true, true);
        }

        return '';
    }

    private function getUserName(int $userId): ?string
    {
        if (!isset($this->userCache[$userId]))
        {
            $this->userCache[$userId] = $this->loadUserName($userId);
        }

        return $this->userCache[$userId];
    }

    protected function prepareColumn($value)
    {
        if (isset($value) && is_numeric($value)) {
            $value = (int)$value;
            if ($value > 0) {
                return "<a href=\"/bitrix/admin/user_edit.php?lang=".LANGUAGE_ID."&ID=$value\">{$this->getUserName($value)} [$value]</a>";
            }
        }

        return null;
    }

    protected function prepareData($value, $columnId)
    {
        $userField = new UserSelectorField($columnId);

        if (isset($value) && is_numeric($value)) {
            $value = (int)$value;
            if ($value > 0) {
                $userField->setValue($value);
            }
        }

        ob_start();
        $userField->render();
        return ob_get_clean();
    }
}
