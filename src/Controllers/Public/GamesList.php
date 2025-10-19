<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\Templates\Public\GamesDetailTemplate;
use App\Templates\Public\GamesListTemplate;
use DateTimeImmutable;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\SvgWriter;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Psr\Http\Message\ResponseInterface;

class GamesList extends Controller
{
    public function show(Request $request) : ResponseInterface {
        $this->params = new GamesListTemplate($this->params);

        /** @var string $date */
        $date = $request->getGet('date', 'now');
        $this->params->date = new DateTimeImmutable($date);
        $this->params->games = GameFactory::getByDate($this->params->date, true);

        return $this->view('pages/public/list');
    }

    /**
     * @param  non-empty-string  $code
     * @return ResponseInterface
     * @throws \Endroid\QrCode\Exception\ValidationException
     * @throws \JsonException
     * @throws \Lsr\Exceptions\TemplateDoesNotExistException
     * @throws \Throwable
     */
    public function detail(string $code) : ResponseInterface {
        $this->params = new GamesDetailTemplate($this->params);

        $this->params->publicUrl = trailingSlashIt(Info::get('liga_api_url', 'https://laserliga.cz')).'g/'.$code;
        $this->params->code = $code;
        $this->params->game = GameFactory::getByCode($code);
        $qr = new Builder(
          writer  : new SvgWriter(),
          data    : $this->params->publicUrl,
          encoding: new Encoding('UTF-8'),
        )
          ->build()
          ->getString();
        assert(!empty($qr));
        $this->params->qr = $qr;

        return $this->view('pages/public/detail');
    }
}
