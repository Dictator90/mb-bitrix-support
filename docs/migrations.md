# Migrations

`MB\Bitrix\Migration\Facade` orchestrates module lifecycle migration steps.

## Public facade contract

`MB\Bitrix\Contracts\Migration\Facade` exposes:

- `up()` / `down()`
- `upAll()` / `downAll()`
- `upFiles()` / `downFiles()`
- `upStorages()` / `downStorages()`
- `upEvents()` / `downEvents()`

Agent-specific `upAgents()` / `downAgents()` were removed from the public facade API.

## Default pipeline

`upAll()` runs:

1. `file`
2. `storage`
3. `event`

`downAll()` runs in reverse order:

1. `event`
2. `storage`
3. `file`

Each step returns `MB\Bitrix\Migration\Result`. The facade aggregates step results and errors.

## Agent management

Agent synchronization is no longer part of the migration facade surface.  
Use `MB\Bitrix\Agent\AgentManager` directly for agent lifecycle operations.

## Verification

Run:

```bash
composer test
composer test:integration
```
