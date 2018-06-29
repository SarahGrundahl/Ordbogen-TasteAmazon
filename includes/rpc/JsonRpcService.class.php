<?php
namespace includes\rpc;

require_once('AbstractRpcService.class.php');
require_once('RpcResponse.class.php');
require_once('DummyRpcResponseWriter.class.php');
require_once('NullRpcResponseWriter.class.php');
require_once('JsonRpcResponseWriter.class.php');

/**
  * JSON-RPC Service
  *
  * The following JSON-RPC specifications are followed:
  * - 1.0 (http://json-rpc.org/wiki/specification)
  * - 1.1 Working Draft (http://json-rpc.org/wd/JSON-RPC-1-1-WD-20060807.html)
  * - 1.1 Alternative specification
  * - 2.0 (http://www.jsonrpc.org/spec.html)
  *
  * The following properties are supported by the JsonRpcService:
  * - AbstractRpcService::PROPERTY_ENCODING
  * - AbstractRpcService::PROPERTY_ALLOW_EXCEPTIONS
  * - AbstractRpcService::PROPERTY_INVOKE_PRE_HOOK
  * - JsonRpcService::PROPERTY_ENABLE_EXTRA_METHODS
  * - JsonRpcService::PROPERTY_UUID
  * - JsonRpcService::PROPERTY_ALLOW_ENUM
  *
  * PROPERTY_ENABLE_EXTRA_METHODS (bool) adds additional methods defined by JSON-RPC 1.1 WD and ALT:
  * - system.listMethods (ALT)
  * - system.methodSignature (ALT)
  * - system.methodHelp (ALT)
  * - system.echo (ALT)
  * - system.multicall (ALT)
  * - system.describe (WD)
  * Default is TRUE
  *
  * PROPERTY_UUID (string) defines the UUID used in system.describe. (Default: '00000000-0000-0000-0000-0000000000')
  *
  * PROPERTY_ALLOW_ENUM (bool) specify whether system.listMethods and system.describe is allowed to list the
  * published methods. Default is FALSE
  *
  * Beware that multicalls doesn't support on-the-fly output (DummyRpcResponseWriter is used for that)
  */
class JsonRpcService extends AbstractRpcService
{
	const PROPERTY_ENABLE_EXTRA_METHODS = 100;
	const PROPERTY_UUID = 101;
	const PROPERTY_ALLOW_ENUM = 102;

	const ERROR_PARSE_ERROR = -32700;
	const ERROR_INVALID_REQUEST = -32600;
	const ERROR_METHOD_NOT_FOUND = -32601;
	const ERROR_INVALID_PARAMS = -32602;
	const ERROR_INTERNAL_ERROR = -32603;

	/**
	  * @var int
	  */
	private $version = '1.0';

	/**
	  * @var mixed
	  */
	private $id = NULL;

	/**
	  * Construct a new JsonRpcService object
	  */
	public function __construct ()
	{
		parent::__construct('UTF-8');
		$this->setProperty(self::PROPERTY_ENABLE_EXTRA_METHODS, TRUE);
		$this->setProperty(self::PROPERTY_UUID, '00000000-0000-0000-0000-0000000000');
		$this->setProperty(self::PROPERTY_ALLOW_ENUM, FALSE);
	}

	/**
	  * @param mixed $response
	  * @return array
	  */
	private function formatResponse ($response)
	{
		$ret = array();
		if ($this->version == '2.0')
			$ret['jsonrpc'] = '2.0';
		else if ($this->version == '1.1')
			$ret['version'] = '1.1';
		$ret = array_merge($ret, $response);
		$ret['id'] = $this->id;
		return $ret;
	}

	/**
	  * Get JSON-RPC description object
	  * @return array JSON-RPC description object
	  */
	protected function systemDescribe ()
	{
		$description = array(
				'sdversion' => '1.0',
				'name' => basename($_SERVER['SCRIPT_NAME']),
				'id' => 'urn:uuid:'.$this->getProperty(self::PROPERTY_UUID),
				'address' => ($_SERVER['SERVER_PORT'] == 443 ? 'https' : 'http').'://'.$_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME']
		);
		if ($this->getProperty(self::PROPERTY_ALLOW_ENUM))
		{
			$description['procs'] = array();
			foreach ($this->getMethods() as $methodName)
			{
				if (strncmp($methodName, 'system.', 7) != 0)
				{
					$proc = array(
							'name' => $methodName,
							'params' => array()
					);

					$reflection = $this->getMethodReflection($methodName);
					foreach ($reflection->getParameters() as $parameter)
					{
						$proc['params'][] = array('name' => $parameter->getName());
					}

					$description['procs'][] = $proc;
				}
			}
		}
		return $description;
	}

	/**
	  * @return string[]
	  */
	protected function systemListMethods ()
	{
		if ($this->getProperty(self::PROPERTY_ALLOW_ENUM))
			return $this->getMethods();
		else
			return array();
	}

