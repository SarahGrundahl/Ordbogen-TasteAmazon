<?php
namespace includes\rpc;

require_once('RpcResponseWriter.class.php');

/**
  * Discarding response writer
  *
  * This response writer discards any data feeded to the writer.
  * This is the default response writer if the RPC service doesn't
  * overwrite it.
  */
class NullRpcResponseWriter implements RpcResponseWriter
{
	/**
	  * Set an immediate value
	  *
	  * Setting an immediate value doesn't grand any advantages
	  * over traditional responses which works regardless of RPC
	  * @param mixed $value Value to set
	  * @return NullRpcResponseWriter
	  */
	public function setValue ($value)
	{
		return $this;
	}

	/**
	  * Add value to array or dictionary
	  * @param mixed $value Value to array
	  * @param mixed $key Key if building a dictionary
	  * @return NullRpcResponseWriter
	  */
	public function addValue ($value, $key=NULL)
	{
		return $this;
	}

	/**
	  * Start a traditional array
	  * @param mixed $key Key if building a dictionary
	  * @return NullRpcResponseWriter
	  */
	public function beginArray ($key=NULL)
	{
		return $this;
	}

	/**
	  * End array
	  * @return NullRpcResponseWriter
	  */
	public function endArray ()
	{
		return $this;
	}

	/**
	  * Start an associative array
	  * @param mixed $key Key if building a dictioanry
	  * @return NullRpcResponseWriter
	  */
	public function beginDictionary ($key=NULL)
	{
		return $this;
	}

	/**
	  * End an associative array
	  * @return NullRpcResponseWriter
	  */
	public function endDictionary ()
	{
		return $this;
	}

	/**
	  * Finalize writer.
	  *
	  * When the builder has been finzlized, the response is done and no more values can be added
	  * @return NullRpcResponseWriter
	  */
	public function finalize ()
	{
		return $this;
	}
}

?>
