<?php

namespace App\Gate\Widgets;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\Models\MusicMode;
use DateTimeImmutable;
use DateTimeInterface;
use Dibi\Row;
use JsonException;

class MusicCount implements WidgetInterface, WithGameIdsInterface
{
    use WithGameIds;

    private ?string $hash = null;
    /** @var array<int, int> */
    private ?array $musicCounts = null;

    public function refresh() : static {
        $this->hash = null;
        $this->musicCounts = null;
        $this->setGameIds(null);
        return $this;
    }

    public function getData(?Game $game = null, ?DateTimeInterface $date = null, ?array $systems = []) : array {
        return [
          'musicCounts' => $this->getMusicCounts($date, $systems),
          'musicModes'  => MusicMode::getAll(),
        ];
    }

    /**
     * @param  DateTimeInterface|null  $date
     * @param  string[]|null  $systems
     * @return int[]
     */
    private function getMusicCounts(?DateTimeInterface $date = null, ?array $systems = []) : array {
        if (isset($this->musicCounts)) {
            return $this->musicCounts;
        }

        $gameIdsAll = $this->getGameIds($date, $date, $systems);
        if (!empty($gameIdsAll)) {
            $query = GameFactory::queryGames(true, null, ['id_music']);
            $where = [];
            foreach ($gameIdsAll as $system => $gameIds) {
                $where[] = ['system = %s AND id_game IN %in', $system, $gameIds];
            }
            $query->where('%or', $where);
        }
        else {
            $query = GameFactory::queryGames(true, $date ?? new DateTimeImmutable(), ['id_music']);
            if (isset($systems) && count($systems) > 0) {
                $query->where('system IN %in', $systems);
            }
            $this->gameIds['all'] = [];
        }

        /** @var array<string,Row[]> $games */
        $games = $query->fetchAssoc('system|id_game', cache: false);
        $this->musicCounts = [];

        foreach ($games as $system => $systemGames) {
            /** @var array<int, Row> $systemGames */
            $this->gameIds['all'][$system] ??= array_keys($systemGames);
            foreach ($systemGames as $systemGame) {
                if (isset($systemGame->id_music)) {
                    $this->musicCounts[$systemGame->id_music] ??= 0;
                    $this->musicCounts[$systemGame->id_music]++;
                }
            }
        }
        arsort($this->musicCounts);
        return $this->musicCounts;
    }

    /**
     * @template G of Game
     * @param  G|null  $game
     * @param  DateTimeInterface|null  $date
     * @param  string[]|null  $systems
     * @return string
     * @throws JsonException
     */
    public function getHash(?Game $game = null, ?DateTimeInterface $date = null, ?array $systems = []) : string {
        if (!isset($this->hash)) {
            $this->hash = md5(json_encode($this->getMusicCounts($date, $systems), JSON_THROW_ON_ERROR));
        }
        return $this->hash;
    }

    public function getTemplate() : string {
        return 'musicCounts.latte';
    }

    public function getSettingsTemplate() : string {
        return '';
    }
}
