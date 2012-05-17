<?php
/**
 * FluxBB
 *
 * LICENSE
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * @category	FluxBB
 * @package		Database
 * @copyright	Copyright (c) 2011 FluxBB (http://fluxbb.org)
 * @license		http://www.gnu.org/licenses/lgpl.html	GNU Lesser General Public License
 */

namespace fluxbb\database;

require_once dirname(__FILE__).'/Query.php';

abstract class Adapter
{
	/**
	 * The default connection charset that is used if none is specified when a
	 * new Database instance is created.
	 */
	const DEFAULT_CHARSET = 'utf8';

	protected $pdo = null;

	protected $options = array();

	protected $handles = array();

	protected $curHandle = 1;

	protected $queries = array();

	protected $type;

	protected $charset = 'utf8';

	public $prefix = '';

	/**
	 * Create a database adapter instance of the given type.
	 *
	 * @param string $type
	 * @param array[optional] $options
	 * @return Adapter
	 */
	public static function factory($type, array $options = array())
	{
		// Sanitise type
		if (preg_match('%[^A-Za-z0-9_]%', $type))
		{
			throw new \Exception('Illegal database adapter type.');
		}

		$name = '\\fluxbb\\database\\adapter\\'.$type;
		$file = str_replace('_', '/', 'Adapter_'.$type).'.php';

		if (file_exists(dirname(__FILE__).'/'.$file))
		{
			include_once dirname(__FILE__).'/'.$file;
			return new $name($options);
		}
		else
		{
			throw new \Exception('Database adapter type "'.$type.'" does not exist.');
		}
	}

	/**
	 * Get a list of all available database drivers
	 *
	 * @return array
	 */
	public static function getDriverList()
	{
		$return = array();
		$pdo_drivers = \PDO::getAvailableDrivers();
		foreach (glob(dirname(__FILE__).'/Adapter/*.php') as $file)
		{
			$name = substr(basename($file), 0, -4);

			if (in_array(strtolower($name), $pdo_drivers))
			{
				$return[] = $name;
			}
		}

		return $return;
	}

	/**
	 * Check whether the given database driver is available
	 *
	 * @param string $driver
	 * @return bool
	 */
	public static function driverExists($driver)
	{
		return in_array($driver, self::getDriverList());
	}

	public function __construct($options = array())
	{
		$this->options = $options;

		if (isset($this->options['prefix']))
		{
			$this->prefix = $this->options['prefix'];
		}
	}

	abstract public function generateDsn();

	protected function getPDO()
	{
		// Connect if we haven't yet
		if ($this->pdo == NULL)
		{
			$this->connect($this->generateDsn());
		}

		return $this->pdo;
	}

	/**
	 * Connect to the requested database via PDO.
	 *
	 * @param string $dsn
	 * 		The Data Source Name, see \PDO::__construct.
	 */
	public function connect($dsn)
	{
		$username = isset($this->options['username']) ? $this->options['username'] : '';
		$password = isset($this->options['password']) ? $this->options['password'] : '';
		$driver_options = isset($this->options['driver_options']) ? $this->options['driver_options'] : array();
		$prefix = isset($this->options['prefix']) ? $this->options['prefix'] : '';
		$charset = isset($this->options['charset']) ? $this->options['charset'] : self::DEFAULT_CHARSET;

		// Avoid displaying connection details by re-throwing the exception here
		try
		{
			$this->pdo = new \PDO($dsn, $username, $password, $driver_options);
			$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		}
		catch (\PDOException $e)
		{
			throw new \Exception('Unable to connect to database.');
		}

		// Fetch the driver type
		$this->type = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

		// Attempt to set names
		$this->setCharset($charset);

		// Set the table prefix
		$this->setPrefix($prefix);
	}

	/**
	 * Set the character set the database connection should use.
	 *
	 * @param string $charset
	 * 		The character set to use.
	 */
	public function setCharset($charset)
	{
		$sql = 'SET NAMES '.$this->quote($charset);
		if ($this->exec($sql) === false)
		{
			return;
		}

		$this->charset = $charset;
	}

