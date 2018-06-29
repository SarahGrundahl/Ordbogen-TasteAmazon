<?php
namespace includes\rpc;

require_once('RpcService.class.php');

/**
  * Static RPC service interface
  *
  * The StaticRpcService interface is a convinience class for situations where
  * only one RpcService is relevant (most cases).
  * Internally it maintains an actual RpcService object which can be fetched with
  * getInstance().
  *
  * Basic order of operations is as follows:
  * - Optionally set special flags with setFlags
  * - Publish methods to make available with publish-functions
  * - Call handleRequest
  *
  * When using StaticRpcService, no data should be written with echo and similar,
  * since this may interfere with the StaticRpcService
  */
interface StaticRpcService
{
	/**
	  * Publish function
	  * @param string $functionName Function to publish
	  * @param string $publicName Public name of function
	  * @return bool Returns TRUE if function was published
	  */
	public static function publishFunction ($functionName, $publicName=NULL);

	/**
	  * Publish method
	  * @param mixed $classOrObject Either a class name or an object
	  * @param string $method Name of method to publish
	  * @param string $publicName Public name of method
	  * @return bool Returns TRUE if method was published
	  */
	public static function publishMethod ($classOrObject, $method, $publicName=NULL);

	/**
	  * Publish class
	  *
	  * PublishClass will publish all public static methods on the
	  * class. Methods starting with _ are omitted
	  * @param string $className Name of class to publish
	  * @param string $prefix Optional prefix to prepend the method names
	  * @return bool Returns TRUE if the class was published
	  */
	public static function publishClass ($className, $prefix='');

	/**
	  * Publish object
	  *
	  * PublishObject will publish all public methods on the
	  * object, both static and non-static. Methods starting
	  * with _ are omitted.
	  * @param object $object Object to publish
	  * @return bool Returns TRUE if the object was published
	  */
	public static function publishObject ($object);

	/**
	  * Get service property
	  * @param int $propertyId Property id
	  * @return mixed Property value
	  */
	public static function getProperty ($propertyId);

	/**
	  * Set service property
	  * @param int $propertyId Property id
	  * @param mixed $value Property value
	  */
	public static function setProperty ($propertyId, $value);

	/**
	  * Get a list of published method names
	  * @return string[] List of method names
	  */
	public static function getMethods ();

	/**
	  * Check if a method name has been published
	  * @param string $methodName Public method name
	  * @return bool TRUE if the method exists
	  */
	public static function hasMethod ($methodName);

	/**
	  * Execute RPC service
	  *
	  * This method will parse input data from the webserver, parsing
	  * the request, executing the proper published method and eventually
	  * encode a proper response.
	  *
	  * Ideally errors and exceptions are also handled within this method.
	  */
	public static function handleRequest ();

	/**
	  * Get RpcService instance
	  *
	  * Use this to get the internal RpcService instance
	  * @return RpcService Internal RpcService instance
	  */
	public static function getInstance ();
}

?>
