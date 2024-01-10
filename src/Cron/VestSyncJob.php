<?php

namespace App\Cron;

use App\Services\LigaApi;
use Lsr\Logging\Logger;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;

final readonly class VestSyncJob implements Job
{

	public function __construct(
		private LigaApi $api
	) {
	}

	public function run(JobLock $lock): void {
		$lock->refresh(120.0);
		if ($this->api->syncVests()) {
			(new Logger(LOG_DIR, 'cron'))->debug('Vest sync successful');
			return;
		}
		(new Logger(LOG_DIR, 'cron'))->warning('Vest sync failed');
	}

	public function getName(): string {
		return 'Vest sync';
	}

}