	/**
	 * Set the table prefix to be used.
	 *
	 * @param string $prefix
	 * 		The table prefix to be used.
	 */
	public function setPrefix($prefix)
	{
		$this->prefix = $prefix;
	}

	/**
	 * Places quotes around the input string (if required) and escapes special
	 * characters within the input string, using a quoting style appropriate
	 * to the underlying driver.
	 *
	 * @param string $str
	 * 		The string to be quoted.
	 *
	 * @return string
	 * 		A quoted string that is theoretically safe to pass into a SQL statement.
	 */
	public function quote($str)
	{
		if (is_null($str))
		{
			return 'NULL';
		}
		else if (is_int($str))
		{
			return $str;
		}
		
		$quoted_str = $this->getPDO()->quote($str);
		
		if ($quoted_str === false)
		{
			$quoted_str = '\''.addslashes($str).'\'';
		}

		return $quoted_str;
	}

	public function prepare($sql)
	{
		$handle = $this->curHandle++;
		$this->handles[$handle] = array(
			'sql'			=> $sql,
			'statement'		=> NULL,
			'had_arrays'	=> false,
		);

		return $handle;
	}

	/**
	 * Execute a database query in a single function call.
	 *
	 * @param int $handle
	 * 		The handle of the statement to execute.
	 *
	 * @param array $params
	 * 		An array of parameters to combine with the query.
	 *
	 * @return \PDOStatement
	 * 		The executed \PDOStatement.
	 */
	public function execute($handle, array $params = array())
	{
		// Note the start time
		$query_start = microtime(true);

		$statement = $this->prepareStatement($handle, $params);

		// Execute the actual statement, and check if an error occurred
		if ($statement->execute($params) === false)
		{
			$error = $statement->errorInfo();
			throw new \Exception($error[2]);
		}

		// Note this query and how long it took
		$this->queries[] = array('sql' => $statement->queryString, 'params' => $params, 'duration' => (microtime(true) - $query_start));

		return $statement;
	}

	protected function prepareStatement($handle, &$params)
	{
		$h =& $this->handles[$handle];
		$sql = $h['sql'];

		// First compilation
		if ($h['statement'] == NULL)
		{
			$h['had_arrays'] = $this->handleArrays($sql, $params);
			$h['statement'] = $this->getPDO()->prepare($sql);
		}
		else if ($h['had_arrays'])
		{
			$this->handleArrays($sql, $params);
			$h['statement'] = $this->getPDO()->prepare($sql);
		}

		return $h['statement'];
	}

	/**
	 * Converts the placeholders for any array parameters into a
	 * comma separated list of placeholders, then merges the array into
	 * the main parameter array.
	 *
	 * This has the effect of converting IN :ids to IN (:ids0, :ids1, ...)
	 * when :ids is bound to an array.
	 *
	 * @param string $sql
	 * 		The compiled SQL query.
	 *
	 * @param array $params
	 * 		An array of parameters to be passed with the query.
	 *
	 * @return bool
	 * 		Whether arrays were found in the parameters.
	 */
	protected function handleArrays(&$sql, &$params)
	{
		$additions = array();

		foreach ($params as $key => $values)
		{
			if (!is_array($values))
			{
				continue;
			}

			// We found a param array, lets handle it
			$temp = array();
			$count = count($values);
			for ($i = 0;$i < $count;$i++)
			{
				$temp[] = $key.$i;
				$additions[$key.$i] = $values[$i];
			}

			// Replace the old placeholder with a collection of new ones
			$sql = str_replace($key, '('.implode(', ', $temp).')', $sql);
			unset ($params[$key], $temp);
		}

		if (!empty($additions))
		{
			$params = array_merge($params, $additions);
			return true;
		}

		return false;
	}

	/**
	 * Execute the given SQL, with the given params if necessary
	 *
	 * @param string $sql
	 * @param array[optional] $params
	 * @return \PDOStatement
	 * @throws \PDOException
	 */
	public function query($sql, array $params = array())
	{
		// Note the start time
		$query_start = microtime(true);

		if (empty($params))
		{
			$result = $this->getPDO()->query($sql);
		}
		else
		{
			$result = $this->getPDO()->prepare($sql);
			$result->execute($params);
		}

		// Note this query and how long it took
		$this->queries[] = array('sql' => $sql, 'params' => $params, 'duration' => (microtime(true) - $query_start));

		return $result;
	}

