<?php

namespace App\Controllers;

use App\Models\GameGroup;
use App\Models\Group\PlayerPayInfoDto;
use App\Models\PriceGroup;
use JsonException;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 *
 */
class GameGroups extends Controller
{

    /**
     * @param  Request  $request
     * @return ResponseInterface
     * @throws JsonException
     * @throws ValidationException
     * @throws Throwable
     */
    public function listGroups(Request $request) : ResponseInterface {
        $groups = $request->getGet('all') !== null ? GameGroup::getAllByDate() : GameGroup::getActiveByDate();
        $data = [];
        foreach ($groups as $group) {
            $groupData = $group->jsonSerialize();
            $groupData['players'] = $group->getPlayers();
            $groupData['teams'] = $group->getTeams();
            $data[] = $groupData;
        }
        return $this->respond($data);
    }

    /**
     * @param  GameGroup  $group
     * @return ResponseInterface
     * @throws JsonException
     * @throws Throwable
     */
    public function getGroup(GameGroup $group) : ResponseInterface {
        $groupData = $group->jsonSerialize();
        $groupData['players'] = $group->getPlayers();
        return $this->respond($groupData);
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    public function create(Request $request) : ResponseInterface {
        $group = new GameGroup();
        $group->name = $request->getPost('name', sprintf(lang('Skupina %s'), date('d.m.Y H:i')));
        try {
            if (!$group->save()) {
                return $this->respond(['error' => 'Save failed'], 500);
            }
        } catch (ValidationException $e) {
            return $this->respond(['error' => 'Validation failed', 'exception' => $e->getMessage()], 400);
        }
        return $this->respond(['status' => 'ok', 'id' => $group->id]);
    }

    /**
     * @param  GameGroup  $group
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    public function update(GameGroup $group, Request $request) : ResponseInterface {
        /** @var string $name */
        $name = $request->getPost('name', '');
        if (!empty($name)) {
            $group->name = $name;
        }
        /** @var bool|string|numeric $active */
        $active = $request->getPost('active');
        if ($active !== null) {
            $group->active = (is_bool($active) && $active) || (is_numeric(
                  $active
                ) && ((int) $active) === 1) || $active === 'true';
        }

        $meta = $request->getPost('meta');
        if (isset($meta) && is_array($meta)) {
            if (isset($meta['payment']) && is_array($meta['payment'])) {
                foreach ($meta['payment'] as $key => $data) {
                    $meta['payment'][$key] = new PlayerPayInfoDto(...$data);
                }
            }
            $group->setMeta($meta);
        }

        try {
            if (!$group->save()) {
                return $this->respond(['error' => 'Save failed'], 500);
            }
        } catch (ValidationException $e) {
            return $this->respond(['error' => 'Validation failed', 'exception' => $e->getMessage()], 400);
        }
        return $this->respond(['status' => 'ok', 'id' => $group->id]);
    }

    /**
     * @param  GameGroup  $group
     * @return ResponseInterface
     * @throws JsonException
     * @throws ValidationException
     * @throws TemplateDoesNotExistException
     */
    public function printPlayerList(GameGroup $group) : ResponseInterface {
        $this->params['group'] = $group;
        $this->params['priceGroups'] = PriceGroup::getAll();
        return $this->view('components/groups/groupPrint');
    }

}