<?php

namespace App\Gate\Screens;

use App\Core\Info;
use App\GameModels\Game\Game;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;

trait WithGameQR
{
    /**
     * Get SVG QR code for game
     *
     * @param  Game  $game
     *
     * @return string
     */
    protected function getQR(Game $game) : string {
        return new Builder(
          writer              : new SvgWriter(),
          data                : $this->getPublicUrl($game),
          encoding            : new Encoding('UTF-8'),
          errorCorrectionLevel: ErrorCorrectionLevel::Low
        )
          ->build()
          ->getString();
    }

    protected function getPublicUrl(Game $game) : string {
        /** @var string $url */
        $url = Info::get('liga_api_url');
        return trailingSlashIt($url).'g/'.$game->code.'?mtm_campaign=QR&mtm_kwd=gate';
    }
}
