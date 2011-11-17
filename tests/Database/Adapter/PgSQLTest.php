<?php

require_once dirname(__FILE__).'/../../../src/Database/Adapter.php';
require_once dirname(__FILE__).'/../AdapterTest.php';

class Flux_Database_Adapter_PgSQLTest extends Flux_Database_AdapterTest
{
	public function createAdapter()
	{
		if (!in_array('pgsql', PDO::getAvailableDrivers())) {
            $this->markTestSkipped(
              'The PgSQL driver cannot be loaded.'
            );
        }

		return Flux_Database_Adapter::factory('PgSQL', array('dbname' => DB_PGSQL_DBNAME, 'username' => DB_PGSQL_USER, 'password' => DB_PGSQL_PASSWD));
	}
}
