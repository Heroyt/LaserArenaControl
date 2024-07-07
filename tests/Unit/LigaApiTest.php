<?php

namespace Tests\Unit;

use App\Core\App;
use App\GameModels\Game\Enums\VestStatus;
use App\GameModels\Vest;
use App\Services\LigaApi;
use Codeception\Stub;
use Codeception\Test\Unit;
use Lsr\Core\DB;
use Spiral\RoadRunner\Metrics\Metrics;
use Symfony\Component\Serializer\Serializer;
use Tests\Mocks\ClientMock;
use Tests\Mocks\GuzzleFactoryMock;
use Tests\Support\UnitTester;

class LigaApiTest extends Unit
{
    protected UnitTester $tester;

    public function testSyncVest(): void {
        $metrics = Stub::makeEmpty(Metrics::class);
        /** @var Serializer $serializer */
        $serializer = App::getService('symfony.serializer');
        $client = new ClientMock();
        $guzzleFactory = new GuzzleFactoryMock($client);
        $connection = DB::getConnection();
        $api = new LigaApi('', '', $metrics, $serializer, $guzzleFactory);

        $vestsAll = Vest::getForSystem('evo5');
        /** @var array{'1':Vest,'2':Vest,'3':Vest} $vests */
        $vests = [];
        foreach ($vestsAll as $vest) {
            $vests[$vest->vestNum] = $vest;
        }

        // Set default values to updated vests
        $vests['1']->status = VestStatus::BROKEN;
        $vests['1']->info = 'abcd';
        $vests['2']->status = VestStatus::OK;
        $vests['2']->info = 'abcd';
        $vests['3']->status = VestStatus::OK;
        $vests['3']->info = 'abcd';

        $connection->begin();
        $api->syncVests();
        $connection->rollback();

        $this->assertCount(2, $client->history);
        $this->assertEquals('GET', $client->history[0]['method']);
        $this->assertEquals('/api/vests', $client->history[0]['uri']);

        $this->assertEquals('POST', $client->history[1]['method']);
        $this->assertEquals('/api/vests', $client->history[1]['uri']);

        // Check if vests were updated
        $this->assertEquals(VestStatus::OK, $vests['1']->status);
        $this->assertEquals(null, $vests['1']->info);
        $this->assertEquals(VestStatus::PLAYABLE, $vests['2']->status);
        $this->assertEquals('test', $vests['2']->info);
        $this->assertEquals(VestStatus::BROKEN, $vests['3']->status);
        $this->assertEquals('error', $vests['3']->info);
    }
}
