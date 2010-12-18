<?php

/**
* Copyright (C) 2010 Jamie Furness (http://www.jamierf.co.uk)
* License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
*/

if (!defined('PHPDB_ROOT'))
	define('PHPDB_ROOT', dirname(__FILE__).'/');

require PHPDB_ROOT.'query.php';
require PHPDB_ROOT.'dialect.php';
require PHPDB_ROOT.'result.php';

abstract class Database
{
	const SQL_DIALECT = null;

	public static function load($type, $args = array())
	{
		if (!class_exists('Database_'.$type))
			require PHPDB_ROOT.'drivers/'.$type.'.php';

		// Confirm the chosen class extends us
		$class = new ReflectionClass('Database_'.$type);
		if ($class->isSubclassOf('Database') === false)
			throw new Exception('Does not conform to the database interface: '.$type);

		// Instantiate the database
		$db = $class->newInstance($args);

		// Load the SQL dialect translator, with the table prefix if there is one
		$db->set_dialect($db::SQL_DIALECT, isset($args['prefix']) ? $args['prefix'] : '');

		return $db;
	}

	private $dialect;

	protected function set_dialect($type, $prefix)
	{
		// We are just using the default dialect
		if ($type === null)
		{
			$this->dialect = new SQLDialect($prefix);
			return;
		}

		if (!class_exists('SQLDialect_'.$type))
			require PHPDB_ROOT.'dialects/'.$type.'.php';

		// Confirm the chosen class implements SQLDialect
		$class = new ReflectionClass('SQLDialect_'.$type);
		if ($class->isSubclassOf('SQLDialect') === false)
			throw new Exception('Does not conform to the SQLDialect interface: '.$type);

		// Instantiate the dialect
		$this->dialect = $class->newInstance($prefix);
	}

	public function compile($query)
	{
		return $this->dialect->compile($query);
	}

	public function query()
	{
		$args = func_get_args();
		$query = array_shift($args);

		// TODO: We actually need a custom vsprintf here, since we want to treat %d etc normally, but escape and add quotes around %s
		$args = array_map(array($this, 'escape'), $args);
		$query = vsprintf($query, $args);

		$result = $this->_query($query);
		if ($result === false)
			throw new Exception($this->error());

		if (is_object($result) || is_resource($result))
			return new DatabaseResult($result, $this);

		return $result;
	}

	protected abstract function _query($query);

	public abstract function start_transaction();
	public abstract function commit_transaction();
	public abstract function rollback_transaction();

	public abstract function escape($str);
	public abstract function insert_id();
	public abstract function error();

	public abstract function affected_rows($result);
	public abstract function has_rows($result);
	public abstract function fetch_row($result);
	public abstract function fetch_assoc($result);
	public abstract function free($result);

	public function close()
	{
		$this->_close();
	}

	protected abstract function _close();
}
