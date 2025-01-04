<?php

namespace App\Gate\Screens;

use App\GameModels\Game\Game;
use App\Gate\Logic\CustomEventDto;
use App\Gate\Logic\ScreenTriggerType;
use Lsr\Core\Requests\Response;
use Lsr\Core\Templating\Latte;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Psr\Http\Message\ResponseInterface;

/**
 * Abstract interface for all gate screens to inherit from.
 */
abstract class GateScreen
{
    /** @var string[] */
    protected array $systems = [];

    protected ?Game $game = null;

    /** @var array<string,mixed> */
    protected array $params = [];

    protected int $reloadTime = -1;

    protected ?ScreenTriggerType $trigger = null;

    protected ?CustomEventDto $triggerEvent = null;

    public function __construct(protected readonly Latte $latte) {}

    /**
     * Get screen name
     *
     * @return string
     */
    abstract public static function getName() : string;

    /**
     * get screen description
     *
     * @return string
     */
    public static function getDescription() : string {
        return '';
    }

    /**
     * Get screen group
     *
     * @return string
     */
    public static function getGroup() : string {
        return '';
    }

    /**
     * Get the key that this screen is registered in the DI container
     *
     * @return string
     */
    abstract public static function getDiKey() : string;

    /**
     * Checks if this screen should be active under the current conditions.
     *
     * @return bool
     */
    public function isActive() : bool {
        if ($this instanceof ReloadTimerInterface) {
            $timer = $this->getReloadTimer();
            return !isset($timer) || $timer > 0;
        }
        return true;
    }

    /**
     * Show the screen
     *
     * @return ResponseInterface Response containing the screen view
     */
    abstract public function run() : ResponseInterface;

    /**
     * @param  string[]  $systems
     *
     * @return $this
     */
    public function setSystems(array $systems) : GateScreen {
        $this->systems = $systems;
        return $this;
    }

    public function getGame() : ?Game {
        return $this->game;
    }

    public function setGame(?Game $game) : GateScreen {
        $this->game = $game;
        return $this;
    }

    /**
     * @param  array<string,mixed>  $params
     * @return $this
     */
    public function setParams(array $params) : GateScreen {
        $this->params = $params;
        return $this;
    }

    public function setTriggerEvent(?CustomEventDto $triggerEvent) : GateScreen {
        $this->triggerEvent = $triggerEvent;
        return $this;
    }

    /**
     * @param  string  $template
     * @param  array<string,mixed>  $params
     *
     * @return ResponseInterface
     * @throws TemplateDoesNotExistException
     */
    protected function view(string $template, array $params) : ResponseInterface {
        if ($this instanceof ReloadTimerInterface && $this->reloadTime <= 0) {
            $this->setReloadTime($this->getReloadTimer() ?? -1);
        }

        $response = $this
          ->respond(
            $this->latte
              ->viewToString(
                $template,
                array_merge(
                  $this->params,
                  [
                    'addJs'       => ['gate/defaultScreen.js'],
                    'reloadTimer' => $this->reloadTime,
                  ],
                  $params
                )
              )
          )
          ->withHeader('Content-Type', 'text/html')
          /** @phpstan-ignore nullsafe.neverNull */
          ->withHeader('X-Trigger', $this->getTrigger()?->value ?? 'null');
        if ($this->reloadTime > 0) {
            return $response->withHeader('X-Reload-Time', (string) $this->reloadTime);
        }
        if (isset($this->triggerEvent)) {
            $now = time();
            $response = $response
              ->withHeader('X-Event', $this->triggerEvent->event)
              ->withHeader('X-Now', (string) $now)
              ->withHeader('X-Event-Time', (string) $this->triggerEvent->time);
            if ($now < $this->triggerEvent->time) {
                return $response->withHeader('X-Reload-Time', (string) ($this->triggerEvent->time - $now));
            }
        }
        return $response;
    }

    public function setReloadTime(int $reloadTime) : GateScreen {
        $this->reloadTime = $reloadTime;
        return $this;
    }

    /**
     * @param  string|array<string, mixed>|object  $data
     * @param  int  $code
     * @param  string[]  $headers
     *
     * @return ResponseInterface
     */
    protected function respond(
      string | array | object $data,
      int                     $code = 200,
      array                   $headers = []
    ) : ResponseInterface {
        $response = new Response(new \Nyholm\Psr7\Response($code, $headers));

        if (is_string($data)) {
            return $response->withStringBody($data);
        }

        return $response->withJsonBody($data);
    }

    public function getTrigger() : ?ScreenTriggerType {
        return $this->trigger;
    }

    public function setTrigger(ScreenTriggerType $trigger) : static {
        $this->trigger = $trigger;
        return $this;
    }
}
