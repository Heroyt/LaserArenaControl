<?php

namespace App\Services;

use App\Api\DataObjects\LigaPlayer\LigaPlayerData;
use App\Core\App;
use App\Models\Auth\Player;
use App\Models\Auth\PlayerConnection;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Lsr\Core\Exceptions\ValidationException;
use Symfony\Component\Serializer\Serializer;

/**
 * @phpstan-type PlayerData array{
 *     id:int,
 *     nickname:string,
 *     code:string,
 *     arena:int,
 *     email:string,
 *     stats:array{rank:int,gamesPlayed:int,arenasPlayed:int},
 *     connections:array{type:string,identifier:string}[]
 * }
 */
readonly class PlayerProvider
{
    public function __construct(
        private LigaApi    $api,
        private Serializer $serializer,
    ) {
    }

    /**
     * @param  string  $search
     * @param  bool  $includeMail  If true, the search checks an user's email too
     *
     * @return Player[]
     * @throws ValidationException
     */
    public function findPlayersLocal(string $search, bool $includeMail = true): array {
        $query = Player::query();
        // Check code format
        if (preg_match('/^(\d+-[A-Z\d]{1,5})$/', trim($search), $matches) === 1) {
            $query->where('[code] LIKE %like~', $matches[1]);
        } else {
            $where = [
              ['[code] LIKE %~like~', $search],
              ['[nickname] LIKE %~like~', $search],
            ];
            if ($includeMail) {
                $where[] = ['[email] LIKE %~like~', $search];
            }
            $query->where(
                '%or',
                $where
            );
        }

        return $query->get();
    }

    /**
     * Find players using the public API.
     *
     * @param  string  $search
     *
     * @return Player[]
     */
    public function findPlayersPublic(string $search): array {
        try {
            $response = $this->api->get('players', ['search' => $search], ['timeout' => 10]);
        } catch (GuzzleException) {
            return [];
        }
        if ($response->getStatusCode() !== 200) {
            return [];
        }
        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();
        try {
            /** @var LigaPlayerData[] $data */
            $data = $this->serializer->deserialize($body, LigaPlayerData::class . '[]', 'json');
        } catch (JsonException) {
            return [];
        }

        // Transform JSON data into model objects
        $objects = [];
        foreach ($data as $playerData) {
            $objects[] = $this->getPlayerObjectFromData($playerData);
        }
        return $objects;
    }

    /**
     * Parse data from API and return player object.
     *
     * If the player already exists in database, it returns the updated model from DB.
     *
     * @warning Does not check the validity of input array. It will throw a warning if the input is not valid.
     *
     * @param  LigaPlayerData  $data
     *
     * @return Player
     */
    public function getPlayerObjectFromData(LigaPlayerData $data): Player {
        // Try to find existing player first
        $player = Player::getByCode($data->code);
        if (!isset($player)) {
            $player = new Player();
        }

        $changed = isset($player->id)
          && (
            $player->nickname !== $data->nickname
            || $player->email !== $data->email
            || $player->rank !== $data->stats->rank
          );
        $player->nickname = $data->nickname;
        $player->code = $data->code;
        $player->email = $data->email;
        $player->rank = $data->stats->rank;
        foreach ($data->connections ?? [] as $connectionData) {
            $connection = new PlayerConnection();
            $connection->type = $connectionData->type;
            $connection->identifier = $connectionData->identifier;
            $player->addConnection($connection);
        }

        if ($changed || !isset($player->id)) {
            // Update player data from public
            try {
                $player->save();
            } catch (ValidationException $e) {
                App::getInstance()->getLogger()->exception($e);
            }
        }
        return $player;
    }

    /**
     * Find only one player from public API by code
     *
     * @param  string  $code
     *
     * @return Player|null
     */
    public function findPublicPlayerByCode(string $code): ?Player {
        try {
            $response = $this->api->get('players/' . $code, config: ['timeout' => 10]);
        } catch (GuzzleException) {
            return null;
        }
        if ($response->getStatusCode() !== 200) {
            return null;
        }
        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();

        try {
            $data = $this->serializer->deserialize($body, LigaPlayerData::class, 'json');
        } catch (JsonException) {
            return null;
        }

        return $this->getPlayerObjectFromData($data);
    }
}
