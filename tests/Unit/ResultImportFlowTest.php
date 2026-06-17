<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\CQRS\CommandHandlers\ImportResultFileCommandHandler;
use App\CQRS\Commands\ImportResultFileCommand;
use App\DataObjects\Import\ResultFileImportResult;
use App\DataObjects\Import\ResultFileImportState;
use App\DataObjects\Import\ResultFileImportStatus;
use App\DataObjects\Import\ResultFileVersion;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\Lasermaxx\Evo6\Player;
use App\GameModels\Game\Lasermaxx\Evo6\Team;
use App\Services\ResultFileImporter;
use App\Services\ResultFileImportFinalizer;
use App\Services\ResultFileImportStateRepository;
use App\Services\ResultFileVersionFactory;
use App\Services\ResultsDirectoryScanner;
use DateTimeInterface;
use Dibi\Row;
use Lsr\Core\App;
use Lsr\Core\Config;
use Lsr\LaserLiga\PlayerProviderInterface;
use Lsr\Lg\Results\AbstractResultsParser;
use Lsr\Lg\Results\Interface\GameModeProviderInterface;
use Lsr\Lg\Results\Interface\Models\GameInterface as ParsedGameInterface;
use Lsr\Lg\Results\PlayerCollection;
use Lsr\Lg\Results\TeamCollection;
use Lsr\Logging\Logger;
use Nette\DI\Container;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Spiral\RoadRunner\Metrics\Metrics;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

class ResultImportFlowTest extends TestCase
{
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();

