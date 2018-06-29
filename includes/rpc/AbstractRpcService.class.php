<?php
namespace includes\rpc;

require_once('RpcService.class.php');
/**
  * Abstract RPC service
  *
  * The Abstract RPC service implements all the publishing funcitonality of an RpcService
  * and invoking of functions. An RPC implementor should extend on this class and only implement
  * handleResponse.
  *
  * When a method should be invoked, handleResponse should call the protected method invoke.
  *
  * The following properties are supported by the abstract RPC service:
  * - PROPERTY_ENCODING
  * - PROPERTY_ALLOW_EXCEPTIONS
  * - PROPERTY_INVOKE_PRE_HOOK
  *
  * PROPERTY_ENCODING (string) defines the encoding of the PHP script using the RPC service. If the
  * encoding is set to NULL, mb_internal_encoding() is used instead. The property is automatically
  * supported by all services extending AbstractRpcService. Default is NULL.
  *
  * PROPERTY_ALLOW_EXCEPTIONS (bool) defines whether PHP exceptions should be converted into RPC
  * exceptions. This property is not necessarily supported by all RPC protocols. Also, beware
  * that ALL exceptions are passed through, so only use this in internal envirionments, or if
  * you have complete control of the exceptions thrown (for instance by catching all exceptions
  * at one point). Default is FALSE.
  *
  * PROPERTY_INVOKE_PRE_HOOK (callback) defines a callback method to be called just before the
  * actual published method. The method takes the following signature:
  *
  *  void preHookCallback(AbstractRpcService $service, string $methodName, array $arguments)
  *
  * The primary purpose of the pre hook is logging and access control. Throwing an exception
  * from the callback will cause the published method not to be called. Beware though, that
  * exceptions thrown by the callback is affected by the PROPERTY_ALLOW_EXCEPTIONS property.
  * Default is NULL.
  *
  */
abstract class AbstractRpcService implements RpcService
{
	/**
	  * String encoding.
	  * If set to NULL, the encoding from mb_internal_encoding is used
	  */
	const PROPERTY_ENCODING = 0;

	/**
	  * bool Allow exceptions to pass through
	  */
	const PROPERTY_ALLOW_EXCEPTIONS = 1;

	/**
	 * callback Called before every invoke
	 */
	const PROPERTY_INVOKE_PRE_HOOK = 2;

	/**
	  * @var mixed[] Current properties
	  */
	private $properties = array(
			self::PROPERTY_ENCODING => NULL,
			self::PROPERTY_ALLOW_EXCEPTIONS => FALSE,
			self::PROPERTY_INVOKE_PRE_HOOK => NULL
	);

	/**
	  * @var array A list of published methods and their respective callbacks
	  */
	private $methods = array();

	/**
	  * @var string RPC encoding
	  */
	private $rpcEncoding;

	/**
	  * Basic constructor
	  *
	  * Classes extending this class must call this method from its constructor.
	  * The call is necessary to inform which internal character encoding the RPC
	  * service class is expecting.
	  *
	  * The AbstractRpcService will automatically convert values to/from the internal
	  * character set based on the PROPERTY_ALLOW_EXCEPTIONS property.
	  * @param string $rpcEncoding RPC encoding
	  */
	protected function __construct ($rpcEncoding = 'UTF-8')
	{
		$this->rpcEncoding = $rpcEncoding;
	}

	/**
	  * Internal method for publishing a function with a callback function
	  * @param callback $callback Callback to publish
	  * @param string $publicName Public name of method
	  * @return bool Returns TRUE if the method was succesfully published
	  */
	private function publishCallback ($callback, $publicName)
	{
		if (array_key_exists($publicName, $this->methods))
		{
			trigger_error('Another method with the name '.$publicName.' already exists', E_USER_WARNING);
			return FALSE;
		}
		else
		{
			$this->methods[$publicName] = $callback;
			return TRUE;
		}
	}

	/**
	  * Publish function
	  * @param string $functionName Function to publish
	  * @param string $publicName Public name of function
	  * @return bool Returns TRUE if function was published
	  */
	public function publishFunction ($functionName, $publicName=NULL)
	{
		return $this->publishCallback($functionName, $publicName == NULL ? $functionName : $publicName);
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
		return $this->publishCallback(array($classOrObject, $method), $publicName === NULL ? $method : $publicName);
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
		if (!class_exists($className))
			return FALSE;

		$ret = TRUE;

		$reflection = new \ReflectionClass($className);
		foreach ($reflection->getMethods(\ReflectionMethod::IS_STATIC | \ReflectionMethod::IS_PUBLIC) as $method)
		{
			if ($method->isStatic() && $method->isPublic())
			{
				$name = $method->getName();
				if (strncmp($name, '_', 1) != 0)
				{
					if (!$this->publishMethod($className, $name, $prefix.$name))
						$ret = FALSE;
				}
			}
		}

		return $ret;
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
		$ret = TRUE;

		$reflection = new \ReflectionObject($object);
		foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method)
		{
			$name = $method->getName();
			if (strncmp($name, '_', 1) != 0)
			{
				if (!$this->publishMethod($object, $name, $prefix.$name))
					$ret = FALSE;
			}
		}

