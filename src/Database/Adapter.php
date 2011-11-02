<?php

/**
* Copyright (C) 2011 FluxBB (http://fluxbb.org)
* License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
*/

abstract class Flux_Database_Adapter
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

	public $prefix = '';

	/**
	 * Create a database adapter instance of the given type.
	 *
	 * @param string $type
	 * @param array[optional] $options
	 * @return Flux_Database_Adapter
	 */
	public static function factory($type, array $options = array())
	{
		// TODO: Should we sanitise type?
		$name = 'Flux_Database_Adapter_'.$type;
		$file = str_replace('_', '/', 'Adapter_'.$type).'.php';

		if (file_exists(dirname(__FILE__).'/'.$file))
		{
			include_once dirname(__FILE__).'/'.$file;
			return new $name($options);
		}
		else
		{
			throw new Exception('Illegal database adapter type "'.$type.'"');
		}
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
	 * 		The Data Source Name, see PDO::__construct.
	 */
	public function connect($dsn)
	{
		$username = isset($this->options['username']) ? $this->options['username'] : '';
		$password = isset($this->options['password']) ? $this->options['password'] : '';
		$driver_options = isset($this->options['driver_options']) ? $this->options['driver_options'] : array();
		$prefix = isset($this->options['prefix']) ? $this->options['prefix'] : '';
		$charset = isset($this->options['charset']) ? $this->options['charset'] : self::DEFAULT_CHARSET;

		// TODO: Handle PDOException properly to avoid displaying connection details
		$this->pdo = new PDO($dsn, $username, $password, $driver_options);
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		// Fetch the driver type
		$this->type = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

		// Attempt to set names
		$this->setNames($charset);

		// Set the table prefix
		$this->setPrefix($prefix);
	}

	/**
	 * Indicates what character set the database connection should use.
	 *
	 * @param string $charset
	 * 		The character set to use.
	 */
	public function setNames($charset)
	{
		$sql = 'SET NAMES '.$this->quote($charset);
		if ($this->exec($sql) === false)
			return;

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
		$quoted_str = $this->getPDO()->quote($str);
		if ($quoted_str === false)
			$quoted_str = '\''.addslashes($str).'\'';

		return $quoted_str;
	}

	abstract public function quoteTable($str);

	abstract public function quoteColumn($str);

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
	 * Execute an database query in a single function call.
	 *
	 * @param int $handle
	 * 		The handle of the statement to execute.
	 *
	 * @param array $params
	 * 		An array of parameters to combine with the query.
	 *
	 * @return PDOStatement
	 * 		The executed PDOStatement.
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
			throw new Exception($error[2]);
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
				continue;

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
	 * @return PDOStatement
	 */
	public function query($sql, array $params = array())
	{
		// Note the start time
		$query_start = microtime(true);

		// TODO: Handle errors
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
	 * Database::commit_transaction. Calling Database::rollback_transaction will roll
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
	 * until the next call to Database::start_transaction starts a new transaction.
	 *
	 * @return bool
	 * 		TRUE on success or FALSE on failure.
	 */
	public function commitTransaction()
	{
		return $this->getPDO()->commit();
	}

	/**
	 * Rolls back the current transaction. It is an error to call this method if no
	 * transaction is active.
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
	public function getDebugQueries()
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

		try { $client = $this->getPDO()->getAttribute(PDO::ATTR_CLIENT_VERSION); } catch (PDOException $e) {}
		try { $server = $this->getPDO()->getAttribute(PDO::ATTR_SERVER_VERSION); } catch (PDOException $e) {}

		return sprintf('%s %s/%s',
			$this->type,
			$client,
			$server
		);
	}


	/*
	 * QUERY TYPES
	 */

	public function compileSelect(Flux_Database_Query_Select $query)
	{
		if (empty($query->fields))
			throw new Exception('A SELECT query must select at least 1 field.');

		$sql = 'SELECT '.($query->distinct ? 'DISTINCT ' : '').implode(', ', $query->fields);

		$table = $query->getTable();
		if (!empty($table))
			$sql .= ' FROM '.$table;

		if (!empty($query->joins))
			$sql .= $this->compileJoin($query->joins);

		if (!empty($query->where))
			$sql .= $this->compileWhere($query->where);

		if (!empty($query->group))
			$sql .= $this->compileGroup($query->group);

		if (!empty($query->having))
			$sql .= $this->compileHaving($query->having);

		if (!empty($query->order))
			$sql .= $this->compileOrder($query->order);

		if ($query->limit > 0 || $query->offset > 0)
			$sql .= $this->compileLimitOffset($query->limit, $query->offset);

		return $sql;
	}

	public function compileInsert(Flux_Database_Query_Insert $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new Exception('An INSERT query must have a table specified.');

		if (empty($query->values))
			throw new Exception('An INSERT query must contain at least 1 value.');

		$sql = 'INSERT INTO '.$table.' ('.implode(', ', array_keys($query->values)).') VALUES ('.implode(', ', array_values($query->values)).')';
		// TODO: Possible dumb question: do we need the array_values() call? Or does that have to do with the order of the elements when calling array_keys()? Same thing in multiple cases, in multiple files.

		return $sql;
	}

	public function compileUpdate(Flux_Database_Query_Update $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new Exception('An UPDATE query must have a table specified.');

		if (empty($query->values))
			throw new Exception('An UPDATE query must contain at least 1 value.');

		$updates = array();
		foreach ($query->values as $key => $value)
			$updates[] = $key.' = '.$value;

		$sql = 'UPDATE '.$query->getTable().' SET '.implode(', ', $updates);

		if (!empty($query->where))
			$sql .= $this->compileWhere($query->where);

		if (!empty($query->order))
			$sql .= $this->compileOrder($query->order);

		if ($query->limit > 0 || $query->offset > 0)
			$sql .= $this->compileLimitOffset($query->limit, $query->offset);

		return $sql;
	}

	public function compileDelete(Flux_Database_Query_Delete $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new Exception('A DELETE query must have a table specified.');

		$sql = 'DELETE FROM '.$table;

		if (!empty($query->where))
			$sql .= $this->compileWhere($query->where);

		if (!empty($query->order))
			$sql .= $this->compileOrder($query->order);

		if ($query->limit > 0 || $query->offset > 0)
			$sql .= $this->compileLimitOffset($query->limit, $query->offset);

		return $sql;
	}

	public function compileReplace(Flux_Database_Query_Replace $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new Exception('A REPLACE query must have a table specified.');

		if (empty($query->values))
			throw new Exception('A REPLACE query must contain at least 1 value.');

		$sql = 'REPLACE INTO '.$table.' ('.implode(', ', array_keys($query->values)).') VALUES ('.implode(', ', array_values($query->values)).')';
		return $sql;
	}

	public function runTruncate(Flux_Database_Query_Truncate $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new Exception('A TRUNCATE query must have a table specified.');

		$sql = 'TRUNCATE TABLE '.$table;
		return $this->exec($sql);
	}

	public function runCreateTable(Flux_Database_Query_CreateTable $query)
	{
		// TODO: Exception if no table etc...
		$fields = array();
		foreach ($query->fields as $field)
			$fields[] = $this->compileColumnDefinition($field);

		$sql = 'CREATE TABLE '.$query->getTable().' ('.implode(', ', $fields).')';
		return $this->exec($sql);
	}

	public function runRenameTable(Flux_Database_Query_RenameTable $query)
	{
		$sql = 'ALTER TABLE '.$query->getTable().' RENAME TO '.$query->new_name;
		return $this->exec($sql);
	}

	public function runDropTable(Flux_Database_Query_DropTable $query)
	{
		$sql = 'DROP TABLE '.$query->getTable();
		return $this->exec($sql);
	}

	public function runTableExists(Flux_Database_Query_TableExists $query)
	{
		$sql = 'SHOW TABLES LIKE '.$this->quote($query->getTable());
		return (bool) $this->query($sql)->fetchColumn();
	}

	public function runAddField(Flux_Database_Query_AddField $query)
	{
		$field = $this->compileColumnDefinition($query->field);

		$sql = 'ALTER TABLE '.$query->getTable().' ADD COLUMN '.$field;
		return $this->exec($sql);
	}

	public function runAlterField(Flux_Database_Query_AlterField $query)
	{
		$field = $this->compileColumnDefinition($query->field);

		$sql = 'ALTER TABLE '.$query->getTable().' MODIFY '.$query->field->name.' '.$field;
		return $this->exec($sql);
		// TODO: Fix return values (bool)!
	}

	public function runDropField(Flux_Database_Query_DropField $query)
	{
		$sql = 'ALTER TABLE '.$query->getTable().' DROP '.$query->field;
		return $this->exec($sql);
	}

	public function runFieldExists(Flux_Database_Query_FieldExists $query)
	{
		$sql = 'SHOW COLUMNS FROM '.$query->getTable().' LIKE '.$this->quote($query->field);
		return (bool) $this->query($sql)->fetchColumn();
	}

	public function runAddIndex(Flux_Database_Query_AddIndex $query)
	{
		$sql = 'ALTER TABLE '.$query->getTable().' ADD '.($query->unique ? 'UNIQUE ' : '').'INDEX '.$query->getTable().'_'.$query->index.' ('.implode(',', $query->fields).')';
		return $this->exec($sql);
	}

	public function runDropIndex(Flux_Database_Query_DropIndex $query)
	{
		$sql = 'ALTER TABLE '.$query->getTable().' DROP INDEX '.$query->getTable().'_'.$query->index;
		return $this->exec($sql);
	}

	public function runIndexExists(Flux_Database_Query_IndexExists $query)
	{
		$sql = 'SHOW INDEX FROM '.$query->getTable();
		return (bool) $this->query($sql)->fetchColumn();
	}

	public function runTableInfo(Flux_Database_Query_TableInfo $query)
	{
		$table = array(
			'columns'		=> array(),
			'primary_key'	=> '',
			'unique'		=> array(),
			'indices'		=> array(),
		);

		// Fetch column information
		$result = $this->query('DESCRIBE '.$query->getTable());
		foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row)
		{
			$table['columns'][$row['Field']] = array(
				'type'			=> $row['Type'],
				'default'		=> $row['Default'],
				'allow_null'	=> $row['Null'] == 'YES',
			);

			// Save primary key
			if ($row['Key'] == 'PRI')
			{
				$table['primary_key'] = $row['Field'];
			}
		}

		// Fetch all indices
		$result = $this->query('SHOW INDEXES FROM '.$query->getTable());
		foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row)
		{
			if ($row['Key_name'] == 'PRIMARY')
			{
				$table['primary_key'] = $row['Column_name'];
				continue;
			}

			if (!isset($table['indices'][$row['Key_name']]))
			{
				$table['indices'][$row['Key_name']] = array(
					'fields'	=> array(),
					'unique'	=> $row['Non_unique'] != 1,
				);

				if ($row['Non_unique'] != 1)
				{
					$table['unique'][] = array($row['Column_name']);
				}
			}
			else
			{
				$table['unique'][count($table['unique']) - 1][] = $row['Column_name'];
			}

			$table['indices'][$row['Key_name']]['fields'][] = $row['Column_name'];
		}

		return $table;
	}

	protected function compileColumnDefinition(Flux_Database_Query_Helper_TableColumn $column)
	{
		if ($column->type === Flux_Database_Query_Helper_TableColumn::TYPE_SERIAL)
			return $this->compileColumnSerial($column->name);

		$sql = $column->name.' '.$this->compileColumnType($column->type);

		if (!empty($column->default))
			$sql .= ' DEFAULT '.$column->default;

		if (!empty($column->key))
			$sql .= ' '.$column->key;

		// TODO: allow_null?
		// TODO: do we need another default value if allow_null is true?
		return $sql;
	}

	protected function compileColumnType($type)
	{
		return $type;
	}

	protected function compileColumnSerial($name)
	{
		return $name.' INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY';
	}

	protected function compileJoin(array $joins)
	{
		$sql = '';

		foreach ($joins as $join)
		{
			$sql .= ' '.$join->type.' '.$join->getTable();
			if (!empty($join->on))
				$sql .= ' ON '.$this->compileConditions($join->on);
		}

		return $sql;
	}

	protected function compileWhere($where)
	{
		return ' WHERE '.$this->compileConditions($where);
	}

	protected function compileGroup($group)
	{
		return ' GROUP BY '.implode(', ', $group);
	}

	protected function compileHaving($having)
	{
		return ' HAVING '.$this->compileConditions($having);
	}

	protected function compileConditions($conditions)
	{
		return '('.$conditions.')';
	}

	protected function compileOrder($order)
	{
		return ' ORDER BY '.implode(', ', $order);
	}

	protected function compileLimitOffset($limit, $offset)
	{
		$sql = '';

		if ($offset > 0 && $limit == 0)
			$limit = PHP_INT_MAX;

		if ($limit > 0)
			$sql .= ' LIMIT '.intval($limit);

		if ($offset > 0)
			$sql .= ' OFFSET '.intval($offset);

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
	 * @return Flux_Database_Query_Select
	 */
	public function select($fields, $table = null, $distinct = false)
	{
		$q = new Flux_Database_Query_Select($this);
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
	 * @return Flux_Database_Query_Insert
	 */
	public function insert($values, $table)
	{
		$q = new Flux_Database_Query_Insert($this);
		$q->values = $values;
		$q->setTable($table);
		return $q;
	}

	/**
	 * Get a INSERT query object with the given values and table
	 *
	 * @param array $values
	 * @param string $table
	 * @return Flux_Database_Query_Update
	 */
	public function update($values, $table)
	{
		$q = new Flux_Database_Query_Update($this);
		$q->values = $values;
		$q->setTable($table);
		return $q;
	}

	/**
	 * Get a DELETE query object with the given table
	 *
	 * @param string $table
	 * @return Flux_Database_Query_Delete
	 */
	public function delete($table)
	{
		$q = new Flux_Database_Query_Delete($this);
		$q->setTable($table);
		return $q;
	}

	/**
	 * Get a TRUNCATE query object with the given table
	 *
	 * @param string $table
	 * @return Flux_Database_Query_Truncate
	 */
	public function truncate($table)
	{
		$q = new Flux_Database_Query_Truncate($this);
		$q->setTable($table);
		return $q;
	}

	/**
	 * Get a REPLACE query object with the given values, table and keys
	 *
	 * @param array $values
	 * @param string $table
	 * @param array $keys
	 * @return Flux_Database_Query_Replace
	 */
	public function replace($values, $table, $keys)
	{
		$q = new Flux_Database_Query_Replace($this);
		$q->values = $values;
		$q->setTable($table);
		$q->keys = $keys;
		return $q;
	}

	/**
	 * Get a CREATE TABLE query object with the given table
	 *
	 * @param string $table
	 * @return Flux_Database_Query_CreateTable
	 */
	public function createTable($table)
	{
		$q = new Flux_Database_Query_CreateTable($this);
		$q->setTable($table);
		return $q;
	}

	/**
	 * Get a RENAME TABLE query object with the given table and columns
	 *
	 * @param string $table
	 * @param string $new_name
	 * @return Flux_Database_Query_RenameTable
	 */
	public function renameTable($table, $new_name)
	{
		$q = new Flux_Database_Query_RenameTable($this);
		$q->setTable($table);
		$q->new_name = $new_name;
		return $q;
	}

	/**
	 * Get a DROP TABLE query object with the given table
	 *
	 * @param string $table
	 * @return Flux_Database_Query_DropTable
	 */
	public function dropTable($table)
	{
		$q = new Flux_Database_Query_DropTable($this);
		$q->setTable($table);
		return $q;
	}

	/**
	 * Get a query object for checking whether the given table exists
	 *
	 * @param string $table
	 * @return Flux_Database_Query_TableExists
	 */
	public function tableExists($table)
	{
		$q = new Flux_Database_Query_TableExists($this);
		$q->setTable($table);
		return $q;
	}

	/**
	 * Get a query object for adding a field to the given table
	 *
	 * @param string $table
	 * @return Flux_Database_Query_AddField
	 */
	public function addField($table)
	{
		$q = new Flux_Database_Query_AddField($this);
		$q->setTable($table);
		return $q;
	}

	/**
	 * Get a query object for altering a field in the given table
	 *
	 * @param string $table
	 * @return Flux_Database_Query_AlterField
	 */
	public function alterField($table)
	{
		$q = new Flux_Database_Query_AlterField($this);
		$q->setTable($table);
		return $q;
	}

	/**
	 * Get a query object for dropping the given field in the given table
	 *
	 * @param string $table
	 * @param string $field
	 * @return Flux_Database_Query_DropField
	 */
	public function dropField($table, $field)
	{
		$q = new Flux_Database_Query_DropField($this);
		$q->setTable($table);
		$q->field = $field;
		return $q;
	}

	/**
	 * Get a query object for checking whether the given field exists in the given table
	 *
	 * @param string $table
	 * @param string $field
	 * @return Flux_Database_Query_FieldExists
	 */
	public function fieldExists($table, $field)
	{
		$q = new Flux_Database_Query_FieldExists($this);
		$q->setTable($table);
		$q->field = $field;
		return $q;
	}

	/**
	 * Get a query object for adding the given index to the given table
	 *
	 * @param string $table
	 * @param string $index
	 * @return Flux_Database_Query_AddIndex
	 */
	public function addIndex($table, $index)
	{
		$q = new Flux_Database_Query_AddIndex($this);
		$q->setTable($table);
		$q->index = $index;
		return $q;
	}

	/**
	 * Get a query object for dropping the given index in the given table
	 *
	 * @param string $table
	 * @param string $index
	 * @return Flux_Database_Query_DropIndex
	 */
	public function dropIndex($table, $index)
	{
		$q = new Flux_Database_Query_DropIndex($this);
		$q->setTable($table);
		$q->index = $index;
		return $q;
	}

	/**
	 * Get a query object for checking whether the given index exists in the given table
	 *
	 * @param string $table
	 * @param string $index
	 * @return Flux_Database_Query_IndexExists
	 */
	public function indexExists($table, $index)
	{
		$q = new Flux_Database_Query_IndexExists($this);
		$q->setTable($table);
		$q->index = $index;
		return $q;
	}

	/**
	 * Get a query object for fetching information about the given table
	 *
	 * @param string $table
	 * @return Flux_Database_Query_TableInfo
	 */
	public function tableInfo($table)
	{
		$q = new Flux_Database_Query_TableInfo($this);
		$q->setTable($table);
		return $q;
	}
}
