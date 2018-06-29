<?php
namespace includes\rpc;

require_once('RpcResponseWriter.class.php');

/**
  * Response writer for JsonRpcService
  */
class JsonRpcResponseWriter implements RpcResponseWriter
{
	const STATE_NONE = 0;			// Nothing was been written
	const STATE_EMPTY_ARRAY = 1;	// Just started an array
	const STATE_ARRAY = 2;			// In the middle of an array
	const STATE_EMPTY_OBJECT = 3;	// Just started an object
	const STATE_OBJECT = 4;			// In the middle of an object
	const STATE_DONE = 5;			// Is done with the value, but is missing }
	const STATE_FINAL = 6;			// Is final

	/**
	  * @var int[]
	  */
	private $stack = array();

	/**
	  * @var int
	  */
	private $state = self::STATE_NONE;

	/**
	  * @var string
	  */
	private $version;

	/**
	  * @var mixed
	  */
	private $id;

	/**
	  * @var bool
	  */
	private $multiCall;

	/**
	  * @var string
	  */
	private $encoding;

	/**
	  * @param string $version
	  * @param mixed $id
	  * @param string $encoding
	  * @param bool $multiCall
	  */
	public function __construct ($version, $id=NULL, $encoding=NULL, $multiCall = FALSE)
	{
		$this->version = $version;
		$this->encoding = $encoding;
		$this->id = $id;
		$this->multiCall = $multiCall;
	}

	/**
	  */
	private function init ()
	{
		if (!$this->multiCall)
		{
			header('Content-Type: application/json');
			header('Cache-Control: nocache');
			header('Pragma: no-cache');
		}

		if ($this->version == '2.0')
			echo('{"jsonrpc":"2.0","result":');
		else if ($this->version == '1.1')
			echo('{"version":"1.1","result":');
		else
			echo('{"result":');
	}

	/**
	  * @param mixed $value
	  * @return mixed
	  */
	private function encode ($value)
	{
		if (is_string($value))
		{
			if ($this->encoding === NULL)
				$encoding = mb_internal_encoding();
			else
				$encoding = $this->encoding;
			$value = iconv($encoding, 'UTF-8', $value);
		}
		else if (is_array($value))
		{
			$newValue = array();
			foreach ($value as $key => $subValue)
				$newValue[$this->encode($key)] = $this->encode($subValue);
			$value = $newValue;
		}
		else if (is_object($value))
		{
			$newObject = new stdClass();
			foreach (get_object_vars($value) as $key => $subValue)
			{
				$key = $this->encode($key);
				$newObject->$key = $this->encode($subValue);
			}
			$value = $newObject;
		}
		return json_encode($value);
	}

	/**
	  * Set an immediate value
	  *
	  * Setting an immediate value doesn't grand any advantages
	  * over traditional responses which works regardless of RPC
	  * @param mixed $value Value to set
	  * @return RpcResponseWriter
	  */
	public function setValue ($value)
	{
		if ($this->state == self::STATE_NONE)
		{
			$this->init();
			echo($this->encode($value));
			$this->state = self::STATE_DONE;
		}
		return $this;
	}

	/**
	  * Add value to array or dictionary
	  * @param mixed $value Value to array
	  * @param mixed $key Key if building a dictionary
	  * @return RpcResponseWriter
	  */
	public function addValue ($value, $key=NULL)
	{
		if ($this->state == self::STATE_EMPTY_ARRAY)
		{
			echo($this->encode($value));
			$this->state = self::STATE_ARRAY;
		}
		else if ($this->state == self::STATE_ARRAY)
		{
			echo(','.$this->encode($value));
		}
		else if ($this->state == self::STATE_EMPTY_OBJECT)
		{
			echo($this->encode($key).':'.$this->encode($value));
			$this->state = self::STATE_OBJECT;
		}
		else if ($this->state == self::STATE_OBJECT)
		{
			echo(','.$this->encode($key).':'.$this->encode($value));
		}
		return $this;
	}