	public function exec($sql)
	{
		// Note the start time
		$query_start = microtime(true);

		$result = $this->getPDO()->exec($sql);

		// Note this query and how long it took
		$this->queries[] = array('sql' => $sql, 'params' => array(), 'duration' => (microtime(true) - $query_start));

		return $result;
	}

	/**
	 * Returns the ID of the last inserted row.
	 *
	 * @return string
	 *		A string representing the row ID of the last row that was inserted
	 * 		into the database.
	 */
	public function insertId()
	{
		return $this->getPDO()->lastInsertId();
	}

	/**
	 * Turns off autocommit mode. While autocommit mode is turned off, changes made
	 * to the database are not committed until you end the transaction by calling
	 * Adapter::commitTransaction. Calling Adapter::rollbackTransaction will roll
	 * back all changes to the database and return the connection to autocommit mode.
	 *
	 * @return bool
	 * 		TRUE on success or FALSE on failure.
	 */
	public function startTransaction()
	{
		return $this->getPDO()->beginTransaction();
	}

	/**
	 * Commits a transaction, returning the database connection to autocommit mode
	 * until the next call to Adapter::startTransaction starts a new transaction.
	 *
	 * @return bool
	 * 		TRUE on success or FALSE on failure.
	 */
	public function commitTransaction()
	{
		return $this->getPDO()->commit();
	}

	/**
	 * Rolls back the current transaction.
	 * 
	 * It is an error to call this method if no transaction is active.
	 * If the database was set to autocommit mode, this function will restore autocommit
	 * mode after it has rolled back the transaction.
	 *
	 * @return bool
	 * 		TRUE on success or FALSE on failure.
	 */
	public function rollbackTransaction()
	{
		return $this->getPDO()->rollBack();
	}

	/**
	 * Checks if a transaction is currently active.
	 *
	 * @return bool
	 * 		TRUE if a transaction is currently active, and FALSE if not.
	 */
	public function inTransaction()
	{
		return $this->getPDO()->inTransaction();
	}

	/**
	 * Fetch a list of all queries which have been executed since the connection
	 * was initiated.
	 *
	 * @return array
	 * 		A list of queries which have been previously executed.
	 */
	public function getExecutedQueries()
	{
		return $this->queries;
	}

	/**
	 * Fetch the underlying driver name and client/server versions.
	 *
	 * @return string
	 * 		A string in the format "driver_name client_version/server_version"
	 */
	public function getVersion()
	{
		$client = $server = '?';

		try
		{
			$client = $this->getPDO()->getAttribute(\PDO::ATTR_CLIENT_VERSION);
		}
		catch (\PDOException $e) {}
		
		try
		{
			$server = $this->getPDO()->getAttribute(\PDO::ATTR_SERVER_VERSION);
		}
		catch (\PDOException $e) {}

		return sprintf('%s %s/%s',
			$this->type,
			$client,
			$server
		);
	}


	/*
	 * QUERY TYPES
	 */

	/**
	 * Compile a SELECT query.
	 * 
	 * @param query\Select $query
	 * @throws \Exception
	 */
	public function compileSelect(query\Select $query)
	{
		if (empty($query->fields))
		{
			throw new \Exception('A SELECT query must select at least one field.');
		}

		$sql = 'SELECT '.($query->distinct ? 'DISTINCT ' : '').implode(', ', $query->fields);

		$table = $query->getTable();
		if (!empty($table))
		{
			$sql .= ' FROM '.$table;
		}

		if (!empty($query->joins))
		{
			$sql .= $this->compileJoin($query->joins);
		}

		if (!empty($query->where))
		{
			$sql .= $this->compileWhere($query->where);
		}

		if (!empty($query->group))
		{
			$sql .= $this->compileGroup($query->group);
		}

		if (!empty($query->having))
		{
			$sql .= $this->compileHaving($query->having);
		}

		if (!empty($query->order))
		{
			$sql .= $this->compileOrder($query->order);
		}

		if ($query->limit > 0 || $query->offset > 0)
		{
			$sql .= $this->compileLimitOffset($query->limit, $query->offset);
		}

		return $sql;
	}

