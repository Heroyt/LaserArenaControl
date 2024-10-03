<?php
declare(strict_types=1);
namespace App\Controllers\System;

use Lsr\Core\Controllers\Controller;
use Psr\Http\Message\ResponseInterface;

class Cache extends Controller {
    protected string $title = 'Mezipaměť';

    public function show(): ResponseInterface {
        return $this->view('pages/settings/cache');
    }
}