<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Core\Middleware;

use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Middleware;
use Lsr\Interfaces\RequestInterface;

/**
 * Middleware to check cross-site request forgery attack using a token
 */
class CSRFCheck implements Middleware
{
    /**
     * @param Request $request
     *
     * @return bool
     */
    public function handle(RequestInterface $request): bool {
        $csrfName = implode('/', $request->path);
        if (formValid($csrfName)) {
            $request->query['error'] = lang('Požadavek vypršel, zkuste to znovu.', context: 'errors');
            return false;
        }
        return true;
    }
}
