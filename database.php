<?php
class Database
{
    private static $dbName = 'internal_search';
    private static $dbHost = 'localhost' ;
    private static $dbUsername = 'YourUserName';
    private static $dbUserPassword = 'YourPassword';
     
    private static $cont  = null;
     
    public function __construct() {
        die('Init function is not allowed');
    }
     
    public static function connect()
    {
		if (self::$cont == null) {     
			self::$cont =  new PDO( "mysql:host=".self::$dbHost.";"."dbname=".self::$dbName, self::$dbUsername, self::$dbUserPassword);
		}
		return self::$cont;
    }
     
    public static function disconnect()
    {
        self::$cont = null;
    }
}
?>