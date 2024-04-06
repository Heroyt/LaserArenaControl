<?php

namespace App\Cli\Commands\Games;

use App\Api\Response\ErrorDto;
use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\Services\ImportService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportGameCommand extends Command
{

	public function __construct(
		private readonly ImportService $importService
	) {
		parent::__construct('games:import');
	}

	public static function getDefaultName(): ?string {
		return 'games:import';
	}

	public static function getDefaultDescription(): ?string {
		return 'Import games from a directory.';
	}

	protected function configure(): void {
		$this->addOption(
			'all',
			'a',
			InputOption::VALUE_NONE,
			'Import all games in a directory - ignore modification time.'
		);
		$this->addOption(
			'limit',
			'l',
			InputOption::VALUE_REQUIRED,
			'Limit games to import.'
		);
		$this->addArgument('directory', InputArgument::REQUIRED, 'Results directory');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$dir = $input->getArgument('directory');
		$limit = (int) $input->getOption('limit');

		if (!file_exists($dir) || !is_dir($dir)) {
			$output->writeln(
				Colors::color(ForegroundColors::RED) . 'Error: argument must be a valid directory.' . Colors::reset()
			);
			return self::FAILURE;
		}

		$response = $this->importService->import($dir, $input->getOption('all'), $limit, $output);
		if ($response instanceof ErrorDto) {
			$output->writeln(
				Colors::color(ForegroundColors::RED) .
				$response->title .
				Colors::reset()
			);
			$output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
			if (!empty($response->values)) {
				$output->writeln(
					Colors::color(ForegroundColors::RED) .
					json_encode($response->values, JSON_PRETTY_PRINT) .
					Colors::reset()
				);
			}
			$output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
			return self::FAILURE;
		}

		$output->writeln(
			Colors::color(ForegroundColors::GREEN) .
			'Imported: ' . $response->imported . '/' . $response->total . ' in ' . $response->time . 's' .
			Colors::reset()
		);
		return self::SUCCESS;
	}

}