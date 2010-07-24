<?php

require_once('MongoQueue.php');

class MongoFunctor
{
	protected $delay = 0;
	protected $className = null;
	
	public function __construct($className, $delay)
	{
		$this->className = $className;
		$this->delay = $delay;
	}
	public function __call($method, $args)
	{
		MongoQueue::push($this->className, $method, $args, time() + $this->delay);
	}
}

