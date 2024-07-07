<?php

namespace Tests\Mocks;

use App\Services\GuzzleFactory;
use GuzzleHttp\Client;
use SensitiveParameter;

class GuzzleFactoryMock extends GuzzleFactory
{
    public function __construct(
        private readonly Client $client,
    ) {
        parent::__construct();
    }

    public function makeClient(string $url, #[SensitiveParameter] string $apiKey): Client {
        return $this->client;
    }
}
