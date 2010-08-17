<?php

require_once('MongoQueue.php');

class MongoFunctor
{
	protected $when;
	protected $className = null;
	
	public function __construct($className, $when, $batch)
	{
		$this->className = $className;
		$this->when = $when;
		$this->batch = $batch;
	}
	public function __call($method, $args)
	{
		MongoQueue::push($this->className, $method, $args, $this->when, $this->batch);
	}
}

