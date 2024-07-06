<?php

namespace App\Models\Auth;

use App\Exceptions\DuplicateRecordException;
use App\Models\Auth\Enums\ConnectionType;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_connection')]
class PlayerConnection extends Model
{
    public const TABLE = 'player_connected_accounts';

    public ConnectionType $type;
    #[ManyToOne]
    public Player $player;
    public string|int $identifier;

    /**
     * Get all connections for a specific user
     *
     * @param Player $player
     *
     * @return PlayerConnection[]
     * @throws ValidationException
     */
    public static function getForPlayer(Player $player): array {
        return self::query()->where('%n = %i', $player::getPrimaryKey(), $player->id)->get();
    }

    /**
     * Get all connections for a specific user and connection type
     *
     * @param Player         $player
     * @param ConnectionType $type
     *
     * @return PlayerConnection[]
     * @throws ValidationException
     */
    public static function getForPlayerAndType(Player $player, ConnectionType $type): array {
        return self::query()->where('%n = %i AND [type] = %s', $player::getPrimaryKey(), $player->id, $type->value)->get();
    }

    /**
     * Get one connection object by its identifier
     *
     * @param int|string     $identifier
     * @param ConnectionType $type
     *
     * @return PlayerConnection|null
     */
    public static function getByIdentifier(int|string $identifier, ConnectionType $type): ?PlayerConnection {
        return self::query()->where('[identifier] = %s AND [type] = %s', $identifier, $type->value)->first();
    }

    /**
     * @return bool
     * @throws DuplicateRecordException
     * @throws ValidationException
     */
    public function insert(): bool {
        // Check for duplicates before inserting a new one
        /** @var int|null $test */
        $test = DB::select($this::TABLE, 'id_user')->where('[type] = %s AND [identifier] = %s', $this->type, $this->identifier)->fetchSingle();
        if (isset($test)) {
            if ($test === $this->player->id) {
                return true; // Trying to add a duplicate for the same user -> skip
            }
            // Trying to add a duplicate for a different user -> error
            throw new DuplicateRecordException('Trying to add a duplicate user connection. This connection already exists for a different user.');
        }
        return parent::insert();
    }

    /**
     * @return bool
     * @throws DuplicateRecordException
     * @throws ValidationException
     */
    public function update(): bool {
        // Check for duplicates before updating an existing one
        $test = DB::select($this::TABLE, '*')->where('[type] = %s AND [identifier] = %s AND %n <> %i', $this->type, $this->identifier, $this::getPrimaryKey(), $this->id)->fetch();
        if (isset($test)) {
            // Trying to add a duplicate -> error
            throw new DuplicateRecordException('Trying to add a duplicate user connection. This connection already exists for a different user.');
        }
        return parent::update();
    }
}
