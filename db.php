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
	/**
	 * The default connection charset that is used if none is specified when a
	 * new Database instance is created.
	 */
	const DEFAULT_CHARSET = 'utf8';

	private $type;
	private $pdo;
	private $dialect;
	private $queries;

	public $prefix;
	public $charset;

	/**
	 * Creates a Database instance to hold a connection to the requested database
	 * via PDO, and a dialect instance for compiling abstract query representations
	 * to SQL.
	 *
	 * @param string $dsn
	 * 		The Data Source Name, see PDO::__construct.
	 *
	 * @param array $args
	 * 		An array of configuration options.
	 *
	 * @param string $dialect
	 * 		The SQL dialect to use.
	 */
	public function __construct($dsn, $args = array())
	{
		$username = isset($args['username']) ? $args['username'] : '';
		$password = isset($args['password']) ? $args['password'] : '';
		$driver_options = isset($args['driver_options']) ? $args['driver_options'] : array();
		$dialect_options = isset($args['dialect_options']) ? $args['dialect_options'] : array();
		$prefix = isset($args['prefix']) ? $args['prefix'] : '';
		$charset = isset($args['charset']) ? $args['charset'] : self::DEFAULT_CHARSET;

		$this->queries = array();

		// TODO: Handle PDOException properly to avoid displaying connection details
		$this->pdo = new PDO($dsn, $username, $password, $driver_options);
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		// Fetch the driver type
		$this->type = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

		// We have a dialect for this database type, load it
		if (file_exists(PHPDB_ROOT.'dialects/'.$this->type.'.php'))
		{
			if (!class_exists($this->type.'Dialect'))
				require PHPDB_ROOT.'dialects/'.$this->type.'.php';

			// Instantiate the dialect
			$dialect = $this->type.'Dialect';
			$this->dialect = new $dialect($this, $dialect_options);
		}
		// Load the default dialect
		else
			$this->dialect = new SQLDialect($this, $dialect_options);

		// Attempt to set names
		$this->set_names($charset);

		// Set the table prefix
		$this->set_prefix($prefix);
	}

	/**
	 * Indicates what character set the database connection should use.
	 *
	 * @param string $charset
	 * 		The character set to use.
	 */
	public function set_names($charset)
	{
		$sql = $this->dialect->set_names($charset);
		if (empty($sql))
			return;

		if ($this->pdo->exec($sql) === false)
			return;

		$this->charset = $charset;
	}

	/**
	 * Set the table prefix to be used.
	 *
	 * @param string $prefix
	 * 		The table prefix to be used.
	 */
	public function set_prefix($prefix)
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
		$quoted_str = $this->pdo->quote($str);
		if ($quoted_str === false)
			$quoted_str = '\''.$str.'\'';

		return $quoted_str;
	}

	/**
	 * Execute an database query in a single function call.
	 *
	 * @param DatabaseQuery $query
	 * 		The database query to execute.
	 *
	 * @param array $params
	 * 		An array of parameters to combine with the query.
	 *
	 * @return mixed
	 * 		Select queries returns the entire result as an array.
	 * 		Other query types returns the number of affected rows.
	 */
	public function query(DatabaseQuery $query, $params = array())
	{
		// Note the start time
		$query_start = microtime(true);

		// If the query hasn't already been compiled
		if ($query->sql === null)
			$query->sql = $this->dialect->compile($query);

		// If there is no query then do nothing
		if (empty($query->sql))
			return 0;

		// Handle any param arrays
		$this->handle_arrays($query->sql, $params);

		// If the statement hasn't already been prepared
		if ($query->statement === null)
			$query->statement = $this->pdo->prepare($query->sql);

		// Execute the actual statement, and check if an error occured
		if ($query->statement->execute($params) === false)
		{
			$error = $query->statement->errorInfo();
			throw new Exception($error[2]);
		}

		// Note this query and how long it took
		$this->queries[] = array('sql' => $query->sql, 'params' => $params, 'duration' => (microtime(true) - $query_start));

		// If it was a select query, return the results
		if ($query instanceof SelectQuery || ($query instanceof DirectQuery && preg_match('%^SELECT\s%i', $query->sql)))
			return $query->statement->fetchAll(PDO::FETCH_ASSOC);

		// Otherwise return the number of affected rows
		return $query->statement->rowCount();
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
	 */
	protected function handle_arrays(&$sql, &$params)
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
			$params = array_merge($params, $additions);
	}

	/**
	 * Returns the ID of the last inserted row.
	 *
	 * @return string
	 *		A string representing the row ID of the last row that was inserted
	 * 		into the database.
	 */
	public function insert_id()
	{
		return $this->pdo->lastInsertId();
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
	public function start_transaction()
	{
		return $this->pdo->beginTransaction();
	}

	/**
	 * Commits a transaction, returning the database connection to autocommit mode
	 * until the next call to Database::start_transaction starts a new transaction.
	 *
	 * @return bool
	 * 		TRUE on success or FALSE on failure.
	 */
	public function commit_transaction()
	{
		return $this->pdo->commit();
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
	public function rollback_transaction()
	{
		return $this->pdo->rollBack();
	}

	/**
	 * Checks if a transaction is currently active.
	 *
	 * @return bool
	 * 		TRUE if a transaction is currently active, and FALSE if not.
	 */
	public function in_transaction()
	{
		return $this->pdo->inTransaction();
	}

	/**
	 * Fetch a list of all queries which have been executed since the connection
	 * was initiated.
	 *
	 * @return array
	 * 		A list of queries which have been previously executed.
	 */
	public function get_debug_queries()
	{
		return $this->queries;
	}

	/**
	 * Fetch the underlying driver name and client/server versions.
	 *
	 * @return string
	 * 		A string in the format "driver_name client_version/server_version"
	 */
	public function get_version()
	{
		$client = $server = '?';

		try { $client = $this->pdo->getAttribute(PDO::ATTR_CLIENT_VERSION); } catch (PDOException $e) {}
		try { $server = $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION); } catch (PDOException $e) {}

		return sprintf('%s %s/%s',
			$this->type,
			$client,
			$server
		);
	}
}
