# Supported API surface (v1 vs legacy)

This matrix tracks what the maintainers treat as **supported for consumers** versus **legacy / experimental / internal**. Open product questions from analytics section 9 are reflected as **TBD** until the owner decides.

| Area | Namespace / entry | v1 supported | Notes |
|------|---------------------|--------------|--------|
| Storage / SqlHelper | `MB\Bitrix\Storage\*` | Yes | Batch upsert/update/delete; delete/update by query are ORM-safe primary-key flows (no regex SQL rewrite), with cross-platform SQL generation in `SqlHelper`. |
| HighloadBlock | `MB\Bitrix\HighloadBlock\*` | Yes | Requires `highloadblock` module at runtime. |
| Migration managers | `MB\Bitrix\Migration\*`, `MB\Bitrix\Agent\*`, `MB\Bitrix\Event\*` | Yes | `Migration\Facade::upAll()` / `downAll()` do **not** run agent sync (see `docs/migrations.md`). |
| Foundation / facades | `MB\Bitrix\Foundation\Application`, `MB\Bitrix\Support\Facades\*` | Yes | Requires explicit bootstrap; global `app()`, `config()`, `module()` via `src/Support/helpers.php` after the kernel sets the instance. |
| Bitrix adapters | `MB\Bitrix\Contracts\Bitrix\*`, `MB\Bitrix\Bitrix\Adapters\*` | Yes | Adapter layer for application/cache, localization and disk quota integration. |
| Module entity | `MB\Bitrix\Module\Entity` | Yes | Uses `MB\Bitrix\Config\ConfigLocator` and `MB\Bitrix\Settings\*`; `fillConfig` depends on working locator + `MB\Bitrix\Config\Entity` discovery. |
| Config entity / options | `MB\Bitrix\Config\Entity`, `ConfigManager`, `UseOptions` | Yes | **`Entity::create($moduleId, $siteId)`** is the supported factory (requires `module()` + `registerModule`). |
| Config locator | `MB\Bitrix\Config\ConfigLocator` | Yes | Discovers subclasses of **`MB\Bitrix\Config\Entity`** under module `lib/`. |
| Admin / settings UI | `MB\Bitrix\Settings\*` | Experimental | Depends on Bitrix UI and D7; some paths were aligned to `MB\Bitrix\UI\*`. Treat as integration-heavy until covered by smoke tests. |
| EntityView | `MB\Bitrix\EntityView\*` | Mixed | `MenuAction` defaults are safe but minimal; override in host module for real UX. |
| UI Components | `MB\Bitrix\UI\*` | Yes | Base classes (View, Form, Grid, Tab, Row), control fields, EntitySelector providers, traits. |
| File services | `MB\Bitrix\File\*`, `MB\Bitrix\File\Services\*`, `MB\Bitrix\Contracts\File\*` | Yes | `FileService` remains the external entrypoint; internals are decomposed into `Uploader`, `DuplicateResolver`, `MetadataReader`, and `FileRepository`. |
| Namespace policy | `MB\Bitrix\*` under `src/` | Documented | Public package namespace is `MB\Bitrix\`. |
| Without global `app()` | n/a | TBD | Supported path today: resolve `Application::getInstance()` and use `container($moduleId)` or inject services directly. |
| PHP / `main` / DB matrix | n/a | TBD | `composer.json` requires PHP `^8.2`; Bitrix D7 ORM assumed. MariaDB vs MySQL for `ROW()` should be documented per environment. |
| v1 subsystem list | n/a | TBD | Admin/Page may stay experimental while Storage/HL/Migration remain v1; confirm with owner. |
| Packagist vs private Composer | n/a | TBD | Several `mb4it/*` deps are `@dev`; public installs need a version policy. |
| Demo module for integration tests | n/a | TBD | Not shipped here; manual smoke on a real module remains default. |

## Related docs

- `docs/README.md`
- `docs/application.md`
- `docs/migrations.md`
- `docs/refactoring-baseline.md`
