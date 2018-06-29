<?php
namespace includes\rpc;

require_once('AbstractStaticRpcService.class.php');
require_once('SoapService.class.php');

/**
  * Static JSON-RPC service
  *
  * Static implementation of SoapService
  */
class StaticSoapService extends AbstractStaticRpcService
{
	/**
	  * @var SoapService
	  */
	private static $instance = NULL;

	/**
	  * Get RpcService instance
	  *
	  * Use this to get the internal RpcService instance
	  * @return SoapService Internal RpcService instance
	  */
	public static function getInstance ()
	{
		if (self::$instance === NULL)
			self::$instance = new SoapService();
		return self::$instance;
	}
}

?>
