# mb4it/bitrix-support

Composer library for **1C-Bitrix** projects: ORM storage helpers (MySQL / PostgreSQL batch SQL), Highload blocks, migration utilities, EntityView, files/images, logging.

## Requirements

- **PHP** `^8.2` (see `composer.json`)
- **Bitrix** with D7 ORM and relevant modules at runtime (`main`, and optionally `highloadblock`, `ui`, etc.)

## Install

```bash
composer require mb4it/bitrix-support
```

Several internal `mb4it/*` packages are pinned `@dev` today; for public consumption, align versions per your registry policy.

## Autoload

Composer maps:

- `MB\Bitrix\` → `src/`

A small **`app()`** helper is registered from `src/Support/helpers.php`; it delegates to `MB\Bitrix\Foundation\Application::getInstance()` (you must bootstrap the application first).

### Namespace policy

| Prefix | Role | Semver |
|--------|------|--------|
| **`MB\Bitrix\`** | Primary public API (Storage, Module, Migration, Foundation, most Config types, etc.). Prefer `use` statements from this tree for new code. | Breaking changes follow semver for documented public entrypoints. |

Single PSR‑4 root is intentional. See `docs/SUPPORTED.md` for subsystem support levels.

## Documentation

- [Documentation index](docs/README.md)
- [Supported vs experimental API](docs/SUPPORTED.md)
- [Application bootstrap](docs/application.md)
- [Migrations](docs/migrations.md)
- [Storage / Highload](docs/storage-and-highloadblock.md)
- [UI Components](docs/ui.md) - Forms, fields, tabs, EntitySelector
- [Laravel-style DI](docs/laravel-parity.md)

## Matrix (high level)

| Layer | Notes |
|--------|--------|
| PHP | `^8.2` |
| Bitrix | D7 ORM assumed; not tested against non-D7 cores |
| DB | Batch helpers target **MySQL** (`ROW` in `UPDATE … JOIN`) and **PostgreSQL** (`ON CONFLICT`, `VALUES` updates). Confirm MariaDB behaviour in your version. |

## Development

```bash
composer validate
composer dump-autoload -o
composer test
composer phpstan
```
