<?php
namespace includes\db;

class Database
{
  const HOST = 'localhost';
  const PORT = '3306';
  const USERNAME = 'root';
  const PASSWORD = 'root';

  /**
  * Get a PDO connection
  * @return \PDO
  * @throws DatabaseException if a connection could not be created
  */
  public static function getConnForDB()
  {
    $connection = new \PDO('mysql:host='.self::HOST, self::USERNAME, self::PASSWORD);
    includes\mom\MOMBase::setConnection($connection, TRUE);
  }
}
