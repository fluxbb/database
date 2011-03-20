<?php

/**
* Copyright (C) 2011 FluxBB (http://fluxbb.org)
* License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
*/

if (!defined('PHPDB_ROOT'))
	define('PHPDB_ROOT', dirname(__FILE__).'/');

require PHPDB_ROOT.'query.php';
require PHPDB_ROOT.'dialect.php';

class Database
{
	const DEFAULT_CHARSET = 'utf8';

	protected $pdo;
	protected $dialect;
	protected $debug;
	protected $queries;

	public function __construct($dsn, $args = array(), $dialect = null)
	{
		$username = isset($args['username']) ? $args['username'] : '';
		$password = isset($args['password']) ? $args['password'] : '';
		$options = isset($args['options']) ? $args['options'] : array();
		$prefix = isset($args['prefix']) ? $args['prefix'] : '';

		// Check if we should store debug information
		$this->debug = isset($args['debug']) ? $args['debug'] : false;
		$this->queries = array();

		$this->pdo = new PDO($dsn, $username, $password, $options);
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		// We are just using the default dialect
		if ($dialect === null)
			$this->dialect = new SQLDialect($prefix);
		else
		{
			if (!class_exists('SQLDialect_'.$dialect))
				require PHPDB_ROOT.'dialects/'.$dialect.'.php';

			// Instantiate the dialect
			$dialect = 'SQLDialect_'.$dialect;
			$this->dialect = new $dialect($prefix);
		}

		$charset = isset($args['charset']) ? $args['charset'] : self::DEFAULT_CHARSET;

		// Attempt to set names
		$query = new SetNamesQuery(':charset');
		$this->query($query, array(':charset' => $charset));
	}

	public function query(DatabaseQuery $query, $args = array())
	{
		// If debug is enabled, note the start time
		if ($this->debug)
			$query_start = microtime(true);

		// If the query hasn't already been compiled
		if ($query->sql === null)
			$query->sql = $this->dialect->compile($query);

		// If there is no query then do nothing
		if (empty($query->sql))
			return false;

		// If the statement hasn't already been prepared
		if ($query->statement === null)
			$query->statement = $this->pdo->prepare($query->sql);

		// Execute the actual statement, and check if an error occured
		if ($query->statement->execute($args) === false)
		{
			$error = $query->statement->errorInfo();
			throw new Exception($error[2]);
		}

		// If debug is enabled, note this query and how long it took
		if ($this->debug)
			$this->queries[] = array('sql' => $query->sql, 'params' => $args, 'duration' => (microtime(true) - $query_start));

		// If it was a select query, return the results
		if ($query instanceof SelectQuery)
			return $query->statement->fetchAll(PDO::FETCH_ASSOC);

		// Otherwise return the number of affected rows
		return $query->statement->rowCount();
	}

	public function insert_id()
	{
		return $this->pdo->lastInsertId();
	}

	public function start_transaction()
	{
		return $this->pdo->beginTransaction();
	}

	public function commit_transaction()
	{
		return $this->pdo->commit();
	}

	public function rollback_transaction()
	{
		return $this->pdo->rollBack();
	}

	public function in_transaction()
	{
		return $this->pdo->inTransaction();
	}

	public function fetch_debug_queries()
	{
		return $this->queries;
	}
}
