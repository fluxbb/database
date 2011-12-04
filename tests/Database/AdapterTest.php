<?php

define('PHPDB_ROOT', realpath(dirname(__FILE__).'/../../src/').'/');
require_once PHPDB_ROOT.'Database/Adapter.php';
require_once 'PHPUnit/Framework/TestCase.php';

abstract class Flux_Database_AdapterTest extends PHPUnit_Framework_TestCase
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

	public function testCRUD()
	{
		$q1 = $this->db->createTable('test1');
		$q1->field('username', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(40));
		$q1->field('name', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(100));
		$r1 = $q1->run();

		$this->assertTrue($r1);

		$q2 = $this->db->insert(array('username' => ':username', 'name' => ':name'), 'test1');
		$params = array(':username' => 'lie2815', ':name' => 'Franz');
		$r2_1 = $q2->run($params);
		$params = array(':username' => 'reines', ':name' => 'Jamie');
		$r2_2 = $q2->run($params);

		$this->assertEquals(1, $r2_1);
		$this->assertEquals(1, $r2_2);

		$q3 = $this->db->update(array('name' => ':name'), 'test1');
		$q3->where = 'username = :username';
		$params = array(':username' => 'lie2815', ':name' => 'Franz Liedke');
		$r3_1 = $q3->run($params);
		$r3_2 = $q3->run($params);

		// Match found rows, not replaced ones
		$this->assertEquals(1, $r3_1);
		$this->assertEquals(1, $r3_2);

		$q4 = $this->db->select(array('*'), 'test1');
		$q4->order = array('username ASC');
		$r4 = $q4->run();

		$expected = array(
			array(
				'username'	=> 'lie2815',
				'name'		=> 'Franz Liedke',
			),
			array(
				'username'	=> 'reines',
				'name'		=> 'Jamie',
			),
		);

		$this->assertEquals($expected, $r4);

		$q5 = $this->db->delete('test1');
		$q5->where = 'username = :username';
		$params = array(':username' => 'lie2815');
		$r5_1 = $q5->run($params);
		$params = array(':username' => 'reines');
		$r5_2 = $q5->run($params);

		$this->assertEquals(1, $r5_1);
		$this->assertEquals(1, $r5_2);

		$q6 = $this->db->dropTable('test1');
		$r6 = $q6->run();

		$this->assertTrue($r6);
	}

	public function testReplaceQuery()
	{
		$q1 = $this->db->createTable('test2');
		$q1->field('username', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(40));
		$q1->field('name', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(100));
		$q1->index('PRIMARY', array('username'));
		$r1 = $q1->run();

		$this->assertTrue($r1);

		$q2 = $this->db->insert(array('username' => ':username', 'name' => ':name'), 'test2');
		$params = array(':username' => 'reines', ':name' => 'Jamie');
		$r2 = $q2->run($params);

		$this->assertEquals(1, $r2);

		$q3 = $this->db->replace(array('name' => ':name'), 'test2', array('username' => ':username'));
		$params = array(':username' => 'lie2815', ':name' => 'Franz');
		$r3 = $q3->run($params);

		$this->assertEquals(1, $r3);

		$q4 = $this->db->replace(array('name' => ':name'), 'test2', array('username' => ':username'));
		$params = array(':username' => 'lie2815', ':name' => 'Franz Liedke');
		$r4 = $q4->run($params);

		$this->assertEquals(2, $r4);

		$q5 = $this->db->select(array('username', 'name'), 'test2');
		$q5->order = array('username ASC');
		$r5 = $q5->run();

		$expected = array(
			array(
				'username'	=> 'lie2815',
				'name'		=> 'Franz Liedke',
			),
			array(
				'username'	=> 'reines',
				'name'		=> 'Jamie',
			),
		);

		$this->assertEquals($expected, $r5);

		$q6 = $this->db->dropTable('test2');
		$r6 = $q6->run();

		$this->assertTrue($r6);
	}

	public function testCreateAndRemoveTable()
	{
		$q1 = $this->db->createTable('test1');
		$q1->field('id', Flux_Database_Query_Helper_TableColumn::TYPE_SERIAL);
		$q1->index('PRIMARY', array('id'));
		$q1->run();

		$q2 = $this->db->tableExists('test1');
		$r2 = $q2->run();
		$this->assertTrue($r2);

		$q3 = $this->db->dropTable('test1');
		$q3->run();

		$r4 = $q2->run();
		$this->assertFalse($r4);
	}

	public function testFillAndEmptyTable()
	{
		$q1 = $this->db->createTable('test2');
		$q1->field('id', Flux_Database_Query_Helper_TableColumn::TYPE_SERIAL);
		$q1->field('number', Flux_Database_Query_Helper_TableColumn::TYPE_INT);
		$q1->index('PRIMARY', array('id'));
		$q1->run();

		$q2 = $this->db->insert(array('number' => ':num'), 'test2');
		$params = array(':num' => 4);
		$r2 = $q2->run($params);
		$this->assertEquals(1, $r2);

		$q3 = $this->db->select(array('number'), 'test2');
		$r3 = $q3->run();
		$this->assertEquals(1, count($r3));
		$this->assertEquals(4, $r3[0]['number']);

		$q4 = $this->db->truncate('test2');
		$q4->run();

		$r5 = $q3->run();
		$this->assertEquals(0, count($r5));

		$q6 = $this->db->dropTable('test2');
		$q6->run();
	}

	public function testComplexTableInfo()
	{
		$q1 = $this->db->createTable('test3');
		$q1->field('id', Flux_Database_Query_Helper_TableColumn::TYPE_SERIAL);
		$q1->field('number', Flux_Database_Query_Helper_TableColumn::TYPE_INT);
		$q1->index('PRIMARY', array('id'));
		$q1->index('number_idx', array('number'), true);
		$r1 = $q1->run();
		$this->assertTrue($r1);

		$q2 = $this->db->tableInfo('test3');
		$r2 = $q2->run();

		$expected = array(
			'columns' => array(
				'id'	=> array(
					'type'			=> 'INTEGER',
					'allow_null'	=> false,
				),
				'number'	=> array(
					'type'			=> 'INTEGER',
					'allow_null'	=> true,
					'default'		=> null,
				),
			),
			'primary_key'	=> array('id'),
			'unique'		=> array(
				array('number'),
			),
			'indices'		=> array(
				'number_idx' => array(
					'fields'	=> array('number'),
					'unique'	=> true,
				),
			),
		);

		$this->assertEquals($expected, $r2);

		$q3 = $this->db->dropTable('test3');
		$r3 = $q3->run();
		$this->assertTrue($r3);
	}

	public function testDefaultValues()
	{
		$q1 = $this->db->createTable('test4');
		$q1->field('id', Flux_Database_Query_Helper_TableColumn::TYPE_SERIAL);
		$q1->field('default_null', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(), 'abc', true);
		$q1->field('default_not_null', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(), 'abc', false);
		$q1->field('no_default_null', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(), null, true);
		$q1->field('no_default_not_null', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(), null, false);
		$q1->index('PRIMARY', array('id'));
		$r1 = $q1->run();
		$this->assertTrue($r1);

		$q2 = $this->db->tableInfo('test4');
		$r2 = $q2->run();

		$this->assertTrue(array_key_exists('default', $r2['columns']['default_null']));
		$this->assertEquals('abc', $r2['columns']['default_null']['default']);

		$this->assertTrue(array_key_exists('default', $r2['columns']['default_not_null']));
		$this->assertEquals('abc', $r2['columns']['default_not_null']['default']);

		$this->assertTrue(array_key_exists('default', $r2['columns']['no_default_null']));
		$this->assertNull($r2['columns']['no_default_null']['default']);

		$this->assertFalse(array_key_exists('default', $r2['columns']['no_default_not_null']));

		$q3 = $this->db->dropTable('test4');
		$r3 = $q3->run();
	}
}
