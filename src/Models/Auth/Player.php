<?php

namespace App\Models\Auth;

use App\Models\Auth\Validators\PlayerCode;
use App\Models\BaseModel;
use DateTimeInterface;
use InvalidArgumentException;
use Lsr\Db\DB;
use Lsr\LaserLiga\PlayerInterface;
use Lsr\ObjectValidation\Attributes\Email;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\ModelCollection;

#[PrimaryKey('id_user')]
class Player extends BaseModel implements PlayerInterface
{
    public const string TABLE = 'players';

    /** @var string Unique code for each player - two players can have the same code if they are from different arenas. */
    #[PlayerCode]
    public string $code;
    public string $nickname;
    #[Email]
    public string $email;
    public ?DateTimeInterface $birthday = null;

    /** @var ModelCollection<PlayerConnection> */
    #[OneToMany(class: PlayerConnection::class, factoryMethod: 'loadConnections')]
    public ModelCollection $connections;

    public int $rank = 100;

    /**
     * @var string[]
     */
    #[NoDB]
    public array $codeHistory = [];

    public static function getByCode(string $code) : ?static {
        $code = strtoupper(trim($code));
        if (preg_match('/(\d)+-([A-Z\d]{5})/', $code) !== 1) {
            throw new InvalidArgumentException('Code is not valid');
        }
        return static::query()->where('[code] = %s', $code)->first();
    }

    /**
     * @param  string  $code
     * @param  Player  $player
     *
     * @return void
     */
    public static function validateCode(string $code, PlayerInterface $player, string $propertyPrefix = '') : void {
        if (!$player->validateUniqueCode($code)) {
            throw ValidationException::createWithValue(
              $player,
              $propertyPrefix.'code',
              'Invalid player\'s code. Must be unique.',
              $code
            );
        }
    }

    /**
     * Validate the unique player's code to be unique for all player in one arena
     *
     * @param  string  $code
     *
     * @return bool
     */
    public function validateUniqueCode(string $code) : bool {
        $id = DB::select($this::TABLE, $this::getPrimaryKey())->where('[code] = %s', $code)->fetchSingle();
        return !isset($id) || $id === $this->id;
    }

    /**
     * @return ModelCollection<PlayerConnection>
     */
    public function loadConnections() : ModelCollection {
        return new ModelCollection(PlayerConnection::getForPlayer($this));
    }

    public function addConnection(PlayerConnection $connection) : Player {
        // Find duplicates
        $found = false;
        foreach ($this->connections as $connectionToTest) {
            if ($connectionToTest->type === $connection->type && $connection->identifier === $connectionToTest->identifier) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $this->connections->add($connection);
        }
        return $this;
    }

    public function jsonSerialize() : array {
        $connections = [];
//        try {
//            foreach ($this->connections as $connection) {
//                if ($connection instanceof PlayerConnection) {
//                    $connections[] = ['type' => $connection->type->value, 'identifier' => $connection->identifier];
//                }
//            }
//        } catch (ValidationException) {
//        }
        return [
          'id'          => $this->id,
          'nickname'    => $this->nickname,
          'code'        => $this->getCode(),
          'email'       => $this->email,
          'rank'        => $this->rank,
          'birthday' => $this->birthday,
          'connections' => $connections,
        ];
    }

    /**
     * @return string
     */
    public function getCode() : string {
        return $this->code;
    }
}
