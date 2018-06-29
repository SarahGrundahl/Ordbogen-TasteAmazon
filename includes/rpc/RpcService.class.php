<?php
namespace includes\rpc;

/**
  * Main RPC Service interface
  *
  * The RpcService interface represents an RPC service of any type.
  *
  * The basic order of operations is as follows:
  * - Construct RpcService object
  * - Optionally set special flags with setFlags
  * - Publish methods to make available with publish-functions
  * - Call handleRequest
  *
  * For convenience you can also use the StaticRpcService interface
  * When using a RpcService, no data should be written with echo and similar, since
  * this may interfere with the RpcService
  */
interface RpcService
{
	/**
	  * Publish function
	  * @param string $functionName Function to publish
	  * @param string $publicName Public name of function
	  * @return bool Returns TRUE if function was published
	  */
	public function publishFunction ($functionName, $publicName=NULL);

	/**
	  * Publish method
	  * @param mixed $classOrObject Either a class name or an object
	  * @param string $method Name of method to publish
	  * @param string $publicName Public name of method
	  * @return bool Returns TRUE if method was published
	  */
	public function publishMethod ($classOrObject, $method, $publicName=NULL);

	/**
	  * Publish class
	  *
	  * PublishClass will publish all public static methods on the
	  * class. Methods starting with _ are omitted
	  * @param string $className Name of class to publish
	  * @param string $prefix Optional prefix to prepend the method names
	  * @return bool Returns TRUE if the class was published
	  */
	public function publishClass ($className, $prefix='');

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
	public function publishObject ($object, $prefix='');

	/**
	  * Get service property
	  * @param int $propertyId Property id
	  * @return mixed Property value
	  */
	public function getProperty ($propertyId);

	/**
	  * Set service property
	  * @param int $propertyId Property id
	  * @param mixed $value Property value
	  */
	public function setProperty ($propertyId, $value);

	/**
	  * Get a list of published method names
	  * @return string[] List of method names
	  */
	public function getMethods ();

	/**
	  * Check if a method name has been published
	  * @param string $methodName Public method name
	  * @return bool TRUE if the method exists
	  */
	public function hasMethod ($methodName);

	/**
	  * Execute RPC service
	  *
	  * This method will parse input data from the webserver, parsing
	  * the request, executing the proper published method and eventually
	  * encode a proper response.
	  *
	  * Ideally errors and exceptions are also handled within this method.
	  */
	public function handleRequest ();
}

?>
