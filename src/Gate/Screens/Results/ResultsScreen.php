<?php

namespace App\Gate\Screens\Results;

use App\Api\Response\ErrorDto;
use App\Core\App;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Game\GameModes\CustomResultsMode;
use App\Gate\Screens\GateScreen;
use App\Gate\Settings\GateSettings;
use App\Gate\Settings\ResultsSettings;
use Exception;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 *
 */
class ResultsScreen extends GateScreen implements ResultsScreenInterface
{
    use WithResultsSettings;

    private ?ResultsScreenInterface $childScreen = null;

    /**
     * @inheritDoc
     */
    public static function getName() : string {
        return lang('Výsledky ze hry', context: 'gate-screens');
    }

    public static function getDescription() : string {
        return lang(
                   'Obrazovka zobrazující výsledky z her. Automaticky vybírá zobrazení podle herního módu.',
          context: 'gate-screens-description'
        );
    }

    /**
     * @inheritDoc
     */
    public static function getSettingsForm() : string {
        return 'gate/settings/results.latte';
    }

    /**
     * @inheritDoc
     */
    public static function buildSettingsFromForm(array $data) : GateSettings {
        return new ResultsSettings(isset($data['time']) ? (int) $data['time'] : null);
    }

    public function isActive() : bool {
        $game = $this->getGame();
        if (!isset($game)) {
            return false;
        }

        try {
            return $this->getChildScreen()->isActive();
        } catch (GameModeNotFoundException) {
            return false;
        }
    }

    /**
     * Get the correct child screen based on the current Game being displayed.
     *
     * Checks the game type and game mode for custom ones.
     *
     * @pre  Game must be set.
     * @post Child screen is initialized. (Game and settings are set)
     *
     * @return GateScreen&ResultsScreenInterface
     * @throws GameModeNotFoundException
     */
    private function getChildScreen() : ResultsScreenInterface {
        if (isset($this->childScreen)) {
            return $this->childScreen;
        }
        $game = $this->getGame();

        if (!isset($game)) {
            throw new RuntimeException('Game must be set.');
        }

        // Find correct screen based on game
        /** @var class-string<ResultsScreenInterface&GateScreen> $screenClass */
        if (
          ($mode = $game->getMode()) !== null &&
          $mode instanceof CustomResultsMode && class_exists($screenClass = $mode->getCustomGateScreen())
        ) {
            $this->childScreen = App::getService($screenClass::getDiKey());
        }

        // Default to basic rankable
        $this->childScreen ??= match ($game::SYSTEM) {
            'evo5', 'evo6' => App::getService('gate.screens.results.lasermaxx.rankable'),
            default        => throw new Exception('Cannot find results screen for system '.$game::SYSTEM),
        };

        $this->childScreen->setGame($game)->setSettings($this->getSettings())->setParams($this->params);

        return $this->childScreen;
    }

    /**
     * @inheritDoc
     */
    public static function getDiKey() : string {
        return 'gate.screens.results';
    }

    /**
     * @inheritDoc
     */
    public function run() : ResponseInterface {
        $game = $this->getGame();

        if (!isset($game)) {
            return $this->respond(new ErrorDto('Cannot show screen without game.'), 412);
        }

        try {
            /** @var ResultsScreenInterface&GateScreen $screen */
            $screen = $this->getChildScreen();
        } catch (Throwable $e) {
            return $this->respond(new ErrorDto('An error occured', exception: $e), 500);
        }

        return $screen->run();
    }
}