		return $ret;
	}

	/**
	  * Get service property
	  * @param int $propertyId Property id
	  * @return mixed Property value
	  */
	public function getProperty ($propertyId)
	{
		return (array_key_exists($propertyId, $this->properties) ? $this->properties[$propertyId] : NULL);
	}

	/**
	  * Set service property
	  * @param int $propertyId Property id
	  * @param mixed $value Property value
	  */
	public function setProperty ($propertyId, $value)
	{
		$this->properties[$propertyId] = $value;
	}

	/**
	  * Get a list of published method names
	  * @return string[] List of method names
	  */
	public function getMethods ()
	{
		return array_keys($this->methods);
	}

	/**
	 * Returns method callback. Used by invokeHook
	 * @return callback
	 */
	public function getMethodCallback($methodName)
	{
		return $this->methods[$methodName];
	}

	/**
	  * Check if a method name has been published
	  * @param string $methodName Public method name
	  * @return bool TRUE if the method exists
	  */
	public function hasMethod ($methodName)
	{
		return array_key_exists($methodName, $this->methods);
	}

	/**
	  * PHP error handler
	  *
	  * The PHP error handler is really an internal method used by
	  * invoke to catch errors.
	  *
	  * E_USER_ERROR and E_RECOVERABLE_ERROR will trigger an ErrorException
	  *
	  * All recognized error types will trigger a log entry
	  * @param int $errno Level of the error raised
	  * @param string $errstr Error message
	  * @param string $errfile Filename that the error was raised in
	  * @param int $errline Line number the error was raised in
	  * @return bool Returns TRUE if the error was handled
	  * @throws \ErrorException if the error should be handled
	  */
	public static function errorHandler ($errno, $errstr, $errfile, $errline)
	{
		if ((error_reporting() & $errno) != $errno)
			return FALSE;

		switch ($errno)
		{
			case E_WARNING:
			case E_CORE_WARNING:
			case E_COMPILE_WARNING:
			case E_USER_WARNING:
				$type = 'PHP Warning';
				$fatal = FALSE;
				break;

			case E_NOTICE:
			case E_USER_NOTICE:
				$type = 'PHP Notice';
				$fatal = FALSE;
				break;

			case E_USER_ERROR:
			case E_RECOVERABLE_ERROR:
				$type = 'PHP Error';
				$fatal = TRUE;
				break;

			default:
				return FALSE;
		}

		error_log($type.': '.$errstr.' in '.$errfile.' on line '.$errline);

		if ($fatal)
			throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);

		return TRUE;
	}

	/**
	  * Get reflection for public method
	  *
	  * Use this method internally to fetch information about the public method.
	  * This is useful for RPC protocols with introspection
	  * @param string $methodName Name of method
	  * @return \ReflectionFunctionAbstract A reflection object or NULL if the method does not exist
	  */
	protected function getMethodReflection ($methodName)
	{
		if (array_key_exists($methodName, $this->methods))
		{
			$callback = $this->methods[$methodName];
			if (is_array($callback))
			{
				if (is_object($callback[0]))
				{
					$reflection = new \ReflectionObject($callback[0]);
					return $reflection->getMethod($callback[1]);
				}
				else
				{
					$reflection = new \ReflectionClass($callback[0]);
					return $reflection->getMethod($callback[1]);
				}
			}
			else
				return new \ReflectionFunction($callback);
		}
		else
			return NULL;
	}

	/**
	  * Convert a value to internal encoding
	  *
	  * A RPC protocol usually use a fixed encoding, while PHP may be set up to use
	  * a different encoding. This method converts a variable to the proper encoding
	  * if it is a string or contains strings.
	  *
	  * The internal encoding is taken from mb_internal_encoding and the RPC encoding
	  * is defined by the PROPERTY_ENCODING property (Default UTF-8).
	  *
	  * Objects will be converted into arrays
	  * @param mixed $value Value to convert
	  * @parm string $rpcEncoding RPC Encoding. If NULL the default is used
	  * @return mixed Converted value
	  */
	protected function convertFromRpcEncoding ($value, $rpcEncoding=NULL)
	{
		if (is_string($value))
		{
			$encoding = $this->getProperty(self::PROPERTY_ENCODING);
			if ($encoding === NULL)
				$encoding = mb_internal_encoding();
			if ($rpcEncoding === NULL)
				$rpcEncoding = $this->rpcEncoding;
			return mb_convert_encoding($value, $encoding, $rpcEncoding);
		}
		else if (is_array($value))
		{
			foreach ($value as $name => $subValue)
			{
				$value[$this->convertFromRpcEncoding($name, $rpcEncoding)] = $this->convertFromRpcEncoding($subValue, $rpcEncoding);
			}
			return $value;
		}
		else if (is_object($value))
		{
			$res = array();
			foreach (get_object_vars($value) as $name => $subValue)
			{
				$res[$this->convertFromRpcEncoding($name, $rpcEncoding)] = $this->convertFromRpcEncoding($subValue, $rpcEncoding);
			}
			return $res;
		}
		else
			return $value;
	}

	/**
	  * Convert a value to RPC encoding
	  *
	  * A RPC protocol usually use a fixed encoding, while PHP may be set up to use
	  * a different encoding. This method converts a variable from the proper encoding
	  * if it is a string or contains strings.
	  *
	  * The internal encoding is taken from mb_internal_encoding and the RPC encoding
	  * is defined by the PROPERTY_ENCODING property (Default UTF-8)
	  *
	  * Objects will be converted into arrays
	  * @param mixed $value Value to convert
	  * @return mixed Converted value
	  */
	protected function convertToRpcEncoding ($value)
	{
		if (is_string($value))
		{
			$encoding = $this->getProperty(self::PROPERTY_ENCODING);
			if ($encoding === NULL)
				$encoding = mb_internal_encoding();
			return mb_convert_encoding($value, $this->rpcEncoding, $encoding);
		}
		else if (is_array($value))
		{
			foreach ($value as $name => $subValue)
			{
				$value[$this->convertToRpcEncoding($name)] = $this->convertToRpcEncoding($subValue);
			}
			return $value;
		}
		else if (is_object($value))
		{
			$res = array();
			foreach (get_object_vars($value) as $name => $subValue)
			{
				$res[$this->convertToRpcEncoding($name)] = $this->convertToRpcEncoding($subValue);
			}
			return $res;
		}
		else
			return $value;
	}

	/**
	  * Invoke a published method
	  *
	  * Invoke should be used handleRequest when a method should be called.
	  * Invoke will automatically handle optional parameters and supports
	  * named parameters.
	  * @param string $methodName Name of method to invoke
	  * @param array $arguments Array of arguments
	  * @return mixed Return value from method
	  * @throws \Exception if the invoked method throws an exception
	  * @throws \ErrorException if the invoke method caused a catachable fatal error
	  */
	protected function invoke ($methodName, array $arguments = array())
	{
		$args = array();
		$reflection = $this->getMethodReflection($methodName);

		if ($reflection !== NULL)
		{
			foreach ($reflection->getParameters() as $idx => $parameter)
			{
				if (array_key_exists($parameter->getName(), $arguments))
					$args[] = $arguments[$parameter->getName()];
				else if (array_key_exists($idx, $arguments))
					$args[] = $arguments[$idx];
				else if ($parameter->isDefaultValueAvailable())
					$args[] = $parameter->getDefaultValue();
				else
					$args[] = NULL;
			}
		}

		$displayErrors = ini_set('display_errors', 'off');
		set_error_handler(array(__CLASS__, 'errorHandler'));
		try
		{
			$this->callInvokePreHook($methodName, $arguments);

			$ret = call_user_func_array($this->methods[$methodName], $args);
			restore_error_handler();
			ini_set('display_errors', $displayErrors);
		}
		catch (\Exception $exception)
		{
			if (!$this->getProperty(self::PROPERTY_ALLOW_EXCEPTIONS))
				error_log('Fatal error: Uncaught exception: '.$exception->__toString());

			restore_error_handler();
			ini_set('display_errors', $displayErrors);

			throw $exception;
		}

		return $ret;
	}

	/**
	 * Calls the invokeHook callback if possible. Called from invoke()
	 * @param string $methodName
	 * @param array $arguments
	 */
	protected function callInvokePreHook($methodName, $arguments)
	{
		$callback = $this->properties[self::PROPERTY_INVOKE_PRE_HOOK];

		if (!is_callable($callback))
		{
			if ($callback !== NULL)
			{
				trigger_error("Rpc invoke pre hook not called", E_USER_WARNING);
			}

			return;
		}

		call_user_func($callback, $this, $methodName, $arguments);
	}
}

?>
