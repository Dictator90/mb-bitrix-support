# Соответствие идеям Laravel (без пакетов Illuminate)

Пакет **не подключает** `illuminate/*`. Ниже — что сознательно **похоже по DX** на Laravel, а что **намеренно отсутствует**.

## Есть в пакете (DI-слой)

| Идея Laravel | Аналог в пакете |
|--------------|-----------------|
| Service container | `MB\Container\Container` через [`Foundation\Application`](../src/Foundation/Application.php) |
| Service providers (`register` / `boot`) | [`Foundation\ServiceProvider`](../src/Foundation/ServiceProvider.php), `Application::register()` |
| Статические фасады | [`Support\Facade`](../src/Support/Facade.php), [`Support\Facades\*`](../src/Support/Facades) |
| Хелпер `app()` | [`src/Support/helpers.php`](../src/Support/helpers.php) |
| Хелпер `config()` | тот же файл, репозиторий `config` в контейнере |
| Хелпер `module($id)` | резолв `"{moduleId}:module"` после `Application::registerModule()` |
| Репозиторий конфигурации (dot-notation) | [`MB\Bitrix\Contracts\Config\Repository`](../src/Contracts/Config/Repository.php), реализация [`ArrayRepository`](../src/Config/ArrayRepository.php) |
| Рендер «страницы» без Illuminate | [`MB\Bitrix\Contracts\Support\Renderable`](../src/Contracts/Support/Renderable.php) |

## Нет и не планируется в этом пакете

- HTTP Kernel, Request/Response pipeline, middleware
- Роутинг в стиле Laravel
- Console / Artisan, очереди, кеш-фасады Laravel
- Eloquent; вместо этого — D7 ORM и слой Storage/HighloadBlock пакета
- Любые зависимости **`illuminate/*`**

## Ограничения и риски

- **`app()` / `module()` / `config()`** доступны только после инициализации [`Application`](../src/Foundation/Application.php) (в конструкторе вызывается `setInstance`). В чужом проекте с другим `app()` порядок автозагрузки `files` может конфликтовать — см. [`application.md`](application.md).
- **`module($id)`** требует предварительного **`$app->registerModule($id)`**; иначе контейнер не найдёт привязку.
- **`config`**: PHP-файлы из каталога `config/` относительно **`setBasePath()`**; вызовите `setBasePath()` до первого чтения конфигурации, если нужны файлы на диске.

## Пример потребителя

См. [`examples/kernel-bootstrap.md`](examples/kernel-bootstrap.md).
