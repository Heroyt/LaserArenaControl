<?php
declare(strict_types=1);
namespace App\Controllers\Public;

use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\Templates\Public\GamesDetailTemplate;
use App\Templates\Public\GamesListTemplate;
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

        $this->params->date = new \DateTimeImmutable($request->getGet('date', 'now'));
        $this->params->games = GameFactory::getByDate($this->params->date, true);

        return $this->view('pages/public/list');
    }

    public function detail(string $code) : ResponseInterface {
        $this->params = new GamesDetailTemplate($this->params);

        $this->params->publicUrl = trailingSlashIt(Info::get('liga_api_url', 'https://laserliga.cz')).'g/'.$code;
        $this->params->code = $code;
        $this->params->game = GameFactory::getByCode($code);
        $this->params->qr = Builder::create()
                                   ->data($this->params->publicUrl)
                                   ->writer(new SvgWriter())
                                   ->encoding(new Encoding('UTF-8'))
                                   ->build()
                                   ->getString();

        return $this->view('pages/public/detail');
    }

}