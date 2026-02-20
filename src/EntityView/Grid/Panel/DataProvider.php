<?php

namespace MB\Bitrix\EntityView\Grid\Panel;

use Bitrix\Main\Grid\Panel\Action\DataProvider as DataProviderBase;
use MB\Bitrix\EntityView\Grid\Settings;
use MB\Bitrix\EntityView\Grid\Panel\Actions\EditAction;
use MB\Bitrix\EntityView\Grid\Panel\Actions\DeleteAction;

class DataProvider extends DataProviderBase
{
    public function prepareActions(): array
    {
        $result = [];
        $result[] = new EditAction($this->getSettings());
        $result[] = new DeleteAction($this->getSettings());

        $settings = $this->getSettings();
        if ($settings instanceof Settings) {
            $allowed = $settings->getGroupActions();
            if ($allowed !== null) {
                $result = array_values(array_filter($result, function ($action) use ($allowed) {
                    return in_array($action::getId(), $allowed, true);
                }));
            }
        }

        return $result;
    }
}
