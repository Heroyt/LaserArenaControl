<?php

/**
 * @file      E404.php
 * @brief     Pages\E404 class
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
 * @class   E404
 * @brief   404 error page
 *
 * @package Pages
 * @ingroup Pages
 *
 * @author  Tomáš Vojík <vojik@wboy.cz>
 * @version 1.0
 * @since   1.0
 */
class E404 extends Controller implements HttpErrorHandler
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
        $this->init($request);
        if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return $this->respond(
              new ErrorResponse(
                           'Resource not found',
                type     : ErrorType::NOT_FOUND,
                detail   : $error?->getMessage(),
                exception: $error
              ),
              404
            );
        }
        if (str_contains($request->getHeaderLine('Accept'), 'text/html')) {
            $this->params['exception'] = $error;
            return $this->view('errors/E404')
                        ->withStatus(404);
        }

        return $this->respond(
          'Resource not found - '.($error?->getMessage() ?? 'unknown error'),
          404,
          ['Content-Type' => 'text/plain']
        );
    }
}
