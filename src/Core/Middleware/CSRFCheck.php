<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Core\Middleware;

use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Routing\Middleware;
use Lsr\Core\Routing\MiddlewareResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware to check cross-site request forgery attack using a token
 */
class CSRFCheck implements Middleware
{
    use MiddlewareResponder;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface {
        $csrfName = implode('/', $request->path);
        if (!formValid($csrfName)) {
            return $this->respond(
              $request,
              new ErrorResponse('Request expired', ErrorType::ACCESS, 'Try reloading the page.'),
            );
        }

        return $handler->handle($request);
    }
}
