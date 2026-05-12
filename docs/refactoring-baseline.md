# Refactoring Baseline (Wave 1)

Date: 2026-04-27

## Current Metrics

- `src/*.php` files: `310`
- files with `declare(strict_types=1)`: `27` (`8.7%`)
- largest files by LOC:
  - `src/File/FileService.php` ~`779`
  - `src/Foundation/Application.php` ~`713`
  - `src/UI/EntitySelector/UserListProvider.php` ~`628`
- test distribution (`*Test.php`):
  - Migration: `5`
  - Foundation: `2`
  - Config/Database/Logger/Module/Storage/Support: `1` each

## Hotspots

- lifecycle orchestration:
  - `src/Foundation/Application.php`
- file subsystem:
  - `src/File/FileService.php`
- batch SQL behavior:
  - `src/Storage/Concerns/BatchUpsertTrait.php`
  - `src/Storage/Concerns/UpdateByWhereTrait.php`
  - `src/Storage/Concerns/DeleteByQueryTrait.php`

## Wave 1 Quality Controls Introduced

- `phpstan.neon` switched to full `src` + `tests` scan.
- `phpstan-baseline.neon` generated for migration to full scan at level 8.
- DB and framework stubs extended for static analysis parity.
- safety compatibility classes added:
  - `src/Reference/Common/Model.php`
  - `src/Reference/Common/Collection.php`

## Target KPIs

- strict types coverage: `>= 80%` of `src/*.php`
- remove baseline items incrementally (goal: baseline size reduction every wave)
- split files over `500` LOC into smaller services
- ensure batch update/delete logic avoids SQL regex rewriting paths
