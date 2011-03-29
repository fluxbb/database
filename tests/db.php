<?php

define('PHPCACHE_ROOT', realpath(dirname(__FILE__).'/../').'/');
require PHPCACHE_ROOT.'db.php';

abstract class DatabaseTestCase extends PHPUnit_Framework_TestCase
{
	protected static $db;

	public function testCreateDropTable()
	{
		$query = new CreateTableQuery('test', array(
			new TableColumn('id', TableColumn::TYPE_SERIAL),
			new TableColumn('username', TableColumn::TYPE_VARCHAR(255), null, TableColumn::KEY_UNIQUE),
		));

		self::$db->query($query);
		unset ($query);

		$query = new DropTableQuery('test');

		self::$db->query($query);
		unset ($query);
	}
}
