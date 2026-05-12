# AGENTS

## Purpose

`mb4it/bitrix-support` is a PHP 8.2+ helper library for 1C-Bitrix. The core entrypoint is `MB\Bitrix\Foundation\Application`, which extends `MB\Container\Container` and wires package providers, module helpers, and lifecycle events.

## Project Map

- `src/Foundation` - application kernel, service-provider contract, lifecycle orchestration.
- `src/Support` - global helpers (`app()`, `config()`, `module()`), facade base, facades.
- `src/Module` - module entity/container and module provider.
- `src/Migration` - migration facade and migration infrastructure.
- `src/Filesystem`, `src/File` - filesystem bridge and file/image workflows.
- `src/Storage`, `src/Database` - storage/ORM helpers and sqlite support for tests.
- `src/UI`, `src/EntityView`, `src/Page`, `src/Config` - admin UI and config-oriented subsystems.
- `tests` - unit/default suite (`phpunit --testsuite default`), plus dedicated sqlite and integration suites.
- `docs` - package docs; keep in sync with actual runtime behavior.

## Stable Entrypoints

- `MB\Bitrix\Foundation\Application`
- `src/Support/helpers.php`: `app()`, `config()`, `module()`
- Contracts under `src/Contracts`

When extending behavior, prefer providers and contracts over direct static calls.

## Application Bootstrap Flow

1. `new Application()` registers base bindings, path services, base providers, and core aliases.
2. Optional `setBasePath()` loads `config/*.php` into the `config` repository.
3. Register custom providers (`register()` / `registerDeferred()`).
4. Call `boot()` once per request lifecycle.

Kernel lifecycle events:

- `onBuildKernelApplication`
- `onBeforeBootKernelApplication`
- `onAfterBootKernelApplication`

## Deferred Providers

- `registerDeferred()` only records service-to-provider mappings.
- Actual provider registration happens on first `make()` of a deferred service.
- `loadDeferredProviders()` forces registration of remaining deferred services.

## Risk Zones

- `src/Foundation/Application.php`
  - contains lifecycle and custom parameterized resolution logic.
  - includes fallback reflection access to parent container internals for alias/binding lookup.
- `src/Module/Entity.php`
  - has re-entrancy/construction guards; changes can break helper-driven module resolution.
- `src/File/FileService.php`
  - large responsibility surface; refactors need careful regression coverage.

## Verification Commands

Run from repository root:

- `composer lint`
- `composer phpstan`
- `composer test`
- `composer test:sqlite`
- `composer test:integration`

For quick iteration on kernel behavior, run targeted tests first:

- `phpunit --testsuite default --filter Application`

## Editing Rules For Agents

- Do not assume Laravel Illuminate internals; this project uses custom `mb4it/container`.
- Keep docs aligned with code changes, especially in `docs/application.md` and `docs/README.md`.
- Avoid introducing new public API names without tests.
- Prefer additive compatibility changes; treat naming renames (`SqlLite`/`Sqlite`, `Cachable`/`Cacheable`) as future major-version work.
