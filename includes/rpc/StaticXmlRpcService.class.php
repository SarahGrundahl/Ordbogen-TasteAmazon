<?php
namespace includes\rpc;

require_once('AbstractStaticRpcService.class.php');
require_once('XmlRpcService.class.php');

/**
  * Static XML-RPC service
  *
  * Static implementation of XmlRpcService
  */
class StaticXmlRpcService extends AbstractStaticRpcService
{
	/**
	  * @var XmlRpcService
	  */
	private static $instance = NULL;

	/**
	  * Get RpcService instance
	  *
	  * Use this to get the internal RpcService instance
	  * @return XmlRpcService Internal RpcService instance
	  */
	public static function getInstance ()
	{
		if (self::$instance === NULL)
			self::$instance = new XmlRpcService();
		return self::$instance;
	}
}

?>
