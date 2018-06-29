<?php
namespace includes\rpc;

require_once('AbstractRpcService.class.php');
require_once('RpcResponse.class.php');
require_once('XmlRpcResponseWriter.class.php');

/**
  * XML-RPC Service
  *
  * XML-RPC follows the protocol specified at http://www.xmlrpc.com/spec.
  *
  * NULL values are converted into nil elements
  * DateTime objects are converted into datetime.iso8601 elements
  * Binary strings (PHP 6) are converted into base64 elements
  *
  * The XmlRpcService does not make use of the XML-RPC API in PHP
  */
class XmlRpcService extends AbstractRpcService
{
	const ERROR_INVALID_REQUEST = 1;
	const ERROR_UNKNOWN_METHOD = 2;
	const ERROR_SERVICE_ERROR = 3;

	/**
	  */
	public function __construct ()
	{
		parent::__construct('UTF-8');
	}

	/**
	  * @param \DOMDocument $doc
	  * @param mixed $value
	  * @return \DOMElement
	  */
	private function encodeValue (\DOMDocument $doc, $value)
	{
		$childNode = NULL;
		if (is_integer($value))
		{
			$tagName = 'int';
			$content = strval($value);
		}
		else if (is_bool($value))
		{
			$tagName = 'boolean';
			$content = ($value ? '1' : '0');
		}
		else if (function_exists('is_binary') && is_binary($value))
		{
			$tagName = 'base64';
			$content = base64_encode($value);
		}
		else if (is_string($value))
		{
			$tagName = 'string';
			$content = $this->convertToRpcEncoding($value);
		}
		else if (is_float($value))
		{
			$tagName = 'double';
			$content = strval($value);
		}
		else if ($value instanceof \DateTime)
		{
			$tagName = 'dateTime.iso8601';
			$content = $value->format(\DateTime::ISO8601);
		}
		else if (is_array($value))
			$childNode = $this->encodeArray($doc, $value);
		else if (is_object($value))
			$childNode = $this->encodeStruct($doc, get_object_vars($value));
		else
			$childNode = $doc->createElement('nil');

		if ($childNode === NULL)
		{
			$childNode = $doc->createElement($tagName);
			$childNode->appendChild($doc->createTextNode($content));
		}

		$value = $doc->createElement('value');
		$value->appendChild($childNode);

		return $value;
	}

	/**
	  * @param \DOMDocument $doc
	  * @param array $value
	  * @return \DOMElement
	  */
	private function encodeArray (\DOMDocument $doc, array $value)
	{
		$ary = $doc->createElement('array');

		$data = $doc->createElement('data');
		$ary->appendChild($data);

		$expectedKey = 0;
		foreach ($value as $key => $childValue)
		{
			if ($key == (string)$expectedKey)
			{
				$data->appendChild($this->encodeValue($doc, $childValue));

				++$expectedKey;
			}
			else
				return $this->encodeStruct($doc, $value);
		}

		return $ary;
	}

	/**
	  * @param \DOMDocument $doc
	  * @param array $value
	  * @return \DOMElement
	  */
	private function encodeStruct (\DOMDocument $doc, array $value)
	{
		$struct = $doc->createElement('struct');

		foreach ($value as $key => $childValue)
		{
			$member = $doc->createElement('member');

			$name = $doc->createElement('name');
			$name->appendChild($doc->createTextNode($this->convertToRpcEncoding($key)));
			$member->appendChild($name);

			$member->appendChild($this->encodeValue($doc, $childValue));

			$struct->appendChild($member);
		}

		return $struct;
	}

	/**
	  * @param \DOMElement $node <value> element
	  * @return mixed
	  */
	private function decodeValue (\DOMElement $node)
	{
		for ($child = $node->firstChild; $child !== NULL; $child = $child->nextSibling)
		{
			if ($child->nodeType == XML_ELEMENT_NODE)
			{
				if ($child->tagName == 'i4' || $child->tagName == 'int')
					return (int)$child->textContent;
				else if ($child->tagName == 'boolean')
					return ($child->textContent == '1');
				else if ($child->tagName == 'string')
					return $this->convertFromRpcEncoding($child->textContent, $child->ownerDocument->encoding);
				else if ($child->tagName == 'double')
					return (float)$child->textContent;
				else if ($child->tagName == 'dateTime.iso8601')
					return \DateTime::createFromFormat(\DateTime::ISO8601, $child->textContent);
				else if ($child->tagName == 'base64')
					return base64_decode($child->textContent);
				else if ($child->tagName == 'struct')
					return $this->decodeStruct($child);
				else if ($child->tagName == 'array')
					return $this->decodeArray($child);
				else if ($child->tagName == 'nil')
					return NULL;
			}
		}
		return $this->convertFromRpcEncoding($node->textContent, $node->ownerDocument->encoding);
	}

	/**
	  * @param DOMElement $node <struct> element
	  * @return array
	  */
	private function decodeStruct (\DOMElement $node)
	{
		$struct = array();
		for ($child = $node->firstChild; $child !== NULL; $child = $child->nextSibling)
		{
			if ($child->nodeType == XML_ELEMENT_NODE && $child->tagName == 'member')
			{
				$nameNode = NULL;
				$valueNode = NULL;
				for ($subChild = $child->firstChild; $subChild !== NULL && ($nameNode === NULL || $valueNode === NULL); $subChild = $subChild->nextSibling)
				{
					if ($subChild->nodeType == XML_ELEMENT_NODE)
					{
						if ($nameNode === NULL && $subChild->tagName == 'name')
							$nameNode = $subChild;
						else if ($valueNode === NULL && $subChild->tagName == 'value')
							$valueNode = $subChild;
					}
				}

				if ($nameNode !== NULL && $valueNode !== NULL)
					$struct[$this->convertFromRpcEncoding($nameNode->textContent, $nameNode->ownerDocument->encoding)] = $this->decodeValue($valueNode);
			}
		}
		return $struct;
	}

