<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\CQRS\Commands\ImportResultFileCommand;
use App\DataObjects\Import\ImportResultFileCommandResult;
use App\DataObjects\Import\ResultFileImportState;
use App\DataObjects\Import\ResultFileImportStatus;
use App\DataObjects\Import\ResultFileVersion;
use App\GameModels\Game\Lasermaxx\Game as LasermaxxGame;
use App\GameModels\Game\Lasermaxx\Player as LasermaxxPlayer;
use App\GameModels\Game\Lasermaxx\Team as LasermaxxTeam;
use App\Services\ImportService;
use App\Services\ResultFileImportStateRepository;
use App\Services\ResultFileVersionFactory;
use Dibi\Row;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\CQRS\CommandBus;
use Lsr\Lg\Results\PlayerCollection;
use Lsr\Lg\Results\TeamCollection;
use PHPUnit\Framework\TestCase;

class ImportServiceTest extends TestCase
{
    public function test_import_game_dispatches_forced_state_table_import_with_identity_preservation(): void {
        $dir = sys_get_temp_dir() . '/lac-import-service-test-' . uniqid('', true);
        self::assertTrue(mkdir($dir));
        $file = $dir . '/0001.game';
        self::assertNotFalse(file_put_contents($file, 'result'));

        $version = new ResultFileVersion(
            $file,
            sha1($file),
            123,
            6,
            str_repeat('a', 64),
            sha1($file . ':123:6:' . str_repeat('a', 64)),
        );

        $game = new ImportServiceTestGame();
        $game->id = 42;
        $game->code = 'manual-code';
        $game->resultsFile = '0001';

        $player = new ImportServiceTestPlayer();
        $player->id = 100;
        $player->vest = 7;
        $game->players = new PlayerCollection([$player], 'vest');

        $team = new ImportServiceTestTeam();
        $team->id = 200;
        $team->color = 3;
        $game->teams = new TeamCollection([$team], 'color');

        $versionFactory = $this
            ->getMockBuilder(ResultFileVersionFactory::class)
            ->onlyMethods(['fromFile'])
            ->getMock();
        $stateRepository = $this
            ->getMockBuilder(ResultFileImportStateRepository::class)
            ->onlyMethods(['saveSeen'])
            ->getMock();
        $commandBus = $this
            ->getMockBuilder(CommandBus::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['dispatch'])
            ->getMock();

        $versionFactory
            ->expects($this->once())
            ->method('fromFile')
            ->with($file)
            ->willReturn($version);
        $stateRepository
            ->expects($this->once())
            ->method('saveSeen')
            ->with($version, 'evo6', ResultFileImportStatus::QUEUED)
            ->willReturn(
                new ResultFileImportState(
                    1,
                    $version->path,
                    $version->pathHash,
                    'evo6',
                    $version->mtime,
                    $version->size,
                    $version->contentHash,
                    $version->version,
                    ResultFileImportStatus::QUEUED,
                    0,
                ),
            );
        $commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (ImportResultFileCommand $command) use ($version): bool {
                self::assertSame($version->path, $command->path);
                self::assertSame($version->pathHash, $command->pathHash);
                self::assertSame('evo6', $command->system);
                self::assertTrue($command->force);
                self::assertSame(42, $command->preserveGameId);
                self::assertSame('manual-code', $command->preserveGameCode);
                self::assertSame([7 => 100], $command->preservePlayerIdsByVest);
                self::assertSame([3 => 200], $command->preserveTeamIdsByColor);

                return true;
            }))
            ->willReturn(
                new ImportResultFileCommandResult(
                    $version->path,
                    $version->version,
                    ResultFileImportStatus::IMPORTED,
                    'manual-code',
                ),
            );

        $service = new ImportService($versionFactory, $stateRepository, $commandBus);

        $response = $service->importGame($game, $dir);

        $this->assertInstanceOf(SuccessResponse::class, $response);
        $this->assertArrayNotHasKey('game', $response->values ?? []);
        $this->assertEquals(new ImportResultFileCommandResult(
            $version->path,
            $version->version,
            ResultFileImportStatus::IMPORTED,
            'manual-code',
        ), $response->values['import'] ?? null);

        unlink($file);
        rmdir($dir);
    }
}

/**
 * @extends LasermaxxGame<ImportServiceTestTeam, ImportServiceTestPlayer>
 */
class ImportServiceTestGame extends LasermaxxGame
{
    public const string SYSTEM = 'evo6';
    public const string TABLE = 'test_games';

    public function __construct(?int $id = null, ?Row $dbRow = null) {
        unset($id, $dbRow);
    }

}

/**
 * @extends LasermaxxPlayer<ImportServiceTestGame, ImportServiceTestTeam>
 */
class ImportServiceTestPlayer extends LasermaxxPlayer
{
    public const string SYSTEM = 'evo6';
    public const string TABLE = 'test_players';

    public function __construct(?int $id = null, ?Row $dbRow = null) {
        unset($id, $dbRow);
    }

    public function getMines(): int {
        return 0;
    }

    public function getBonusCount(): int {
        return 0;
    }
}

/**
 * @extends LasermaxxTeam<ImportServiceTestPlayer, ImportServiceTestGame>
 */
class ImportServiceTestTeam extends LasermaxxTeam
{
    public const string SYSTEM = 'evo6';
    public const string TABLE = 'test_teams';

    public function __construct(?int $id = null, ?Row $dbRow = null) {
        unset($id, $dbRow);
    }
}
