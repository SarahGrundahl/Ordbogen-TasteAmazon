<?php
namespace includes\rpc;

require_once('AbstractStaticRpcService.class.php');
require_once('JsonRpcService.class.php');

/**
  * Static JSON-RPC service
  *
  * Static implementation of JsonRpcService
  */
class StaticJsonRpcService extends AbstractStaticRpcService
{
	/**
	  * @var JsonRpcService
	  */
	private static $instance = NULL;

	/**
	  * Get RpcService instance
	  *
	  * Use this to get the internal RpcService instance
	  * @return JsonRpcService Internal RpcService instance
	  */
	public static function getInstance ()
	{
		if (self::$instance === NULL)
			self::$instance = new JsonRpcService();
		return self::$instance;
	}
}

?>
