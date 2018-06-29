<?php
namespace includes\mom;

use includes\mom\MOMBaseException as BaseException;
use includes\mom\MOMMySQLException as MySQLException;

class MOMCompound extends MOMBase
{
	/**
	  * Constructs an object of the extending class using parent constructor
	  * Checks if compound keys has been defined on the extending class
	  * @param \PDO $connection PDO connection
	  * @param \memcached $memcache memcache connection
	  * @param int $memcacheExpiration memcache expiration in seconds
	  */
	public function __construct(\PDO $connection = NULL, \Memcached $memcache = NULL, $memcacheExpiration = 0)
	{
		self::hasCompoundKeys();
		parent::__construct($connection, $memcache, $memcacheExpiration);
	}

	/**
	  * Get by compound key
	  * @param mixed[] $ids
	  * @throws BaseException
	  * @throws MySQLException
	  * @return object
	  */
	public static function getByIds($ids)
	{
		$class = get_called_class();
		self::checkDbAndTableConstants($class);
		self::hasCompoundKeys();

		if (empty($ids) || !is_array($ids))
			throw new BaseException(BaseException::OBJECT_NOT_FOUND, get_called_class().'::'.__FUNCTION__.' got empty compound key value');

		$new = NULL;

		if (($row = self::getRowByIdsStatic($ids)) !== false)
		{
			$new = new static();
			$new->fillByStatic($row);
		}

		return $new;
	}

	/**
	  * Save the object
	  * @throws BaseException
	  */
	public function save()
	{
		$sql = static::buildSaveSql();

		$this->tryToSave($sql);

		$ids = array();
		foreach (self::getCompoundKeys() as $key)
			$ids[$key] = $this->$key;

		if (($row = self::getRowByIds($ids)) === NULL)
			throw new BaseException(BaseException::OBJECT_NOT_UPDATED, get_called_class().'->'.__FUNCTION__.' failed to update object with data from database');

		$this->fillByObject($row);
	}

	/**
	  * Delete the object
	  * Will throw exceptions on all errors, if no exception, then object is deleted
	  * @throws BaseException
	  */
	public function delete()
	{
		$keys = $this->getKeyPairs();

		$sql =
			'DELETE FROM `'.self::getDbName().'`.`'.static::TABLE.'`'.
			' WHERE '.join(' AND ', $keys);

		static::tryToDelete($sql);
	}

	/**
	  * Build sql statement for saving a compound object
	  * @return string
	  */
	protected function buildSaveSql()
	{
		$values = $this->getValuePairs();
		$keys = $this->getKeyPairs();

		if ($this->__mbNewObject)
		{
			$values = array_merge($keys, $values);

			$sql =
				'INSERT INTO `'.self::getDbName().'`.`'.static::TABLE.'` SET'.
				' '.join(', ', $values);
		}
		else
		{
			$sql =
				'UPDATE `'.self::getDbName().'`.`'.static::TABLE.'` SET'.
				' '.join(', ', $values).
				' WHERE '.join(' AND ', $keys);

		}

		return $sql;
	}

	/**
	  * Get object value pairs
	  * Returns mysql field and value string
	  * Several rows of pairs like these: `field` = 'value'
	  * @return string[]
	  */
	protected function getValuePairs()
	{
		$values = array();
		$compoundKeys = self::getCompoundKeys();
		foreach (static::$__mbDescriptions[get_called_class()] as $field)
		{
			if (!in_array($field['Field'], $compoundKeys) &&
				!in_array($field['Default'], self::$__mbProtectedValueDefaults))
				$values[] = $this->escapeObjectPair($field['Field'], $field['Type']);

		}

		return $values;
	}

	/**
	  * Explode constant COLUMN_COMPOUND_KEYS
	  * @return string[]
	  */
	protected static function getCompoundKeys()
	{
		return array_map('trim', explode(',', static::COLUMN_COMPOUND_KEYS));
	}

	/**
	  * Get mysql row by primary key
	  * @param mixed $id escaped
	  * @throws MySQLException
	  * @return resource(mysql resource) or NULL on failure
	  */
	private function getRowByIds($ids)
	{
		$sql = self::buildCompoundSql($ids, array($this, 'escapeObject'));

		$res = $this->queryObject($sql);

		return $res->fetch();
	}

	/**
	  * Get mysql row by primary key
	  * @param mixed $id escaped
	  * @throws MySQLException
	  * @return resource(mysql resource) or NULL on failure
	  */
	private static function getRowByIdsStatic($ids)
	{
		$sql = self::buildCompoundSql($ids, 'self::escapeStatic');

		$res = self::queryStatic($sql);

		return $res->fetch();
	}

	/**
	  * Get object key pairs
	  * Returns mysql field and value string
	  * Several rows of pairs like these: `field` = 'value'
	  * @return string[]
	  */
	private function getKeyPairs()
	{
		$wheres = '';
		$description = static::$__mbDescriptions[get_called_class()];
		foreach (self::getCompoundKeys() as $key)
		{
			if (!isset($this->$key))
				throw new BaseException(BaseException::COMPOUND_KEY_MISSING_VALUE, get_called_class().'->'.__FUNCTION__.' failed to save object to database, '.$key.' is not set on object');

			$wheres[] = $this->escapeObjectPair($key, $description[$key]['Type']);
		}

		return $wheres;
	}

	/**
	  * Build sql statement used for fetching compound object
	  * @param string[] $ids contains key => value pairs that make up the compound key
	  * @param callback $callback
	  * @return string sql statement
	  */
	private static function buildCompoundSql($ids, $callback)
	{
		$wheres = array();
		foreach (self::getCompoundKeys() as $key)
		{
			if (!array_key_exists($key, $ids))
				throw new BaseException(BaseException::COMPOUND_KEY_MISSING_IN_WHERE, get_called_class().'->'.__FUNCTION__.' failed to fetch object from database, '.$key.' is not present amoung ids');

			if ($ids[$key] !== NULL)
				$wheres[] = '`'.$key.'` = '.call_user_func($callback, $ids[$key]);
			else
				$wheres[] = '`'.$key.'` = NULL';
		}

		$sql =
			'SELECT * FROM `'.self::getDbName().'`.`'.static::TABLE.'`'.
			' WHERE '.join(' AND ', $wheres);

		return $sql;
	}

	/**
	  * Get a rows unique identifier, e.g. primary key, or a compound key
	  * @return string
	  */
	protected function getRowIdentifier()
	{
		$identifier = '';
		foreach (self::getCompoundKeys() as $key)
			$identifier .= $this->{$key};

		return $identifier;
	}

	/**
	  * Checks if the extending class has defined a primary key
	  * @throws BaseException
	  */
	private static function hasCompoundKeys()
	{
		if (!defined('static::COLUMN_COMPOUND_KEYS'))
			throw new BaseException(BaseException::COMPOUND_KEYS_NOT_DEFINED, get_called_class().' has no COLUMN_COMPOUND_KEYS const');
	}
}
?>
