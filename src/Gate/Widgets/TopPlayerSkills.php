<?php

namespace App\Gate\Widgets;

use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

class TopPlayerSkills implements WidgetInterface, WithGameIdsInterface
{
    use WithGameIds;

    private ?string $hash = null;

    /**
     * @var Player[]
     * @phpstan-ignore missingType.generics
     */
    private ?array $topPlayers = null;


    public function refresh() : static {
        $this->hash = null;
        $this->topPlayers = null;
        $this->setGameIds(null);
        return $this;
    }

    /**
     * @template T of Team
     * @template P of Player
     * @template G of Game<T,P>
     * @param  G|null  $game
     * @param  DateTimeInterface|null  $date
     * @param  string[]|null  $systems
     * @return array{topPlayers: P[]}
     * @throws Throwable
     */
    public function getData(?Game $game = null, ?DateTimeInterface $date = null, ?array $systems = []) : array {
        /** @phpstan-ignore return.type */
        return [
          'topPlayers' => $this->getTopPlayers($date, $systems),
        ];
    }

    /**
     * @param  string[]|null  $systems
     * @return Player[]
     * @throws Throwable
     * @phpstan-ignore missingType.generics
     */
    private function getTopPlayers(?DateTimeInterface $date = null, ?array $systems = []) : array {
        if (!isset($this->topPlayers)) {
            $this->topPlayers = [];
            $gameIds = $this->getGameIds(rankableOnly: true);
            if (!empty($gameIds)) {
                $topScores = PlayerFactory::queryPlayers($gameIds)
                                          ->orderBy('[skill]')
                                          ->desc()
                                          ->limit(10)
                                          ->fetchAssoc('name', cache: false);
            }
            else {
                $q = PlayerFactory::queryPlayersWithGames()->where(
                  'DATE([start]) = %d AND [end] IS NOT NULL',
                  $date ?? new DateTimeImmutable()
                )->orderBy('[skill]')->desc()->limit(10);
                if (!empty($systems)) {
                    $q->where('[system] IN %in', $systems);
                }
                $topScores = $q->fetchAssoc('name', cache: false);
            }

            if (!empty($topScores)) {
                foreach ($topScores as $score) {
                    $player = PlayerFactory::getById(
                      (int) $score->id_player,
                      ['system' => (string) $score->system]
                    );
                    if ($player !== null) {
                        $this->topPlayers[] = $player;
                    }
                }
            }
        }
        return $this->topPlayers;
    }

    /**
     * @inheritDoc
     */
    public function getHash(?Game $game = null, ?DateTimeInterface $date = null, ?array $systems = []) : string {
        if (!isset($this->hash)) {
            $data = '';
            foreach ($this->getTopPlayers($date, $systems) as $player) {
                $data .= $player->name.$player->skill;
            }
            $this->hash = md5($data);
        }
        return $this->hash;
    }

    public function getTemplate() : string {
        return 'topPlayerSkills.latte';
    }

    public function getSettingsTemplate() : string {
        return '';
    }
}
