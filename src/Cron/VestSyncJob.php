<?php

namespace App\Cron;

use App\Services\LigaApi;
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
			echo date('[Y-m-d H:i:s] ') . 'Vest sync successful' . PHP_EOL;
			return;
		}
		echo date('[Y-m-d H:i:s] ') . 'Vest sync failed' . PHP_EOL;
	}

	public function getName(): string {
		return 'Vest sync';
	}

}