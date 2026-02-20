<?php

namespace MB\Bitrix\EntityView\Grid\Row\Assembler\Field;

use Bitrix\Main\Application;
use Bitrix\Main\UserTable;
use MB\Bitrix\EntityView\Grid\Row\Assembler\BaseFieldAssembler;

/**
 * Формирует поле для типа Пользователь и Дата со временем
 */
class UserTimeFieldAssembler extends BaseFieldAssembler
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
        $exValue = explode('/', $value);
        $result = [];
        if ($userId = trim($exValue[0])) {
            $result[] = "<a href=\"/bitrix/admin/user_edit.php?lang=".LANGUAGE_ID."&ID=$userId\">{$this->getUserName($userId)} [$userId]</a>";
        }

        if ($dateTimeValue = trim($exValue[1])) {
            $dateTime = Application::getConnection()->getSqlHelper()->convertFromDbDateTime($dateTimeValue);
            $result[] = $dateTime->toString();
        }

        return "<div style='display: flex; flex-direction: column; gap: 5px'>" . implode(' ', $result) . "</div>";
    }
}
