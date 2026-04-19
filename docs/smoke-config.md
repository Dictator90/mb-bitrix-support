# Smoke: конфиг модуля и `ConfigManager`

Повторяемые проверки после правок в слое `Config` / `ConfigLocator` / `Module\Entity::fillConfig`.

## Предусловия

1. Ядро Битрикс загружено, `vendor/autoload.php` подключён.
2. `MB\Bitrix\Foundation\Application` создан, для модулей вызван `registerModule('<module.id>')`, при необходимости `boot()`.
3. Модуль `<module.id>` установлен, у него есть каталог `lib/` на диске.

## Шаги

### A. `ConfigManager::get`

```php
use MB\Bitrix\Config\ConfigManager;

$entity = ConfigManager::get('vendor.module', '');
// Ожидание: объект MB\Bitrix\Config\Entity, без fatal «undefined method createByModuleId».
```

С вариантом «текущий сайт»:

```php
$entity = ConfigManager::get('vendor.module', false);
```

### B. Цепочка `Module\Entity::fillConfig`

```php
use MB\Bitrix\Module\Entity;

$m = new Entity('vendor.module');
// Ожидание: конструктор не падает в fillConfig из-за несуществующего MB\Core\Config\Entity при вызове ConfigLocator.
```

### C. `ConfigLocator` (точечно)

```php
use MB\Core\Config\ConfigLocator;

$class = ConfigLocator::getConfigByModuleId('vendor.module');
// Ожидание: class-string подкласса MB\Bitrix\Config\Entity или null, без ошибки автозагрузки «MB\Core\Config\Entity».
```

## Локальная статика (без полного Битрикс)

- `vendor/bin/phpstan analyse -c phpstan.neon --memory-limit=512M`
- `vendor/bin/phpunit`
