<?php
namespace includes\rpc;

require_once('StaticRpcService.class.php');

/**
  * Abstract static RPC service
  *
  * The Abstract static RPC service makes it easy to implement a StaticRpcService based
  * on an RpcService class.
  * The only method you need to implement is the getInstance() method, which should work
  * as a singleton constructor of the actual instance
  */
abstract class AbstractStaticRpcService implements StaticRpcService
{
	/**
	  * Publish function
	  * @param string $functionName Function to publish
	  * @param string $publicName Public name of function
	  * @return bool Returns TRUE if function was published
	  */
	public static function publishFunction ($functionName, $name=NULL)
	{
		return static::getInstance()->publishFunction($functionName, $name);
	}

	/**
	  * Publish method
	  * @param mixed $classOrObject Either a class name or an object
	  * @param string $method Name of method to publish
	  * @param string $publicName Public name of method
	  * @return bool Returns TRUE if method was published
	  */
	public static function publishMethod ($classOrObject, $method, $name=NULL)
	{
		return static::getInstance()->publishMethod($classOrObject, $method, $name);
	}

	/**
	  * Publish class
	  *
	  * PublishClass will publish all public static methods on the
	  * class. Methods starting with _ are omitted
	  * @param string $className Name of class to publish
	  * @param string $prefix Optional prefix to prepend the method names
	  * @return bool Returns TRUE if the class was published
	  */
	public static function publishClass ($className, $prefix='')
	{
		return static::getInstance()->publishClass($className, $prefix);
	}

	/**
	  * Publish object
	  *
	  * PublishObject will publish all public methods on the
	  * object, both static and non-static. Methods starting
	  * with _ are omitted.
	  * @param object $object Object to publish
	  * @param string $prefix Optional prefix to prepend the method names
	  * @return bool Returns TRUE if the object was published
	  */
	public static function publishObject ($object, $prefix='')
	{
		return static::getInstance()->publishObject($object, $prefix);
	}

	/**
	  * Get service property
	  * @param int $propertyId Property id
	  * @return mixed Property value
	  */
	public static function getProperty ($propertyId)
	{
		return static::getInstance()->getProperty($propertyId);
	}

	/**
	  * Set service property
	  * @param int $propertyId Property id
	  * @param mixed $value Property value
	  */
	public static function setProperty ($propertyId, $value)
	{
		static::getInstance()->setProperty($propertyId, $value);
	}

	/**
	  * Get a list of published method names
	  * @return string[] List of method names
	  */
	public static function getMethods ()
	{
		return self::getInstance()->getMethods();
	}

	/**
	  * Check if a method name has been published
	  * @param string $methodName Public method name
	  * @return bool TRUE if the method exists
	  */
	public static function hasMethod ($methodName)
	{
		return self::getInstance()->hasMethod($methodName);
	}

	/**
	  * Execute RPC service
	  *
	  * This method will parse input data from the webserver, parsing
	  * the request, executing the proper published method and eventually
	  * encode a proper response.
	  *
	  * Ideally errors and exceptions are also handled within this method.
	  */
	public static function handleRequest ()
	{
		static::getInstance()->handleRequest();
	}
}

?>
