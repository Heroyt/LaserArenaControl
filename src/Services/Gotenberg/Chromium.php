<?php

namespace App\Services\Gotenberg;

use App\Services\GotenbergService;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;

class Chromium
{

	public const PATHS = [
		'url'      => '/forms/chromium/convert/url',
		'html'     => '/forms/chromium/convert/html',
		'markdown' => '/forms/chromium/convert/markdown',
	];

	public const DEFAULT_DATA = [
		[
			'name'     => 'preferCssPageSize',
			'contents' => 'true',
		],
		[
			'name'     => 'printBackground',
			'contents' => 'true',
		],
		[
			'name'     => 'marginTop',
			'contents' => '0',
		],
		[
			'name'     => 'marginLeft',
			'contents' => '0',
		],
		[
			'name'     => 'marginRight',
			'contents' => '0',
		],
		[
			'name'     => 'marginBottom',
			'contents' => '0',
		],
		[
			'name'     => 'emulatedMediaType',
			'contents' => 'print',
		],
		[
			'name'     => 'failOnConsoleExceptions',
			'contents' => 'true',
		],
	];

	public function __construct(
		public readonly GotenbergService $service
	) {
	}

	/**
	 * @param string               $url  URL to convert to PDF
	 * @param array<string, mixed> $data Additional data
	 *
	 * @return string Returns the PDF content or empty string on error
	 * @see https://gotenberg.dev/docs/modules/chromium#url
	 *
	 */
	public function getFromUrl(string $url, array $data = []) : string {
		$data = array_merge($this::DEFAULT_DATA, $data);
		$data[] = [
			'name'     => 'url',
			'contents' => $url,
		];
		$response = $this->service->post($this::PATHS['url'], $data, ['Gotenberg-Trace' => 'debug']);

		if (!isset($response) || !$this->isResponseValid($response)) {
			$this->service->getLogger()->warning('Invalid response '.json_encode(['isset' => isset($response), 'code' => $response->getStatusCode() === 200, 'type' => str_contains($response->getHeaderLine('Content-Type'), 'application/pdf')]));
			// Error - will be logged by the GotenbergService
			return '';
		}

		$response->getBody()->rewind();
		return $response->getBody()->getContents();
	}

	/**
	 * @param ResponseInterface $response
	 *
	 * @return bool
	 */
	protected function isResponseValid(ResponseInterface $response) : bool {
		return $response->getStatusCode() === 200 && str_contains($response->getHeaderLine('Content-Type'), 'application/pdf');
	}

	/**
	 * @param string               $html URL to convert to PDF
	 * @param array<string, mixed> $data Additional data
	 *
	 * @return string Returns the PDF content or empty string on error
	 * @see https://gotenberg.dev/docs/modules/chromium#html
	 *
	 */
	public function getFromHTML(string $html, array $data = []) : string {
		$data = array_merge($this::DEFAULT_DATA, $data);
		$htmlFile = $this->getTmpDir().'index.html';
		file_put_contents($htmlFile, $html);
		$data[] = [
			'name'     => 'files',
			'contents' => Utils::tryFopen($htmlFile, 'r'),
		];
		$response = $this->service->post($this::PATHS['html'], $data);

		if (!isset($response) || !$this->isResponseValid($response)) {
			$this->service->getLogger()->warning('Invalid response '.json_encode(['isset' => isset($response), 'code' => $response->getStatusCode() === 200, 'type' => str_contains($response->getHeaderLine('Content-Type'), 'application/pdf')]));
			// Error - will be logged by the GotenbergService
			return '';
		}

		$response->getBody()->rewind();
		return $response->getBody()->getContents();
	}

	protected function getTmpDir() : string {
		$dir = TMP_DIR.'gotenberg/';
		if (is_dir($dir) || (mkdir($dir) && is_dir($dir))) {
			return $dir;
		}
		return TMP_DIR;
	}

	/**
	 * @param string               $markdown URL to convert to PDF
	 * @param array<string, mixed> $data     Additional data
	 *
	 * @return string Returns the PDF content or empty string on error
	 * @see https://gotenberg.dev/docs/modules/chromium#markdown
	 *
	 */
	public function getFromMarkdown(string $markdown, array $data = []) : string {
		$data = array_merge($this::DEFAULT_DATA, $data);
		$htmlFile = $this->getTmpDir().'index.html';
		$mdFile = $this->getTmpDir().'file.md';
		file_put_contents($htmlFile, '<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>My PDF</title>
  </head>
  <body>
    {{ toHTML "file.md" }}
  </body>
</html>');
		file_put_contents($mdFile, $markdown);
		$data[] = [
			'name'     => 'files',
			'contents' => '@'.$htmlFile
		];
		$data[] = [
			'name'     => 'files',
			'contents' => '@'.$mdFile
		];
		$response = $this->service->post($this::PATHS['html'], $data);

		if (!isset($response) || !$this->isResponseValid($response)) {
			$this->service->getLogger()->warning('Invalid response '.json_encode(['isset' => isset($response), 'code' => $response->getStatusCode() === 200, 'type' => str_contains($response->getHeaderLine('Content-Type'), 'application/pdf')]));
			// Error - will be logged by the GotenbergService
			return '';
		}

		$response->getBody()->rewind();
		return $response->getBody()->getContents();
	}

}