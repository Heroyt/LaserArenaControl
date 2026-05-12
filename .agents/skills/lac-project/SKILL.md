---
name: lac-project
description: Use for general development in LaserArenaControl, including PHP services, controllers, routes, CQRS
commands, console commands, modules, Docker/RoadRunner runtime, validation, and repository conventions.
---

# LaserArenaControl Project Workflow

## Start

1. Read `AGENTS.md`.
2. Inspect the nearest implementation, test, route, and config files before editing.
3. Check `git status --short` and preserve user changes.

## Main Map

- HTTP routes: `routes/web.php`, `routes/api.php`, `routes/public.php`, `routes/settings.php`.
- Controllers: `src/Http/Controllers/`.
- Services: `src/Services/`.
- DI/config: `config/services*.neon`, `config/services.php`, `config/symfony.*`, `config/cqrs.neon`.
- CQRS: `src/CQRS/Commands/`, `src/CQRS/CommandHandlers/`, `src/CQRS/Queries/`.
- Console: `src/Cli/Commands/`, entrypoint `bin/console`.
- Scheduled/background: `src/Cron/`, `src/Tasks/`, `config/jobs.neon`.
- Modules: `modules/Tables/`, `modules/Tournament/`, `modules/Core/`.
- Runtime: `.rr.yaml`, `config/roadrunner.neon`, `docker-compose-dev.yml`, `start.sh`.

## Implementation Rules

- Prefer existing LSR/Nette/Dibi patterns over new framework abstractions.
- Add services through existing DI config patterns when constructor injection is needed.
- Keep controller actions thin; put parsing, persistence, sync, and calculations into services or command handlers.
- Preserve route compatibility unless the task explicitly asks to change it.
- Treat `src/GameModels/` as a separate nested Git checkout/ownership area; avoid broad edits there.
- Keep generated/runtime files out of changes: `temp/`, `logs/`, `upload/`, `dist/`, `vendor/`, `node_modules/`, `lac/`,
  `lmx/`, `.env`, `private/config.ini`.

## Validation

Use the narrowest meaningful check:

```sh
vendor/bin/phpunit
composer phpstan
vendor/bin/phpcs
pnpm run build
```

For full regression when behavior spans modules or import flows:

```sh
vendor/bin/phpunit
```

When Docker runtime behavior is relevant:

```sh
docker compose -f docker-compose-dev.yml up
```

Do not run dependency updates or Docker image publishing scripts unless explicitly requested.