	/**
	 * Compile an INSERT query.
	 * 
	 * @param query\Insert $query
	 * @throws \Exception
	 */
	public function compileInsert(query\Insert $query)
	{
		$table = $query->getTable();
		if (empty($table))
		{
			throw new \Exception('An INSERT query must have a table specified.');
		}

		if (empty($query->values))
		{
			throw new \Exception('An INSERT query must contain at least one value.');
		}

		$sql = 'INSERT INTO '.$table.' ('.implode(', ', array_keys($query->values)).') VALUES ('.implode(', ', array_values($query->values)).')';

		return $sql;
	}

	/**
	 * Compile an UPDATE query.
	 * 
	 * @param query\Update $query
	 * @throws \Exception
	 */
	public function compileUpdate(query\Update $query)
	{
		$table = $query->getTable();
		if (empty($table))
		{
			throw new \Exception('An UPDATE query must have a table specified.');
		}

		if (empty($query->values))
		{
			throw new \Exception('An UPDATE query must contain at least one value.');
		}

		$updates = array();
		foreach ($query->values as $key => $value)
		{
			$updates[] = $key.' = '.$value;
		}

		$sql = 'UPDATE '.$table.' SET '.implode(', ', $updates);

		if (!empty($query->where))
		{
			$sql .= $this->compileWhere($query->where);
		}

		return $sql;
	}

	/**
	 * Compile a DELETE query.
	 * 
	 * @param query\Delete $query
	 * @throws \Exception
	 */
	public function compileDelete(query\Delete $query)
	{
		$table = $query->getTable();
		if (empty($table))
		{
			throw new \Exception('A DELETE query must have a table specified.');
		}

		$sql = 'DELETE FROM '.$table;

		if (!empty($query->where))
		{
			$sql .= $this->compileWhere($query->where);
		}

		return $sql;
	}

	/**
	 * Compile and run a REPLACE query.
	 * 
	 * @param query\Replace $query
	 * @param array $params
	 * @return int
	 * @throws \Exception
	 */
	public function runReplace(query\Replace $query, array $params = array())
	{
		$table = $query->getTable();
		if (empty($table))
		{
			throw new \Exception('A REPLACE query must have a table specified.');
		}

		if (empty($query->values))
		{
			throw new \Exception('A REPLACE query must contain at least one value.');
		}

		if (empty($query->keys))
		{
			throw new \Exception('A REPLACE query must contain at least one key.');
		}

		$values = array_merge($query->keys, $query->values);

		$sql = 'REPLACE INTO '.$table.' ('.implode(', ', array_keys($values)).') VALUES ('.implode(', ', array_values($values)).')';
		$result = $this->query($sql, $params);
		return $result->rowCount();
	}

	/**
	 * Compile and run a TRUNCATE query.
	 * 
	 * @param query\Truncate $query
	 * @return void
	 * @throws \Exception
	 * @throws \PDOException
	 */
	public function runTruncate(query\Truncate $query)
	{
		$table = $query->getTable();
		if (empty($table))
		{
			throw new \Exception('A TRUNCATE query must have a table specified.');
		}

		$sql = 'TRUNCATE TABLE '.$table;
		$this->exec($sql);
	}

