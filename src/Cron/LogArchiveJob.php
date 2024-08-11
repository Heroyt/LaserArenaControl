<?php

namespace App\Cron;

use Lsr\Logging\Exceptions\ArchiveCreationException;
use Lsr\Logging\LogArchiver;
use Lsr\Logging\Logger;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Spiral\RoadRunner\Metrics\Metrics;

final readonly class LogArchiveJob implements Job
{
    public function __construct(
        private LogArchiver $archiver,
        private Metrics $metrics,
    ) {
    }

    public function run(JobLock $lock): void {
        $this->metrics->add('cron_job_started', 1, ['log_archive']);
        $it = new RecursiveDirectoryIterator(LOG_DIR);
        $it = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::LEAVES_ONLY);
        $it = new RegexIterator($it, '/.*-\\d{4}-\\d{2}-\\d{2}\\.log/');
        $processed = [];
        $logger = new Logger(LOG_DIR, 'cron');
        $success = true;
        foreach ($it as $file) {
            $path = pathinfo($file, PATHINFO_DIRNAME);
            $fileName = pathinfo($file, PATHINFO_BASENAME);
            preg_match('/^(.*)-\d{4}-\d{2}-\d{2}\.log$/', $fileName, $matches);
            $name = $matches[1] ?? '';
            if (empty($name) || isset($processed[$name])) {
                continue;
            }
            try {
                $this->archiver->archiveOld($path, $name, LOG_DIR . 'archive/');
            } catch (ArchiveCreationException $e) {
                $logger->exception($e);
                $success = false;
            }
            $processed[$name] = true;
        }
        $logger->debug('Log archive done');
        $this->metrics->add($success ? 'cron_job_ok' : 'cron_job_error', 1, ['log_archive']);
    }

    public function getName(): string {
        return 'Vest sync';
    }
}
