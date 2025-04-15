<?php
declare(strict_types=1);

namespace App\CQRS\Queries\GameModes;

use App\Models\System;
use App\Models\SystemType;

trait QueryModeBySystemsTrait
{
    /**
     * @param  value-of<SystemType>|System|SystemType|int  ...$systems  System type name, System object or system ID
     * @return $this
     */
    public function systems(string | System | SystemType | int ...$systems) : self {
        $or = [
          'systems IS NULL',
        ];
        foreach ($systems as $system) {
            if (is_string($system) && SystemType::tryFrom($system) === null) {
                continue;
            }
            if ($system instanceof SystemType) {
                $system = $system->value;
            }
            if (is_int($system)) {
                $system = System::get($system)->type->value;
            }
            if ($system instanceof System) {
                $system = $system->type->value;
            }
            if (!empty($system)) {
                $or[] = [
                  'systems LIKE %~like~',
                  $system,
                ];
            }
        }
        $this->query->where('(%or)', $or);
        return $this;
    }
}