<?php
namespace includes\rpc;

require_once('RpcResponseWriter.class.php');

/**
  * Dummy response writer
  *
  * The dummy response writer doesn't really write anything to the output buffer, instead
  * it stores the response in an internal variable.
  *
  * Rpc services which doesn't implement its own response writer, can use this response writer
  * and then use getValue() to get the actual value
  */
class DummyRpcResponseWriter implements RpcResponseWriter
{
	private $value = NULL;

	private $stack = array();

	private $ptr;

	/**
	  */
	public function __construct ()
	{
		$this->ptr =& $this->value;
	}

	/**
	  * Set an immediate value
	  *
	  * Setting an immediate value doesn't grand any advantages
	  * over traditional responses which works regardless of RPC
	  * @param mixed $value Value to set
	  * @return DummyRpcResponseWriter
	  */
	public function setValue ($value)
	{
		if (count($position) == 0)
			$this->value = $value;
		return $this;
	}

	/**
	  * Add value to array or dictionary
	  * @param mixed $value Value to array
	  * @param mixed $key Key if building a dictionary
	  * @return DummyRpcResponseWriter
	  */
	public function addValue ($value, $key=NULL)
	{
		if ($key !== NULL)
			$this->ptr[$key] = $value;
		else
			$this->ptr[] = $value;
		return $this;
	}

	/**
	  * Start a traditional array
	  * @param mixed $key Key if building a dictionary
	  * @return DummyRpcResponseWriter
	  */
	public function beginArray ($key=NULL)
	{
		$this->stack[] =& $this->ptr;
		$value = array();
		if (is_array($this->ptr))
		{
			if ($key !== NULL)
				$this->ptr[$key] =& $value;
			else
				$this->ptr[] =& $value;
			unset($this->ptr);
			$this->ptr =& $value;
		}
		else
			$this->ptr = $value;
		return $this;
	}

	/**
	  * End array
	  * @return DummyRpcResponseWriter
	  */
	public function endArray ()
	{
		unset($this->ptr);
		$this->ptr =& array_pop($this->stack);
		return $this;
	}

	/**
	  * Start an associative array
	  * @param mixed $key Key if building a dictioanry
	  * @return DummyRpcResponseWriter
	  */
	public function beginDictionary ($key=NULL)
	{
		$this->beginArray($key);
		return $this;
	}

	/**
	  * End an associative array
	  * @return DummyRpcResponseWriter
	  */
	public function endDictionary ()
	{
		$this->endArray();
		return $this;
	}

	/**
	  * Finalize writer.
	  *
	  * When the builder has been finzlized, the response is done and no more values can be added
	  * @return DummyRpcResponseWriter
	  */
	public function finalize ()
	{
		return $this;
	}

	/**
	  * Get value of writer
	  * @return mixed Value or writer
	  */
	public function getValue ()
	{
		return $this->value;
	}
}

?>