	/**
	  * @param \DOMElement $node <array> element
	  * @return array
	  */
	private function decodeArray (\DOMElement $node)
	{
		$ary = array();
		for ($child = $node->firstChild; $child !== NULL; $child = $child->nextSibling)
		{
			if ($child->nodeType == XML_ELEMENT_NODE && $child->tagName == 'data')
			{
				for ($subChild = $child->firstChild; $subChild !== NULL && ($nameNode === NULL || $valueNode === NULL); $subChild = $subChild->nextSibling)
				{
					if ($subChild->nodeType == XML_ELEMENT_NODE && $subChild->tagName == 'value')
						$ary[] = $this->decodeValue($subChild);
				}

				break;
			}
		}
		return $ary;
	}

	/**
	  * @param \DOMElement $node
	  * @return array
	  */
	private function decodeParams (\DOMElement $node)
	{
		$params = array();
		for ($child = $node->firstChild; $child !== NULL; $child = $child->nextSibling)
		{
			if ($child->nodeType == XML_ELEMENT_NODE && $child->tagName == 'param')
			{
				for ($subChild = $child->firstChild; $subChild !== NULL; $subChild = $subChild->nextSibling)
				{
					if ($subChild->nodeType == XML_ELEMENT_NODE && $subChild->tagName == 'value')
					{
						$params[] = $this->decodeValue($subChild);
						break;
					}
				}
			}
		}

		return $params;
	}

	/**
	  * @param int $errorCode
	  * @param string $errorMessage
	  */
	private function sendError ($errorCode, $errorMessage)
	{
		$doc = new \DOMDocument('1.0', 'UTF-8');

		$methodResponse = $doc->createElement('methodResponse');
		$doc->appendChild($methodResponse);

		$fault = $doc->createElement('fault');
		$fault->appendChild($this->encodeValue($doc, array('faultCode' => $errorCode, 'faultString' => $errorMessage)));
		$methodResponse->appendChild($fault);

		echo($doc->saveXML());
	}

	/**
	  * @param mixed $response
	  */
	private function sendResponse ($response)
	{
		$doc = new \DOMDocument('1.0', 'UTF-8');

		$methodResponse = $doc->createElement('methodResponse');
		$doc->appendChild($methodResponse);

		$params = $doc->createElement('params');
		$methodResponse->appendChild($params);

		$param = $doc->createElement('param');
		$param->appendChild($this->encodeValue($doc, $response));
		$params->appendChild($param);

		echo($doc->saveXML());
	}

	/**
	  * @param string $xml XML data
	  * @return mixed
	  */
	private function executeRequest ($xml)
	{
		try
		{
			$doc = \DOMDocument::loadXML($xml);
			if ($doc instanceof \DOMDocument)
			{
				$rootNode = $doc->documentElement;
				if ($rootNode->tagName == 'methodCall')
				{
					$methodNameNode = NULL;
					$paramsNode = NULL;
					for ($child = $rootNode->firstChild; $child !== NULL && ($methodNameNode === NULL || $paramsNode === NULL); $child = $child->nextSibling)
					{
						if ($child->nodeType == XML_ELEMENT_NODE)
						{
							if ($methodNameNode === NULL && $child->tagName == 'methodName')
								$methodNameNode = $child;
							else if ($paramsNode === NULL && $child->tagName == 'params')
								$paramsNode = $child;
						}
					}

					if ($methodNameNode === NULL)
						$this->sendError(self::ERROR_INVALID_REQUEST, 'Missing element <methodName>');
					else
					{
						$methodName = $methodNameNode->textContent;
						if ($this->hasMethod($methodName))
						{
							if ($paramsNode === NULL)
								$params = array();
							else
								$params = $this->decodeParams($paramsNode);

							RpcResponse::setWriter(new XmlRpcResponseWriter());
							$res = $this->invoke($methodName, $params);
							if ($res instanceof XmlRpcResponseWriter)
								$res->finalize();
							else
								$this->sendResponse($res);
						}
						else
							$this->sendError(self::ERROR_UNKNOWN_METHOD, 'No such method: '.$methodName);
					}
				}
				else
					$this->sendError(self::ERROR_INVALID_REQUEST, 'Expected root element <methodCall>, got <'.$rootNode->tagName.'>');
			}
			else
				$this->sendError(self::ERROR_INVALID_REQUEST, 'Parse error');
		}
		catch (\Exception $exception)
		{
			if ($this->getProperty(self::PROPERTY_ALLOW_EXCEPTIONS))
				$this->sendError($exception->getCode(), $exception->getMessage());
			else
				$this->sendError(self::ERROR_SERVICE_ERROR, 'Service error');
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
		header('Content-Type: text/xml');

		if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_SERVER['CONTENT_TYPE'] == 'text/xml')
		{
			$xml = file_get_contents('php://input');
			$this->executeRequest($xml);
		}
		else
			$this->sendError(self::ERROR_INVALID_REQUEST, 'Got no data');
	}
}

?>
