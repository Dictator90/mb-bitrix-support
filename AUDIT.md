# Audit: `mb4it/bitrix-support` and its integration with `mb.core`

**Subject package**: `mb4it/bitrix-support` at `F:\phpstorm\mb\composer\mb-bitrix-support`
**Consumer module**: `mb.core` (Bitrix module)
**PHP target**: `^8.2`
**Bitrix target**: D7 ORM, `main`, optional `highloadblock` / `ui`
**PHP files in `src/`**: 306
**Files in `src/` with `declare(strict_types=1)`**: 23 (≈ 7.5%)
**PHPStan level**: 5, only ~12 paths included (most of `src/` not analysed)
**Tests**: 6 unit files, 2 integration smoke tests against real Bitrix
**Date**: 2026-04-19

---

## 0. TL;DR

`mb4it/bitrix-support` is a maturing Laravel-style foundation (DI container, providers, facades, contracts) wrapping 1C-Bitrix D7 primitives for ORM, migrations, file handling, HighloadBlock, settings UI, logging, and admin EntityView. Its architecture is clean in intent: `Foundation\Application` extends a standalone `MB\Container\Container`, service providers register typed services, `registerModule($id)` produces per-module services keyed by strings. Cross-platform batch SQL (MySQL + PostgreSQL) via `Storage\SqlHelper` is a standout asset.

The concrete issues are three-fold:

