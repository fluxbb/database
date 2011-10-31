<?php

define('PHPCACHE_ROOT', realpath(dirname(__FILE__).'/../../src/').'/');
require PHPCACHE_ROOT.'Database/Adapter.php';

abstract class Flux_Database_AdapterTestCase extends PHPUnit_Framework_TestCase
{
	/**
	 * @var Flux_Database_Adapter
	 */
	protected $db;

	public function setUp()
	{
		$this->db = $this->createAdapter();
	}

	/**
	 * @return Flux_Database_Adapter
	 */
	abstract public function createAdapter();

	public function testCreateAndRemoveTable()
	{
		$q1 = $this->db->createTable('test1');
		$q1->run();

		$q2 = $this->db->tableExists('test1');
		$r2 = $q2->run();
		$this->assertTrue($r2);

		$q3 = $this->db->dropTable('test1');
		$q3->run();

		$r4 = $q2->run();
		$this->assertFalse($r4);
	}
}