	/**
	 * Compile and run a CREATE TABLE query.
	 * 
	 * @param query\CreateTable $query
	 * @return void
	 * @throws \Exception
	 * @throws \PDOException
	 */
	public function runCreateTable(query\CreateTable $query)
	{
		$table = $query->getTable();
		if (empty($table))
		{
			throw new \Exception('A CREATE TABLE query must have a table specified.');
		}

		if (empty($query->fields))
		{
			throw new \Exception('A CREATE TABLE query must contain at least one field.');
		}

		$fields = array();
		foreach ($query->fields as $field)
		{
			$fields[] = $this->compileColumnDefinition($field);
		}

		$sql = 'CREATE TABLE '.$table.' ('.implode(', ', $fields);

		if (!empty($query->primary))
		{
			$sql .= ', PRIMARY KEY ('.implode(', ', $query->primary).')';
		}

		$sql .= ')';

		$this->exec($sql);

		if (!empty($query->indices))
		{
			foreach ($query->indices as $name => $index)
			{
				// Add indices manually
				$q = $this->addIndex($table, $name);
				$q->fields = $index['columns'];
				$q->unique = $index['unique'];
				$q->usePrefix = false;
				$q->run();
			}
		}
	}

	/**
	 * Compile and run a RENAME TABLE query.
	 * 
	 * @param query\RenameTable $query
	 * @return void
	 * @throws \Exception
	 * @throws \PDOException
	 */
	public function runRenameTable(query\RenameTable $query)
	{
		$table = $query->getTable();
		if (empty($table))
		{
			throw new \Exception('A RENAME TABLE query must have a table specified.');
		}

		$new_name = $query->getNewName();
		if (empty($new_name))
		{
			throw new \Exception('A RENAME TABLE query must have a new table name specified.');
		}

		$sql = 'ALTER TABLE '.$table.' RENAME TO '.$new_name;
		$this->exec($sql);
	}

	/**
	 * Compile and run a DROP TABLE query.
	 * 
	 * @param query\DropTable $query
	 * @return void
	 * @throws \Exception
	 * @throws \PDOException
	 */
	public function runDropTable(query\DropTable $query)
	{
		$table = $query->getTable();
		if (empty($table))
		{
			throw new \Exception('A DROP TABLE query must have a table specified.');
		}

		$sql = 'DROP TABLE '.$table;
		$this->exec($sql);
	}

	/**
	 * Compile and run a TABLE EXISTS query.
	 * 
	 * @param query\TableExists $query
	 * @return bool
	 * @throws \Exception
	 * @throws \PDOException
	 */
	public function runTableExists(query\TableExists $query)
	{
		$table = $query->getTable();
		if (empty($table))
		{
			throw new \Exception('A TABLE EXISTS query must have a table specified.');
		}

		$sql = 'SHOW TABLES LIKE '.$this->quote($table);
		return (bool) $this->query($sql)->fetchColumn();
	}

	/**
	 * Compile and run an ADD FIELD query.
	 * 
	 * @param query\AddField $query
	 * @return void
	 * @throws \Exception
	 * @throws \PDOException
	 */
	public function runAddField(query\AddField $query)
	{
		$table = $query->getTable();
		if (empty($table))
		{
			throw new \Exception('An ADD FIELD query must have a table specified.');
		}

		if ($query->field == NULL)
		{
			throw new \Exception('An ADD FIELD query must have field information specified.');
		}

		$field = $this->compileColumnDefinition($query->field);

		$sql = 'ALTER TABLE '.$table.' ADD COLUMN '.$field;
		$this->exec($sql);
	}

	/**
	 * Compile and run an ALTER FIELD query.
	 * 
	 * @param query\AlterField $query
	 * @return void
	 * @throws \Exception
	 * @throws \PDOException
	 */
	public function runAlterField(query\AlterField $query)
	{
		$table = $query->getTable();
		if (empty($table))
		{
			throw new \Exception('An ALTER FIELD query must have a table specified.');
		}

		if ($query->field == NULL)
		{
			throw new \Exception('An ALTER FIELD query must have field information specified.');
		}

		$field = $this->compileColumnDefinition($query->field);

		$sql = 'ALTER TABLE '.$table.' MODIFY '.$query->field->name.' '.$field;
		$this->exec($sql);
	}

	/**
	 * Compile and run a DROP FIELD query.
	 * 
	 * @param query\DropField $query
	 * @return void
	 * @throws \Exception
	 * @throws \PDOException
	 */
	public function runDropField(query\DropField $query)
	{
		$table = $query->getTable();
		if (empty($table))
		{
			throw new \Exception('A DROP FIELD query must have a table specified.');
		}

		if (empty($query->field))
		{
			throw new \Exception('A DROP FIELD query must have a field specified.');
		}

		$sql = 'ALTER TABLE '.$table.' DROP '.$query->field;
		$this->exec($sql);
	}

