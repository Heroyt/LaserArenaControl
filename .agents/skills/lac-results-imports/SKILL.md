---
name: lac-results-imports
description: Use for LaserArenaControl result file parsing/import workflows, result versioning, game persistence,
scan/import commands, precache, highlights, LaserLiga result sync, and related tests.
---

# Result Import Workflow

## Read First

- Import orchestration: `src/Services/ResultFileImporter.php`, `src/Services/ResultFileImportFinalizer.php`,
  `src/Services/ImportService.php`.
- Scan/version state: `src/Services/ResultsDirectoryScanner.php`, `src/Services/ResultFileVersionFactory.php`,
  `src/Services/ResultFileImportStateRepository.php`.
- CQRS: `src/CQRS/Commands/ImportResultFileCommand.php`, `src/CQRS/CommandHandlers/ImportResultFileCommandHandler.php`,
  `src/CQRS/Commands/ScanResultsDirectoryCommand.php`,
  `src/CQRS/CommandHandlers/ScanResultsDirectoryCommandHandler.php`.
- CLI: `src/Cli/Commands/Games/ImportGameCommand.php`, `src/Cli/Commands/Games/SyncGameCommand.php`.
- Game models/parsers: `src/GameModels/`, `lsr/lg-result-parsing`.
- Related migrations: `config/migrations/resultImports.neon`, `config/migrations/games.neon`.
- Tests: `tests/Unit/ResultParserTest.php`, `tests/Unit/ImportResultFileCommandTest.php`,
  `tests/Unit/ResultFileImportFinalizerTest.php`, `tests/Unit/ResultFileVersionFactoryTest.php`,
  `tests/Unit/ScanResultsDirectoryCommandHandlerTest.php`.

## Rules

- Preserve idempotency. Re-importing the same unchanged file should not create duplicate or inconsistent state.
- Keep result file identity explicit: path, mtime, size, content hash, and version each have different uses.
- Preserve unfinished/loaded/started/empty/save-failed distinctions from `ResultFileImportResult`.
- Be careful with parser file names and temporary inline content; many parsers infer metadata from filenames.
- Keep logging payloads useful for diagnosing arena file issues: include file path, system, game code, and state where
  available.
- Do not hide parse errors broadly. Only retry known recoverable cache/model issues using the existing pattern.
- When changing imports, update tests with representative fixture-like data or mocked parser behavior rather than
  relying only on live files.

## Validation

Prefer targeted tests first:

```sh
vendor/bin/phpunit tests/Unit/ResultFileVersionFactoryTest.php
vendor/bin/phpunit tests/Unit/ResultFileImportFinalizerTest.php
vendor/bin/phpunit tests/Unit/ImportResultFileCommandTest.php
vendor/bin/phpunit tests/Unit/ScanResultsDirectoryCommandHandlerTest.php
```

Then run:

```sh
vendor/bin/phpunit
composer phpstan
```
