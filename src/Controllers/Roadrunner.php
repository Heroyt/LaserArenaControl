<?php

namespace App\Controllers;

use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Psr\Http\Message\ResponseInterface;
use Spiral\Goridge\RPC\AsyncRPCInterface;

class Roadrunner extends Controller
{
    public function __construct(
        private readonly AsyncRPCInterface $rpc
    ) {
        parent::__construct();
    }

    public function reset(Request $request): ResponseInterface {
        $service = $request->getPost('service', 'all');
        if ($service === 'all') {
            $this->resetAll();
        } else {
            $this->rpc->callIgnoreResponse('resetter.Reset', $service);
        }
        return $this->respond('Resetting workers...');
    }

    private function resetAll(): void {
        /** @var string[] $list */
        $list = $this->rpc->call('resetter.List', true);
        foreach ($list as $service) {
            $this->rpc->callIgnoreResponse('resetter.Reset', $service);
        }
    }
}
