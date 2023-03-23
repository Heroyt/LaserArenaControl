<?php

namespace App\Tools\Evo5;

use App\Core\Info;
use App\Exceptions\InsuficientRegressionDataException;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\GameModes\AbstractMode;
use App\Services\RegressionCalculator;
use Dibi\Exception;
use Dibi\Row;
use Lsr\Core\DB;
use Lsr\Core\Dibi\Fluent;

/**
 * Regression calculator class used for predicting player's hits, deaths, team hits and team deaths
 */
class RegressionStatCalculator
{
	private RegressionCalculator $regressionCalculator;

	public function __construct() {
		$this->regressionCalculator = new RegressionCalculator();
	}

	/**
	 * Get a regression model for player's deaths based on the game type
	 *
	 * @param GameModeType      $type
	 * @param AbstractMode|null $mode
	 *
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 * @see RegressionCalculator::calculateRegressionPrediction() To calculate a value from this model
	 */
	public function getDeathsModel(GameModeType $type, ?AbstractMode $mode = null) : array {
		$infoKey = 'deathModel'.$type->value.(isset($mode) && !$mode->rankable ? $mode->id : '');

		/** @var numeric[]|null $model */
		$model = Info::get($infoKey);
		if (empty($model)) {
			$model = $this->calculateDeathRegression($type, $mode);
			try {
				Info::set($infoKey, $model);
			} catch (Exception) {
				// Failed to save the value - ignore
			}
		}
		return $model;
	}

	/**
	 * @param GameModeType      $type
	 * @param AbstractMode|null $mode
	 *
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 */
	public function calculateDeathRegression(GameModeType $type, ?AbstractMode $mode = null) : array {
		$query = DB::select('vEvo5RegressionData', $type === GameModeType::TEAM ? 'AVG(deaths_other) as [value], enemies, teammates, game_length' : 'AVG(deaths) as [value], teammates, game_length')
							 ->where('game_type = %s', $type->value)
							 ->groupBy('id_game, enemies, teammates');

		$this->filterQueryByMode($mode, $query);

		$data = $query->fetchAll();

		echo 'DataPoints: '.count($data).PHP_EOL;
		if (count($data) < 10) {
			throw new InsuficientRegressionDataException();
		}

		$inputsLinear = [];
		$inputsMultiplication = [];
		$inputsSquared = [];
		$matY = [];
		$actual = [];

		if ($type === GameModeType::TEAM) {
			foreach ($data as $row) {
				$this->prepareDataTeam($row, $inputsLinear, $inputsMultiplication, $inputsSquared, $matY, $actual);
			}
		}
		else {
			foreach ($data as $row) {
				$this->prepareDataSolo($row, $inputsLinear, $inputsMultiplication, $inputsSquared, $matY, $actual);
			}
		}

		return $this->createAndCompareModels($matY, $actual, $inputsLinear, $inputsMultiplication, $inputsSquared);
	}

	/**
	 * @param AbstractMode|null $mode
	 * @param Fluent            $query
	 *
	 * @return void
	 */
	private function filterQueryByMode(?AbstractMode $mode, Fluent $query) : void {
		if (isset($mode) && !$mode->rankable) {
			$query->where('id_mode = %i', $mode->id);
		}
		else {
			$query->where('rankable = 1');
		}
	}

