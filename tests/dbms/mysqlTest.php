<?php

require_once dirname(__FILE__).'/../db.php';

class Database_SQLiteTest extends DatabaseTestCase
{
	public static function setUpBeforeClass()
	{
		self::$db = new Database('mysql:dbname=test_db;host=sparrow', array('username' => 'test', 'password' => 'BHGFV8puamD48DR2'), 'mysql');
		self::$db->start_transaction();
	}

	public static function tearDownAfterClass()
	{
		self::$db->commit_transaction();
	}
}
