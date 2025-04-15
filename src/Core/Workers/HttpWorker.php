<?php
declare(strict_types=1);

namespace App\Core\Workers;

use App\Core\Info;
use App\Services\FontAwesomeManager;
use Lsr\Interfaces\RequestInterface;
use Lsr\Roadrunner\ErrorHandlers\HttpErrorHandler;

class HttpWorker extends \Lsr\Roadrunner\Workers\HttpWorker
{

    public function __construct(
      HttpErrorHandler                    $error500Handler,
      HttpErrorHandler                    $error404Handler,
      HttpErrorHandler                    $error403Handler,
      private readonly FontAwesomeManager $fontAwesome,
    ) {
        parent::__construct($error500Handler, $error404Handler, $error403Handler);
    }

    public function handleRequest(RequestInterface $request) : void {
        Info::clearStaticCache();
        parent::handleRequest($request);
        $this->fontAwesome->saveIcons();
    }

}