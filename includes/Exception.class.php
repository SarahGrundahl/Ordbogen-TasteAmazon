<?php
namespace includes;

abstract class Exception extends \Exception
{
	/**
	  * EXCEPTION CONSTANTS BEGIN
	  * A pair exist for each exception code
	  * An exception code and an exception message
	  * Has to be in humanreadable for, can be presented for end user
	  * Use $internalMessage for technical stuff
	  */
	// Add constant pairs in the extending class, NOT HERE
	/**
	  * EXCEPTION CONSTANTS END
	  */

	private $internalMessage = '';

	private static $language = NULL;

	/**
	  * Construct a message using a code
	  * Will translate the code into a message
	  * @param int $code
	  * @param string $internalMessage
	  * @param Exception $previous
	  */
	public function __construct ($code, $internalMessage = '', $previous = NULL)
	{
		$message = FALSE;
		if (self::$language !== NULL)
			$message = $this->getConstant('MESSAGE_'.$code.'_'.self::$language);

		if ($message === FALSE)
			$message = $this->getConstant('MESSAGE_'.$code);

		parent::__construct($message, $code, $previous);
		$this->internalMessage = $internalMessage;
	}

	/**
	  * Returns the internal message, used for debugging, may contain sensitive information
	  * DO NOT SHOW END USERS
	  * @return string
	  */
	public function getInternalMessage()
	{
		return $this->internalMessage;
	}

	/**
	  * Defines a language to use for exception message constants
	  * If constant matching language is not found, the default is used
	  * @param string $language
	  */
	public static function setLanguage($language)
	{
		self::$language = $language;
	}

	/**
	 * Returns the constant value
	 * @param string $name
	 * @return value
	 */
	private function getConstant($name)
	{
		$reflection = new \ReflectionClass($this);
		return $reflection->getConstant($name);
	}
}