	/**
	 * @param Row         $row
	 * @param numeric[][] $inputsLinear
	 * @param numeric[][] $inputsMultiplication
	 * @param numeric[][] $inputsSquared
	 * @param numeric[][] $matY
	 * @param numeric[]   $actual
	 */
	public function prepareDataTeam(Row $row, array &$inputsLinear, array &$inputsMultiplication, array &$inputsSquared, array &$matY, array &$actual) : void {
		$inputsLinear[] = [1, $row->enemies, $row->teammates, $row->game_length];
		$inputsMultiplication[] = [
			1,
			$row->enemies,
			$row->teammates,
			$row->game_length,
			$row->enemies * $row->teammates,
			$row->enemies * $row->game_length,
			$row->teammates * $row->game_length,
		];
		$inputsSquared[] = [
			1,
			$row->enemies,
			$row->teammates,
			$row->game_length,
			$row->enemies * $row->teammates,
			$row->enemies * $row->game_length,
			$row->teammates * $row->game_length,
			$row->enemies ** 2,
			$row->teammates ** 2,
			$row->game_length ** 2,
		];
		$matY[] = [$row->value];
		$actual[] = $row->value;
	}

	/**
	 * @param Row         $row
	 * @param numeric[][] $inputsLinear
	 * @param numeric[][] $inputsMultiplication
	 * @param numeric[][] $inputsSquared
	 * @param numeric[][] $matY
	 * @param numeric[]   $actual
	 */
	public function prepareDataSolo(Row $row, array &$inputsLinear, array &$inputsMultiplication, array &$inputsSquared, array &$matY, array &$actual) : void {
		$inputsLinear[] = [1, $row->teammates, $row->game_length];
		$inputsMultiplication[] = [
			1,
			$row->teammates,
			$row->game_length,
			$row->teammates * $row->game_length,
		];
		$inputsSquared[] = [
			1,
			$row->teammates,
			$row->game_length,
			$row->teammates * $row->game_length,
			$row->teammates ** 2,
			$row->game_length ** 2,
		];
		$matY[] = [$row->value];
		$actual[] = $row->value;
	}

	/**
	 * @param numeric[][] $matY
	 * @param numeric[]   $actual
	 * @param numeric[][] $inputsLinear
	 * @param numeric[][] $inputsMultiplication
	 * @param numeric[][] $inputsSquared
	 *
	 * @return numeric[]
	 */
	public function createAndCompareModels(array $matY, array $actual, array $inputsLinear, array $inputsMultiplication, array $inputsSquared) : array {
		$linearModel = $this->regressionCalculator->regression($inputsLinear, $matY);
		$predictions = $this->regressionCalculator->calculatePredictions($inputsLinear, $linearModel);
		$r2Linear = $this->regressionCalculator->calculateRSquared($predictions, $actual);

		echo 'Linear model: '.json_encode($linearModel).PHP_EOL.'R2: '.$r2Linear.PHP_EOL;

		$multiplicationModel = $this->regressionCalculator->regression($inputsMultiplication, $matY);
		$predictions = $this->regressionCalculator->calculatePredictions($inputsMultiplication, $multiplicationModel);
		$r2Multiplication = $this->regressionCalculator->calculateRSquared($predictions, $actual);

		echo 'Multiplication model: '.json_encode($multiplicationModel).PHP_EOL.'R2: '.$r2Multiplication.PHP_EOL;

		$combinedModel = $this->regressionCalculator->regression($inputsSquared, $matY);
		$predictions = $this->regressionCalculator->calculatePredictions($inputsSquared, $combinedModel);
		$r2Combined = $this->regressionCalculator->calculateRSquared($predictions, $actual);

		echo 'Combined model: '.json_encode($combinedModel).PHP_EOL.'R2: '.$r2Combined.PHP_EOL;

		// Return the best model
		$maxR2 = max($r2Linear, $r2Combined, $r2Multiplication);
		return match (true) {
			$maxR2 === $r2Linear => $linearModel,
			$maxR2 === $r2Multiplication => $multiplicationModel,
			default => $combinedModel,
		};
	}

	/**
	 * Recalculate a regression model for player's deaths based on the game type
	 *
	 * @param GameModeType      $type
	 * @param AbstractMode|null $mode
	 *
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 * @see RegressionCalculator::calculateRegressionPrediction() To calculate a value from this model
	 */
	public function updateDeathsModel(GameModeType $type, ?AbstractMode $mode = null) : array {
		$infoKey = 'deathModel'.$type->value.(isset($mode) && !$mode->rankable ? $mode->id : '');
		$model = $this->calculateDeathRegression($type, $mode);
		try {
			Info::set($infoKey, $model);
		} catch (Exception) {
			// Failed to save the value - ignore
		}
		return $model;
	}

