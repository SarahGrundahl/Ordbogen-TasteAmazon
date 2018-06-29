<?php
namespace includes\rpc;

require_once('RpcService.class.php');
require_once('JsonRpcService.class.php');
require_once('XmlRpcService.class.php');
require_once('SoapService.class.php');

/**
  * Dynamic RPC Service
  *
  * The dynamic RPC service is a convinience RPC service delivering the published
  * methods through several JSON-RPC, XML-RPC and SOAP simultaniously.
  *
  * The query parameter rpc will determine what kind of interface the service will
  * deliver at runtime.
  *
  * Beware that JsonRpcService::PROPERTY_ALLOW_ENUM is set to TRUE when using this class.
  * The rationale is that SoapService ALWAYS has introspection, so disabling it in
  * JsonRpcService doesn't add any security.
  */
class DynamicRpcService implements RpcService
{
	/**
	  * @var RpcService[]
	  */
	private $service;

	/**
	  * Construct new DynamicRpcService
	  *
	  * Exactly which type of interface is published is determined by the rpc
	  * query parameter when the object is constructed
	  */
	public function __construct ()
	{
		$rpc = '';
		if (array_key_exists('rpc', $_GET))
			$rpc = $_GET['rpc'];

		if ($rpc == 'xmlrpc')
			$this->service = new XmlRpcService();
		else if ($rpc == 'soap')
		{
			$soap = new SoapService();
			$location = $soap->getProperty(SoapService::PROPERTY_LOCATION).'?rpc=soap';
			$soap->setProperty(SoapService::PROPERTY_LOCATION, $location);

			$this->service = $soap;
		}
		else // ($rpc == 'jsonrpc')
		{
			$jsonrpc = new JsonRpcService();
			$jsonrpc->setProperty(JsonRpcService::PROPERTY_ALLOW_ENUM, TRUE);
			$this->service = $jsonrpc;
		}
	}

	/**
	  * Publish function
	  * @param string $functionName Function to publish
	  * @param string $publicName Public name of function
	  * @return bool Returns TRUE if function was published
	  */
	public function publishFunction ($functionName, $name=NULL)
	{
		return $this->service->publishFunction($functionName, $name);
	}

	/**
	  * Publish method
	  * @param mixed $classOrObject Either a class name or an object
	  * @param string $method Name of method to publish
	  * @param string $publicName Public name of method
	  * @return bool Returns TRUE if method was published
	  */
	public function publishMethod ($classOrObject, $method, $publicName=NULL)
	{
		return $this->service->publishMethod($classOrObject, $method, $publicName);
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
	public function publishClass ($className, $prefix='')
	{
		return $this->service->publishClass($className, $prefix);
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
	public function publishObject ($object, $prefix='')
	{
		return $this->service->publishObject($object, $prefix);
	}

	/**
	  * Get service property
	  * @param int $propertyId Property id
	  * @return mixed Property value
	  */
	public function getProperty ($propertyId)
	{
		return $this->service->getProperty($propertyId);
	}

	/**
	  * Set service property
	  * @param int $propertyId Property id
	  * @param mixed $value Property value
	  */
	public function setProperty ($propertyId, $value)
	{
		$this->service->setProperty($propertyId, $value);
	}

	/**
	  * Get a list of published method names
	  * @return string[] List of method names
	  */
	public function getMethods ()
	{
		return $this->service->getMethods();
	}

	/**
	  * Check if a method name has been published
	  * @param string $methodName Public method name
	  * @return bool TRUE if the method exists
	  */
	public function hasMethod ($methodName)
	{
		return $this->service->hasMethod($methodName);
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
	public function handleRequest ()
	{
		$this->service->handleRequest();
	}
}

?>