	/**
	  * @param mixed $data
	  * @return mixed $data
	  */
	protected function systemEcho ($data)
	{
		return $data;
	}

	/**
	  * @param string $name
	  * @return array
	  */
	protected function systemMethodSignature ($name)
	{
		return NULL;
	}

	/**
	  * @param string $name
	  * @return string
	  */
	protected function systemMethodHelp ($name)
	{
		return '';
	}

	/**
	  * @param array $requests
	  * @return array
	  */
	protected function systemMulticall ($requests)
	{
		$ret = array();

		$oldId = $this->id;
		$oldVersion = $this->version;

		foreach ($requests as $request)
		{
			if (
					is_array($request)
					&& array_key_exists('version', $request)
				   	&& $request['version'] == '1.1'
					&& array_key_exists('method', $request))
			{
				$method = $request['method'];
				$params = array();
				if (array_key_exists('params', $request) && is_array($request['params']))
					$params = $request['params'];
				$this->id = (array_key_exists('id', $request) ? $request['id'] : NULL);
				$this->version = '1.1';

				if (!$this->hasMethod($method))
					$ret[] = $this->formatError(self::ERROR_METHOD_NOT_FOUND);
				else
				{
					try
					{
						RpcResponse::setWriter(new DummyRpcResponseWriter());
						$res = $this->invoke($method, $params);
						if ($res instanceof DummyRpcResponseWriter)
							$ret[] = $this->formatResult($res->getValue());
						else
							$ret[] = $this->formatResult($res);
					}
					catch (\Exception $exception)
					{
						$ret[] = $this->formatException($exception);
					}
				}
			}
		}

		$this->id = $oldId;
		$this->version = $oldVersion;

		return $ret;
	}

	/**
	  */
	private function publishExtraMethods ()
	{
		// JSON-RPC 1.1 ALT
		$this->publishMethod($this, 'systemListMethods', 'system.listMethods');
		$this->publishMethod($this, 'systemMethodSignature', 'system.methodSignature');
		$this->publishMethod($this, 'systemMethodHelp', 'system.methodHelp');
		$this->publishMethod($this, 'systemEcho', 'system.echo');
		$this->publishMethod($this, 'systemMulticall', 'system.multicall');

		// JSON-RPC 1.1 Working Draft (http://json-rpc.org/wd/JSON-RPC-1-1-WD-20060807.html)
		$this->publishMethod($this, 'systemDescribe', 'system.describe');
	}

	/**
	  * @param string $message
	  * @param int $code
	  * @param mixed $data
	  * @return array
	  */
	private function formatError ($code, $message = NULL, $data=NULL)
	{
		if ($message === NULL)
		{
			switch ($code)
			{
				case self::ERROR_PARSE_ERROR:
					$message = 'Parse error.';
					break;

				case self::ERROR_INVALID_REQUEST:
					$message = 'Invalid request.';
					break;

				case self::ERROR_METHOD_NOT_FOUND:
					$message = 'Method not found.';
					break;

				case self::ERROR_INVALID_PARAMS:
					$message = 'Invalid params';
					break;

				case self::ERROR_INTERNAL_ERROR:
					$message = 'Internal error';
					break;

				default:
					$message = '';
			}
		}

		if ($this->version == '1.1')
		{
			$code = 32800 + $code;
			$error = array('name' => 'JSONRPCError', 'code' => (int)$code, 'message' => (string)$message);
			if ($data !== NULL)
				$error['error'] = $data;
		}
		else
		{
			$error= array('code' => (int)$code, 'message' => (string)$message);
			if ($data !== NULL)
				$error['data'] = $data;
		}
		return $this->formatResponse(array('error' => $error));
	}

	/**
	  * Format an exception as per JSON-RPC
	  * @return array
	  */
	private function formatException (\Exception $exception)
	{
		if ($this->getProperty(self::PROPERTY_ALLOW_EXCEPTIONS))
			return $this->formatError($exception->getCode(), $exception->getMessage());
		else
			return $this->formatError(self::ERROR_INTERNAL_ERROR);
	}

	/**
	  * @param mixed $result
	  */
	private function formatResult ($result)
	{
		$json = array('result' => $result);
		if ($this->version == '1.0')
			$json['error'] = NULL;
		return $this->formatResponse($json);
	}

	/**
	  * @param mixed $request
	  * @return string
	  */
	private function getRpcVersion ($request)
	{
		if (!is_object($request))
			return FALSE;
		else if (isset($request->version) && $request->version == '1.1')
			return '1.1';
		else if (isset($request->jsonrpc) && $request->jsonrpc == '2.0')
			return '2.0';
		else
			return '1.0';
	}

