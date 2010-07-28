<?php

require_once('MongoQueue.php');

class MongoFunctor
{
	protected $when;
	protected $className = null;
	
	public function __construct($className, $when)
	{
		$this->className = $className;
		$this->when = $when;
	}
	public function __call($method, $args)
	{
		MongoQueue::push($this->className, $method, $args, $this->when);
	}
}

