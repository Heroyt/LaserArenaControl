<?php

namespace App\Gate\Screens;

use App\GameModels\Game\Game;
use JsonException;
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

	public function __construct(protected readonly Latte $latte,) {
	}

	/**
	 * Get screen name
	 *
	 * @return string
	 */
	abstract public static function getName(): string;

	/**
	 * get screen description
	 *
	 * @return string
	 */
	public static function getDescription(): string {
		return '';
	}

	/**
	 * Show the screen
	 *
	 * @return ResponseInterface Response containing the screen view
	 */
	abstract public function run(): ResponseInterface;

	/**
	 * @param string[] $systems
	 *
	 * @return $this
	 */
	public function setSystems(array $systems): GateScreen {
		$this->systems = $systems;
		return $this;
	}

	public function getGame(): ?Game {
		return $this->game;
	}

	public function setGame(?Game $game): GateScreen {
		$this->game = $game;
		return $this;
	}

	/**
	 * @param string              $template
	 * @param array<string,mixed> $params
	 *
	 * @return ResponseInterface
	 * @throws TemplateDoesNotExistException
	 */
	protected function view(string $template, array $params): ResponseInterface {
		return $this->respond($this->latte->viewToString($template, $params));
	}


	/**
	 * @param string|array<string, mixed>|object $data
	 * @param int                                $code
	 * @param string[]                           $headers
	 *
	 * @return ResponseInterface
	 * @throws JsonException
	 */
	protected function respond(string|array|object $data, int $code = 200, array $headers = []): ResponseInterface {
		$response = new Response(new \Nyholm\Psr7\Response($code, $headers));

		if (is_string($data)) {
			return $response->withStringBody($data);
		}

		return $response->withJsonBody($data);
	}

}