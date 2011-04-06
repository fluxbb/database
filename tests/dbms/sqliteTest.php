<?php

require_once dirname(__FILE__).'/../db.php';

class Database_SQLiteTest extends DatabaseTestCase
{
	public static function setUpBeforeClass()
	{
		self::$db = new Database('sqlite::memory:', array(), 'sqlite');
		self::$db->start_transaction();
	}

	public static function tearDownAfterClass()
	{
		self::$db->commit_transaction();
	}
}