	/**
	  * Execute a JSON-RPC request
	  * @param object $request Response object
	  * @param bool $isMultiCall
	  * @return array Response array or FALSE if no response is desired (notifications)
	  */
	private function handleSingleRequest ($request, $isMultiCall = FALSE)
	{
		// Set id to null so that formatError will use the right id

		$this->id = NULL;

		// Sanitize

		if (!is_object($request))
			return $this->formatError(self::ERROR_INVALID_REQUEST);

		// In multi call mode the version of each call must be 2.0

		if ($this->getRpcVersion($request) != $this->version)
			return $this->formatError(self::ERROR_INVALID_REQUEST);

		// 'method' is a required field

		if (!isset($request->method))
			return $this->formatError(self::ERROR_INVALID_REQUEST);

		$method = $request->method;

		// Depending on JSON-RPC version, notifications and the id field are handled differently

		if ($this->version == '2.0')
		{
			// In JSON-RPC 2.0 a request is a notification if the id field is omitted

			if (isset($request->id))
			{
				$isNotification = FALSE;
				$this->id = $request->id;
			}
			else
			{
				$isNotification = TRUE;
				$this->id = NULL;
			}
		}
		else if ($this->version == '1.1')
		{
			// In JSON-RPC 1.1 notifications is not supported, but id may be omitted

			$this->id = (isset($request->id) ? $request->id : NULL);
			$isNotification = FALSE;
		}
		else
		{
			// In JSON-RPC 1.0 notifications have id set to null

			$this->id = (isset($request->id) ? $request->id : NULL);
			$isNotification = ($this->id === NULL);
		}
		$params = (isset($request->params) ? $request->params : array());

		if (!is_object($params) && !is_array($params))
			$params = array();
		else
			$params = $this->convertFromRpcEncoding($params);

		if (!$this->hasMethod($method))
		{
			if ($isNotification)
				return FALSE;
			else
				return $this->formatError(self::ERROR_METHOD_NOT_FOUND);
		}

		try
		{
			if ($isNotification)
				RpcResponse::setWriter(new NullRpcResponseWriter());
			else if (!$isMultiCall)
				RpcResponse::setWriter(new JsonRpcResponseWriter($this->version, $this->id, $this->getProperty(self::PROPERTY_ENCODING)));
			else
				RpcResponse::setWriter(new DummyRpcResponseWriter());
			$result = $this->invoke($method, $params);
			if ($isNotification)
				return FALSE;
			else if ($result instanceof DummyRpcResponseWriter)
			{
				$result->finalize();
				return $this->formatResult($result->getValue());
			}
			else if ($result instanceof JsonRpcResponseWriter)
			{
				$result->finalize();
				return FALSE;
			}
			else
				return $this->formatResult($result);
		}
		catch (\Exception $exception)
		{
			if ($isNotification)
				return FALSE;
			else
				return $this->formatException($exception);
		}
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
		$this->version = '1.0';
		$this->id = NULL;

		if ($this->getProperty(self::PROPERTY_ENABLE_EXTRA_METHODS))
			$this->publishExtraMethods();

		if ($_SERVER['REQUEST_METHOD'] == 'POST')
		{

			$json = file_get_contents('php://input');
			$request = \json_decode($json);

			if (is_array($request))
			{
				// Multicall
				$this->version = '2.0';

				$response = array();
				foreach ($request as $singleRequest)
				{
					$singleResponse = $this->handleSingleRequest($singleRequest, TRUE);
					if ($singleResponse !== FALSE)
						$response[] = $singleResponse;
				}

				// If all methods in a multicall are notifications, we must not return an empty array
				if (count($response) == 0)
					$response = FALSE;
			}
			else if (is_object($request))
			{
				$this->version = $this->getRpcVersion($request);
				$response = $this->handleSingleRequest($request);
			}
			else
				$response = $this->formatError(self::ERROR_INVALID_REQUEST);
		}
		else if ($_SERVER['PATH_INFO'] != '')
		{
			$this->version = '1.1';
			$this->id = NULL;

			$method = substr($_SERVER['PATH_INFO'], 1);
			$params = $this->convertFromRpcEncoding($_GET);

			if (!$this->hasMethod($method))
				$response = $this->formatError(self::ERROR_METHOD_NOT_FOUND);
			else
			{
				try
				{
					RpcResponse::setWriter(new JsonRpcResponseWriter($this->version, $this->id));
					$res = $this->invoke($method, $params);
					if ($res instanceof JsonRpcResponseWriter)
					{
						$res->finalize();
						$resposne = FALSE;
					}
					else
						$response = $this->formatResult($res);
				}
				catch (\Exception $exception)
				{
					$response = $this->formatException($exception);
				}
			}
		}
		else
		{
			$response = $this->formatError(self::ERROR_PARSE_ERROR);
		}

		if (!headers_sent())
		{
			header('Content-Type: application/json', TRUE);
			header('Cache-Control: nocache', TRUE);
			header('Pragma: no-cache', TRUE);
		}

		if ($response !== FALSE)
		{
			$result = \json_encode($this->convertToRpcEncoding($response));

			if ($result === FALSE)
				error_log(var_export($response, TRUE));
			else
				echo($result);
		}
	}
}

?>
