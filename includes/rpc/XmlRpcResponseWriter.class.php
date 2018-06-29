<?php
namespace includes\rpc;

require_once('RpcResponseWriter.class.php');

/**
  * Response writer for XmlRpcService
  */
class XmlRpcResponseWriter implements RpcResponseWriter
{
	const STATE_NONE = 0;
	const STATE_ARRAY = 1;
	const STATE_STRUCT = 2;
	const STATE_DONE = 3;
	const STATE_FINAL = 4;

	/**
	  * @var \XmlWriter
	  */
	private $writer = NULL;

	/**
	  * @var int
	  */
	private $state = self::STATE_NONE;

	/**
	  * @var int
	  */
	private $stack = array();

	/**
	  */
	private function init ()
	{
		if ($this->writer === NULL)
		{
			header('Content-Type: text/xml');
			$this->writer = new \XmlWriter();
			$this->writer->openURI('php://output');
			$this->writer->startDocument();
			$this->writer->startElement('methodResponse');
			$this->writer->startElement('params');
			$this->writer->startElement('param');
		}
	}

	/**
	  * @param mixed $value
	  */
	private function writeValue ($value)
	{
		if (is_integer($value))
			$this->writer->writeElement('int', $value);
		else if (is_bool($value))
			$this->writer->writeElement('boolean', $value ? '1' : '0');
		else if (function_exists('is_binary') && is_binary($value))
			$this->writer->writeElement('base64', base64_encode($value));
		else if (is_string($value))
			$this->writer->writeElement('string', $value);	// TODO
		else if (is_float($value))
			$this->writer->writeElement('double', strval($value));
		else if ($value instanceof \DateTime)
			$this->writer->writeElement('dateTime.iso8601', $value->format(\DateTime::ISO8601));
		else if (is_array($value))
		{
			$isArray = TRUE;
			$expectedKey = 0;
			foreach ($value as $key => $childValue)
			{
				if ($key != $expectedKey)
				{
					$isArray = FALSE;
					break;
				}
				++$expectedKey;
			}

			if ($isArray)
			{
				$this->beginArray();
				foreach ($value as $childValue)
					$this->addValue($childValue);
				$this->endArray();
			}
			else
			{
				$this->beginDictionary();
				foreach ($value as $key => $childValue)
					$this->addValue($childValue, $key);
				$this->endDictionary();
			}
		}
		else if (is_object($value))
		{
			$this->beginDictionary();
			foreach (get_object_vars($value) as $key => $subValue)
				$this->addValue($subValue, $key);
			$this->endDictionary();
		}
		else
			$this->writer->writeElement('nil');
	}

	/**
	  * Set an immediate value
	  *
	  * Setting an immediate value doesn't grand any advantages
	  * over traditional responses which works regardless of RPC
	  * @param mixed $value Value to set
	  * @return XmlRpcResponseWriter
	  */
	public function setValue ($value)
	{
		if ($this->state == self::STATE_NONE)
		{
			$this->init();
			$this->writeValue($value);
			$this->state == self::STATE_DONE;
		}
		return $this;
	}

	/**
	  * Add value to array or dictionary
	  * @param mixed $value Value to array
	  * @param mixed $key Key if building a dictionary
	  * @return XmlRpcResponseWriter
	  */
	public function addValue ($value, $key=NULL)
	{
		if ($this->state == self::STATE_ARRAY)
		{
			$this->writer->startElement('data');
			$this->writeValue($value);
			$this->writer->endElement();
		}
		else if ($this->state == self::STATE_STRUCT)
		{
			$this->writer->startElement('member');
			$this->writer->writeElement('name', $key);
			$this->writeValue($value);
			$this->writer->endElement();
		}
		return $this;
	}

	/**
	  * Start a traditional array
	  * @param mixed $key Key if building a dictionary
	  * @return XmlRpcResponseWriter
	  */
	public function beginArray ($key=NULL)
	{
		if ($this->state == self::STATE_NONE)
		{
			$this->init();
			$this->writer->startElement('array');
			$this->stack[] = $this->state;
			$this->state = self::STATE_ARRAY;
		}
		else if ($this->state == self::STATE_ARRAY)
		{
			$this->writer->startElement('data');
			$this->writer->startElement('array');
			$this->stack[] = $this->state;
			$this->state = self::STATE_ARRAY;
		}
		else if ($this->state == self::STATE_STRUCT)
		{
			$this->writer->startElement('member');
			$this->writer->writeElement('name', $key);
			$this->writer->startElement('array');
			$this->stack[] = $this->state;
			$this->state = self::STATE_ARRAY;
		}
		return $this;
	}

	/**
	  * End array
	  * @return XmlRpcResponseWriter
	  */
	public function endArray ()
	{
		if ($this->state == self::STATE_ARRAY)
		{
			$this->state = array_pop($this->stack);
			$this->writer->endElement();
			if ($this->state == self::STATE_STRUCT || $this->state == self::STATE_ARRAY)
				$this->writer->endElement();
		}
		return $this;
	}

	/**
	  * Start an associative array
	  * @param mixed $key Key if building a dictioanry
	  * @return XmlRpcResponseWriter
	  */
	public function beginDictionary ($key=NULL)
	{
		if ($this->state == self::STATE_NONE)
		{
			$this->init();
			$this->writer->startElement('struct');
			$this->stack[] = $this->state;
			$this->state = self::STATE_STRUCT;
		}
		else if ($this->state == self::STATE_ARRAY)
		{
			$this->writer->startElement('data');
			$this->writer->startElement('struct');
			$this->stack[] = $this->state;
			$this->state = self::STATE_STRUCT;
		}
		else if ($this->state == self::STATE_STRUCT)
		{
			$this->writer->startElement('member');
			$this->writer->writeElement('name', $key);
			$this->writer->startElement('struct');
			$this->stack[] = $this->state;
			$this->state = self::STATE_STRUCT;
		}
		return $this;
	}

	/**
	  * End an associative array
	  * @return XmlRpcResponseWriter
	  */
	public function endDictionary ()
	{
		if ($this->state == self::STATE_STRUCT)
		{
			$this->state = array_pop($this->stack);
			$this->writer->endElement();
			if ($this->state == self::STATE_STRUCT || $this->state == self::STATE_ARRAY)
				$this->writer->endElement();
		}
		return $this;
	}

	/**
	  * Finalize writer.
	  *
	  * When the builder has been finzlized, the response is done and no more values can be added
	  * @return XmlRpcResponseWriter
	  */
	public function finalize ()
	{
		if ($this->state == self::STATE_NONE)
		{
			$this->init();
			$this->state = self::STATE_DONE;
		}

		if ($this->state != self::STATE_FINAL)
		{
			while ($this->state != self::STATE_DONE)
			{
				if ($this->state == self::STATE_ARRAY)
					$this->endArray();
				else if ($this->state == self::STATE_STRUCT)
					$this->endDictionary();
			}
			$this->writer->endElement();
			$this->writer->endElement();
			$this->writer->endElement();
			$this->writer->endDocument();
			$this->writer->flush();
			$this->state = self::STATE_FINAL;
		}
		return $this;
	}

}
