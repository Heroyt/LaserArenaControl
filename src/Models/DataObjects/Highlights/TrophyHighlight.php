<?php

namespace App\Models\DataObjects\Highlights;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\Helpers\Gender;
use App\Services\GenderService;
use App\Services\NameInflectionService;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Throwable;

class TrophyHighlight extends GameHighlight
{
    /**
     * @template T of Team
     * @template G of Game
     * @param  string  $value
     * @param  Player<G,T>  $player
     * @param  int  $rarityScore
     */
    public function __construct(
      string                 $value,
      public readonly Player $player,
      int                    $rarityScore = GameHighlight::LOW_RARITY,
    ) {
        parent::__construct(GameHighlightType::TROPHY, $value, $rarityScore);
    }

    /**
     * @param  array{type:string,score:int,value:string,description:string,player:array{vest:numeric,name:string}}  $data
     * @param  Game  $game
     * @return static
     */
    public static function fromJson(array $data, Game $game) : static {
        return new self(
          $data['value'],
          $game->getVestPlayer($data['player']['vest']),
          $data['score'],
        );
    }

    public function jsonSerialize() : array {
        $data = parent::jsonSerialize();
        $data['player'] = ['vest' => $this->player->vest, 'name' => $this->player->name];
        return $data;
    }

    /**
     * @return string
     * @throws ModelNotFoundException
     * @throws ValidationException
     * @throws DirectoryCreationException
     * @throws Throwable
     */
    public function getDescription() : string {
        $fields = $this->player->trophy::getFields();
        $name = $this->player->name;
        if ($this->value === 'favouriteTarget') {
            $name2 = $this->player->favouriteTarget?->name ?? '';
            $gender = GenderService::rankWord($name);
            return sprintf(
              lang(
                match ($gender) {
                    Gender::MALE   => '%s si zasedl na %s',
                    Gender::FEMALE => '%s si zasedla na %s',
                    Gender::OTHER  => '%s si zasedlo na %s',
                },
                context: 'trophy',
                domain : 'highlights'
              ),
              '@'.$name.'@',
              '@'.$name2.'@<'.NameInflectionService::accusative($name2).'>'
            );
        }
        if ($this->value === 'favouriteTargetOf') {
            $name2 = $this->player->favouriteTargetOf?->name ?? '';
            $gender = GenderService::rankWord($name);
            return sprintf(
              lang(
                match ($gender) {
                    Gender::MALE   => '%s byl pronásledovaný od %s',
                    Gender::FEMALE => '%s byla pronásledovaná od %s',
                    Gender::OTHER  => '%s bylo pronásledováno od %s',
                },
                context: 'trophy',
                domain : 'highlights'
              ),
              '@'.$name.'@',
              '@'.$name2.'@<'.NameInflectionService::genitive($name2).'>'
            );
        }
        return sprintf(
          lang(
                     '%s získává trofej: %s',
            context: 'trophy',
            domain : 'highlights'
          ),
          '@'.$name.'@',
          ($fields[$this->value] ?? ['name' => lang('Hráč', context: 'bests', domain: 'results')])['name']
        );
    }
}
