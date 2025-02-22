<?php

/**
 * @file      E500.php
 * @brief     Pages\E500 class
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @date      2021-09-22
 * @version   1.0
 * @since     1.0
 *
 * @ingroup   Pages
 */

namespace App\Controllers;

use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Roadrunner\ErrorHandlers\HttpErrorHandler;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * @class   E500
 * @brief   404 error page
 *
 * @package Pages
 * @ingroup Pages
 *
 * @author  Tomáš Vojík <vojik@wboy.cz>
 * @version 1.0
 * @since   1.0
 */
class E500 extends Controller implements HttpErrorHandler
{
    /**
     * @var string $title Page name
     */
    protected string $title = '404';
    /**
     * @var string $description Page description
     */
    protected string $description = 'Page not found';

    public function showError(Request $request, ?Throwable $error = null) : ResponseInterface {
        if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return $this->respond(
              new ErrorResponse(
                           'Internal error',
                type     : ErrorType::INTERNAL,
                detail   : $error?->getMessage(),
                exception: $error
              ),
              500
            );
        }
        if (str_contains($request->getHeaderLine('Accept'), 'text/html')) {
            $this->params['exception'] = $error;
            return $this->view('errors/E500')
                        ->withStatus(500);
        }

        return $this->respond('Internal error - '.$error->getMessage(), 500, ['Content-Type' => 'text/plain']);
    }
}
