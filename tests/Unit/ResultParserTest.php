<?php

namespace Tests\Unit;

use App\Core\App;
use App\Exceptions\ResultsParseException;
use App\GameModels\Game\Game;
use App\Services\PlayerProvider;
use App\Tools\ResultParsing\ResultsParser;
use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use Generator;
use Lsr\Exceptions\FileException;
use Tests\Support\UnitTester;

/**
 * @phpstan-type GameMeta array{}
 */
class ResultParserTest extends Unit
{
    protected UnitTester $tester;
    protected PlayerProvider $playerProvider;

    #[DataProvider('getFiles')]
    public function testParser(string $file, array $meta): void {
        $this->assertInstanceOf(PlayerProvider::class, $this->playerProvider);
        $parser = new ResultsParser($this->playerProvider);
        $parser->setFile($file);
        $game = $parser->parse();

        $this->validateGame($game, $file, $meta);
    }

    #[DataProvider('getFiles')]
    public function testParserFromString(string $file, array $meta): void {
        $this->assertInstanceOf(PlayerProvider::class, $this->playerProvider);
        $parser = new ResultsParser($this->playerProvider);
        $parser->setContents(file_get_contents($file));
        $game = $parser->parse();

        $this->validateGame($game, $file, $meta);
    }

    protected function validateGame(Game $game, string $file, array $meta): void {
        $this->assertEquals($meta['system'], $game::SYSTEM, 'Value is different (file: ' . $file . ')');
        $this->assertEquals($meta['playerCount'], $game->getPlayerCount(), 'Value is different (file: ' . $file . ')');
        $this->assertEquals($meta['teamCount'], $game->getTeamCount(), 'Value is different (file: ' . $file . ')');
        $this->assertEquals($meta['start']->getTimestamp(), $game->start->getTimestamp(), 'Value is different (file: ' . $file . ')');
        $this->assertEquals($meta['end']->getTimestamp(), $game->end->getTimestamp(), 'Value is different (file: ' . $file . ')');

        $mode = $game->getMode();
        $this->assertEquals($meta['mode'], $mode->getName(), 'Value is different (file: ' . $file . ')');
        $this->assertEquals($meta['modeType'], $mode->type, 'Value is different (file: ' . $file . ')');

        foreach ($meta['timing'] as $key => $value) {
            $this->assertEquals($value, $game->timing->{$key}, 'Value is different (file: ' . $file . ')');
        }
        foreach ($meta['scoring'] as $key => $value) {
            $this->assertEquals($value, $game->scoring->{$key}, 'Value is different (file: ' . $file . ')');
        }

        foreach ($game->getPlayers() as $player) {
            $playerData = $meta['players'][(string) $player->vest];
            foreach ($playerData as $key => $value) {
                switch ($key) {
                    case 'user':
                        $this->assertEquals($value, $player->user?->id, $file);
                        break;
                    case 'code':
                        $this->assertEquals($value, $player->user?->code, $file);
                        break;
                    case 'team':
                        $this->assertEquals($value, $player->getTeamColor(), $file);
                        break;
                    case 'bonusCount':
                        $this->assertEquals($value, $player->getBonusCount(), $file);
                        break;
                    case 'bonus':
                        foreach ($value as $power => $count) {
                            $this->assertEquals($count, $player->bonus->{$power}, $file);
                        }
                        break;
                    case 'hitsPlayer':
                        foreach ($value as $target => $count) {
                            $this->assertEquals($count, $player->getHitsPlayers()[$target]->count, $file);
                        }
                        break;
                    default:
                        $this->assertEquals($value, $player->{$key}, $file);
                        break;
                }
            }
        }
        foreach ($game->getTeams() as $team) {
            $teamData = $meta['teams'][(string) $team->color];
            foreach ($teamData as $key => $value) {
                $this->assertEquals($value, $team->{$key}, $file);
            }
        }
    }

    #[DataProvider('getFilesError')]
    public function testParserError(string $file): void {
        $parser = new ResultsParser($this->playerProvider);
        $parser->setFile($file);
        $this->expectException(ResultsParseException::class);
        $game = $parser->parse();
    }

    public function testUnknownFile(): void {
        $this->expectException(FileException::class);
        $parser = new ResultsParser($this->playerProvider);
        $parser->setFile('');
    }

    public function testGetGlob(): void {
        $files = glob(ROOT . 'results-test/' . \App\Tools\ResultParsing\Evo5\ResultsParser::getFileGlob());
        $this->assertIsArray($files);
        $this->assertNotEmpty($files);

        $files = glob(ROOT . 'results-test/' . \App\Tools\ResultParsing\Evo6\ResultsParser::getFileGlob());
        $this->assertIsArray($files);
        $this->assertNotEmpty($files);
    }

    public function testCheckFileEmpty(): void {
        $this->assertFalse(\App\Tools\ResultParsing\Evo5\ResultsParser::checkFile());
        $this->assertFalse(\App\Tools\ResultParsing\Evo6\ResultsParser::checkFile());
    }

    public function testCheckFileInvalid(): void {
        $this->assertFalse(\App\Tools\ResultParsing\Evo5\ResultsParser::checkFile(ROOT . 'index.php'));
        $this->assertFalse(\App\Tools\ResultParsing\Evo6\ResultsParser::checkFile(ROOT . 'index.php'));

    }

    // TODO: Test with invalid meta - base64
    // TODO: Test with invalid meta - gzinflate
    // TODO: Test with invalid meta - gzinflate^2
    // TODO: Test with invalid meta - json
    // TODO: Test with invalid meta - hash
    // TODO: Test with invalid meta - hash + mode
    // TODO: Test with invalid meta - hash + mode + load time

    protected function _before() {
        $this->playerProvider = App::getService('playersProvider');
    }

    protected function getFiles(): Generator {
        $files = glob(ROOT . 'results-test/*_archive.game');
        if ($files === false) {
            return [];
        }
        foreach ($files as $file) {
            // Load metadata
            $metaFile = str_replace('.game', '.meta', $file);
            if (!file_exists($metaFile)) {
                continue;
            }
            $contents = file_get_contents($metaFile);
            if ($contents === false) {
                continue;
            }
            $meta = unserialize($contents);
            if ($meta === false) {
                continue;
            }
            yield ['file' => $file, 'meta' => $meta];
        }
    }

    /**
     * @return string[][]
     */
    protected function getFilesError(): array {
        $files = glob(ROOT . 'results-test/????_error.game');
        if ($files === false) {
            return [];
        }
        return array_map(static function (string $fName) {
            return [$fName];
        }, $files);
    }
}
