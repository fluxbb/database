<?php

require_once dirname(__FILE__).'/../../../Database/Adapter.php';

class Flux_Database_Adapter_PgSQLTestCase extends Flux_Database_AdapterTestCase
{
	public function createAdapter()
	{
		return Flux_Database_Adapter::factory('PgSQL');
	}
}
