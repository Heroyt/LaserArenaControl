<?php

/**
 * @file      E403.php
 * @brief     Pages\E403 class
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @date      2021-09-22
 * @version   1.0
 * @since     1.0
 *
 * @ingroup   Pages
 */

namespace App\Http\Controllers;

use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Roadrunner\ErrorHandlers\HttpErrorHandler;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * @class   E403
 * @brief   403 error page
 *
 * @package Pages
 * @ingroup Pages
 *
 * @author  Tomáš Vojík <vojik@wboy.cz>
 * @version 1.0
 * @since   1.0
 */
class E403 extends Controller implements HttpErrorHandler
{
    /**
     * @var string $title Page name
     */
    protected string $title = '403';
    /**
     * @var string $description Page description
     */
    protected string $description = 'Access denied';

    public function showError(Request $request, ?Throwable $error = null) : ResponseInterface {
        $this->init($request);
        if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return $this->respond(
              new ErrorResponse(
                           'Access denied',
                type     : ErrorType::ACCESS,
                detail   : $error?->getMessage(),
                exception: $error
              ),
              403
            );
        }
        if (str_contains($request->getHeaderLine('Accept'), 'text/html')) {
            $this->params['exception'] = $error;
            return $this->view('errors/E403')
                        ->withStatus(403);
        }

        return $this->respond(
          'Access denied - '.($error?->getMessage() ?? 'unknown error'),
          403,
          ['Content-Type' => 'text/plain']
        );
    }
}
