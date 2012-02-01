<?php

abstract class MongoQueue
{
	public static $database = null;
	public static $connection = null;
	public static $environment = null;
	public static $context = null;
	public static $collectionName = 'mongo_queue';

	protected static $environmentLoaded = false;

	public static function push($className, $methodName, $parameters, $when, $batch = false, $priority = null)
	{
		if (!$batch)
		{
			$collection = self::getCollection();
			$collection->save(array(
				'object_class' => $className, 
				'object_method' => $methodName, 
				'parameters' => $parameters, 
				'when' => $when,
				'priority' => $priority,
				'locked' => null,
				'locked_at' => null,
				'batch' => 1));
		}
		else
		{	
			$db = self::getDatabase(array('object_class' => $className, 'object_method' => $methodName, 'parameters' => $parameters));
			$collection = $db->selectCollection(self::$collectionName);
			
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
				$touched = false;

				// take the lower 'when'
				if (!isset($job['when']) || $job['when'] > $when)
				{
					$job['when'] = $when;
					$touched = true;
				}

				// take the higher 'priority'
				if (!isset($job['priority']) || ($priority !== null && $job['priority'] < $priority))
				{
					$job['priority'] = $priority;
					$touched = true;
				}

				if ($touched)
					$collection->save($job);
			}
		}
	}

	public static function hasRunnable($class_name = null, $method_name = null)
	{
		$collection = self::getCollection();
		
		$query = array('$or' => array(array('when' => array('$lte' => time())), array('when' => null)), 'locked' => null);
		$fields = array('_id' => 0, 'when' => 1);

		if ($class_name)
			$query['object_class'] = $class_name;

		if ($method_name)
			$query['object_method'] = $method_name;

		return ($collection->findOne($query, $fields) != null);
	}

	public static function count()
	{
		$collection = self::getCollection();

		$query = array('$or' => array(array('when' => array('$lte' => time())), array('when' => null)), 'locked' => null);
		return $collection->count($query);
	}

	public static function run($class_name = null, $method_name = null, $prioritize = true)
	{
		$db = self::getDatabase();
		$environment = self::initializeEnvironment();

		$query = array('$or' => array(array('when' => array('$lte' => time())), array('when' => null)), 'locked' => null);
	
		if ($class_name)
			$query['object_class'] = $class_name;
	
		if ($method_name)
			$query['object_method'] = $method_name;

		$sort = array('when' => 1);

		if ($prioritize)
			$sort = array('priority' => -1, 'when' => 1);

		$job = $db->command(
			array(
				"findandmodify" => self::$collectionName,
				"query" => $query,
				"sort" => $sort,
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
			$db->selectCollection(self::$collectionName)->remove(array('_id' => $jobID));

			return true;
		}

		return false;
	}

	protected static function getConnection($hint = null)
	{
		if (is_array(self::$connection))
		{
			$count = count(self::$connection);
	
			if (!$hint)
				$hint = md5(rand());
			
			// convert the hint into an index
			$hint = abs(crc32(serialize($hint)) % $count);
			
			return self::$connection[$hint];
		}
		else
		{
			return self::$connection;
		}
	}

	protected static function getDatabase($hint = null)
	{
		$collection_name = self::$collectionName;
		$connection = self::getConnection($hint);

		if (self::$database == null)
			throw new Exception("BaseMongoRecord::database must be initialized to a proper database string");

		if ($connection == null)
			throw new Exception("BaseMongoRecord::connection must be initialized to a valid Mongo object");
		
		if (!$connection->connected)
			$connection->connect();

		return $connection->selectDB(self::$database);
	}
	
	protected static function getCollection($hint = null)
	{
		$collection_name = self::$collectionName;
		$connection = self::getConnection($hint);

		if (self::$database == null)
			throw new Exception("BaseMongoRecord::database must be initialized to a proper database string");

		if ($connection == null)
			throw new Exception("BaseMongoRecord::connection must be initialized to a valid Mongo object");
		
		
		if (!$connection->connected)
			$connection->connect();

		return $connection->selectCollection(self::$database, $collection_name);
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
