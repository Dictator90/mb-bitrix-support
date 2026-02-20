<?php

namespace MB\Bitrix\UI\Providers;

use MB\Bitrix\UI\Control\Field\FieldFactory;

class ServiceProvider extends \MB\Bitrix\Foundation\ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('ui.field', FieldFactory::class);
        $this->app->alias('ui.field', FieldFactory::class);
    }
}