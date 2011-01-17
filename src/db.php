<?php

/**
* Copyright (C) 2010 Jamie Furness (http://www.jamierf.co.uk)
* License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
*/

if (!defined('PHPDB_ROOT'))
	define('PHPDB_ROOT', dirname(__FILE__).'/');

require PHPDB_ROOT.'query.php';
require PHPDB_ROOT.'dialect.php';

class Database
{
	private $pdo;
	private $dialect;

	public function __construct($dsn, $args = array(), $dialect = null)
	{
		$username = isset($args['username']) ? $args['username'] : '';
		$password = isset($args['password']) ? $args['password'] : '';
		$options = isset($args['options']) ? $args['options'] : array();
		$prefix = isset($args['prefix']) ? $args['prefix'] : '';

		$this->pdo = new PDO($dsn, $username, $password, $options);
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		// We are just using the default dialect
		if ($dialect === null)
			$this->dialect = new SQLDialect($prefix);
		else
		{
			if (!class_exists('SQLDialect_'.$dialect))
				require PHPDB_ROOT.'dialects/'.$dialect.'.php';

			// Confirm the chosen class implements SQLDialect
			$class = new ReflectionClass('SQLDialect_'.$dialect);
			if ($class->isSubclassOf('SQLDialect') === false)
				throw new Exception('Does not conform to the SQLDialect interface: '.$dialect);

			// Instantiate the dialect
			$this->dialect = $class->newInstance($prefix);
		}
	}

	public function query()
	{
		$args = func_get_args();
		$query = array_shift($args);

		// If the query hasn't already been compiled/prepared
		if ($query->statement === null)
		{
			$query->sql = $this->dialect->compile($query);
			$query->statement = $this->pdo->prepare($query->sql);
		}

		// Execute the actual statement
		$result = empty($args) ? $query->statement->execute() : $query->statement->execute($args);

		// Check if an error occured
		if ($query->statement->execute($args) === false)
		{
			$error = $query->statement->errorInfo();
			throw new Exception($error[2]);
		}

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
}
