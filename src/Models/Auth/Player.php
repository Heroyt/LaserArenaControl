<?php

namespace App\Models\Auth;

use App\Models\Auth\Validators\PlayerCode;
use InvalidArgumentException;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\OneToMany;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Attributes\Validation\Email;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_user')]
class Player extends Model
{
    public const TABLE = 'players';

    /** @var string Unique code for each player - two players can have the same code if they are from different arenas. */
    #[PlayerCode]
    public string $code;
    public string $nickname;
    #[Email]
    public string $email;

    /** @var PlayerConnection[] */
    #[OneToMany(class: PlayerConnection::class)]
    public array $connections = [];

    public int $rank = 100;

    public static function getByCode(string $code): ?static {
        $code = strtoupper(trim($code));
        if (preg_match('/(\d)+-([A-Z\d]{5})/', $code) !== 1) {
            throw new InvalidArgumentException('Code is not valid');
        }
        return static::query()->where('[code] = %s', $code)->first();
    }

    /**
     * @param string $code
     * @param Player $player
     *
     * @return void
     * @throws ValidationException
     */
    public static function validateCode(string $code, Player $player): void {
        if (!$player->validateUniqueCode($code)) {
            throw new ValidationException('Invalid player\'s code. Must be unique.');
        }
    }

    /**
     * Validate the unique player's code to be unique for all player in one arena
     *
     * @param string $code
     *
     * @return bool
     */
    public function validateUniqueCode(string $code): bool {
        $id = DB::select($this::TABLE, $this::getPrimaryKey())->where('[code] = %s', $code)->fetchSingle();
        return !isset($id) || $id === $this->id;
    }

    /**
     * @return string
     */
    public function getCode(): string {
        return $this->code;
    }

    /**
     * @return PlayerConnection[]
     * @throws ValidationException
     */
    public function getConnections(): array {
        if (empty($this->connections)) {
            $this->connections = PlayerConnection::getForPlayer($this);
        }
        return $this->connections;
    }

    public function addConnection(PlayerConnection $connection): Player {
        // Find duplicates
        $found = false;
        foreach ($this->getConnections() as $connectionToTest) {
            if ($connectionToTest->type === $connection->type && $connection->identifier === $connectionToTest->identifier) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $this->connections[] = $connection;
        }
        return $this;
    }

    public function jsonSerialize(): array {
        $connections = [];
        try {
            foreach ($this->getConnections() as $connection) {
                $connections[] = ['type' => $connection->type->value, 'identifier' => $connection->identifier];
            }
        } catch (ValidationException) {
        }
        return [
            'id'          => $this->id,
            'nickname'    => $this->nickname,
            'code'        => $this->getCode(),
            'email'       => $this->email,
            'rank'        => $this->rank,
            'connections' => $connections,
        ];
    }
}
