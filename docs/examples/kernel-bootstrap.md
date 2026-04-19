# Пример: bootstrap ядра и хелперов

Пакет подключает глобальные функции `app()`, `config()`, `module()` через `composer.json` → `autoload.files` → `src/Support/helpers.php`.

## Минимальный сценарий

```php
<?php

use MB\Bitrix\Foundation\Application;

require __DIR__ . '/vendor/autoload.php';

$app = new Application();

// Корень проекта (local/php_interface, корень сайта и т.д.) — для загрузки config/*.php
$app->setBasePath(__DIR__);

// Каждый модуль, к которому обращаетесь через module('vendor.module')
$app->registerModule('vendor.module');

$app->boot();

// Дальше в коде модуля / пролога:
$value = config('app.name', 'default');
$entity = module('vendor.module');
```

## Замечания

- Вызов `module($id)` возможен только после `registerModule($id)` на том же экземпляре `Application`.
- Если в проекте уже есть глобальная `app()` (например, другой фреймворк), согласуйте порядок автозагрузки — см. [`laravel-parity.md`](../laravel-parity.md).
