<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Gate\Models\MusicGroupDto;
use App\Models\MusicMode;
use App\Templates\Public\MusicTemplate;
use Lsr\Core\Controllers\Controller;
use Psr\Http\Message\ResponseInterface;

/**
 * @property MusicTemplate $params
 */
class Music extends Controller
{
    public function __construct() {
        parent::__construct();
        $this->params = new MusicTemplate();
    }

    public function show() : ResponseInterface {
        $musicModes = MusicMode::query()->where('public = 1')->orderBy('order')->get();
        $this->params->music = [];
        foreach ($musicModes as $music) {
            if (!$music->public) {
                continue;
            }
            $group = empty($music->group) ? $music->name : $music->group;
            $this->params->music[$group] ??= new MusicGroupDto($group);
            $this->params->music[$group]->music[] = $music;
        }

        return $this->view('pages/public/music');
    }
}
