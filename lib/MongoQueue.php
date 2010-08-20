<?php

abstract class MongoQueue
{
	public static $database = null;
	public static $connection = null;
	public static $environment = null;
	public static $context = null;
	public static $collectionName = 'mongo_queue';

	protected static $environmentLoaded = false;

	public static function push($className, $methodName, $parameters, $when, $batch = false)
	{
		if (!$batch)
		{
			$collection = self::getCollection();
			$collection->save(array('object_class' => $className, 'object_method' => $methodName, 'parameters' => $parameters, 'when' => $when));
		}
		else
		{	
			$db = self::getDatabase();
			$collection = self::getCollection();
			
			$job = $db->command(
				array(
					'findandmodify' => self::$collectionName,
					'query' => array('object_class' => $className, 'object_method' => $methodName, 'parameters' => $parameters, 'locked' => null),
					'update' => array('$inc' => array('batch' => 1)), 
					'upsert' => true,
					'new' => true
				));
			
			if ($job['ok'])
			{
				$job = $job['value'];

				if (!isset($job['when']))
				{
					$job['when'] = $when;
					$collection->save($job);
				}
			}
		}
	}

	public static function count($class_name = null)
	{
		$collection = self::getCollection();
		
		$query = array('when' => array('$lte' => time()), 'locked' => null);
	
		if ($class_name)
			$query['object_class'] = $class_name;
	
		return $collection->count($query);
	}

	public static function run($class_name = null)
	{
		$db = self::getDatabase();
		$environment = self::initializeEnvironment();

		$query = array('when' => array('$lte' => time()), 'locked' => null);
	
		if ($class_name)
			$query['object_class'] = $class_name;
	
		$job = $db->command(
			array(
				"findandmodify" => self::$collectionName,
				"query" => $query,
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
		if (self::$environment && !self::$environmentLoaded)
		{
			$environment = self::$environment;
			
			spl_autoload_register(
				function ($className) use ($environment) 
				{
					require_once($environment . '/' . $className . '.php');
				}
			);

			self::$environmentLoaded = true;
		}
	}
}
