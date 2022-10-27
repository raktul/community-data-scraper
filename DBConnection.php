<?php

class DatabaseConnection {
	private static $db;
	private $connection;
	
	private $servername = "localhost";
	private $username = "root";
	private $password = "";
	private static $dbname = "community_records";
	
	private function __construct() {
		$this->connection = new mysqli($this->servername, $this->username, $this->password, self::$dbname);
	}

	public static function getConnection() {
		if(self::$db == null) {
			self::$db = new DatabaseConnection();
		}
		return self::$db->connection;
	}

	public static function getDBName(){
		return self::$dbname;
	}
}

?>