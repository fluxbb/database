<?php

require_once dirname(__FILE__).'/../../../src/Database/Adapter.php';
require_once dirname(__FILE__).'/../AdapterTest.php';

class Flux_Database_Adapter_SQLiteTest extends Flux_Database_AdapterTest
{
	public function createAdapter()
	{
		if (!in_array('sqlite', PDO::getAvailableDrivers())) {
            $this->markTestSkipped(
              'The SQLite driver cannot be loaded.'
            );
        }

		return Flux_Database_Adapter::factory('SQLite', array('dbname' => $GLOBALS['DB_SQLITE_DBNAME']));
	}
}
