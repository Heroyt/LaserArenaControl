<?php

namespace App\Cron;

use App\Api\Response\ErrorDto;
use App\Services\ImportService;
use Lsr\Logging\Logger;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;
use RuntimeException;
use Throwable;

/**
 * Fallback results import. Results should be imported automatically using the API.
 */
final readonly class ResultsImportJob implements Job
{

	private Logger $logger;

	public function __construct(private ImportService $importService) {
		$this->logger = new Logger(LOG_DIR, 'cron');
	}

	public function getName(): string {
		return 'Import results';
	}

	public function run(JobLock $lock): void {
		$lock->refresh(30.0);

		try {
			$response = $this->importService->import(DEFAULT_RESULTS_DIR);
		} catch (Throwable $e) {
			$this->logger->exception($e);
			throw new RuntimeException('Error while running import', 0, $e);
		}

		if ($response instanceof ErrorDto) {
			$this->logger->error(
				$response->title . (!empty($response->detail) ? ' ' . $response->detail : ''),
				$response->values ?? []
			);
			if (isset($response->exception)) {
				$this->logger->exception($response->exception);
			}
			throw new RuntimeException($response->title, 0, $response->exception);
		}

		if ($response->imported) {
			$this->logger->info('Imported ' . $response->imported . '/' . $response->total . ' results.');
		}
	}
}