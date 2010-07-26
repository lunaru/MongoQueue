<?php

abstract class MongoQueue
{
	public static $database = null;
	public static $connection = null;
	public static $environment = null;
	public static $context = null;
	public static $collectionName = 'mongo_queue';

	public static function push($className, $methodName, $parameters, $when)
	{
		$collection = self::getCollection();
		$collection->save(array('object_class' => $className, 'object_method' => $methodName, 'parameters' => $parameters, 'when' => $when));
	}

	public static function run()
	{
		$db = self::getDatabase();
		$environment = self::initializeEnvironment();
	
		$job = $db->command(
			array(
				"findandmodify" => self::$collectionName,
				"query" => array('when' => array('$lte' => time()), 'locked' => null),
				"sort" => array('when' => 1),
				"update" => array('$set' => array('locked' => true, 'locked_at' => time()))
			));
		
		if ($job['ok'])
		{
			$jobRecord = $job['value'];
			$jobID = $jobRecord['_id'];

			// run the job
			if (isset($jobRecord['object_class']))
			{
				$className = $jobRecord['object_class'];
				$method = isset($jobRecord['object_method']) ? $jobRecord['object_method'] : 'perform';
				$parameters = isset($jobRecord['parameters']) ? $jobRecord['parameters'] : array();
				
				if (self::$context)
				{
					foreach (self::$context as $key => $value)
					{
						if (property_exists($className, $key))
							$className::$$key = $value;
					}
				}
				
				call_user_func_array(array(new $className, $method), $parameters); 
			}

			// remove the job from the queue
			self::getCollection()->remove(array('_id' => $jobID));

			return true;
		}

		return false;
	}

	protected static function getDatabase()
	{
		$collection_name = self::$collectionName;

		if (self::$database == null)
			throw new Exception("BaseMongoRecord::database must be initialized to a proper database string");

		if (self::$connection == null)
			throw new Exception("BaseMongoRecord::connection must be initialized to a valid Mongo object");
		
		if (!self::$connection->connected)
			self::$connection->connect();

		return self::$connection->selectDB(self::$database);
	}
	
	protected static function getCollection()
	{
		$collection_name = self::$collectionName;

		if (self::$database == null)
			throw new Exception("BaseMongoRecord::database must be initialized to a proper database string");

		if (self::$connection == null)
			throw new Exception("BaseMongoRecord::connection must be initialized to a valid Mongo object");
		
		if (!self::$connection->connected)
			self::$connection->connect();

		return self::$connection->selectCollection(self::$database, $collection_name);
	}

	protected static function initializeEnvironment()
	{
		if (self::$environment)
		{
			$environment = self::$environment;
			spl_autoload_register(
				function ($className) use ($environment) 
				{
					require_once($environment . '/' . $className . '.php');
				}
			);
		}
	}
}
