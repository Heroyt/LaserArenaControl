<?php

namespace App\Models;

use App\GameModels\Game\GameModes\AbstractMode;
use Lsr\Db\DB;
use Lsr\Orm\Attributes\PrimaryKey;

#[PrimaryKey('id_variation')]
class GameModeVariation extends BaseModel
{
    public const string TABLE = 'game_modes_variations';
    public const string TABLE_VALUES = 'game_modes_variations_values';

    public string $name;

    public bool $public = true;
    public int $order = 0;

    /** @var GameModeVariationValue[][] */
    private array $valuesModes = [];

    /**
     * @param  AbstractMode  $mode
     *
     * @return GameModeVariationValue[]
     */
    public function getValuesForMode(AbstractMode $mode) : array {
        if (empty($this->valuesModes[$mode->id])) {
            $this->valuesModes[$mode->id] = [];
            $rows = DB::select(self::TABLE_VALUES, '[value], [suffix], [order]')
              ->where('id_variation = %i AND id_mode = %i', $this->id, $mode->id)
              ->orderBy('[order]')
              ->fetchAll();
            foreach ($rows as $row) {
                $this->valuesModes[$mode->id][] = new GameModeVariationValue(
                  $this,
                  $mode,
                  $row->value,
                  $row->suffix,
                  $row->order
                );
            }
        }
        return $this->valuesModes[$mode->id];
    }
}