	/**
	 * Compile and run a FIELD EXISTS query.
	 * 
	 * @param query\FieldExists $query
	 * @return bool
	 * @throws \Exception
	 * @throws \PDOException
	 */
	public function runFieldExists(query\FieldExists $query)
	{
		$table = $query->getTable();
		if (empty($table))
		{
			throw new \Exception('A FIELD EXISTS query must have a table specified.');
		}

		if (empty($query->field))
		{
			throw new \Exception('A FIELD EXISTS query must have a field specified.');
		}

		$sql = 'SHOW COLUMNS FROM '.$table.' LIKE '.$this->quote($query->field);
		return (bool) $this->query($sql)->fetchColumn();
	}

	/**
	 * Compile and run an ADD INDEX query.
	 * 
	 * @param query\AddIndex $query
	 * @result string
	 * @throws \Exception
	 * @throws \PDOException
	 */
	public function runAddIndex(query\AddIndex $query)
	{
		$table = $query->getTable();
		if (empty($table))
		{
			throw new \Exception('An ADD INDEX query must have a table specified.');
		}

		if (empty($query->index))
		{
			throw new \Exception('An ADD INDEX query must have an index specified.');
		}

		if (empty($query->fields))
		{
			throw new \Exception('An ADD INDEX query must have at least one field specified.');
		}

		$sql = 'ALTER TABLE '.$table.' ADD '.($query->unique ? 'UNIQUE ' : '').'INDEX '.$table.'_'.$query->index.' ('.implode(',', array_keys($query->fields)).')';
		$this->exec($sql);
	}

	/**
	 * Compile and run a DROP INDEX query.
	 * 
	 * @param query\DropIndex $query
	 * @return void
	 * @throws \Exception
	 * @throws \PDOException
	 */
	public function runDropIndex(query\DropIndex $query)
	{
		$table = $query->getTable();
		if (empty($table))
		{
			throw new \Exception('A DROP INDEX query must have a table specified.');
		}

		if (empty($query->index))
		{
			throw new \Exception('A DROP INDEX query must have an index specified.');
		}

		$sql = 'ALTER TABLE '.$table.' DROP INDEX '.$table.'_'.$query->index;
		$this->exec($sql);
	}

	/**
	 * Compile and run an INDEX EXISTS query.
	 * 
	 * @param query\IndexExists $query
	 * @return bool
	 * @throws \Exception
	 * @throws \PDOException
	 */
	public function runIndexExists(query\IndexExists $query)
	{
		$table = $query->getTable();
		if (empty($table))
		{
			throw new \Exception('An INDEX EXISTS query must have a table specified.');
		}

		if (empty($query->index))
		{
			throw new \Exception('An INDEX EXISTS query must have an index specified.');
		}

		$sql = 'SHOW INDEX FROM '.$table.' WHERE Key_name = '.$this->quote($table.'_'.$query->index);
		return (bool) $this->query($sql)->fetchColumn();
	}

	/**
	 * Run a table info query.
	 * 
	 * @param query\TableInfo $query
	 * @return array
	 */
	abstract public function runTableInfo(query\TableInfo $query);

	/**
	 * Compile a table column definition.
	 * 
	 * @param query\Helper_TableColumn $column
	 * @return string
	 */
	protected function compileColumnDefinition(query\Helper_TableColumn $column)
	{
		if ($column->type === query\Helper_TableColumn::TYPE_SERIAL)
		{
			return $this->compileColumnSerial($column->name);
		}

		$sql = $column->name.' '.$this->compileColumnType($column->type);

		if (!$column->allow_null)
		{
			$sql .= ' NOT NULL';
		}

		if ($column->default !== NULL)
		{
			$sql .= ' DEFAULT '.$this->quote($column->default);
		}
		else if ($column->allow_null)
		{
			$sql .= ' DEFAULT NULL';
		}

		if (!empty($column->collation))
		{
			$sql .= ' COLLATE '.$this->quote($this->charset.'_'.$column->collation);
		}

		return $sql;
	}

