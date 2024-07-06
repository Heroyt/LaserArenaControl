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
     * @param Game $game
     *
     * @return string
     */
    protected function getQR(Game $game): string {
        $result = Builder::create()
                         ->data($this->getPublicUrl($game))
                         ->writer(new SvgWriter())
                         ->encoding(new Encoding('UTF-8'))
                         ->errorCorrectionLevel(ErrorCorrectionLevel::Low)
                         ->build();
        return $result->getString();
    }

    protected function getPublicUrl(Game $game): string {
        /** @var string $url */
        $url = Info::get('liga_api_url');
        return trailingSlashIt($url) . 'g/' . $game->code;
    }
}