	/**
	 * Get a regression model for player's hits based on the game type
	 *
	 * @param GameModeType      $type
	 * @param AbstractMode|null $mode
	 *
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 * @see RegressionCalculator::calculateRegressionPrediction() To calculate a value from this model
	 */
	public function getHitsModel(GameModeType $type, ?AbstractMode $mode = null) : array {
		$infoKey = 'hitModel'.$type->value.(isset($mode) && !$mode->rankable ? $mode->id : '');

		/** @var numeric[]|null $model */
		$model = Info::get($infoKey);
		if (empty($model)) {
			$model = $this->calculateHitRegression($type, $mode);
			try {
				Info::set($infoKey, $model);
			} catch (Exception) {
				// Failed to save the value - ignore
			}
		}
		return $model;
	}

	/**
	 * @param GameModeType      $type
	 * @param AbstractMode|null $mode
	 *
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 */
	public function calculateHitRegression(GameModeType $type, ?AbstractMode $mode = null) : array {
		$query = DB::select('vEvo5RegressionData', $type === GameModeType::TEAM ? 'AVG(hits_other) as [value], enemies, teammates, game_length' : 'AVG(hits) as [value], teammates, game_length')
							 ->where('game_type = %s', $type->value)
							 ->groupBy('id_game, enemies, teammates');

		$this->filterQueryByMode($mode, $query);

		$data = $query->fetchAll();

		echo 'DataPoints: '.count($data).PHP_EOL;
		if (count($data) < 10) {
			throw new InsuficientRegressionDataException();
		}

		$inputsLinear = [];
		$inputsMultiplication = [];
		$inputsSquared = [];
		$matY = [];
		$actual = [];

		if ($type === GameModeType::TEAM) {
			foreach ($data as $row) {
				$this->prepareDataTeam($row, $inputsLinear, $inputsMultiplication, $inputsSquared, $matY, $actual);
			}
		}
		else {
			foreach ($data as $row) {
				$this->prepareDataSolo($row, $inputsLinear, $inputsMultiplication, $inputsSquared, $matY, $actual);
			}
		}

		return $this->createAndCompareModels($matY, $actual, $inputsLinear, $inputsMultiplication, $inputsSquared);
	}

	/**
	 * Recalculate a regression model for player's teammate deaths
	 *
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 * @see RegressionCalculator::calculateRegressionPrediction() To calculate a value from this model
	 */
	public function updateDeathsOwnModel(?AbstractMode $mode = null) : array {
		$infoKey = 'deathsOwnModel'.(isset($mode) && !$mode->rankable ? $mode->id : '');
		$model = $this->calculateDeathOwnRegression($mode);
		try {
			Info::set($infoKey, $model);
		} catch (Exception) {
			// Failed to save the value - ignore
		}
		return $model;
	}

	/**
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 */
	public function calculateDeathOwnRegression(?AbstractMode $mode = null) : array {
		$query = DB::select('vEvo5RegressionData', 'AVG(deaths_own) as value, enemies, teammates, game_length')
							 ->where('game_type = %s', GameModeType::TEAM->value)
							 ->groupBy('id_game, enemies, teammates');

		$this->filterQueryByMode($mode, $query);

		$data = $query->fetchAll();

		echo 'DataPoints: '.count($data).PHP_EOL;
		if (count($data) < 10) {
			throw new InsuficientRegressionDataException();
		}

		$inputsLinear = [];
		$inputsMultiplication = [];
		$inputsSquared = [];
		$matY = [];
		$actual = [];
		foreach ($data as $row) {
			$this->prepareDataTeam($row, $inputsLinear, $inputsMultiplication, $inputsSquared, $matY, $actual);
		}

		return $this->createAndCompareModels($matY, $actual, $inputsLinear, $inputsMultiplication, $inputsSquared);
	}

