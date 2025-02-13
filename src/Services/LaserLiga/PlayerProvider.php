<?php

namespace App\Services\LaserLiga;

use App\Core\App;
use App\Models\Auth\Player;
use App\Models\Auth\PlayerConnection;
use GuzzleHttp\Exception\GuzzleException;
use Lsr\LaserLiga\DataObjects\LigaPlayer\LigaPlayerData;
use Lsr\LaserLiga\PlayerProviderInterface;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface;
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
readonly class PlayerProvider implements PlayerProviderInterface
{
    public function __construct(
      private LigaApi    $api,
      private Serializer $serializer,
    ) {}

    /**
     * @param  string  $search
     * @param  bool  $includeMail  If true, the search checks an user's email too
     *
     * @return Player[]
     * @throws ValidationException
     */
    public function findPlayersLocal(string $search, bool $includeMail = true) : array {
        $query = Player::query();
        // Check code format
        if (preg_match('/^(\d+-[A-Z\d]{1,5})$/', trim($search), $matches) === 1) {
            $query->where('[code] LIKE %like~', $matches[1]);
        }
        else {
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
     * @return Player[]|null
     */
    public function findPlayersPublic(string $search, bool $noSave = false) : ?array {
        try {
            $response = $this->api->get('players', ['search' => $search], ['timeout' => 10]);
        } catch (GuzzleException) {
            return null;
        }
        if ($response->getStatusCode() !== 200) {
            return null;
        }
        return $this->getPlayersFromResponse($response, $noSave);
    }

    /**
     * @param  ResponseInterface  $response
     * @return Player[]|null
     */
    public function getPlayersFromResponse(ResponseInterface $response, bool $noSave = false) : ?array {
        if ($response->getStatusCode() !== 200) {
            return null;
        }
        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();

        /** @var LigaPlayerData[] $data */
        $data = $this->serializer->deserialize($body, LigaPlayerData::class.'[]', 'json');

        // Transform JSON data into model objects
        $objects = [];
        foreach ($data as $playerData) {
            $objects[] = $this->getPlayerObjectFromData($playerData, $noSave);
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
     * @return Player
     */
    public function getPlayerObjectFromData(LigaPlayerData $data, bool $noSave = false) : Player {
        // Try to find existing player first
        $player = Player::getByCode($data->code);
        if ($noSave || !isset($player)) {
            $player = new Player();
        }

        $changed = !$noSave && isset($player->id)
          && (
            $player->nickname !== $data->nickname
            || $player->email !== $data->email
            || $player->rank !== $data->stats->rank
            || $player->birthday?->format('Y-m-d') !== $data->birthday?->format('Y-m-d')
          );
        $player->nickname = $data->nickname;
        $player->code = $data->code;
        $player->email = $data->email;
        $player->rank = $data->stats->rank;
        $player->birthday = $data->birthday;
        foreach ($data->connections ?? [] as $connectionData) {
            $connection = new PlayerConnection();
            $connection->type = $connectionData->type;
            $connection->identifier = $connectionData->identifier;
            $player->connections[] = $connection;
        }
        if (isset($data->codeHistory)) {
            $player->codeHistory = $data->codeHistory;
        }

        if (!$noSave && ($changed || !isset($player->id))) {
            // Update player data from public
            try {
                $player->save();
            } catch (\Lsr\Orm\Exceptions\ValidationException $e) {
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
    public function findPublicPlayerByCode(string $code, bool $noSave = false) : ?Player {
        try {
            $response = $this->api->get('players/'.$code, config: ['timeout' => 10]);
        } catch (GuzzleException) {
            return null;
        }
        if ($response->getStatusCode() !== 200) {
            return null;
        }
        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();

        $data = $this->serializer->deserialize($body, LigaPlayerData::class, 'json');

        return $this->getPlayerObjectFromData($data, $noSave);
    }

    /**
     * @return Player[]|null
     */
    public function findAllPublicPlayers(bool $noSave = false) : ?array {
        try {
            $response = $this->api->get('players', ['arena' => 'self'], config: ['timeout' => 10]);
        } catch (GuzzleException) {
            return null;
        }
        return $this->getPlayersFromResponse($response, $noSave);
    }

    /**
     * @param  string[]  $codes
     * @return Player[]|null
     */
    public function findAllPublicPlayersByCodes(array $codes, bool $noSave = false) : ?array {
        try {
            $response = $this->api->get('players', ['codes' => $codes], config: ['timeout' => 10]);
        } catch (GuzzleException) {
            return null;
        }
        return $this->getPlayersFromResponse($response, $noSave);
    }

    /**
     * @return Player[]|null
     */
    public function findAllPublicPlayersByOldCode(string $code, bool $noSave = false) : ?array {
        try {
            $response = $this->api->get('players/old/'.$code, config: ['timeout' => 10]);
        } catch (GuzzleException) {
            return null;
        }
        return $this->getPlayersFromResponse($response, $noSave);
    }
}
