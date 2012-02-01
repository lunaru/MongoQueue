<?php

require_once('MongoQueue.php');

class MongoFunctor
{
	protected $when;
	protected $className = null;
	protected $batch;
	protected $priority;

	public function __construct($className, $when, $batch, $priority)
	{
		$this->className = $className;
		$this->when = $when;
		$this->batch = $batch;
		$this->priority = $priority;
	}

	public function __call($method, $args)
	{
		MongoQueue::push($this->className, $method, $args, $this->when, $this->batch, $this->priority);
	}
}

