<?php

require_once('MongoQueue.php');
require_once('MongoFunctor.php');

abstract class MongoJob
{
	public static function later($delay = 0, $batch = false, $priority = null)
	{
		return self::at(time() + $delay, $batch, $priority);
	}

	public static function at($time = null, $batch = false, $priority = null)
	{
		if ($time === null) $time = time();
		$className = get_called_class();
		return new MongoFunctor($className, $time, $batch, $priority);
	}

	public static function batchLater($delay = 0, $priority = null)
	{
		return self::later($delay, true, $priority);
	}

	public static function batchAt($time = null, $priority = null)
	{
		return self::at($time, true, $priority);
	}

	public static function run()
	{
		return MongoQueue::run(get_called_class());
	}

	public static function count()
	{
		return MongoQueue::count(get_called_class());
	}
}
