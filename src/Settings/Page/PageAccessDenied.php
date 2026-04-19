<?php

namespace MB\Core\Settings\Page;


class PageAccessDenied extends Entity\ContentPage
{

    public static function getId(): string
    {
        return 'denied';
    }

    public static function getTitle(): string
    {
        return 'Доступ запрещен';
    }

    public static function isSystem(): bool
    {
        return true;
    }

    protected function getContent(): string
    {
        global $APPLICATION;

        ob_start();
        $APPLICATION->IncludeComponent(
            'bitrix:ui.info.error',
            '',
        );
        return ob_get_clean();
    }
}
