<?php

require_once('MongoQueue.php');
require_once('MongoFunctor.php');

abstract class MongoJob
{
	public static function later($delay = 0)
	{
		return $this->at(time() + $delay);
	}

	public static function at($time = null)
	{
		if (!$time) $time = time();
		$className = get_called_class();
		return new MongoFunctor($className, $time);
	}
}
