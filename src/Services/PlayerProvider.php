<?php

namespace App\Services;

use App\Models\Auth\Enums\ConnectionType;
use App\Models\Auth\Player;
use App\Models\Auth\PlayerConnection;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Lsr\Core\Exceptions\ValidationException;

class PlayerProvider
{
    public function __construct(
        private readonly LigaApi $api
    ) {
    }

    /**
     * @param string $search
     * @param bool   $includeMail If true, the search checks an user's email too
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
     * @param string $search
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
            /** @var object{id:int,nickname:string,code:string,arena:int,email:string,stats:object{rank:int,gamesPlayed:int,arenasPlayed:int},connections:object{type:string,identifier:string}[]}[] $data */
            $data = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
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
     * @param array{id:int,nickname:string,code:string,arena:int,email:string,stats:array{rank:int,gamesPlayed:int,arenasPlayed:int},connections:array{type:string,identifier:string}[]} $data
     *
     * @return Player
     */
    public function getPlayerObjectFromData(array|object $data): Player {
        if (is_array($data)) {
            $data = (object) $data;
        }
        if (is_array($data->stats)) {
            $data->stats = (object) $data->stats;
        }

        // Try to find existing player first
        $player = Player::getByCode($data->code);
        if (!isset($player)) {
            $player = new Player();
        }

        $changed = isset($player->id) && ($player->nickname !== $data->nickname || $player->email !== $data->email || $player->rank !== $data->stats->rank);
        $player->nickname = $data->nickname;
        $player->code = $data->code;
        $player->email = $data->email;
        $player->rank = $data->stats->rank;
        foreach ($data->connections ?? [] as $connectionData) {
            if (is_array($connectionData)) {
                $connectionData = (object) $connectionData;
            }
            $connection = new PlayerConnection();
            $connection->type = ConnectionType::tryFrom($connectionData->type) ?? ConnectionType::OTHER;
            $connection->identifier = $connectionData->identifier;
            $player->addConnection($connection);
        }

        if ($changed || !isset($player->id)) {
            // Update player data from public
            $player->save();
        }
        return $player;
    }

    /**
     * Find only one player from public API by code
     *
     * @param string $code
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
            /** @var object{id:int,nickname:string,code:string,arena:int,email:string,stats:object{rank:int,gamesPlayed:int,arenasPlayed:int},connections:object{type:string,identifier:string}[]}[] $data */
            $data = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return $this->getPlayerObjectFromData($data);
    }
}
