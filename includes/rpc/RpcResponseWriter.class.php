<?php
namespace includes\rpc;

/**
  * Response builder
  *
  * The RPC response builder allows a method to return data on-the-fly without
  * directly exposing the underlying RPC protocol.
  *
  * Basically, the order of operation is as follows:
  * <code>
  * public funciton publishedMethod ()
  * {
  * $res = RpcResponse::getResponseBuilder();
  * $res->beginArray();
  * $res->addValue('Hello');
  * $res->addValue('World');
  * $res->addValue(2);
  * $res->endArray();
  * return $res;
  * }
  * </code>
  *
  * Alternatively you could have written:
  * <code>
  * public function publishedMethod ()
  * {
  * $res = array();
  * $res[] = 'Hello';
  * $res[] = 'World';
  * $res[] = 2;
  * return $res;
  * }
  * </code>
  * But then the response wouldn't be parsed to the user until the end
  *
  * In other languages associative arrays and traditional arrays are typically
  * not related. The RpcService would normally analyse each PHP array and determine
  * whether to respond with an associative array or an ordinary array. Since the builder
  * cannot undo what is done, since it writes output on-the-fly, the builder needs to
  * distinguish the two kinds of arrays from the start. The builder therefore has
  * two methods, beginArray which will start a traditional array and beginDictionary
  * which will start an associative array.
  */
interface RpcResponseWriter
{
	/**
	  * Set an immediate value
	  *
	  * Setting an immediate value doesn't grand any advantages
	  * over traditional responses which works regardless of RPC
	  * @param mixed $value Value to set
	  * @return RpcResponseWriter
	  */
	public function setValue ($value);

	/**
	  * Add value to array or dictionary
	  * @param mixed $value Value to array
	  * @param mixed $key Key if building a dictionary
	  * @return RpcResponseWriter
	  */
	public function addValue ($value, $key=NULL);

	/**
	  * Begin writing a long string
	  * @param string $value Initial string value
	  * @param mixed $key Key if building a dictionary
	  */
	//public function beginString ($value, $key=NULL);

	/**
	  * Concatenate string to current string
	  * @param string $value String to concatenate
	  */
	//public function appendString ($value);

	/**
	  * End ongoing string
	  */
	//public function endString ();

	/**
	  * Start a traditional array
	  * @param mixed $key Key if building a dictionary
	  * @return RpcResponseWriter
	  */
	public function beginArray ($key=NULL);

	/**
	  * End array
	  * @return RpcResponseWriter
	  */
	public function endArray ();

	/**
	  * Start an associative array
	  * @param mixed $key Key if building a dictioanry
	  * @return RpcResponseWriter
	  */
	public function beginDictionary ($key=NULL);

	/**
	  * End an associative array
	  * @return RpcResponseWriter
	  */
	public function endDictionary ();

	/**
	  * Finalize writer.
	  *
	  * When the builder has been finzlized, the response is done and no more values can be added
	  * @return RpcResponseWriter
	  */
	public function finalize ();
}

?>
