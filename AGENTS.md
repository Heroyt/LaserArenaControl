# LaserArenaControl Agent Guidelines

These instructions apply to the whole repository. Follow them before making changes in this project, regardless of the
agent or editor being used.

## Project Shape

LaserArenaControl is a PHP 8.4 application for LaserGame arena control, result import, game management, gates/screens,
printing, and LaserLiga integration.

- Main app namespace: `App\`, mapped to `src/`.
- Local module namespaces live under `modules/`, especially `LAC\Modules\Tables\` and `LAC\Modules\Tournament\`.
- Runtime stack: RoadRunner, Nginx, MariaDB, Redis/KeyDB, Gotenberg, Prometheus/Grafana in Docker.
- Backend framework pieces: LSR packages, Nette DI, LSR routing, Dibi ORM/database access, Latte templates, Symfony
  Console/Serializer/Lock, Orisai scheduler.
- Frontend stack: TypeScript, SCSS, Bootstrap 5, Font Awesome subsets, esbuild, pnpm.
- Tests use basic PHPUnit. Static analysis uses PHPStan level 8. Formatting uses PHPCS with the repository `phpcs.xml`.

## Read First

Before changing behavior, inspect the relevant nearby files and config:

- Routes: `routes/web.php`, `routes/api.php`, `routes/public.php`, `routes/settings.php`.
- Services and DI: `config/services*.neon`, `config/services.php`, `config/symfony.*`, `config/cqrs.neon`.
- Console commands: `src/Cli/Commands/` and `bin/console`.
- Async/background work: `src/Cron/`, `src/Tasks/`, `src/CQRS/`.
- Game model behavior: `src/GameModels/` is a nested Git checkout/submodule-like tree; treat it as a separate ownership
  area and avoid broad edits there unless required.
- Frontend entry/build: `assets/js/`, `assets/scss/`, `esbuild.mjs`, `package.json`.
- Templates: `templates/**/*.latte`.
- Translations: `languages/**/*.po`, `languages/**/*.pot`; compiled `.mo` files are generated artifacts unless the task
  explicitly asks for them.

## Coding Rules

- Use `declare(strict_types=1);` in new PHP files unless matching an existing file family that omits it.
- Prefer constructor injection and existing services over `App::getService()` in new code; route files and legacy code
  may still use service lookups.
- Keep public API route names, URL shapes, payload keys, and result file semantics backward compatible unless the task
  explicitly calls for a break.
- Follow existing model and repository patterns around Dibi/LSR ORM instead of introducing a second persistence style.
- Use typed properties, return types, enums, readonly DTOs/services, and PHPDoc generics where they help PHPStan
  understand the code.
- Do not add unrelated refactors while fixing a bug. This codebase has legacy and modern styles side by side; match the
  edited area.
- Be careful with hardware/control flows. Game loading, gates, vest sync, RTSP, and Windows `lmxControl/` scripts affect
  real arena operations.
- Do not commit secrets or local runtime config. `private/`, `.env`, `logs/`, `temp/`, `upload/`, `lac/`, `lmx/`,
  `node_modules/`, `vendor/`, and `dist/` are local/generated.

## Validation

Run the smallest useful check for the change, then broaden if the touched area is shared.

- Tests: `vendor/bin/phpunit`, or a specific test file such as `vendor/bin/phpunit tests/Unit/FairTeamsTest.php`.
- Static analysis: `composer phpstan`.
- Coding standard: `composer cs`; automatic PHP fixes: `composer cbf`.
- Frontend build: `pnpm run build`.
- Frontend watch during active work: `pnpm run watch`.
- Docker dev stack: `docker compose -f docker-compose-dev.yml up`.

If dependencies are missing, do not rewrite lockfiles casually. Ask before running install/update commands that may
fetch from the network or change many dependency files.

## Project Skills

Repository-local skills live under `.agents/skills/`. Load the relevant `SKILL.md` when the task matches:

- `.agents/skills/lac-project/SKILL.md` for general app changes, setup, validation, routing, services, CQRS, console,
  Docker/RoadRunner, and module work.
- `.agents/skills/lac-db-migrations/SKILL.md` for DB schema changes, migration NEON files, indexes, foreign keys, and
  install/update behavior.
- `.agents/skills/lac-results-imports/SKILL.md` for result parsing/import, game persistence, result files, scan/import
  commands, precache, highlights, or LaserLiga result sync.
- `.agents/skills/lac-ui-assets/SKILL.md` for Latte templates, Bootstrap UI, SCSS, TypeScript, gate screens,
  public/results pages, and esbuild assets.
