---
name: lac-db-migrations
description: Use for LaserArenaControl database schema work, including config/migrations NEON files, table definitions,
versioned modifications, indexes, foreign keys, views, module migrations, install/update behavior, and migration
validation.
---

# DB Migration Workflow

## Read First

- Root migration include file: `config/migrations.neon`.
- Domain migrations: `config/migrations/*.neon`, plus nested files such as `config/migrations/games/evo5.neon`.
- Module migrations: `modules/*/config/migrations.neon`.
- Installer: `src/Install/DbInstall.php`, called through `src/Install/Install.php` and `bin/console install`.
- Migration DTO/loader reference: `vendor/lsr/core/src/Migrations/MigrationLoader.php`, `Migration.php`, `Index.php`,
  `ForeignKey.php`.
- Model table constants/classes for class-keyed migrations, especially under `src/Models/`, `src/GameModels/`, and
  `src/Gate/Models/`.

## Format

Migration files are NEON. The main sections are:

- `includes`: list of migration files to load before the current file.
- `tables`: map of table keys to schema data.
- `views`: map of view names to SQL `SELECT` definitions.

Table keys may be real table names or model class names. If the key is a class extending `Lsr\Orm\Model`, install
resolves the real DB table from the model `TABLE` constant.

Table data supports:

- `order`: lower numbers are created earlier; use this for dependency order.
- `definition`: full `CREATE TABLE` body, including primary key and inline constraints.
- `modifications`: map of schema version to `ALTER TABLE` fragments.
- `indexes`: list with `name`, `columns`, optional `unique`, optional `pk`.
- `foreignKeys`: list with `column`, `refTable`, `refColumn`, optional `onDelete`, optional `onUpdate`.

## Rules

- Put new schema in the correct domain file. Add a new include only when the domain does not already exist.
- For a brand-new table, write the full current `definition`; do not rely on modifications to create its initial shape.
- For an existing table, update the `definition` to the desired final schema and add a new `modifications` version with
  the needed `ALTER TABLE` fragments.
- Use a new monotonically increasing version under that table, such as `0.4`, `0.4.1`, or the next local pattern. Do not
  reuse an existing version key for a different historical change.
- `modifications` values are `ALTER TABLE` fragments. Do not include `ALTER TABLE table_name`; `DbInstall` adds that.
- Prefer safe defaults for non-null columns on existing tables.
- Define persistent indexes in `indexes`, not only inline SQL. The installer creates missing listed indexes and drops
  undefined non-primary indexes.
- Define foreign keys in `foreignKeys` when possible. The installer checks them separately and creates missing
  relations.
- Be careful when renaming/dropping columns or indexes. The installer ignores some duplicate/missing-column errors, but
  destructive data changes still need an explicit migration strategy.
- Keep `views` as `SELECT` bodies only; the installer wraps them in `CREATE OR REPLACE VIEW`.
- When changing a model property that maps to DB schema, update the matching migration in the same change.

## Validation

Validate syntax and runtime behavior with the smallest useful command:

```sh
php -l src/Install/DbInstall.php
vendor/bin/phpstan analyse src/Install config routes --memory-limit=1G
php ./bin/console install
```

For fresh-install compatibility, use a disposable database and run:

```sh
php ./bin/console install --fresh
```

Do not run `install --fresh` against a real/dev database with data that must be kept.