        if ( ! defined('LOG_DIR')) {
            define('LOG_DIR', sys_get_temp_dir() . '/lac-result-flow-logs/');
        }
        if ( ! is_dir(LOG_DIR)) {
            mkdir(LOG_DIR);
        }
    }

    protected function setUp(): void {
        parent::setUp();

        new ReflectionProperty(GameFactory::class, 'supportedSystems')
            ->setValue(null, ['evo6']);
        new ReflectionProperty(App::class, 'container')
            ->setValue(null, $this->createContainer());
    }

    public function test_scan_queue_and_command_import_flow_marks_imported_and_finalizes(): void {
        $file = $this->createTempResultFile();
        $state = null;

        try {
            $stateRepository = $this->createFlowStateRepository($state);
            $scanner = $this->createScanner($stateRepository);
            $scanResult = $scanner->scanFile($file, includeContent: true);

            $this->assertSame(1, $scanResult->seen);
            $this->assertSame(1, $scanResult->queued);
            $this->assertCount(1, $scanResult->queuedFiles);
            $this->assertInstanceOf(ResultFileImportState::class, $state);
            $this->assertSame(ResultFileImportStatus::QUEUED, $state->status);

            $game = $this->createGame('FLOW-001');
            $importer = $this->createMock(ResultFileImporter::class);
            $importer
                ->expects($this->once())
                ->method('parseContent')
                ->with(
                    $this->isInstanceOf(AbstractResultsParser::class),
                    'evo6',
                    $scanResult->queuedFiles[0]->path,
                    $scanResult->queuedFiles[0]->content,
                    $scanResult->queuedFiles[0]->mtime,
                    $this->isInstanceOf(Logger::class),
                )
                ->willReturn($game);
            $importer
                ->expects($this->once())
                ->method('importParsed')
                ->willReturn(ResultFileImportResult::imported($game));

            $stateRepository
                ->expects($this->once())
                ->method('markImported')
                ->willReturnCallback(
                    function (ResultFileVersion $version, DateTimeInterface $now, ?string $gameCode) use (&$state): bool {
                        $state = $this->completedState($version, ResultFileImportStatus::IMPORTED, $now, $gameCode, 'imported');
                        return true;
                    },
                );
            $stateRepository->expects($this->never())->method('markStarted');
            $stateRepository->expects($this->never())->method('markLoaded');

            $finalizer = $this->createMock(ResultFileImportFinalizer::class);
            $finalizer->expects($this->once())->method('triggerImported')->with(1);
            $finalizer
                ->expects($this->once())
                ->method('finalize')
                ->with($this->identicalTo([$game]), $this->isInstanceOf(Logger::class));
            $finalizer->expects($this->never())->method('triggerUnfinished');

            $result = $this->createHandler($stateRepository, $importer, $finalizer)
                ->handle(ImportResultFileCommand::fromQueuedFile($scanResult->queuedFiles[0]));

            $this->assertSame(ResultFileImportStatus::IMPORTED, $result->status);
            $this->assertSame('imported', $result->event);
            $this->assertSame('FLOW-001', $result->gameCode);
            $this->assertSame(ResultFileImportStatus::IMPORTED, $state->status);
            $this->assertSame($scanResult->queuedFiles[0]->version, $state->processedVersion);
            $this->assertSame('FLOW-001', $state->lastGameCode);
        } finally {
            $this->removeTempResultFile($file);
        }
    }

    public function test_scan_queue_and_command_import_flow_marks_started_without_processing_version(): void {
        $file = $this->createTempResultFile();
        $state = null;

        try {
            $stateRepository = $this->createFlowStateRepository($state);
            $scanner = $this->createScanner($stateRepository);
            $scanResult = $scanner->scanFile($file, includeContent: true);

            $game = $this->createGame('FLOW-STARTED');
            $importer = $this->createMock(ResultFileImporter::class);
            $importer->expects($this->once())->method('parseContent')->willReturn($game);
            $importer
                ->expects($this->once())
                ->method('importParsed')
                ->willReturn(ResultFileImportResult::unfinished($game, 'game-started'));

            $stateRepository
                ->expects($this->once())
                ->method('markStarted')
                ->willReturnCallback(
                    function (ResultFileVersion $version, DateTimeInterface $now) use (&$state): bool {
                        $state = $this->activeState($version, ResultFileImportStatus::STARTED, $now, 'game-started');
                        return true;
                    },
                );
            $stateRepository->expects($this->never())->method('markImported');
            $stateRepository->expects($this->never())->method('markLoaded');

            $finalizer = $this->createMock(ResultFileImportFinalizer::class);
            $finalizer->expects($this->never())->method('triggerImported');
            $finalizer->expects($this->never())->method('finalize');
            $finalizer
                ->expects($this->once())
                ->method('triggerUnfinished')
                ->with($this->identicalTo($game), 'game-started', $this->isInstanceOf(Logger::class));

            $result = $this->createHandler($stateRepository, $importer, $finalizer)
                ->handle(ImportResultFileCommand::fromQueuedFile($scanResult->queuedFiles[0]));

            $this->assertSame(ResultFileImportStatus::STARTED, $result->status);
            $this->assertSame('game-started', $result->event);
            $this->assertInstanceOf(ResultFileImportState::class, $state);
            $this->assertSame(ResultFileImportStatus::STARTED, $state->status);
            $this->assertNull($state->processedVersion);
            $this->assertNull($state->processedHash);
            $this->assertSame('game-started', $state->lastEvent);
        } finally {
            $this->removeTempResultFile($file);
        }
    }

    /**
     * @param ResultFileImportState|null $state
     * @return ResultFileImportStateRepository&MockObject
     */
    private function createFlowStateRepository(?ResultFileImportState &$state): ResultFileImportStateRepository {
        $stateRepository = $this
            ->getMockBuilder(ResultFileImportStateRepository::class)
            ->onlyMethods([
                'findByPathHash',
                'saveSeen',
                'markProcessing',
                'markImported',
                'markLoaded',
                'markStarted',
                'markSkipped',
                'markFailed',
                'markStale',
            ])
            ->getMock();

        $stateRepository
            ->method('findByPathHash')
            ->willReturnCallback(
                static function () use (&$state): ?ResultFileImportState {
                    return $state;
                },
            );
        $stateRepository
            ->expects($this->once())
            ->method('saveSeen')
            ->willReturnCallback(
                function (
                    ResultFileVersion $version,
                    string $system,
                    ResultFileImportStatus $status,
                    ?DateTimeInterface $queuedAt,
                ) use (&$state): ResultFileImportState {
                    $state = new ResultFileImportState(
                        id: 1,
                        path: $version->path,
                        pathHash: $version->pathHash,
                        system: $system,
                        seenMtime: $version->mtime,
                        seenSize: $version->size,
                        seenHash: $version->contentHash,
                        seenVersion: $version->version,
                        status: $status,
                        attempts: 0,
                        queuedAt: $queuedAt,
                    );

                    return $state;
                },
            );
        $stateRepository
            ->expects($this->once())
            ->method('markProcessing')
            ->willReturnCallback(
                function (ResultFileVersion $version, DateTimeInterface $now) use (&$state): bool {
                    $currentState = $state;
                    if ($currentState === null) {
                        return false;
                    }

                    $state = new ResultFileImportState(
                        id: 1,
                        path: $version->path,
                        pathHash: $version->pathHash,
                        system: $currentState->system,
                        seenMtime: $version->mtime,
                        seenSize: $version->size,
                        seenHash: $version->contentHash,
                        seenVersion: $version->version,
                        status: ResultFileImportStatus::PROCESSING,
                        attempts: $currentState->attempts + 1,
                        queuedAt: $currentState->queuedAt,
                        processingVersion: $version->version,
                        processingStartedAt: $now,
                    );

                    return true;
                },
            );
        $stateRepository->expects($this->never())->method('markSkipped');
        $stateRepository->expects($this->never())->method('markFailed');
        $stateRepository->expects($this->never())->method('markStale');

        return $stateRepository;
    }

    private function createScanner(ResultFileImportStateRepository $stateRepository): ResultsDirectoryScanner {
        return new ResultsDirectoryScanner(
            new ResultFileVersionFactory(),
            $stateRepository,
            $this->createConfig(),
        );
    }

    private function createHandler(
        ResultFileImportStateRepository $stateRepository,
        ResultFileImporter $importer,
        ResultFileImportFinalizer $finalizer,
    ): ImportResultFileCommandHandler {
        return new ImportResultFileCommandHandler(
            $stateRepository,
            new ResultFileVersionFactory(),
            $importer,
            $finalizer,
            new LockFactory(new InMemoryStore()),
            $this->createStub(Metrics::class),
            $this->createConfig(),
        );
    }

    private function createConfig(): Config {
        return new class extends Config {
            public function __construct() {
                parent::__construct('/tmp');
            }

            /**
             * @return array<string, mixed>
             */
            public function getConfig(?string $category = null): array {
                $config = [
                    'ENV' => [
                        'RESULT_IMPORT_PROCESSING_TTL' => 300,
                        'RESULT_IMPORT_QUEUED_TTL' => 120,
                        'GAME_LOADED_TIME' => 300,
                        'GAME_STARTED_TIME' => 1800,
                    ],
                ];

                return $category === null ? $config : ($config[$category] ?? []);
            }
        };
    }

    private function createContainer(): Container {
        return new class ($this->createParser()) extends Container {
            public function __construct(ResultImportFlowParser $parser) {
                parent::__construct();
                $this->addService('result.parser.evo6', $parser);
            }
        };
    }

    private function createParser(): ResultImportFlowParser {
        return new ResultImportFlowParser(
            $this->createStub(PlayerProviderInterface::class),
            $this->createStub(GameModeProviderInterface::class),
        );
    }

    /**
     * @return Game<Team, Player>
     */
    private function createGame(string $code): Game {
        $game = new class extends \App\GameModels\Game\Lasermaxx\Evo6\Game {
            public function __construct(?int $id = null, ?Row $dbRow = null) {
                unset($id, $dbRow);
            }
        };
        $game->code = $code;
        $game->resultsFile = '0012';
        $game->players = new PlayerCollection([]);
        $game->teams = new TeamCollection([]);

        return $game;
    }

    private function completedState(
        ResultFileVersion $version,
        ResultFileImportStatus $status,
        DateTimeInterface $now,
        ?string $gameCode,
        string $event,
    ): ResultFileImportState {
        return new ResultFileImportState(
            id: 1,
            path: $version->path,
            pathHash: $version->pathHash,
            system: 'evo6',
            seenMtime: $version->mtime,
            seenSize: $version->size,
            seenHash: $version->contentHash,
            seenVersion: $version->version,
            status: $status,
            attempts: 1,
            processedVersion: $version->version,
            processedHash: $version->contentHash,
            processedAt: $now,
            lastGameCode: $gameCode,
            lastEvent: $event,
        );
    }

    private function activeState(
        ResultFileVersion $version,
        ResultFileImportStatus $status,
        DateTimeInterface $now,
        string $event,
    ): ResultFileImportState {
        return new ResultFileImportState(
            id: 1,
            path: $version->path,
            pathHash: $version->pathHash,
            system: 'evo6',
            seenMtime: $version->mtime,
            seenSize: $version->size,
            seenHash: $version->contentHash,
            seenVersion: $version->version,
            status: $status,
            attempts: 1,
            queuedAt: $now,
            lastEvent: $event,
        );
    }

    /**
     * @return non-empty-string
     */
    private function createTempResultFile(): string {
        $dir = sys_get_temp_dir() . '/lac-result-flow-' . uniqid('', true);
        if ( ! mkdir($dir)) {
            throw new RuntimeException('Failed to create result flow fixture directory.');
        }
        $file = $dir . '/0012.game';
        if (file_put_contents($file, "SITE{57690,0602022,EVO-6 MAXX}#\r\nGAME{12,,20250208214146,20250208214447,3}#\r\n") === false) {
            throw new RuntimeException('Failed to write result flow fixture.');
        }

        return $file;
    }

    private function removeTempResultFile(string $file): void {
        if (is_file($file)) {
            unlink($file);
        }
        $dir = dirname($file);
        if (is_dir($dir)) {
            rmdir($dir);
        }
    }
}

/**
 * @extends AbstractResultsParser<ParsedGameInterface<\App\GameModels\Game\Lasermaxx\Evo6\Team, \App\GameModels\Game\Lasermaxx\Evo6\Player, array<string, mixed>>>
 */
final class ResultImportFlowParser extends AbstractResultsParser
{
    public function __construct(
        PlayerProviderInterface $playerProvider,
        GameModeProviderInterface $gameModeProvider,
    ) {
        unset($playerProvider, $gameModeProvider);
    }

    public static function getFileGlob(): string {
        return '*.game';
    }

    public static function checkFile(string $fileName = '', string $contents = ''): bool {
        if ($contents === '' && $fileName !== '') {
            $contents = (string) file_get_contents($fileName);
        }

        return str_contains($contents, 'EVO-6 MAXX');
    }

    /**
     * @return ParsedGameInterface<Team, Player, array<string, mixed>>
     */
    public function parse(): ParsedGameInterface {
        throw new RuntimeException('Flow tests mock parsing through ResultFileImporter.');
    }

    /**
     * @param ParsedGameInterface<Team, Player, array<string, mixed>> $game
     * @param array<string, mixed> $meta
     */
    protected function processExtensions(ParsedGameInterface $game, array $meta): void {
    }
}