	/**
	 * Recalculate a regression model for player's teammate hits
	 *
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 * @see RegressionCalculator::calculateRegressionPrediction() To calculate a value from this model
	 */
	public function updateHitsOwnModel(?AbstractMode $mode = null) : array {
		$infoKey = 'hitsOwnModel'.(isset($mode) && !$mode->rankable ? $mode->id : '');
		$model = $this->calculateHitOwnRegression($mode);
		try {
			Info::set($infoKey, $model);
		} catch (Exception) {
			// Failed to save the value - ignore
		}
		return $model;
	}

	/**
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 */
	public function calculateHitOwnRegression(?AbstractMode $mode = null) : array {
		$query = DB::select('vEvo5RegressionData', 'AVG(hits_own) as [value], enemies, teammates, game_length')
							 ->where('game_type = %s', GameModeType::TEAM->value)
							 ->groupBy('id_game, enemies, teammates');

		$this->filterQueryByMode($mode, $query);

		$data = $query->fetchAll();

		echo 'DataPoints: '.count($data).PHP_EOL;
		if (count($data) < 10) {
			throw new InsuficientRegressionDataException();
		}

		$inputsLinear = [];
		$inputsMultiplication = [];
		$inputsSquared = [];
		$matY = [];
		$actual = [];
		foreach ($data as $row) {
			$this->prepareDataTeam($row, $inputsLinear, $inputsMultiplication, $inputsSquared, $matY, $actual);
		}

		return $this->createAndCompareModels($matY, $actual, $inputsLinear, $inputsMultiplication, $inputsSquared);
	}

	/**
	 *
	 * Recalculate a regression model for player's hits based on the game type
	 *
	 * @param GameModeType      $type
	 * @param AbstractMode|null $mode
	 *
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 * @see RegressionCalculator::calculateRegressionPrediction() To calculate a value from this model
	 */
	public function updateHitsModel(GameModeType $type, ?AbstractMode $mode = null) : array {
		$infoKey = 'hitModel'.$type->value.(isset($mode) && !$mode->rankable ? $mode->id : '');
		$model = $this->calculateHitRegression($type, $mode);
		try {
			Info::set($infoKey, $model);
		} catch (Exception) {
			// Failed to save the value - ignore
		}
		return $model;
	}

	/**
	 * Get a regression model for player's teammate hits
	 *
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 * @see RegressionCalculator::calculateRegressionPrediction() To calculate a value from this model
	 */
	public function getHitsOwnModel(?AbstractMode $mode = null) : array {
		$infoKey = 'hitsOwnModel'.(isset($mode) && !$mode->rankable ? $mode->id : '');

		/** @var numeric[]|null $model */
		$model = Info::get($infoKey);
		if (empty($model)) {
			$model = $this->calculateHitOwnRegression($mode);
			try {
				Info::set($infoKey, $model);
			} catch (Exception) {
				// Failed to save the value - ignore
			}
		}
		return $model;
	}

	/**
	 * Get a regression mode for player's teammate deaths
	 *
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 * @see RegressionCalculator::calculateRegressionPrediction() To calculate a value from this model
	 */
	public function getDeathsOwnModel(?AbstractMode $mode = null) : array {
		$infoKey = 'deathsOwnModel'.(isset($mode) && !$mode->rankable ? $mode->id : '');

		/** @var numeric[]|null $model */
		$model = Info::get($infoKey);
		if (empty($model)) {
			$model = $this->calculateDeathOwnRegression($mode);
			try {
				Info::set($infoKey, $model);
			} catch (Exception) {
				// Failed to save the value - ignore
			}
		}
		return $model;
	}
}