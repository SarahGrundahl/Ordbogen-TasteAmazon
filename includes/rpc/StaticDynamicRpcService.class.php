<?php
namespace includes\rpc;

require_once('AbstractStaticRpcService.class.php');
require_once('DynamicRpcService.class.php');

/**
  * Static dynamic RPC service
  *
  * Static version of DynamicRpcService
  */
class StaticDynamicRpcService extends AbstractStaticRpcService
{
	/**
	  * @var DynamicRpcService
	  */
	private static $instance = NULL;

	/**
	  * Get RpcService instance
	  *
	  * Use this to get the internal RpcService instance
	  * @return DynamicRpcService Internal RpcService instance
	  */
	public static function getInstance ()
	{
		if (self::$instance === NULL)
			self::$instance = new DynamicRpcService();
		return self::$instance;
	}
}

?>
