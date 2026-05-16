---
name: lac-gates-screens
description: Use for LaserArenaControl gate display work, gate screen lifecycle, gate settings, custom gate events,
screen triggers, idle/results/game-loaded/game-playing screens, gate widgets, RTSP/Youtube/image screens, gate hardware
start/stop controls, and related Latte, TypeScript, SCSS, routes, services, and validation.
---

# Gate And Screen Workflow

## Read First

- Gate routes: `routes/web.php`, `routes/api.php`, `routes/settings.php`.
- Gate service and state selection: `src/Gate/Gate.php`, `src/Gate/Logic/ScreenTriggerType.php`.
- Gate HTTP controllers: `src/Http/Controllers/Gate/GateController.php`, `src/Http/Controllers/Settings/Gate.php`,
  `src/Http/Controllers/Api/Gates.php`.
- Gate models and persistence: `src/Gate/Models/GateType.php`, `src/Gate/Models/GateScreenModel.php`,
  `config/migrations/gate.neon`.
- Screen classes: `src/Gate/Screens/`, especially `GateScreen.php`, `WithSettings.php`, `ReloadTimerInterface.php`,
  `WithReloadTimer.php`, and `Results/`.
- Settings DTOs: `src/Gate/Settings/`.
- Widgets: `src/Gate/Widgets/`, `templates/gate/widgets/`.
- DI registration: `config/gate.neon`, included from `config/services-common.neon`.
- Gate templates and settings partials: `templates/gate/@layout.latte`, `templates/gate/screens/`,
  `templates/gate/settings/`, `templates/components/settings/gate*.latte`, `templates/pages/settings/gate.latte`.
- Gate frontend lifecycle: `assets/js/pages/gate.ts`, `assets/js/components/gate.ts`, `assets/js/gate/gateScreen.ts`,
  `assets/js/gate/`, `assets/js/pages/settings/gate.ts`, `assets/js/api/endpoints/gate.ts`,
  `assets/js/api/endpoints/gates.ts`.
- Gate styles: `assets/scss/pages/gate.scss`, `assets/scss/pages/gateSettings.scss`, `assets/scss/gate/`.

## How Gate Screens Work

- `GateController::show()` asks `App\Gate\Gate` for the current screen and returns partial HTML on AJAX requests.
- `Gate::getCurrentScreen()` chooses by `ScreenTriggerType`: custom event, active/loaded/started/finished game, manual
  results, or default idle screen.
- Screen models persist the DI key and serialized settings. New screen types need a stable `getDiKey()` and a
  `config/gate.neon` service entry.
- `GateScreen::view()` injects reload headers, trigger headers, `addJs`, and `reloadTimer`; gate frontend code depends
  on
  those headers and metadata.
- The browser dynamically imports the module listed by the rendered `add-script` meta. Each gate module must implement
  `assets/js/gate/gateScreen.ts`.

## Implementation Rules

- Preserve existing gate URLs, event names, response headers, and `Info` keys unless the task explicitly changes them.
- Treat gate and gate hardware flows as operational code. Check start/stop, reload, loaded, idle, custom event, and
  active game behavior before changing state selection.
- For a new screen, add the PHP screen class, settings DTO/form only if needed, Latte screen template, TypeScript screen
  module when behavior differs from `defaultScreen`, SCSS file if styles are screen-specific, and a DI entry in
  `config/gate.neon`.
- Keep screen settings serializable with igbinary. Do not store request objects, services, closures, or non-portable
  runtime resources in `GateSettings`.
- If a screen has a finite duration, implement `ReloadTimerInterface` and reuse `WithReloadTimer` when it fits.
- Keep `isActive()` strict enough that expired result/custom screens fall through to the next valid/default screen.
- Use `lang(..., domain: 'gate', context: ...)` for screen names, groups, descriptions, and gate-visible text.
- For templates, preserve `templates/gate/@layout.latte` expectations and the `.content` container structure used by the
  JS transition code.
- For frontend modules, implement `init`, `isSame`, `animateIn`, `animateOut`, `clear`, and `showTimer`; clear timers,
  players, streams, and event listeners in `clear()`.
- For RTSP, Youtube, image, vest, and gate hardware changes, prefer the existing services/endpoints and avoid adding
  direct browser access to private network details unless the current pattern already exposes them.
- Do not edit generated `dist/` assets. Build from source.

## Common Change Checklist

For a new or changed gate screen:

1. Register or update the screen in `config/gate.neon`.
2. Keep the screen DI key, settings form path, JS asset name, and CSS asset name aligned.
3. Verify settings save/load through `Settings\Gate` and `GateScreenModel`.
4. Check trigger behavior for default, custom event, game loaded, game playing, game ended, and manual results as
   relevant.
5. Check gate AJAX reload behavior and `X-Screen`/`X-Reload-Time` headers when the screen duration matters.

For gate controls:

1. Inspect both server endpoints and frontend callers.
2. Preserve existing payload keys and route names.
3. Consider all supported game systems and the special `all` system.
4. Check event server broadcasts such as `gate-reload`, `game-imported`, `game-started`, and `game-loaded`.

## Validation

Use the narrowest meaningful checks:

```sh
pnpm run build
vendor/bin/phpcs
composer phpstan
```

For PHP behavior touching screen selection, settings serialization, or route handling:

```sh
vendor/bin/phpunit
```

When behavior depends on RoadRunner, event reloads, streams, or real gate pages, use the Docker dev stack and manually
exercise `/gate`, `/gate/{slug}`, settings gate pages, and new-game gate controls:

```sh
docker compose -f docker-compose-dev.yml up
```
