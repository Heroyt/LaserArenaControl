<?php

namespace App\Controllers\Cli;

use App\Exceptions\InsuficientRegressionDataException;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\Services\RegressionCalculator;
use App\Tools\Evo5\RegressionStatCalculator;
use Lsr\Core\CliController;
use Lsr\Core\Requests\CliRequest;
use Lsr\Helpers\Cli\Colors;
use Lsr\Helpers\Cli\Enums\ForegroundColors;

/**
 *
 */
class Regression extends CliController
{

	public function calculateHitRegression(CliRequest $request) : void {
		$calculator = new RegressionStatCalculator();

		$type = strtoupper($request->args[0] ?? 'TEAM');
		$model = $calculator->getHitsModel(GameModeType::from($type));

		if ($type === 'TEAM') {
			$teammates = (int) ($request->args[1] ?? 5);
			$enemies = (int) ($request->args[2] ?? 5);
			$length = (int) ($request->args[3] ?? 15);
			echo PHP_EOL.'Average hits prediction ('.$teammates.' teammates, '.$enemies.' enemies, '.$length.' minutes): '.RegressionCalculator::calculateRegressionPrediction([$teammates, $enemies, $length], $model).PHP_EOL.PHP_EOL;
		}
		else {
			$enemies = (int) ($request->args[1] ?? 9);
			$length = (int) ($request->args[2] ?? 15);
			echo PHP_EOL.'Average hits prediction ('.$enemies.' enemies, '.$length.' minutes): '.RegressionCalculator::calculateRegressionPrediction([$enemies, $length], $model).PHP_EOL.PHP_EOL;
		}
	}

	public function calculateDeathRegression(CliRequest $request) : void {
		$calculator = new RegressionStatCalculator();

		$type = strtoupper($request->args[0] ?? 'TEAM');
		$model = $calculator->getDeathsModel(GameModeType::from($type));

		if ($type === 'TEAM') {
			$teammates = (int) ($request->args[1] ?? 5);
			$enemies = (int) ($request->args[2] ?? 5);
			$length = (int) ($request->args[3] ?? 15);
			echo PHP_EOL.'Average deaths prediction ('.$teammates.' teammates, '.$enemies.' enemies, '.$length.' minutes): '.RegressionCalculator::calculateRegressionPrediction([$teammates, $enemies, $length], $model).PHP_EOL.PHP_EOL;
		}
		else {
			$enemies = (int) ($request->args[1] ?? 9);
			$length = (int) ($request->args[2] ?? 15);
			echo PHP_EOL.'Average deaths prediction ('.$enemies.' enemies, '.$length.' minutes): '.RegressionCalculator::calculateRegressionPrediction([$enemies, $length], $model).PHP_EOL.PHP_EOL;
		}
	}

	public function calculateHitOwnRegression(CliRequest $request) : void {
		$calculator = new RegressionStatCalculator();

		$model = $calculator->getHitsOwnModel();

		$teammates = (int) ($request->args[0] ?? 5);
		$enemies = (int) ($request->args[1] ?? 5);
		$length = (int) ($request->args[2] ?? 15);
		echo PHP_EOL.'Average teammate hits prediction ('.$teammates.' teammates, '.$enemies.' enemies, '.$length.' minutes): '.RegressionCalculator::calculateRegressionPrediction([$teammates, $enemies, $length], $model).PHP_EOL.PHP_EOL;
	}

	public function calculateDeathOwnRegression(CliRequest $request) : void {
		$calculator = new RegressionStatCalculator();

		$model = $calculator->getDeathsOwnModel();

		$teammates = (int) ($request->args[0] ?? 5);
		$enemies = (int) ($request->args[1] ?? 5);
		$length = (int) ($request->args[2] ?? 15);
		echo PHP_EOL.'Average teammate deaths prediction ('.$teammates.' teammates, '.$enemies.' enemies, '.$length.' minutes): '.RegressionCalculator::calculateRegressionPrediction([$teammates, $enemies, $length], $model).PHP_EOL.PHP_EOL;
	}

	public function updateRegressionModels() : void {
		$calculator = new RegressionStatCalculator();

		$calculator->updateHitsModel(GameModeType::SOLO);
		$calculator->updateHitsModel(GameModeType::TEAM);
		$calculator->updateDeathsModel(GameModeType::SOLO);
		$calculator->updateDeathsModel(GameModeType::TEAM);
		$calculator->updateHitsOwnModel();
		$calculator->updateDeathsOwnModel();

		$modes = GameModeFactory::getAll(['rankable' => false]);
		foreach ($modes as $mode) {
			echo 'Calculating models for game mode: '.$mode->name.PHP_EOL;
			try {
				echo 'Calculating hits model'.PHP_EOL;
				$calculator->updateHitsModel($mode->type, $mode);
				echo 'Calculating deaths model'.PHP_EOL;
				$calculator->updateDeathsModel($mode->type, $mode);
				if ($mode->type === GameModeType::TEAM) {
					echo 'Calculating team hits model'.PHP_EOL;
					$calculator->updateHitsOwnModel($mode);
					echo 'Calculating team deaths model'.PHP_EOL;
					$calculator->updateDeathsOwnModel($mode);
				}
			} catch (InsuficientRegressionDataException) {
				$this->errorPrint('Insufficient data for game mode: %s (#%d)', $mode->name, $mode->id);
			}
		}

		echo PHP_EOL.Colors::color(ForegroundColors::GREEN).'Updated all regression models'.Colors::reset().PHP_EOL;
	}

}