<?php

namespace App\Tools\GameLoading;

use App\Core\App;
use App\Services\TaskProducer;
use App\Tasks\MusicLoadTask;
use App\Tasks\Payloads\MusicLoadPayload;
use Lsr\Core\Config;
use Lsr\Logging\Logger;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\Options;

/**
 *
 */
trait MusicLoading
{
    private Config $config;
    private TaskProducer $taskProducer;
    private Logger $logger;
    private bool $loadAsync;

    /**
     * @param  non-empty-string  $system
     */
    protected function loadOrPlanMusic(int $musicId, string $system = 'evo5'): void {
        // Always eager-load armed music
        $this->loadArmedMusic($musicId, $this::MUSIC_FILE, $system);

        // Lazy-load music file in the background.
        // This is useful if the music mode should be copied to some network-attached directory, which could take a few seconds.
        if ($this->isLoadAsync()) {
            $this->getLogger()->debug('Loading music (' . $musicId . ') - ASYNC');
            try {
                $this->planMusicLoad($musicId, $system);
                return;
            } catch (JobsException $e) {
                $this->getLogger()->exception($e);
            }
        }

        $this->getLogger()->debug('Loading music (' . $musicId . ') - Direct');

        // Load music file right now
        $this->loadMusic($musicId, $this::MUSIC_FILE, $system);
    }

    protected function isLoadAsync(): bool {
        if (!isset($this->loadAsync)) {
            $this->loadAsync = (bool) ($this->getConfig()->getConfig('ENV')['MUSIC_LOAD_ASYNC'] ?? false);
        }
        return $this->loadAsync;
    }

    protected function getConfig(): Config {
        if (!isset($this->config)) {
            $this->config = App::getInstance()->config;
        }
        return $this->config;
    }

    public function getLogger(): Logger {
        if (!isset($this->logger)) {
            $this->logger = new Logger(LOG_DIR . 'loading/', $this::DI_NAME);
        }
        return $this->logger;
    }

    /**
     * @param  int  $musicId
     * @param  string  $system
     * @return void
     * @throws JobsException
     */
    protected function planMusicLoad(int $musicId, string $system = 'evo5'): void {
        $this->getTaskProducer()->push(
            MusicLoadTask::class,
            new MusicLoadPayload($musicId, $this::MUSIC_FILE, $this::DI_NAME, $system, microtime(true)),
            new Options(priority: 1) // Priority job should be done as soon as possible
        );
    }

    protected function getTaskProducer(): TaskProducer {
        if (!isset($this->taskProducer)) {
            $taskProducer = App::getService('taskProducer');
            assert($taskProducer instanceof TaskProducer);
            $this->taskProducer = $taskProducer;
        }
        return $this->taskProducer;
    }
}
