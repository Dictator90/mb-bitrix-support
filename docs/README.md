# mb4it/bitrix-support Docs

`mb4it/bitrix-support` is a PHP 8.2+ helper library for 1C-Bitrix.

Main package namespace: `MB\Bitrix\`.

## Key Subsystems

- `Foundation` - kernel application and service-provider lifecycle.
- `Storage` / `Database` - ORM-oriented helpers and SQL tooling.
- `File` / `Filesystem` - file workflows and adapters.
- `Migration` - migration facade and infrastructure.
- `Module` / `Config` / `Settings` - module-aware configuration and options.
- `UI` / `EntityView` / `Page` - admin UI abstractions.

## Current Refactoring Direction

The package is moving to a stricter modular architecture:

- explicit contracts,
- reduced helper/static coupling,
- predictable batch-operation behavior,
- quality gates driven by phpstan + tests.

See:

- `docs/application.md`
- `docs/SUPPORTED.md`
- `docs/refactoring-baseline.md`
- `docs/storage-and-highloadblock.md`
- `docs/file-and-image.md`

## Verification Commands

Run from repository root:

```bash
composer lint
composer phpstan
composer test
composer test:sqlite
composer test:integration
```
