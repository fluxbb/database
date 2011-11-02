<?php

require_once dirname(__FILE__).'/../../../src/Database/Adapter.php';
require_once dirname(__FILE__).'/../AdapterTest.php';

class Flux_Database_Adapter_SQLiteTestCase extends Flux_Database_AdapterTestCase
{
	public function createAdapter()
	{
		return Flux_Database_Adapter::factory('SQLite', array('file' => $GLOBALS['DB_SQLITE_FILE']));
	}
}