	/**
	  * Start a traditional array
	  * @param mixed $key Key if building a dictionary
	  * @return RpcResponseWriter
	  */
	public function beginArray ($key=NULL)
	{
		if ($this->state == self::STATE_NONE)
		{
			$this->init();
			echo('[');
			$this->stack[] = self::STATE_DONE;
			$this->state = self::STATE_EMPTY_ARRAY;
		}
		else if ($this->state == self::STATE_EMPTY_ARRAY)
		{
			echo('[');
			$this->stack[] = self::STATE_ARRAY;
			$this->state = self::STATE_EMPTY_ARRAY;
		}
		else if ($this->state == self::STATE_ARRAY)
		{
			echo(',[');
			$this->stack[] = self::STATE_ARRAY;
			$this->state = self::STATE_EMPTY_ARRAY;
		}
		else if ($this->state == self::STATE_EMPTY_OBJECT)
		{
			echo($this->encode($key).':[');
			$this->stack[] = self::STATE_OBJECT;
			$this->state = self::STATE_EMPTY_ARRAY;
		}
		else if ($this->state == self::STATE_OBJECT)
		{
			echo(','.$this->encode($key).':[');
			$this->stack[] = self::STATE_OBJECT;
			$this->state = self::STATE_EMPTY_ARRAY;
		}
		return $this;
	}

	/**
	  * End array
	  * @return RpcResponseWriter
	  */
	public function endArray ()
	{
		if ($this->state == self::STATE_EMPTY_ARRAY || $this->state == self::STATE_ARRAY)
		{
			echo(']');
			$this->state = array_pop($this->stack);
		}
		return $this;
	}

	/**
	  * Start an associative array
	  * @param mixed $key Key if building a dictioanry
	  * @return RpcResponseWriter
	  */
	public function beginDictionary ($key=NULL)
	{
		if ($this->state == self::STATE_NONE)
		{
			$this->init();
			echo('{');
			$this->stack[] = self::STATE_DONE;
			$this->state = self::STATE_EMPTY_OBJECT;
		}
		else if ($this->state == self::STATE_EMPTY_ARRAY)
		{
			echo('{');
			$this->stack[] = self::STATE_ARRAY;
			$this->state = self::STATE_EMPTY_OBJECT;
		}
		else if ($this->state == self::STATE_ARRAY)
		{
			echo(',{');
			$this->stack[] = self::STATE_ARRAY;
			$this->state = self::STATE_EMPTY_OBJECT;
		}
		else if ($this->state == self::STATE_EMPTY_OBJECT)
		{
			echo($this->encode($key).':{');
			$this->stack[] = self::STATE_OBJECT;
			$this->state = self::STATE_EMPTY_OBJECT;
		}
		else if ($this->state == self::STATE_OBJECT)
		{
			echo(','.$this->encode($key).':{');
			$this->stack[] = self::STATE_OBJECT;
			$this->state = self::STATE_EMPTY_OBJECT;
		}
		return $this;
	}

	/**
	  * End an associative array
	  * @return RpcResponseWriter
	  */
	public function endDictionary ()
	{
		if ($this->state == self::STATE_EMPTY_OBJECT || $this->state == self::STATE_OBJECT)
		{
			echo('}');
			$this->state = array_pop($this->stack);
		}
		return $this;
	}

	/**
	  * Finalize writer.
	  *
	  * When the builder has been finalized, the response is done and no more values can be added
	  * @return RpcResponseWriter
	  */
	public function finalize ()
	{
		if ($this->state == self::STATE_NONE)
		{
			$this->init();
			$this->setValue(NULL);
		}

		if ($this->state != self::STATE_FINAL)
		{
			while ($this->state != self::STATE_DONE)
			{
				switch ($this->state)
				{
					case self::STATE_EMPTY_ARRAY:
					case self::STATE_ARRAY:
						$this->endArray();
						break;

					case self::STATE_EMPTY_OBJECT:
					case self::STATE_OBJECT:
						$this->endDictionary();
						break;
				}
			}
			if ($this->version == '1.0')
				echo(',"error":null');
			echo(',"id":'.json_encode($this->id).'}');
			$this->state = self::STATE_FINAL;
		}

		return $this;
	}
}

?>
