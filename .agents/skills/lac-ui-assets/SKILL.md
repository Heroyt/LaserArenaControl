---
name: lac-ui-assets
description: Use for LaserArenaControl Latte templates, TypeScript, SCSS, Bootstrap UI, Font Awesome assets, gate
screens, public/results pages, and esbuild build behavior.
---

# UI And Asset Workflow

## Read First

- Templates: `templates/**/*.latte`.
- Gate screens/settings: `templates/gate/**`, `src/Gate/Screens/`, `src/Gate/Widgets/`.
- Frontend TS: `assets/js/`.
- Styles: `assets/scss/`.
- Build: `esbuild.mjs`, `package.json`, `pnpm-lock.yaml`.
- Layouts: `templates/@layout.latte`, `templates/@layoutPublic.latte`, `templates/@layoutPrint.latte`,
  `templates/gate/@layout.latte`.
- Routes/controllers for page context: `routes/*.php`, `src/Http/Controllers/`.

## Rules

- Match the existing Bootstrap 5 and Latte style before introducing new UI conventions.
- Prefer existing components/partials under `templates/components/` and page-specific directories.
- Keep gate screens readable at distance and resilient to auto-refresh/reload behavior.
- Keep print templates conservative; layout changes may affect physical result printing.
- Use existing Font Awesome subset generation and imports instead of adding ad hoc icon assets.
- Add TypeScript behavior in `assets/js/` and wire it through existing entry points/build patterns.
- Do not edit generated assets in `dist/`; build from source with pnpm/esbuild.
- Preserve translation calls and message keys when editing user-visible strings. Update `.po`/`.pot` files only when the
  task includes translation work.

## Validation

For asset/template changes:

```sh
pnpm run build
vendor/bin/phpcs
```

For PHP-backed UI changes:

```sh
vendor/bin/phpunit
composer phpstan
```

If a local server is needed, use the Docker dev stack:

```sh
docker compose -f docker-compose-dev.yml up
```
