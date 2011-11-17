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
        
        $conf = array(
        		'dbname'	=> $_ENV['DB_SQLITE_DBNAME'],
        );

		return Flux_Database_Adapter::factory('SQLite', $conf);
	}
}
