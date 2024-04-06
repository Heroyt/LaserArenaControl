<?php

namespace App\Tasks;

use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;

interface TaskDispatcherInterface
{

	public static function getDiName() : string;

	public function process(ReceivedTaskInterface $task) : void;

}