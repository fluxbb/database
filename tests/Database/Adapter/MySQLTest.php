<?php

require_once dirname(__FILE__).'/../../../src/Database/Adapter.php';
require_once dirname(__FILE__).'/../AdapterTest.php';

class Flux_Database_Adapter_MySQLTest extends Flux_Database_AdapterTest
{
	public function createAdapter()
	{
		if (!in_array('mysql', PDO::getAvailableDrivers())) {
            $this->markTestSkipped(
              'The MySQL driver cannot be loaded.'
            );
        }

		return Flux_Database_Adapter::factory('MySQL', array('dbname' => DB_MYSQL_DBNAME, 'username' => DB_MYSQL_USER, 'password' => DB_MYSQL_PASSWD));
	}
}
