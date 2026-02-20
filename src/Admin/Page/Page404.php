<?php

namespace MB\Core\Settings\Page;

class Page404 extends Entity\ContentPage
{

    public static function getId(): string
    {
        return '404';
    }

    public static function getTitle(): string
    {
        return '404';
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
            [
                'TITLE' => 'Страница не найдена',
                'DESCRIPTION' => 'Запрашиваемая вами страница не найдена'
            ]
        );
        return ob_get_clean();
    }
}
