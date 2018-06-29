<?php
namespace includes\rpc;

require_once('NullRpcResponseWriter.class.php');

/**
  * RPC Response manager
  */
class RpcResponse
{
	/**
	  * @var RpcResponseWriter
	  */
	private static $writer = NULL;

	/**
	  * @return RpcResponseWriter
	  */
	public static function getWriter ()
	{
		if (self::$writer === NULL)
			self::$writer = new NullRpcResponseWriter();
		return self::$writer;
	}

	/**
	  * @param RpcResponseWriter $writer
	  */
	public static function setWriter (RpcResponseWriter $writer)
	{
		self::$writer = $writer;
	}
}

?>
