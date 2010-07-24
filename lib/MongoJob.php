<?php

require_once('MongoQueue.php');
require_once('MongoFunctor.php');

abstract class MongoJob
{
	public static function later($delay = 0)
	{
		$className = get_called_class();
		return new MongoFunctor($className, $delay);
	}
}
