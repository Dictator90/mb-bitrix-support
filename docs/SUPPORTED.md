# Supported API surface (v1 vs legacy)

This matrix tracks what the maintainers treat as **supported for consumers** versus **legacy / experimental / internal**. Open product questions from analytics §9 are reflected as **TBD** until the owner decides.

| Area | Namespace / entry | v1 supported | Notes |
|------|---------------------|--------------|--------|
| Storage / SqlHelper | `MB\Bitrix\Storage\*` | Yes | Batch upsert/update/delete; MySQL `ROW()` vs PostgreSQL `ON CONFLICT` / `VALUES` — see `docs/storage-and-highloadblock.md` and tests. |
| HighloadBlock | `MB\Bitrix\HighloadBlock\*` | Yes | Requires `highloadblock` module at runtime. |
| Migration managers | `MB\Bitrix\Migration\*`, `MB\Bitrix\Agent\*`, `MB\Bitrix\Event\*` | Yes | `Migration\Facade::upAll()` / `downAll()` do **not** run agent sync (see `docs/migrations.md`). |
| Foundation / facades | `MB\Bitrix\Foundation\Application`, `MB\Bitrix\Support\Facades\*` | Yes | Requires explicit bootstrap; global `app()`, `config()`, `module()` via `src/Support/helpers.php` after the kernel sets the instance (constructor). See `docs/laravel-parity.md`. |
| Module entity | `MB\Bitrix\Module\Entity` | Yes | Uses `MB\Bitrix\Config\ConfigLocator` and `MB\Bitrix\Settings\*`; `fillConfig` depends on working locator + `MB\Bitrix\Config\Entity` discovery. |
| Config entity / options | `MB\Bitrix\Config\Entity`, `ConfigManager`, `UseOptions` | Yes | **`Entity::create($moduleId, $siteId)`** is the supported factory (requires `module()` + `registerModule`). |
| Config locator | `MB\Bitrix\Config\ConfigLocator` | Yes | Discovers subclasses of **`MB\Bitrix\Config\Entity`** under module `lib/`. |
| Admin / settings UI | `MB\Bitrix\Settings\*` | Experimental | Depends on Bitrix UI and D7; some paths were aligned to `MB\Bitrix\UI\*`. Treat as integration-heavy until covered by smoke tests. |
| EntityView | `MB\Bitrix\EntityView\*` | Mixed | `MenuAction` defaults are safe but minimal — override in the host module for real UX. |
| UI Components | `MB\Bitrix\UI\*` | Yes | Base classes (View, Form, Grid, Tab, Row), Control fields (20+), EntitySelector providers (5), Traits (20+) — see `docs/ui.md`. |
| Namespace policy | `MB\Bitrix\*` under `src/` | Documented | **Policy:** see **Namespace policy** in root `README.md`. Public package namespace is `MB\Bitrix\`. |
| Without global `app()` | — | TBD (§9.2) | Supported path today: resolve `Application::getInstance()` and use `container($moduleId)` or inject services directly. |
| PHP / `main` / DB matrix | — | TBD (§9.3) | `composer.json` requires PHP `^8.2`; Bitrix D7 ORM assumed. MariaDB vs MySQL for `ROW()` — document per environment. |
| v1 subsystem list | — | TBD (§9.4) | Admin/Page may stay experimental while Storage/HL/Migration remain v1 — confirm with owner. |
| Packagist vs private Composer | — | TBD (§9.5) | Several `mb4it/*` deps are `@dev`; public installs need a version policy (see production plan). |
| Demo module for integration tests | — | TBD (§9.7) | Not shipped here; manual smoke on a real module remains the default. |

## Related docs

- `docs/README.md` — index and install name `mb4it/bitrix-support`
- `docs/application.md` — application bootstrap
- `docs/migrations.md` — migration facade behaviour
