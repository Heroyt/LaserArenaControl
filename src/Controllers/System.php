<?php

namespace App\Controllers;

use Lsr\Core\Controllers\Controller;
use Psr\Http\Message\ResponseInterface;

class System extends Controller
{
    public function restart(): ResponseInterface {
        // start.sh is set up in a way to observe the restart.txt file and if its present, stop the container.
        // The restarting happens automatically due to the docker-compose "restart: unless-stopped" setting.
        touch(TMP_DIR . 'restart.txt');
        return $this->respond('Restarting...');
    }
}
