<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Api\DataObjects\LigaPlayer\LigaPlayerData;
use App\Core\Info;
use App\Services\LaserLiga\LigaApi;
use App\Services\LaserLiga\PlayerProvider;
use App\Templates\Public\LaserLigaTemplate;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\SvgWriter;
use GuzzleHttp\Exception\GuzzleException;
use Lsr\Caching\Cache;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Nette\Utils\Validators;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Serializer\Serializer;

/**
 * @property LaserLigaTemplate $params
 */
class LaserLiga extends Controller
{
    public function __construct(
      private readonly LigaApi        $api,
      private readonly Serializer     $serializer,
      private readonly PlayerProvider $playerProvider,
      private readonly Cache          $cache,
    ) {

        $this->params = new LaserLigaTemplate();
    }

    public function show() : ResponseInterface {
        $this->prepareParams();
        return $this->view('pages/public/laserliga');
    }

    private function prepareParams() : void {
        $this->params->addCss = ['pages/laserLigaPublic.css'];
        $id = Info::get('liga_arena_id');
        $this->params->url = 'https://laserliga.cz/';
        if (is_numeric($id)) {
            $this->params->url .= 'arena/'.$id;
        }
        $this->params->qr = new Builder(
          writer  : new SvgWriter(),
          data    : $this->params->url,
          encoding: new Encoding('UTF-8'),
        )
          ->build()
          ->getString();
    }

    public function register(Request $request) : ResponseInterface {
        $this->prepareParams();
        $acceptTypes = $this->getAcceptTypes($request);
        $sendJson = $request->isAjax() || in_array('application/json', $acceptTypes);

        // Validate register values
        $name = $request->getPost('name');
        if (empty($name) || !is_string($name)) {
            $this->params->errors['name'] = lang('Přezdívka je povinná', context: 'errors');
        }
        else {
            $this->params->registerValues['name'] = $name;
        }

        $email = $request->getPost('email');
        if (empty($email) || !is_string($email)) {
            $this->params->errors['email'] = lang('E-mail je povinný', context: 'errors');
        }
        else {
            if (!Validators::isEmail($email)) {
                $this->params->errors['email'] = lang('E-mail není platný', context: 'errors');
            }
            else {
                $this->params->registerValues['email'] = $email;
            }
        }

        $password = $request->getPost('password');
        if (empty($password) || !is_string($password)) {
            $this->params->errors['password'] = lang('Heslo je povinné', context: 'errors');
        }

        // Send error response
        if (!empty($this->params->errors)) {
            if ($sendJson) {
                return $this->respond(
                  new ErrorResponse(
                            lang('Formulář obsahuje chyby'),
                            ErrorType::VALIDATION,
                    values: $this->params->errors
                  ),
                  400
                );
            }
            return $this->view('pages/public/laserliga')->withStatus(400);
        }

        // Send registration
        try {
            $response = $this->api->post('/api/players', ['name' => $name, 'email' => $email, 'password' => $password]);
        } catch (GuzzleException $e) {
            if ($sendJson) {
                return $this->respond(
                  new ErrorResponse(
                               lang('Registraci se nepodařilo odeslat'),
                               ErrorType::INTERNAL,
                    exception: $e
                  ),
                  500
                );
            }
            $this->params->errors[] = lang('Registraci se nepodařilo odeslat');
            return $this->view('pages/public/laserliga')->withStatus(500);
        }

        if ($response->getStatusCode() > 299) {
            $response->getBody()->rewind();
            $errorResponse = $this->serializer->deserialize(
              $response->getBody()->getContents(),
              ErrorResponse::class,
              'json'
            );
            if ($sendJson) {
                return $this->respond($errorResponse, $response->getStatusCode());
            }
            $this->params->errors[] = $errorResponse->title;
            return $this->view('pages/public/laserliga')->withStatus(500);
        }

        // Clear if successful
        $this->params->registerValues = [];

        $response->getBody()->rewind();
        $playerData = $this->serializer->deserialize(
          $response->getBody()->getContents(),
          LigaPlayerData::class,
          'json'
        );
        $player = $this->playerProvider->getPlayerObjectFromData($playerData);
        $this->params->newPlayer = $player;

        if ($sendJson) {
            return $this->respond($player);
        }
        return $this->view('pages/public/laserliga');
    }

    public function topPlayers() : ResponseInterface {
        $response = $this->cache->load(
          'topLigaPlayers',
          function (array &$dependencies) {
              try {
                  $response = $this->api->get('players', ['arena' => 'self', 'limit' => 20], ['timeout' => 10]);
              } catch (GuzzleException $e) {
                  $dependencies[$this->cache::Expire] = '1 minutes';
                  return new ErrorResponse(lang('Nepodařilo se stáhnout informace o hráčích'), exception: $e);
              }

              $players = $this->playerProvider->getPlayersFromResponse($response, true);
              return $players ?? new ErrorResponse(lang('Nepodařilo se stáhnout informace o hráčích'));
          },
          [
            $this->cache::Tags   => ['api', 'players'],
            $this->cache::Expire => '1 days',
          ]
        );

        if ($response instanceof ErrorResponse) {
            return $this->respond($response, 500);
        }
        return $this->respond($response);
    }
}
