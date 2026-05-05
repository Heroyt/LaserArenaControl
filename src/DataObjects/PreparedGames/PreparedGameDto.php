<?php

namespace App\DataObjects\PreparedGames;

use App\DataObjects\NewGame\GameLoadData;
use App\Models\System;
use DateTimeInterface;
use JsonSerializable;
use OpenApi\Attributes as OA;

#[OA\Schema]
class PreparedGameDto implements JsonSerializable
{
    #[OA\Property]
    public ?System $system {
        get {
            if (isset($this->system)) {
                return $this->system;
            }
            if ($this->id_system === null) {
                return null;
            }
            return System::get($this->id_system);
        }
    }

    public function __construct(
      #[OA\Property]
      public GameLoadData       $data,
      #[OA\Property(property: 'id_game')]
      public ?int               $id = null,
      #[OA\Property]
      public ?DateTimeInterface $datetime = null,
      #[OA\Property]
      public PreparedGameType   $type = PreparedGameType::PREPARED,
      #[OA\Property]
      public bool               $active = true,
      public ?int               $id_system = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize() : array {
        return [
          'id_game'  => $this->id,
          'datetime' => $this->datetime,
          'data'     => $this->data,
          'type'     => $this->type,
          'active'   => $this->active,
          'system'   => $this->system,
        ];
    }
}
