<?php

declare(strict_types=1);

namespace App\Tools\GameLoading;

use App\Models\GameGroup;
use Lsr\Core\Exceptions\ValidationException;

/**
 * @phpstan-import-type GameData from LasermaxxGameLoader
 */
trait GroupLoading
{
    /**
     * @param  LasermaxxLoadData  $loadData
     * @param  GameData  $data
     */
    public function prepareGroup(LasermaxxLoadData $loadData, array $data): void {
        if (empty($data['groupSelect'])) {
            return;
        }

        if ($data['groupSelect'] === 'new-custom') {
            $name = $data['groupName'];
            if (empty($name)) {
                $name = sprintf(
                    lang('Skupina %s'),
                    isset($game->start) ? date('d.m.Y H:i') : ''
                );
            }

            $group = new GameGroup();
            $group->name = $name;
            $group->active = true;
            try {
                if ($group->save()) {
                    $group::clearQueryCache();
                    $loadData->meta['group'] = $group->id;
                    $loadData->meta['groupName'] = $name;
                    return;
                }
            } catch (ValidationException) {

            }
            // Fall back to a new group without name
            $loadData->meta['group'] = 'new';
            return;
        }

        $loadData->meta['group'] = $data['groupSelect'];
    }
}
