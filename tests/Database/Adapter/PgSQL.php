<?php

require_once dirname(__FILE__).'/../../../src/Database/Adapter.php';
require_once dirname(__FILE__).'/../Adapter.php';

class Flux_Database_Adapter_PgSQLTestCase extends Flux_Database_AdapterTestCase
{
	public function createAdapter()
	{
		return Flux_Database_Adapter::factory('PgSQL', array('dbname' => $GLOBALS['DB_PGSQL_DBNAME'], 'username' => $GLOBALS['DB_PGSQL_USER'], 'password' => $GLOBALS['DB_PGSQL_PASSWD']));
	}
}