	/**
	 * Compile a table column type definition.
	 * 
	 * @param string $type
	 * @return string
	 */
	protected function compileColumnType($type)
	{
		return $type;
	}

	/**
	 * Compile a table column type definition for serial columns.
	 * 
	 * @param string $name
	 * @return string
	 */
	protected function compileColumnSerial($name)
	{
		return $name.' INTEGER UNSIGNED NOT NULL AUTO_INCREMENT';
	}

	/**
	 * Compile a JOIN.
	 * 
	 * @param array $joins
	 * @return string
	 */
	protected function compileJoin(array $joins)
	{
		$sql = '';

		foreach ($joins as $join)
		{
			$sql .= ' '.$join->type.' '.$join->getTable();
			if (!empty($join->on))
			{
				$sql .= ' ON '.$this->compileConditions($join->on);
			}
		}

		return $sql;
	}

	/**
	 * Compile a WHERE clause.
	 * 
	 * @param string $where
	 * @return string
	 */
	protected function compileWhere($where)
	{
		return ' WHERE '.$this->compileConditions($where);
	}

	/**
	 * Compile a GROUP BY clause.
	 * 
	 * @param array $group
	 * @return string
	 */
	protected function compileGroup(array $group)
	{
		return ' GROUP BY '.implode(', ', $group);
	}

	/**
	 * Compile a HAVING clause.
	 * 
	 * @param string $having
	 * @return string
	 */
	protected function compileHaving($having)
	{
		return ' HAVING '.$this->compileConditions($having);
	}

	/**
	 * Compile any condition clause.
	 * 
	 * @param string $conditions
	 * @return string
	 */
	protected function compileConditions($conditions)
	{
		return '('.$conditions.')';
	}

	/**
	 * Compile an ORDER BY clause.
	 * 
	 * @param array $order
	 * @return string
	 */
	protected function compileOrder(array $order)
	{
		return ' ORDER BY '.implode(', ', $order);
	}

	/**
	 * Compile LIMIT and OFFSET clauses.
	 * 
	 * @param int $limit
	 * @param int $offset
	 * @return string
	 */
	protected function compileLimitOffset($limit, $offset)
	{
		$sql = '';

		if ($offset > 0 && $limit == 0)
		{
			$limit = PHP_INT_MAX;
		}

		if ($limit > 0)
		{
			$sql .= ' LIMIT '.intval($limit);
		}

		if ($offset > 0)
		{
			$sql .= ' OFFSET '.intval($offset);
		}

		return $sql;
	}


	/*
	 * QUERY CREATOR HELPER FUNCTIONS
	 */

	/**
	 * Get a SELECT query object with the given fields and table
	 *
	 * @param array $fields
	 * @param string[optional] $table
	 * @param bool[optional] $distinct
	 * @return query\Select
	 */
	public function select($fields, $table = null, $distinct = false)
	{
		$q = new query\Select($this);
		$q->fields = $fields;
		$q->setTable($table);
		$q->distinct = $distinct;
		return $q;
	}

	/**
	 * Get a INSERT query object with the given values and table
	 *
	 * @param array $values
	 * @param string $table
	 * @return query\Insert
	 */
	public function insert($values, $table)
	{
		$q = new query\Insert($this);
		$q->values = $values;
		$q->setTable($table);
		return $q;
	}

	/**
	 * Get a INSERT query object with the given values and table
	 *
	 * @param array $values
	 * @param string $table
	 * @return query\Update
	 */
	public function update($values, $table)
	{
		$q = new query\Update($this);
		$q->values = $values;
		$q->setTable($table);
		return $q;
	}

	/**
	 * Get a DELETE query object with the given table
	 *
	 * @param string $table
	 * @return query\Delete
	 */
	public function delete($table)
	{
		$q = new query\Delete($this);
		$q->setTable($table);
		return $q;
	}