1. **Dual-root PSR-4 with `MB\Bitrix\` and `MB\Core\` both mapped to `src/`** collides with `mb.core/lib` (which is PSR-4-autoloaded as `MB\Core\` from the Bitrix module). `mb.core` does **not** composer-depend on `bitrix-support`, and ships its own parallel `MB\Core\Config\ConfigLocator`, `MB\Core\KernelApplication`, `MB\Core\Module\ModuleContainer`, etc. — identical FQCNs, different implementations. If both autoloaders ever register in the same process, the first-wins rule makes the resulting behaviour non-deterministic.
2. **Bitrix legacy surfaces leak into modern classes**: `global $APPLICATION`, direct `new \CUserTypeEntity()`, direct `new \CDiskQuota()`, `new \CHTMLEditor()`, `GetMessage()`, `$APPLICATION->LAST_ERROR`, `$APPLICATION->AuthForm()`, `$APPLICATION->GetGroupRight()`. These defeat unit testing, DI, and strict-types.
3. **Code-quality regressions in otherwise sensible files**: useless `try { ... } catch { throw $e; }` blocks in four Storage traits; regex-parsed `SELECT → DELETE` rewrite in `DeleteByQueryTrait` (brittle against subqueries / CTEs / comments); singleton static state in `HighloadBlock\Base`; in-memory-only cache traits with no Bitrix `ManagedCache` adapter and no TTL wiring in `Traits\Cacheable`; `MassUpdateTrait` is the one that got it right and should be the template for the others.

The roadmap below proposes: merge the `MB\Core\*` tree into `MB\Bitrix\*` (keep an alias-shim for a minor cycle), replace Bitrix legacy globals with injectable contracts, unify caching through a PSR-16 adapter wrapping Bitrix `ManagedCache`, raise PHPStan to level 8 with full `src/` coverage, and set `mb.core` to consume this package via composer instead of duplicating it.

---

## 1. Phase 1 — Audit

### 1.1 Package structure & architecture

Top-level layout (`src/`):

```
Agent/                  Bitrix agent wrappers (Agent/AgentManager)
Component/              Bitrix component scaffolding (BaseComponent, Parameters)
Config/                 ArrayRepository, ConfigLocator, ConfigManager, Entity, UseOptions
Contracts/              Interfaces: Component, Config, File, Iblock, Log, Migration, Module, Storage, Support, UI
EntityView/             Admin UI framework (Base, Builder, Grid, Form, Filter, Parameters) + README
Event/                  Base + EventManager (Bitrix event façade)
File/                   FileService + Image subtree + ServiceProvider
Filesystem/             Filesystem + ServiceProvider (bridge to mb4it/filesystem)
Foundation/             Application (777 lines) + ServiceProvider
HighloadBlock/          Base + HighloadBlockManager + Fields/*
Iblock/                 Helper, DetailUrl, UserType
Logger/                 LoggerFactory, ModuleLoggerFactory, EventLogger, NotificationLogger, UniversalLogger, LogLevel, ServiceProvider
Migration/              Facade, BaseEntity/BaseEntityManager, Entities/*, Facades/*, Result, ServiceProvider, StorageEntityManager
Module/                 Entity, ModuleContainer, ServiceProvider
Page/                   Asset, Common, Includer, ServiceProvider
Settings/               Admin/, Contracts/, Controller/, Options/, Page/, Builder
Storage/                Base, Collection, Entity, EntityObject, Query, QueryHelper, SqlHelper + Concerns/ (5 traits)
Support/                Facade, Facades/*, LoggerServiceProvider, helpers.php
Traits/                 Cacheable, RememberCachable, FluentTrait, BitrixEventsObservableTrait, ObservableTrait, SingletonTrait, SingletonFabricTrait
UI/                     Admin, Base (CSS-backed view), Control (form controls), EntitySelector, Providers, Traits
ServiceProvider.php     Abstract base provider
```

#### 1.1.1 Architectural patterns (intent is good)

- **DI container**: `Foundation\Application` extends `MB\Container\Container` (external package, `mb4it/container @dev`), offering Laravel-like `make` / `makeWith` / `singleton` / `bind` / deferred providers / boot callbacks. Registers `AssetServiceProvider`, `FilesystemServiceProvider`, `FileServiceProvider`, `MigrationServiceProvider`, `BitrixServiceProvider`, `LoggerServiceProvider`, `ModuleServiceProvider` at construction.
- **Service providers**: per-subsystem `ServiceProvider.php` files register typed services. Good separation.
- **Contracts**: a healthy `Contracts/` tree defines interfaces for Module, Config, Storage, Migration, File, etc. Mostly consumed internally.
- **Facades**: `Support/Facade.php` + `Support/Facades/*` re-implement Laravel's static-facade pattern (resolves from container in `__callStatic`).
- **Cross-platform SQL**: `Storage/SqlHelper.php` dispatches via `match ($connection->getType())` between PostgreSQL (`ON CONFLICT … DO UPDATE`) and MySQL (`ON DUPLICATE KEY UPDATE`, `ROW(...)` syntax in `UPDATE … JOIN`). Clean.
- **Trait composition in `Storage\Base`**: `BuildIndexes`, `BatchUpsertTrait`, `UpdateByWhereTrait`, `MassUpdateTrait`, `DeleteByQueryTrait`. Each trait is a focused feature. `@method static` PHPDoc annotations on `Storage\Base` carry the typing.
- **Dual-root PSR-4**: `composer.json` maps both `MB\Bitrix\` and `MB\Core\` to `src/`. README calls this “intentional, one physical tree, two logical prefixes” pending a future merge. In practice it is a collision hazard (see §1.3.1).

#### 1.1.2 `@deprecated` surface

Only four files carry deprecation markers:

| File | What's deprecated | Note |
|---|---|---|
| `Migration/Facade.php` | `upAgents()` / `downAgents()` → "Don't use — Need Refactor" | Still exposed on a public interface; no replacement documented. |
| `Migration/Entities/Agent.php` | Legacy agent-entity path | Marked but referenced by the optional pipeline. |
| `EntityView/Builder.php` | Subset of builder methods | Partial. |
| `EntityView/Grid/Grid.php` | One or more legacy shapes | Partial. |

No package-level `CHANGELOG`-style deprecation roadmap beyond what's in `CHANGELOG.md`.

### 1.2 Usage in `mb.core`

**Headline finding**: `mb.core` does not consume `mb4it/bitrix-support` at all. Its `composer.json` has only:

```json
{
  "require": {
    "illuminate/collections": "^10.48",
    "symfony/var-dumper": "^7.3"
  }
}
```

`MB\Bitrix\` is **never** used in `mb.core/lib` (`grep -r "use MB\\\\Bitrix" mb.core` = 0 matches).

Instead, `mb.core` ships its **own parallel implementation** under the `MB\Core\` namespace with the same class names as the `bitrix-support` tree:

| Same FQCN, different implementations | `mb.core/lib/...` | `mb-bitrix-support/src/...` |
|---|---|---|
| `MB\Core\Config\ConfigLocator` | `Config/ConfigLocator.php` | `Config/ConfigLocator.php` |
| `MB\Core\Config\ConfigEntity` | `Config/ConfigEntity.php` | `Config/Entity.php` (name differs) |
| `MB\Core\Config\ConfigManager` | `Config/ConfigManager.php` | `Config/ConfigManager.php` |
| `MB\Core\Config\UseOptions` | `Config/UseOptions.php` | `Config/UseOptions.php` |
| `MB\Core\Container\Container` | `Container/Container.php` | (external `mb4it/container`) |
| `MB\Core\KernelApplication` | `KernelApplication.php` | `Foundation/Application.php` (FQCN differs) |
| `MB\Core\Module\ModuleContainer` | `Module/ModuleContainer.php` | `Module/ModuleContainer.php` |
| `MB\Core\Module\ModuleEntity` | `Module/ModuleEntity.php` | `Module/Entity.php` (name differs) |
| `MB\Core\Module\ModuleManager` | `Module/ModuleManager.php` | (not present in bitrix-support) |
| `MB\Core\Migration\MigrationFacade` | `Migration/MigrationFacade.php` | `Migration/Facade.php` (name differs) |
| `MB\Core\Settings\Page\PageManager` | `Settings/Page/PageManager.php` | `Settings/Page/*` tree |
| `MB\Core\Support\Text\Str` | `Support/Text/Str.php` | (uses external `mb4it/stringable`) |

Where class names match exactly (`MB\Core\Config\ConfigLocator`, `MB\Core\Config\UseOptions`, `MB\Core\Module\ModuleContainer`), Composer PSR-4 autoload first-wins semantics apply. The behaviour you get at runtime depends on the order `vendor/autoload.php` registers autoloaders and on which module's `include.php` has been loaded first — a deeply undesirable property.

Takeaways:

- The bitrix-support package is a **rewrite-in-progress** of the `mb.core` module's `lib/` tree, not (yet) a consumed dependency.
- Nothing in `mb.core/lib` imports from `MB\Bitrix\*`; no integration to audit at the use-statement level.
- `mb.core` continues to use its own `KernelApplication::getInstance()`, exposed via `mb.core/lib/helper.php` (which defines `app()`, `module()`, `config()`, `pageAsset()`, `dump()`, `dd()`, `message()`).
- The path forward is **merge, not coexist**: pick one tree (recommendation: the Foundation/Application-based one under `MB\Bitrix\`), alias the old names during a deprecation cycle, delete the duplicated copies from `mb.core/lib`.

### 1.3 Code-quality & Bitrix-compat issues (representative catalogue)

#### 1.3.1 Dual PSR-4 + cross-module collision (high)

```json
"autoload": {
  "psr-4": {
    "MB\\Bitrix\\": "src/",
    "MB\\Core\\":   "src/"
  }
}
```

Consequences when both this package and `mb.core` load in the same Bitrix instance:

- Composer resolves `MB\Core\Config\ConfigLocator` to whichever autoloader got registered first (non-deterministic across hosts).
- Static analysis and IDE go-to-definition bounces between two different sources for the same FQCN.
- Refactors that move files under `src/` silently change the public API of the `MB\Core\*` tree.

README acknowledges this ("planned merge into MB\Bitrix\, no ETA"). The merge should be scheduled, not open-ended.

#### 1.3.2 Legacy Bitrix globals / procedural calls inside modern classes (high)

A search for `global $APPLICATION | $DB | $USER | $CACHE_MANAGER` returns 20+ files. Spot checks:

- **`HighloadBlock/Base.php:159`** — `createFields()` uses `global $APPLICATION;` then `new \CUserTypeEntity();`. On failure reads `$APPLICATION->LAST_ERROR`. Same pattern in `refreshFields()` (line 197). This class is otherwise a clean D7 abstract over `HighloadBlockTable`, so the regression is all the more visible.
- **`EntityView/Base.php:123`** — `checkPermissions()` does `global $APPLICATION;` then calls `$APPLICATION->AuthForm(...)` and `$APPLICATION->GetGroupRight('mb.core')`. Hard-codes the module id `'mb.core'` inside a base class of a reusable framework.
- **`File/FileService.php:465/880/465`** — `@chmod($path, BX_FILE_PERMISSIONS)` (undefined in unit tests), `new \CDiskQuota()`, `GetMessage(...)` (procedural global for Loc messages).
- **`UI/Control/Field/HtmlEditorField.php:24`** — `new \CHTMLEditor()`.
- **`Settings/Controller/Favorite.php`**, **`Settings/Page/Page404.php`**, **`Settings/Page/PageAccessDenied.php`**, **`Page/Includer.php`** — additional `global $APPLICATION` usages (admin-page bootstrap contexts, slightly less objectionable there but still untestable).

Net impact: classes that look modern (typed, namespaced, DI-ready) become untestable in isolation, and `strict_types=1` becomes impossible because `GetMessage()` and `$APPLICATION->*` return mixed.

#### 1.3.3 `Storage\Concerns\*` defects (high)

- **`BatchUpsertTrait::addBatch`** (lines 36–117)
  - Infers the `$sqlFieldPart` from the **first row only** (`$issetFieldsPart` flag, line 72). If subsequent rows have different keys, `prepareInsert` emits values for those extra keys while `INSERT INTO ... ({fields})` lists only the first-row fields, producing broken SQL that the DB rejects — but only if the field counts differ. If the counts match but keys differ, MySQL will silently insert into the wrong columns.
  - `try { … } catch (\Exception $e) { throw $e; }` (lines 36 / 114–116) is a no-op catch. Either wrap the throw with context or remove it.
  - Returns an empty `AddResult()` (line 30) — callers get no affected-rows count, no last-insert-id, no error detail from `queryExecute`.

- **`DeleteByQueryTrait::deleteWhere`** (lines 19–48)
  - `preg_match('/^SELECT\s.*?\s(FROM\s.*)$/si', $selectSql, $match)` rewrites the SQL `SELECT` emitted by the ORM into a `DELETE alias FROM ...` by string replacement. Breaks on queries with `WITH` (CTEs), with subqueries prefixed by `FROM` in the projection, with inline comments containing `FROM`, or with Bitrix alias mangling that changes between ORM versions. The fallback is a `SystemException('invalid deleteBatch query')`.
  - Same `try { … } catch (\Exception $exception) { throw $exception; }` no-op.

- **`UpdateByWhereTrait`** — same try-catch anti-pattern (per `grep -l "throw \$e"`).

- **`MassUpdateTrait`** is the positive counter-example: proper transaction (`startTransaction`/`commitTransaction`/`rollbackTransaction`), validates composite-vs-simple PK consistency, uses `modifyValueBeforeSave` + `convertToDb` through the ORM field pipeline. This is the template the other traits should converge on.

- **`Traits/FluentTrait.php`** — also contains `throw $e` rethrow.

#### 1.3.4 HighloadBlock singleton + filesystem scan (medium)

- **`HighloadBlock\Base::$_instance`** is a `protected static` array keyed by `getName()`. The class is abstract; `getInstance()` does `new static()` once per concrete. Global singleton state: not test-isolated, `__clone`/`__wakeup` disabled but no `resetInstance` hook. Each `new` calls `Loader::includeModule('highloadblock')` (throws `SystemException`) and immediately `fillEntity()` which hits `HighloadBlockTable` via a fresh `Query` (no cache).
- **`HighloadBlockManager::createTable` / `dropTable`** scan the module's `lib/` directory via `Filesystem::classFinder()->extends($libPath, Base::class)` and iterate. For N HL blocks that means N `Loader::includeModule` guards + N `HighloadBlockTable` queries + N `UserFieldTable` queries (from `refreshFields`). No batching, no bulk caching.
- **`refreshFields()`** (line 195) — if the map entry is `AbstractField` it's created/updated; if the map entry is a plain array, the branch `else { if ($data instanceof AbstractField) ... }` means array-shaped entries that do not exist in the DB are silently dropped. Either accept arrays explicitly or `throw` on unknown types.

#### 1.3.5 Cache traits without a Bitrix backend or TTL wiring (medium)

- **`Traits/Cacheable.php`** — a process-local static `array $cache`. `setToCache` takes no TTL. Prefix is `static::class . '::'`. No integration with Bitrix `\Bitrix\Main\Application::getInstance()->getManagedCache()` or PSR-16. Good for hot-path memoization, useless across requests.
- **`Traits/RememberCachable.php`** — adds TTL (`expires` timestamp) but still process-local. `public static function __callStatic` routes `::remember(...)` to a new instance each call (`(new static())->remember(...)`), which means the "static shortcut" is a different instance from the caller's and does not share cache with it unless both reach the same `self::$cache`. It does because `$cache` is `static` on the trait — but this is subtle and undocumented; the magic constructor call also requires the trait's host class to have a public/no-arg constructor. For `HighloadBlock\Base` (which has a `protected __construct`) the `__callStatic` path will throw.
- **`File/FileService::cleanCache`** relies on `defined('CACHED_b_file')` — a legacy Bitrix constant for the b_file table bucket cache. Appropriate here, but the rest of the package does not use `\Bitrix\Main\Application::getInstance()->getManagedCache()` at all (only this one place), so there is no consistent cache invalidation story.

#### 1.3.6 Unbounded loop in file upload path (low)

- **`File/FileService::generateRandomSubdir()`** (line 611) — `while (true) { … if (!$fylesystem->existsDirectory(...)) return $subdir; }`. Bounded in practice by 32-char `Security\Random::getString(32)` collisions being astronomically rare, but syntactically unbounded + typo in `$fylesystem`.

#### 1.3.7 FileService: silent failures (medium)

- **`getFileData`**, **`getFilesData`**, **`updateDescription`**, **`deleteFile`** all catch `\Exception` (or `\Throwable`) and return `null` / `false`. No logger injection. A production deletion that throws a DB error looks identical to "file not found". Inject `Psr\Log\LoggerInterface` and log.

- **`getFilesByFilter`** (line 173) calls `setFilter(normalizeFilter($filter))` that explicitly whitelists fields to 9 columns. Not a bug, but documents a non-obvious behaviour — worth a `@note` in the PHPDoc.

#### 1.3.8 PHPStan: level 5, partial paths (high)

`phpstan.neon` analyses only:
```
src/Storage/SqlHelper.php
src/Config
src/Foundation
src/Logger/{LoggerFactory,ModuleLoggerFactory,ServiceProvider}.php
src/Migration/{BaseEntity,Facade,Result}.php
src/Module
src/Contracts/Config/Repository.php
src/Contracts/Support/Renderable.php
src/Support/Facade.php
src/Support/helpers.php
src/Support/Facades/Config.php
tests
```

That is <15 of ~306 files. The big, legacy-laden subsystems (`EntityView`, `UI/*`, `Storage/Base`, `Storage/Concerns/*`, `HighloadBlock/*`, `File/FileService`, `Settings/*`, `Agent/*`, `Component/*`, `Page/*`, `Iblock/*`, `Event/*`, most of `Traits/*`, `Filesystem/*`, `Module/Entity`) have no static-analysis floor.

Level 5 itself is below modern-project norms for PHP 8.2 (level 8 / strict-rules is the common bar).

#### 1.3.9 `declare(strict_types=1)` absent almost everywhere (high)

Out of 306 `src/` files, 283 (≈92.5%) do **not** declare strict types. Consequence: `int|string` implicit coercion in places like `Module/Entity::fillPath` (which accepts arbitrary `$moduleHolder` strings built from `Loader::LOCAL_HOLDER` constants — happens to be safe today, but the boundary is unchecked).

#### 1.3.10 `@dev` composer constraints (high)

`composer.json` requires five of the `mb4it/*` siblings at `@dev` (`container`, `stringable`, `collections`, `conditionable`, `logger`) plus `avshatalov48/bitrix-core-business @dev` in dev-deps. The package cannot be `composer require`d by external consumers with a normal stability policy (`minimum-stability: stable` in consumer projects forces an explicit `minimum-stability: dev` or `stability-flags`). The README notes "align versions per your registry policy" but there are no tagged releases yet.

#### 1.3.11 `Foundation/Application` reaches into the parent Container via Reflection (medium)

```php
private static ?\ReflectionProperty $containerAliasesReflection = null;
private static ?\ReflectionProperty $containerBindingsReflection = null;

private function aliasesRegistry(): AliasRegistry {
    self::$containerAliasesReflection ??= (function () {
        $p = (new ReflectionClass(Container::class))->getProperty('aliases');
        $p->setAccessible(true);
        return $p;
    })();
    return self::$containerAliasesReflection->getValue($this);
}
```

`MB\Bitrix\Foundation\Application` accesses private `$aliases` / `$bindings` on `MB\Container\Container` via reflection to implement `resolveAliasName` and `getConcreteBinding`. Any future refactor of `mb4it/container` that renames or removes those properties silently breaks `buildWithParameters` / `makeWith` on this side — with no PHPStan coverage today to catch it. Either the container should expose protected accessors, or these paths should be re-routed through its public API (`has` / `make`) with acceptance of the non-parameterised semantics.

#### 1.3.12 String-keyed per-module services (medium)

`Application::registerModule($moduleId)` registers:
```php
"$moduleId:module", "$moduleId:config", "$moduleId:migration", "$moduleId:logger"
```

String service keys are stringly-typed, un-refactorable, and invisible to IDE navigation. Compare to typed factory methods (`->moduleContainer($id)->module()`, `->migrationFacade()`). The `mb.core` module already uses a `getByModule('config', $moduleId)` indirection — the bitrix-support side could formalise that.

#### 1.3.13 Cross-cutting: error collection vs exception vs boolean return (medium)

Inconsistent error surfacing across the package:

- `HighloadBlock\Base` → `ErrorCollection` (pull model) plus `SystemException` at construction.
- `Storage` traits → `AddResult` / `UpdateResult` / `DeleteResult` (Bitrix's pull model) plus `SystemException` / `ArgumentException`.
- `File\FileService` → boolean or `null` return on failure, silent swallow of `\Exception`.
- `Migration\Facade::callEntity` → `Result` with `addThrowable($throwable)`. This is the closest to a uniform approach.

Pick one (the `Migration\Result` pattern is strong) and standardise.

#### 1.3.14 `Support/helpers.php` global functions (low)

`app()`, `config()`, `module()` are declared behind `function_exists` guards. Fine for a framework, but `app()` returns `mixed` when an abstract is provided — caller typing is lost. Consider generic `app<T>(class-string<T> $abstract): T` via `@template`/`@return` PHPDoc.

#### 1.3.15 MySQL `VALUES(col)` deprecation + MariaDB uncertainty (medium)

`SqlHelper::buildMysqlUpsertSql` emits `… ON DUPLICATE KEY UPDATE col = VALUES(col)`. MySQL 8.0.20+ deprecates `VALUES()` in favour of row aliases (`… AS new ON DUPLICATE KEY UPDATE col = new.col`). MySQL 8.0.19 and below, and MariaDB 10.x, still require `VALUES()`. README explicitly says "Confirm MariaDB behaviour in your version." The current behaviour is correct for the majority environment but will emit deprecation warnings on modern MySQL.

`SqlHelper::buildMysqlUpdateSql` uses `ROW(val1, val2)` syntax inside `UPDATE tbl AS t INNER JOIN (VALUES ROW(…),ROW(…)) AS updates(cols) …`. MySQL 8.0.19+ supports this. MariaDB as of 10.6 still does not support `VALUES` as a derived table with column aliases in this form.

### 1.4 Performance / scale

- **HL-block sync** — O(N) with N = number of HL classes, no batching, one full `HighloadBlockTable` query per class (`HighloadBlock\Base::getHlblock()` → `new Query(HighloadBlockTable::class)->where('NAME',…)->where('TABLE_NAME',…)->fetch()` per `getInstance`).
- **`FileService::fileDataCache`** — per-instance in-memory map; request-scoped. Cleaned on write. No managed-cache invalidation.
- **`Storage\Concerns\BatchUpsertTrait`** — builds one large `INSERT ... VALUES (...)(...)(...)` string. No chunking: a 10k-row call will emit a 10k-row SQL. MySQL `max_allowed_packet` is the implicit limit; worth chunking by default.
- **EntityView `checkPermissions`** — `Event::send()` fires per request on every entity-view action; not memoized.

### 1.5 Tests

- **Unit tests**: `Config/ArrayRepositoryTest`, `Logger/LoggerServiceProviderTest`, `Migration/FacadeTest`, `Migration/FacadeLoadTest`, `Migration/FileEntityTest`, `Storage/SqlHelperTest`, `Support/HelpersAndFacadeTest`. That covers ≈ 6 of ~300 source files.
- **Integration**: `tests/Integration/Migration/RealBitrixFileEntitySmokeTest`, `RealBitrixResultSmokeTest` — require a real Bitrix checkout. Good in intent, smoke-only in scope.
- No tests for `HighloadBlock/*`, `Storage/Base` + four of five `Concerns/*` traits, `File/FileService`, `EntityView`, `UI/*`, `Settings/*`.
- `phpunit.xml` correctly separates `default` vs `integration` suites via `<exclude>tests/Integration</exclude>`.

---

## 2. Phase 2 — Refactor plan

### 2.1 Package-level roadmap (`mb4it/bitrix-support`)

#### 2.1.1 Namespace unification (priority: H)

Pick **`MB\Bitrix\`** as the one true public namespace.

- Move all `src/` files under `MB\Core\Config\*`, `MB\Core\Settings\*`, `MB\Core\Foundation\*` (if any) to `MB\Bitrix\…` mirrors.
- For every renamed class, emit a `class_alias(MB\Bitrix\New::class, MB\Core\Old::class)` in a dedicated `src/compat/aliases.php` loaded via `composer.json` `autoload.files`. Mark the aliases `@deprecated` with a version (`since 2.0, removed in 3.0`).
- Update `composer.json`:
  ```json
  "autoload": {
    "psr-4": { "MB\\Bitrix\\": "src/" },
    "files": ["src/Support/helpers.php", "src/compat/aliases.php"]
  }
  ```
- Coordinate with `mb.core` consumers (see §2.2).

Acceptance: `grep -r "namespace MB\\\\Core" src/` returns 0 lines.

#### 2.1.2 Extract Bitrix legacy into contracts + adapters (priority: H)

Create contracts for every place a `global $APPLICATION`, `new \C…`, or bare `GetMessage(...)` appears:

- `Contracts\Bitrix\CurrentApplication` → methods `getGroupRight(string $moduleId)`, `requireAuthForm(string $message)`, `getLastError()`.
- `Contracts\Bitrix\UserFieldRegistry` → `create(array $definition): int`, `update(int $id, array $definition): bool`.
- `Contracts\Bitrix\DiskQuota` → `checkUpload(array $fileData): bool`, `notifyInsert(int $size): void`, `notifyDelete(int $size): void`.
- `Contracts\Bitrix\HtmlEditor` → `render(array $params): string`.
- `Contracts\Localization\Translator` → replace `GetMessage` / `message()` helper uses.

Default Bitrix-backed adapters live under `Bitrix/` (e.g. `Bitrix/DiskQuotaAdapter` wrapping `\CDiskQuota`). Classes under audit (`HighloadBlock\Base`, `EntityView\Base`, `File\FileService`, `UI/Control/Field/HtmlEditorField`) take the contract via constructor. The DI container binds the default adapter; tests bind a fake.

Acceptance: `grep -rE "global \\\$APPLICATION|global \\\$DB|global \\\$USER" src/` returns 0.

#### 2.1.3 Fix the four `Storage\Concerns\*` traits (priority: H)

- **`BatchUpsertTrait`**:
  - Collect the union of fields across all rows, then normalize each row to that union (emitting `NULL` / defaults for missing keys) so the generated `INSERT INTO ({fields})` always matches row widths.
  - Chunk by `max_allowed_packet` / configurable `chunkSize` (default 500).
  - Populate `AddResult` with `$connection->getAffectedRowsCount()` and (for MySQL) `$connection->getInsertedId()` when the INSERT is non-ignored.
  - Remove `try { … } catch { throw $e; }`. Let exceptions bubble, or catch-wrap with context (`throw new StorageOperationException('addBatch failed', 0, $e)`).

- **`DeleteByQueryTrait`**:
  - Do not regex-rewrite SELECT SQL. Instead, build the DELETE directly from `Query`'s public filter tree via `$query->getFilterHandler()->getConditionTree()` and `$connection->getSqlHelper()->prepareDelete($tableName, $whereSql)`.
  - If a safe rewrite path cannot be implemented, at minimum guard: bail with a clear exception when the SELECT contains `WITH ` (CTE), comments, or UNION — do not silently proceed.

- **`UpdateByWhereTrait`**: drop the useless try-catch; add a test covering non-trivial filter trees.

- **`MassUpdateTrait`**: leave mostly alone; it is the template. Add a test for composite primary keys.

- **`FluentTrait`**: drop the useless try-catch.

Acceptance: the existing `Storage/SqlHelperTest` expands to cover `addBatch` shape conformance (same field set across rows), chunking, and deleteWhere SQL emission.

#### 2.1.4 HighloadBlock de-singletonification + caching (priority: M)

- Replace `protected static $_instance` with a container-resolved factory (`$app->make(HighloadBlockRegistry::class)->forClass(static::class)`).
- Cache the `HighloadBlockTable::fetch()` result per process in a dedicated `HighloadBlockRegistry` (PSR-16 backing) keyed by `NAME|TABLE_NAME`. Invalidate on `createTable` / `dropTable`.
- `HighloadBlockManager::createTable`: batch the class-discovery scan result (one `classFinder` call is fine; the N queries are the cost). Consider a single `HighloadBlockTable::getList(filter: [NAME in [...]])` to prefill the registry before the loop.
- `refreshFields()`: explicitly handle array-shaped map entries (accept a normalized `buildUserField` from arrays) or throw `InvalidArgumentException` on unknown types.

#### 2.1.5 Unified cache abstraction (priority: M)

- Introduce `Contracts\Cache\Repository` (PSR-16 lookalike: `get`, `set(key, value, ttl)`, `forget`, `remember`, `flush`).
- Default adapter: `Bitrix\ManagedCacheAdapter` that wraps `\Bitrix\Main\Application::getInstance()->getManagedCache()` with a consistent `module:dir/key` convention derived from `registerModule($id)`.
- Re-implement `Traits\Cacheable` as a thin shim that calls through `app('cache')`. Preserve the static-class-prefix semantics for backward compatibility.
- Delete `Traits\RememberCachable` or rewrite it on top of the same repository. Fix the `__callStatic` + `new static()` footgun for classes with non-public constructors.
- Wire the `.settings.php` file to expose a `cache.store` setting so an installation can swap Bitrix-managed cache for in-memory (tests) or APCu (hot-path).

Acceptance: `grep -r "static \\\$cache" src/Traits` returns 0 definitions (only uses via the repository).

#### 2.1.6 PHPStan to level 8, full `src/` coverage (priority: H)

- Expand `phpstan.neon` `paths:` to `src/`.
- Raise `level: 5` → `level: 8` gradually; commit a `phpstan-baseline.neon` for the current noise, then burn it down.
- Require `declare(strict_types=1);` on every new file. Add a PHPCS rule or a `scripts/dev/lint.php` check.
- Scan files for Bitrix stubs already exist (`scripts/dev/bitrix-app-minimal.php`, `bitrix-db-minimal.php`, `bitrix-phpstan-stubs.php`); extend them to cover `CUserTypeEntity`, `CDiskQuota`, `CHTMLEditor`, `CAllMain`, `$APPLICATION->*` for the files that still use them during the deprecation cycle.

Acceptance: `composer phpstan` runs at level 8 on all of `src/` with an accepted baseline.

#### 2.1.7 `@dev` version tagging (priority: H)

- Tag `mb4it/container`, `mb4it/stringable`, `mb4it/collections`, `mb4it/conditionable`, `mb4it/logger` at `0.x` or `1.x`.
- Replace `@dev` constraints with `^0.x` / `^1.x` in `bitrix-support` `composer.json`.
- Remove the `avshatalov48/bitrix-core-business @dev` dev-dep constraint in favour of the tagged version (or a versioned `path` repository).

Acceptance: `composer require mb4it/bitrix-support` succeeds in a project with default stability `stable`.

#### 2.1.8 `@deprecated` roadmap table (priority: M)

Add `docs/DEPRECATIONS.md`:

| Entry | Since | Removed in | Replacement |
|---|---|---|---|
| `Migration\Facade::upAgents()` / `downAgents()` | (current) | 3.0 | Dedicated `AgentMigrationFacade` in `Migration\Facades\` |
| `MB\Core\Config\ConfigLocator` (and all `MB\Core\*` FQCNs) | 2.0 | 3.0 | `MB\Bitrix\Config\ConfigLocator` (§2.1.1) |
| `Traits\RememberCachable::__callStatic('remember')` | 2.0 | 2.1 | `app('cache')->remember(…)` |
| Direct `global $APPLICATION` uses in public methods | 2.0 | 3.0 | `Contracts\Bitrix\CurrentApplication` (§2.1.2) |
| `DeleteByQueryTrait::deleteWhere` regex path | 2.0 | 2.1 | Direct DELETE from `Query` filter tree (§2.1.3) |
| EntityView `Builder` / `Grid` deprecated method subset | (current) | next minor | (to be filed) |

#### 2.1.9 Test-coverage expansion (priority: M)

New unit tests:

- `Storage/BaseBatchUpsertTest` — mixed-key rows → union; chunk boundaries; empty input; PK variance.
- `Storage/DeleteByQueryTest` — simple `whereIn`, composite PK, CTE-containing SELECT rejection.
- `HighloadBlock/BaseTest` — with a fake `HighloadBlockTable` via the new registry contract.
- `File/FileServiceTest` — with a fake `Filesystem`, `FileTable`, `DiskQuota` contract; covers `saveFile` → duplicate path, `deleteFile` → logs on failure.
- `EntityView/BaseTest` — with a fake `CurrentApplication` contract for `checkPermissions`.

Integration:

- Expand the two smoke tests with at least: `addBatch` round-trip, HL-block create + refresh + drop, file save-with-dedup.

Acceptance: line coverage > 70% on the six critical subsystems above.

#### 2.1.10 Error-surfacing consolidation (priority: L)

- Pick `Migration\Result` as the canonical return shape for write paths (it already holds errors + throwables + per-step data).
- Migrate `FileService::deleteFile` / `updateDescription` etc. to return `Result` and to inject a logger.
- Keep Bitrix ORM's `AddResult` / `UpdateResult` / `DeleteResult` at the ORM boundary, translate to `Result` at the service boundary.

### 2.2 Integration roadmap for `mb.core`

Given `mb.core` does not currently depend on `bitrix-support`:

#### 2.2.1 Introduce the dependency (priority: H)

- Add `"mb4it/bitrix-support": "^1.0"` to `mb.core/composer.json` after §2.1.7 tagging is in.
- Load both autoloaders via `mb.core/include.php` (the line `require __DIR__ . '/vendor/autoload.php';` already exists).

#### 2.2.2 Collapse duplicates (priority: H) — specific files to touch

Delete from `mb.core/lib` and replace with `use MB\Bitrix\…`:

- `mb.core/lib/KernelApplication.php` → use `MB\Bitrix\Foundation\Application` (keep `class_alias` shim under `MB\Core\KernelApplication` if public API widely references it).
- `mb.core/lib/Container/Container.php`, `ContainerException.php`, `NotFoundException.php` → delete; use `MB\Container\Container` transitively.
- `mb.core/lib/Config/*` (`ConfigEntity`, `ConfigLocator`, `ConfigManager`, `UseOptions`) → all present in `mb-bitrix-support/src/Config/*`. Delete from `mb.core`, update `mb.core/lib/Reference/Config/*` and `mb.core/lib/Settings/*` imports.
- `mb.core/lib/Module/ModuleContainer.php`, `ModuleEntity.php` → delete; `MB\Bitrix\Module\{ModuleContainer,Entity}` replaces them. `ModuleManager` stays in `mb.core` (not in bitrix-support).
- `mb.core/lib/Migration/MigrationFacade.php`, `StateTable.php`, `Storage.php`, `Version.php`, `File.php`, `Event.php`, `Agent.php`, `Entity/*`, `Reference/*` → migrate to `MB\Bitrix\Migration\*`. Inverse dependency: `mb.core/lib/Migration/Controllers/*` (not audited above) likely remains module-specific.
- `mb.core/lib/Page/Common.php` → delete; use `MB\Bitrix\Page\Common`.
- `mb.core/lib/Settings/Page/*`, `Options/*`, `Controller/*` → migrate to `MB\Bitrix\Settings\*` (the tree already exists in bitrix-support).
- `mb.core/lib/UI/Control/*`, `UI/Reference/*` → migrate to `MB\Bitrix\UI\Control\*` / `MB\Bitrix\UI\…`. Largest chunk (~80 files).
- `mb.core/lib/Support/Text/Str.php` → delete; use `MB\Support\Str` (from sibling `mb4it/stringable`) already used by bitrix-support.
- `mb.core/lib/Support/Conditionable/*` → delete; `mb4it/conditionable` dep covers it.
- `mb.core/lib/Support/Finder/ClassFinder.php` → delete; `MB\Bitrix\Filesystem\Filesystem::classFinder()` covers it.
- `mb.core/lib/Support/File/Image/*` → converge with `MB\Bitrix\File\Image\*`.
- `mb.core/lib/helper.php` → keep as a thin shim over `app()` from `bitrix-support/src/Support/helpers.php`; `dump()`/`dd()` can stay module-local.

Rule of thumb: if a `mb.core/lib/*.php` class has the same FQCN as an `mb-bitrix-support/src/*.php` class, **the bitrix-support version wins**; update `mb.core` to consume it.

#### 2.2.3 Staged cutover (priority: M)

For each chunk above:

1. Tag a `mb.core` release on `main`.
2. Switch `use MB\Core\X` → `use MB\Bitrix\X` in `mb.core/lib` internal consumers.
3. Delete the duplicated `mb.core/lib/X.php`. Rely on `MB\Bitrix\X` + the `class_alias` shim from §2.1.1 to cover external `MB\Core\X` consumers.
4. Run `mb.core`'s regression (ideally: add a smoke test under `mb.core/tests` that boots the kernel, registers the module, resolves config/migration facade, renders one admin settings page).
5. Commit per chunk; keep PR diffs focused.

Order of chunks by risk (low → high):
- Config (4 files)
- Module container (2 files)
- Migration (~12 files)
- Settings / Page (~10 files)
- UI (~80 files — riskiest; do last).

#### 2.2.4 Kernel merge (priority: H)

Merge `MB\Core\KernelApplication` and `MB\Bitrix\Foundation\Application`:

- `KernelApplication::getByModule(key, moduleId)` maps cleanly to `Application::make("$moduleId:$key")`.
- `KernelApplication::registerModule(ModuleContainer $c)` → `Application::registerModule(string $id)` + a dedicated `ModuleContainer` decorator if the per-module container boundary is valuable (it mostly duplicates service-key prefixing today).
- `KernelApplication::getCoreContainer()` → `Application::make('app')` (core container = the app).

This is the heaviest chunk and should be the final step, not the first.

### 2.3 Prioritised task list (H/M/L)

| # | Prio | Area | Task | Files / scope |
|---|---|---|---|---|
| 1 | **H** | Namespace | Merge `MB\Core\*` → `MB\Bitrix\*`, add `class_alias` shims in `src/compat/aliases.php` | `composer.json`, `src/Config/*`, `src/Settings/*`, new `src/compat/` |
| 2 | **H** | Bitrix coupling | Introduce `Contracts\Bitrix\CurrentApplication`, `UserFieldRegistry`, `DiskQuota`, `HtmlEditor`, `Translator`; inject into `HighloadBlock\Base`, `EntityView\Base`, `File\FileService`, `UI/Control/Field/HtmlEditorField` | `src/HighloadBlock/Base.php:159/197`, `src/EntityView/Base.php:123`, `src/File/FileService.php:465/880`, `src/UI/Control/Field/HtmlEditorField.php:24` |
| 3 | **H** | Storage | Fix `BatchUpsertTrait` (field-union, chunking, populate `AddResult`); remove try-catch-rethrow | `src/Storage/Concerns/BatchUpsertTrait.php` |
| 4 | **H** | Storage | Replace regex `SELECT→DELETE` with direct DELETE builder | `src/Storage/Concerns/DeleteByQueryTrait.php` |
| 5 | **H** | CI | Raise PHPStan `level: 5 → 8`, expand paths to `src/`, baseline | `phpstan.neon`, `scripts/dev/*stubs.php` |
| 6 | **H** | Composer | Tag sibling `mb4it/*` packages and swap `@dev` constraints for `^1.x` | `composer.json` |
| 7 | **H** | mb.core | Add `mb4it/bitrix-support` to `mb.core/composer.json`; begin Config chunk cutover | `mb.core/composer.json`, `mb.core/lib/Config/*`, `mb.core/lib/Reference/Config/*` |
| 8 | **M** | HighloadBlock | De-singletonify `HighloadBlock\Base`; add `HighloadBlockRegistry` with Bitrix-cache backing | `src/HighloadBlock/Base.php`, `src/HighloadBlock/HighloadBlockManager.php`, new `src/HighloadBlock/Registry.php` |
| 9 | **M** | Cache | `Contracts\Cache\Repository` + Bitrix `ManagedCache` adapter; rewrite `Traits\Cacheable` and `Traits\RememberCachable` over it | `src/Traits/Cacheable.php`, `src/Traits/RememberCachable.php`, new `src/Contracts/Cache/Repository.php`, new `src/Bitrix/ManagedCacheAdapter.php` |
| 10 | **M** | Storage | Remove try-catch-rethrow in `UpdateByWhereTrait`, `FluentTrait`; add test for composite PK in `MassUpdateTrait` | `src/Storage/Concerns/UpdateByWhereTrait.php`, `src/Traits/FluentTrait.php`, `tests/Storage/*` |
| 11 | **M** | Logging | Inject `Psr\Log\LoggerInterface` into `File\FileService`; replace `return false` swallows with logged warnings | `src/File/FileService.php` |
| 12 | **M** | Strict types | Add `declare(strict_types=1);` everywhere; lint guard | 283 of 306 files |
| 13 | **M** | Container | Expose `protected` accessors in `MB\Container\Container` for `aliases` / `bindings`, drop Reflection hacks | `mb4it/container` package, then `src/Foundation/Application.php:265-293` |
| 14 | **M** | mb.core | Cutover Module + Migration chunks | `mb.core/lib/Module/*`, `mb.core/lib/Migration/*` |
| 15 | **M** | Docs | Add `docs/DEPRECATIONS.md`; link from README | `docs/DEPRECATIONS.md`, `README.md` |
| 16 | **L** | Perf | Chunk `BatchUpsertTrait` batches by size; expose `chunkSize` parameter | `src/Storage/Concerns/BatchUpsertTrait.php` |
| 17 | **L** | Perf | Prefill `HighloadBlockRegistry` with one query in `HighloadBlockManager::createTable/dropTable` | `src/HighloadBlock/HighloadBlockManager.php` |
| 18 | **L** | Code | Rename `$fylesystem` → `$filesystem`; convert `while (true)` loop to bounded retry | `src/File/FileService.php:608-619` |
| 19 | **L** | MySQL | Switch `ON DUPLICATE KEY UPDATE col = VALUES(col)` to row-alias syntax on MySQL ≥ 8.0.20; detect version | `src/Storage/SqlHelper.php:138-144` |
| 20 | **L** | API | Type `app(class-string<T>): T` via `@template` in `src/Support/helpers.php` | `src/Support/helpers.php` |
| 21 | **L** | mb.core | UI cutover (last, highest file count) | `mb.core/lib/UI/*` → `MB\Bitrix\UI\*` |
| 22 | **L** | mb.core | Final kernel merge: delete `mb.core/lib/KernelApplication.php` in favour of `MB\Bitrix\Foundation\Application` | `mb.core/lib/KernelApplication.php`, `mb.core/lib/helper.php` |

---

## 3. Architecture diagram

```mermaid
flowchart TD
    subgraph Composer["mb4it/bitrix-support · composer"]
        CJ[composer.json<br/>dual PSR-4: MB\\Bitrix + MB\\Core]
        HELP[src/Support/helpers.php<br/>app / config / module]
    end

    subgraph Foundation["Foundation layer"]
        APP[Foundation\\Application<br/>extends MB\\Container\\Container<br/>777 lines, static getInstance]
        SP[ServiceProvider<br/>abstract base]
    end

    subgraph Providers["ServiceProviders registered on boot"]
        P1[Page\\ServiceProvider]
        P2[Filesystem\\ServiceProvider]
        P3[File\\ServiceProvider]
        P4[Migration\\ServiceProvider]
        P5[Bitrix ServiceProvider<br/>root src/ServiceProvider.php]
        P6[Logger\\ServiceProvider]
        P7[Module\\ServiceProvider]
    end

    subgraph Subsystems["Subsystems"]
        MOD[Module\\Entity<br/>ModuleContainer<br/>registerModule → moduleId:module/config/migration/logger]
        CFG[Config\\ArrayRepository<br/>Config\\Entity · ConfigLocator · ConfigManager]
        MIG[Migration\\Facade<br/>BaseEntity · BaseEntityManager<br/>Entities/ Agent · Event · File · Storage]
        STO[Storage\\Base abstract DataManager<br/>+ Concerns<br/>BuildIndexes · BatchUpsert · UpdateByWhere · MassUpdate · DeleteByQuery<br/>+ SqlHelper MySQL/PgSQL]
        HL[HighloadBlock\\Base<br/>singleton static _instance<br/>+ HighloadBlockManager]
        FIL[File\\FileService<br/>+ Image pipeline]
        LOG[Logger<br/>LoggerFactory · ModuleLoggerFactory<br/>EventLogger · NotificationLogger · UniversalLogger]
        EV[EntityView\\Base · Builder<br/>Grid · Form · Filter · Actions]
        UI[UI\\Control Control/Base split<br/>Field · Row · Tab · Form<br/>EntitySelector · Providers]
        SET[Settings\\Builder · Page · Controller · Options]
        IB[Iblock\\Helper · DetailUrl · UserType]
        AG[Agent\\AgentManager]
        EVT[Event\\EventManager · Base]
        CMP[Component\\BaseComponent · ControllerComponent]
    end

    subgraph Bitrix["1C-Bitrix runtime"]
        BX_MAIN[Bitrix\\Main<br/>Application · Loader · ORM · Config · Web · Security · IO]
        BX_HL[Bitrix\\Highloadblock<br/>HighloadBlockTable · compileEntity]
        BX_GLOB[Legacy globals<br/>\\$APPLICATION · CUserTypeEntity · CDiskQuota · CHTMLEditor · GetMessage]
    end

    subgraph External["mb4it/* peers"]
        C1[mb4it/container @dev]
        C2[mb4it/stringable @dev]
        C3[mb4it/collections @dev]
        C4[mb4it/conditionable @dev]
        C5[mb4it/filesystem ^1.0]
        C6[mb4it/logger @dev]
        SP1[spatie/image ^3.8]
        PSR[psr/log]
    end

    subgraph MbCore["mb.core Bitrix module · separate codebase"]
        MK[MB\\Core\\KernelApplication<br/>mb.core/lib/KernelApplication.php]
        MC[MB\\Core\\Container\\Container<br/>own re-implementation]
        MCFG[MB\\Core\\Config\\ConfigLocator<br/>FQCN collision with bitrix-support]
        MCM[MB\\Core\\Module\\ModuleContainer<br/>FQCN collision]
        MMIG[MB\\Core\\Migration\\MigrationFacade]
        MHELP[mb.core/lib/helper.php<br/>app · module · config · dump · dd · message]
    end

    CJ --> APP
    CJ --> HELP
    APP --> SP
    APP --> P1 & P2 & P3 & P4 & P5 & P6 & P7
    P1 --> Subsystems
    P2 --> Subsystems
    P3 --> FIL
    P4 --> MIG
    P5 --> Subsystems
    P6 --> LOG
    P7 --> MOD
    MOD --> CFG
    MOD --> MIG
    STO --> BX_MAIN
    HL --> BX_HL
    HL -. "global \\$APPLICATION · new CUserTypeEntity" .-> BX_GLOB
    EV -. "global \\$APPLICATION · AuthForm · GetGroupRight mb.core" .-> BX_GLOB
    FIL -. "CDiskQuota · GetMessage · BX_FILE_PERMISSIONS" .-> BX_GLOB
    UI -. "CHTMLEditor" .-> BX_GLOB
    APP --> C1
    HELP --> C2 & C3 & C4
    P2 --> C5
    LOG --> C6 & PSR
    FIL --> SP1

    MK -. "runtime collision risk · same MB\\Core\\* FQCNs" .-> CFG
    MCFG -. collision .-> CFG
    MCM -. collision .-> MOD
    MMIG -. parallel impl .-> MIG
    MHELP -. duplicates Support/helpers.php .-> HELP

    classDef red fill:#ffe2e2,stroke:#c00
    classDef amber fill:#fff3e0,stroke:#e67e22
    classDef green fill:#e8f5e9,stroke:#2e7d32
    class BX_GLOB,MCFG,MCM,MK red
    class HL,EV,FIL,UI,STO amber
    class MIG,LOG,CFG,MOD green
```

Legend: red = legacy or collision hotspot; amber = modern class with legacy leakage; green = clean subsystem.

---

## 4. Appendix — evidence pointers

- Dual PSR-4: `mb-bitrix-support/composer.json:30-37`
- `global $APPLICATION` in modern classes: `grep -rn "global \\$APPLICATION" mb-bitrix-support/src` → 20+ hits including `HighloadBlock/Base.php:159`, `EntityView/Base.php:123`, `UI/Control/Tab/GroupRightsTab.php`, `Settings/Page/Page404.php`.
- Try-catch-rethrow: `BatchUpsertTrait.php:36,114-116`; `DeleteByQueryTrait.php:31,43-45`; `UpdateByWhereTrait.php`; `MassUpdateTrait.php`; `Traits/FluentTrait.php`.
- Regex SELECT-to-DELETE: `DeleteByQueryTrait.php:34`.
- Singleton: `HighloadBlock/Base.php:22 + 59-67`.
- Legacy C-class instantiation: `HighloadBlock/Base.php:160,197` (`\CUserTypeEntity`); `File/FileService.php:880` (`\CDiskQuota`); `UI/Control/Field/HtmlEditorField.php:24` (`\CHTMLEditor`).
- `while (true)`: `File/FileService.php:611` (`$fylesystem` typo inside).
- PHPStan partial coverage: `phpstan.neon:7-23`.
- Strict types absent: 283 / 306 `src/` files lack `declare(strict_types=1)`.
- `@dev` constraints: `composer.json:9-17,20`.
- Reflection hack on parent container: `Foundation/Application.php:265-293`.
- `mb.core` has no `MB\Bitrix\` imports: `grep -r "use MB\\\\Bitrix" mb.core` → 0 results.
- Duplicate `MB\Core\Config\ConfigLocator`: `mb.core/lib/Config/ConfigLocator.php` vs `mb-bitrix-support/src/Config/ConfigLocator.php`.
- Duplicate `MB\Core\Module\ModuleContainer`: both paths present in `ls`.
- `KernelApplication` vs `Foundation\Application`: `mb.core/lib/KernelApplication.php` vs `mb-bitrix-support/src/Foundation/Application.php`.
