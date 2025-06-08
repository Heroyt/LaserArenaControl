<?php

namespace App\Cli\Commands\Regression;

use App\Exceptions\InsufficientRegressionDataException;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Tools\Lasermaxx\RegressionStatCalculator;
use Lsr\Lg\Results\Enums\GameModeType;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateRegressionModelsCommand extends Command
{
    public function __construct(
      private readonly RegressionStatCalculator $calculator,
    ) {
        parent::__construct('regression:update');
    }

    public static function getDefaultName() : ?string {
        return 'regression:update';
    }

    public static function getDefaultDescription() : ?string {
        return 'Update all regression models.';
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int {
        try {
            $this->calculator->updateHitsModel(GameModeType::SOLO);
        } catch (InsufficientRegressionDataException) {
            $output->writeln('<comment>Insufficient data for SOLO mode, skipping hits model update.</comment>');
        }
        try {
            $this->calculator->updateHitsModel(GameModeType::TEAM);
        } catch (InsufficientRegressionDataException) {
            $output->writeln('<comment>Insufficient data for TEAM mode, skipping hits model update.</comment>');
        }
        try {
            $this->calculator->updateDeathsModel(GameModeType::SOLO);
        } catch (InsufficientRegressionDataException) {
            $output->writeln('<comment>Insufficient data for SOLO mode, skipping deaths model update.</comment>');
        }
        try {
            $this->calculator->updateDeathsModel(GameModeType::TEAM);
        } catch (InsufficientRegressionDataException) {
            $output->writeln('<comment>Insufficient data for TEAM mode, skipping deaths model update.</comment>');
        }
        try {
            $this->calculator->updateHitsOwnModel();
        } catch (InsufficientRegressionDataException) {
            $output->writeln('<comment>Insufficient data for SOLO mode, skipping hits own model update.</comment>');
        }
        try {
            $this->calculator->updateDeathsOwnModel();
        } catch (InsufficientRegressionDataException) {
            $output->writeln('<comment>Insufficient data for SOLO mode, skipping deaths own model update.</comment>');
        }

        $modes = GameModeFactory::getAll(['rankable' => false]);
        foreach ($modes as $mode) {
            $output->writeln('Calculating models for game mode: '.$mode->name);
            try {
                $output->writeln('Calculating hits model');
                $this->calculator->updateHitsModel($mode->type, $mode);
                $output->writeln('Calculating deaths model');
                $this->calculator->updateDeathsModel($mode->type, $mode);
                if ($mode->type === GameModeType::TEAM) {
                    $output->writeln('Calculating team hits model');
                    $this->calculator->updateHitsOwnModel($mode);
                    $output->writeln('Calculating team deaths model');
                    $this->calculator->updateDeathsOwnModel($mode);
                }
            } catch (InsufficientRegressionDataException) {
                $output->writeln(
                  sprintf('<error>Insufficient data for game mode: %s (#%d)</error>', $mode->name, $mode->id)
                );
            }
        }

        $output->writeln('<info>Updated all models</info>');
        return self::SUCCESS;
    }
}