	/**
	 * Get a TRUNCATE query object with the given table
	 *
	 * @param string $table
	 * @return query\Truncate
	 */
	public function truncate($table)
	{
		$q = new query\Truncate($this);
		$q->setTable($table);
		return $q;
	}

	/**
	 * Get a REPLACE query object with the given values, table and keys
	 *
	 * @param array $values
	 * @param string $table
	 * @param array $keys
	 * @return query\Replace
	 */
	public function replace($values, $table, $keys)
	{
		$q = new query\Replace($this);
		$q->values = $values;
		$q->setTable($table);
		$q->keys = $keys;
		return $q;
	}

	/**
	 * Get a CREATE TABLE query object with the given table
	 *
	 * @param string $table
	 * @return query\CreateTable
	 */
	public function createTable($table)
	{
		$q = new query\CreateTable($this);
		$q->setTable($table);
		return $q;
	}

	/**
	 * Get a RENAME TABLE query object with the given table and columns
	 *
	 * @param string $table
	 * @param string $new_name
	 * @return query\RenameTable
	 */
	public function renameTable($table, $new_name)
	{
		$q = new query\RenameTable($this);
		$q->setTable($table);
		$q->setNewName($new_name);
		return $q;
	}

	/**
	 * Get a DROP TABLE query object with the given table
	 *
	 * @param string $table
	 * @return query\DropTable
	 */
	public function dropTable($table)
	{
		$q = new query\DropTable($this);
		$q->setTable($table);
		return $q;
	}

	/**
	 * Get a query object for checking whether the given table exists
	 *
	 * @param string $table
	 * @return query\TableExists
	 */
	public function tableExists($table)
	{
		$q = new query\TableExists($this);
		$q->setTable($table);
		return $q;
	}

	/**
	 * Get a query object for adding a field to the given table
	 *
	 * @param string $table
	 * @return query\AddField
	 */
	public function addField($table)
	{
		$q = new query\AddField($this);
		$q->setTable($table);
		return $q;
	}

	/**
	 * Get a query object for altering a field in the given table
	 *
	 * @param string $table
	 * @return query\AlterField
	 */
	public function alterField($table)
	{
		$q = new query\AlterField($this);
		$q->setTable($table);
		return $q;
	}

	/**
	 * Get a query object for dropping the given field in the given table
	 *
	 * @param string $table
	 * @param string $field
	 * @return query\DropField
	 */
	public function dropField($table, $field)
	{
		$q = new query\DropField($this);
		$q->setTable($table);
		$q->field = $field;
		return $q;
	}

	/**
	 * Get a query object for checking whether the given field exists in the given table
	 *
	 * @param string $table
	 * @param string $field
	 * @return query\FieldExists
	 */
	public function fieldExists($table, $field)
	{
		$q = new query\FieldExists($this);
		$q->setTable($table);
		$q->field = $field;
		return $q;
	}

	/**
	 * Get a query object for adding the given index to the given table
	 *
	 * @param string $table
	 * @param string $index
	 * @return query\AddIndex
	 */
	public function addIndex($table, $index)
	{
		$q = new query\AddIndex($this);
		$q->setTable($table);
		$q->index = $index;
		return $q;
	}

	/**
	 * Get a query object for dropping the given index in the given table
	 *
	 * @param string $table
	 * @param string $index
	 * @return query\DropIndex
	 */
	public function dropIndex($table, $index)
	{
		$q = new query\DropIndex($this);
		$q->setTable($table);
		$q->index = $index;
		return $q;
	}

	/**
	 * Get a query object for checking whether the given index exists in the given table
	 *
	 * @param string $table
	 * @param string $index
	 * @return query\IndexExists
	 */
	public function indexExists($table, $index)
	{
		$q = new query\IndexExists($this);
		$q->setTable($table);
		$q->index = $index;
		return $q;
	}

	/**
	 * Get a query object for fetching information about the given table
	 *
	 * @param string $table
	 * @return query\TableInfo
	 */
	public function tableInfo($table)
	{
		$q = new query\TableInfo($this);
		$q->setTable($table);
		return $q;
	}
}
