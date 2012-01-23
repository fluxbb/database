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
 * @package		Flux_Database
 * @copyright	Copyright (c) 2011 FluxBB (http://fluxbb.org)
 * @license		http://www.gnu.org/licenses/lgpl.html	GNU Lesser General Public License
 */

namespace fluxbb\database\query;

/**
 * The base class for all database queries.
 *
 * @abstract
 */
abstract class Query
{
	/**
	 * The adapter that manages the connection to the database.
	 *
	 * @var \fluxbb\database\Adapter
	 */
	public $adapter = null;

	/**
	 * The table that is affected by the query.
	 *
	 * @var string
	 */
	protected $table = '';

	/**
	 * Whether or not the global table prefix should automatically be applied.
	 *
	 * Defaults to true.
	 *
	 * @var bool
	 */
	public $usePrefix = true;

	/**
	 * Whether the query has already been run before.
	 *
	 * @var bool
	 */
	protected $run = false;

	/**
	 * Constructor
	 *
	 * Used to assign a database adapter.
	 *
	 * @param \fluxbb\database\Adapter $adapter
	 * 		The database adapter to use.
	 */
	public function __construct(\fluxbb\database\Adapter $adapter)
	{
		$this->adapter = $adapter;
	}

	/**
	 * Set the table that is affected by the query.
	 *
	 * @param string $table
	 * 		The table name.
	 *
	 * @return void
	 */
	public function setTable($table)
	{
		$this->table = $table;
	}

	/**
	 * Get the table that is affected by the query.
	 *
	 * If {@see usePrefix} is set to true, the global prefix will be prepended.
	 *
	 * @return string
	 * 		The table name.
	 */
	public function getTable()
	{
		return $this->usePrefix ? $this->adapter->prefix.$this->table : $this->table;
	}

	/**
	 * Execute the query with the given parameters, if specified.
	 *
	 * If this method is called twice on the same object, it will throw an
	 * exception.
	 *
	 * @param array $params
	 * 		An array of parameters to pass to the database along with the query.
	 *
	 * @throws \Exception
	 *
	 * @return mixed
	 * 		Query results (type depending on the query type).
	 */
	public function run(array $params = array())
	{
		// This query type does not support multiple executions with different parameters
		if ($this->run && !empty($params))
		{
			throw new \Exception('This query type does not support multiple executions with different parameter sets.');
		}

		$this->run = true;
		return $this->_run($params);
	}

	/**
	 * Template method for actually running the query with the given parameters.
	 *
	 * This method should be overwritten by subclasses. It needs to pass the
	 * given parameters to the database adapter and execute an SQL query.
	 *
	 * @param array $params
	 * 		An array of parameters to pass to the database along with the query.
	 *
	 * @return mixed
	 * 		Query results (type depending on the query type).
	 */
	abstract protected function _run(array $params = array());
}

/**
 * The base class for all database queries that support multiple calls with
 * different queries.
 *
 * @abstract
 */
abstract class Multi extends Query
{
	/**
	 * The handle of the pre-compiled query, as returned by the adapter.
	 *
	 * @var int
	 */
	protected $handle = null;

	/**
	 * Execute the query with the given parameters.
	 *
	 * If this method is called multiple times, changes to attributes in the
	 * meantime will be ignored, as the query has already been compiled.
	 *
	 * @param array $params
	 * 		An array of parameters to pass to the database along with the query.
	 * @return mixed
	 * 		Query results (type depending on the query type).
	 */
	public function run(array $params = array())
	{
		// Compile first, if necessary
		if ($this->handle == NULL)
		{
			$sql = $this->compile();
			$this->handle = $this->adapter->prepare($sql);
		}

		return $this->adapter->execute($this->handle, $params);
	}

	/**
	 * Template method for running the query with the given parameters.
	 *
	 * This function will not have any effect if overwritten by subclasses.
	 *
	 * @param array $params
	 * 		An array of parameters to pass to the database along with the query.
	 *
	 * @return void
	 */
	protected function _run(array $params = array())
	{ }

	/**
	 * Compile the query to be run.
	 *
	 * This method should be overwritten by subclasses. It needs to assemble
	 * the SQL for the query using the database adapter and return the SQL.
	 *
	 * @return string
	 * 		The SQL of the compiled query.
	 */
	abstract public function compile();
}


/*
 * STANDARD QUERIES
 */

/**
 * Represents a plain SQL query which bypasses the abstraction layer.
 *
 * This will also ignore the value of the $table field, even if set.
 */
class Direct extends Query
{
	/**
	 * The plain SQL query to represent.
	 *
	 * @var string
	 */
	public $sql = '';

	/**
	 * Run the given query.
	 *
	 * This method should be overwritten by subclasses. It needs to pass the
	 * given parameters to the database adapter and execute an SQL query.
	 *
	 * @param array $params
	 * @return mixed
	 */
	protected function _run(array $params = array())
	{
		return $this->adapter->query($this->sql, $params);
	}

	/**
	 * Get the table that is affected by the query.
	 *
	 * This will not have any effect, as direct queries will ignore the table
	 * property, even if set. Therefore, this function will simply return an
	 * empty string.
	 *
	 * @return string
	 */
	public function getTable()
	{
		return '';
	}
}

/**
 * Represents a SELECT query. Used to fetch data from the database.
 */
class Select extends Multi
{
	/**
	 * An array of columns to be fetched.
	 *
	 * The keys should not be omitted (to allow extensibility) and should be
	 * the alias of the column.
	 *
	 * @var array
	 */
	public $fields = array();

	/**
	 * Whether or not duplicate rows should be filtered.
	 *
	 * Defaults to false.
	 *
	 * @var bool
	 */
	public $distinct = false;

	/**
	 * A SQL condition string for use in the WHERE clause of the query.
	 *
	 * @var string
	 */
	public $where = '';

	/**
	 * An array of columns to sort by.
	 *
	 * Every element should consist of the column name (including table alias)
	 * and a sort order (ASC/DESC). The key should be the column name.
	 *
	 * @var array
	 */
	public $order = array();

	/**
	 * An array of tables to join.
	 *
	 * Every element should be an instance of {@see Join}.
	 * For convenience, use the {@see innerJoin()} and {@see leftJoin()} methods
	 * for adding new tables to the array.
	 *
	 * @var array
	 */
	public $joins = array();

	/**
	 * An array of columns to group by.
	 *
	 * Every element should consist of the column name (including table alias),
	 * in the correct order. The key should be the column name.
	 *
	 * @var array
	 */
	public $group = array();

	/**
	 * A SQL condition string for use in the HAVING clause of the query. Usually
	 * used with aggregate functions (like COUNT).
	 *
	 * Only applicable if columns to group by have been specified.
	 *
	 * @var string
	 */
	public $having = '';

	/**
	 * The maximum number of rows to be fetched.
	 *
	 * @var int
	 */
	public $limit = 0;

	/**
	 * The offset at which rows of the result set should start to be returned.
	 *
	 * This is ignored if no limit is provided.
	 *
	 * @var int
	 */
	public $offset = 0;

	/**
	 * Compile the query to be run.
	 *
	 * @return string
	 */
	public function compile()
	{
		return $this->adapter->compileSelect($this);
	}

	/**
	 * Execute the query with the given parameters.
	 *
	 * If this method is called multiple times, changes to attributes in the
	 * meantime will be ignored, as the query has already been compiled.
	 *
	 * @param array $params
	 * @return array
	 */
	public function run(array $params = array())
	{
		$stmt = parent::run($params);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function innerJoin($key, $table, $on = '')
	{
		$j = new Join_Inner($this, $table);
		$j->on = $on;

		$this->joins[$key] = $j;
		return $j;
	}

	public function leftJoin($key, $table, $on = '')
	{
		$j = new Join_Left($this, $table);
		$j->on = $on;

		$this->joins[$key] = $j;
		return $j;
	}
}

/**
 * Represents an INSERT query. Used to insert new data into a table.
 *
 * @param array $values
 * 		An array of key=>value pairs containing the field name and data value.
 *
 * @param string $table
 * 		The table which this data should be inserted to.
 */
class Insert extends Multi
{
	public $values = array();

	/**
	 * Compile the query to be run.
	 *
	 * @return string
	 */
	public function compile()
	{
		return $this->adapter->compileInsert($this);
	}

	/**
	 * Execute the query with the given parameters.
	 *
	 * If this method is called multiple times, changes to attributes in the
	 * meantime will be ignored, as the query has already been compiled.
	 *
	 * @param array $params
	 * @return int
	 */
	public function run(array $params = array())
	{
		$stmt = parent::run($params);
		return $stmt->rowCount();
	}

	// TODO: How to retrieve insert ID?
}

/**
 * Represents an UPDATE query. Used to update an existing row in a table.
 *
 * @param array $values
 * 		An array of key=>value pairs containing the field name and data value.
 *
 * @param string $table
 * 		The table which this data should be updated in.
 */
class Update extends Multi
{
	public $values = array();

	public $where = '';

	/**
	 * Compile the query to be run.
	 *
	 * @return string
	 */
	public function compile()
	{
		return $this->adapter->compileUpdate($this);
	}

	/**
	 * Execute the query with the given parameters.
	 *
	 * If this method is called multiple times, changes to attributes in the
	 * meantime will be ignored, as the query has already been compiled.
	 *
	 * @param array $params
	 * @return int
	 */
	public function run(array $params = array())
	{
		$stmt = parent::run($params);
		return $stmt->rowCount();
	}
}

/**
 * Represents a DELETE query. Used to delete a subset of data in a chosen table.
 *
 * @param string $table
 * 		The table from which to delete data.
 */
class Delete extends Multi
{
	public $where = '';

	/**
	 * Compile the query to be run.
	 *
	 * @return string
	 */
	public function compile()
	{
		return $this->adapter->compileDelete($this);
	}

	/**
	 * Execute the query with the given parameters.
	 *
	 * If this method is called multiple times, changes to attributes in the
	 * meantime will be ignored, as the query has already been compiled.
	 *
	 * @param array $params
	 * @return int
	 */
	public function run(array $params = array())
	{
		$stmt = parent::run($params);
		return $stmt->rowCount();
	}
}

/**
 * Represents a REPLACE query. If the table contains a matching row, it is
 * updated - otherwise a new row is inserted.
 *
 * @param array $values
 * 		An array of key=>value pairs containing the field name and data value.
 *
 * @param string $table
 * 		The table which this data should be updated in or inserted to.
 *
 * @param array $keys
 * 		An array of field names which are considered unique keys for the table.
 */
class Replace extends Query
{
	public $values = array();
	public $keys = array();

	public function _run(array $params = array())
	{
		return $this->adapter->runReplace($this, $params);
	}
}


/*
 * JOIN HELPERS
 */

/**
 * Base class for all query joins.
 *
 * @abstract
 *
 * @param string $type
 * 		The type of join, for example INNER or LEFT.
 *
 * @param string $table
 * 		The table on which we are joining.
 */
abstract class Join
{
	protected $query = null;
	public $type = '';
	protected $table = '';
	public $on = '';

	public function __construct(Select $query, $type, $table)
	{
		$this->query = $query;
		$this->type = $type;
		$this->table = $table;
	}

	public function setTable($table)
	{
		$this->table = $table;
	}

	public function getTable()
	{
		return $this->query->usePrefix ? $this->query->adapter->prefix.$this->table : $this->table;
	}
}

/**
 * Represents an INNER JOIN.
 *
 * @param string $table
 * 		The table on which we are joining.
 */
class Join_Inner extends Join {
	public function __construct(Select $query, $table)
	{
		parent::__construct($query, 'INNER JOIN', $table);
	}
}

/**
 * Represents a LEFT JOIN.
 *
 * @param string $table
 * 		The table on which we are joining.
 */
class Join_Left extends Join {
	public function __construct(Select $query, $table)
	{
		parent::__construct($query, 'LEFT JOIN', $table);
	}
}


/*
 * UTILITY QUERIES
 */

/**
 * Represents a TRUNCATE query. Used to delete all data in a chosen table.
 *
 * @param string $table
 * 		The table from which to delete data.
 */
class Truncate extends Query
{
	protected function _run(array $params = array())
	{
		return $this->adapter->runTruncate($this);
	}
}

class CreateTable extends Query
{
	public $fields = array();

	public $indices = array();
	public $primary = array();

	public $engine = '';

	protected function _run(array $params = array())
	{
		return $this->adapter->runCreateTable($this);
	}

	public function field($name, $type, $default = null, $allow_null = true, $collation = '')
	{
		$c = new Helper_TableColumn($name, $type, $default, $allow_null, $collation);
		$this->fields[] = $c;

		return $c;
	}

	public function index($name, array $columns, $unique = false)
	{
		if ($name == 'PRIMARY')
		{
			$this->primary = $columns;
		}
		else
		{
			$this->indices[$name] = array('columns' => $columns, 'unique' => $unique);
		}
	}
}

class RenameTable extends Query
{
	protected $new_name = '';

	protected function _run(array $params = array())
	{
		return $this->adapter->runRenameTable($this);
	}

	public function setNewName($table)
	{
		$this->new_name = $table;
	}

	public function getNewName()
	{
		return $this->usePrefix ? $this->adapter->prefix.$this->new_name : $this->new_name;
	}
}

class DropTable extends Query
{
	protected function _run(array $params = array())
	{
		return $this->adapter->runDropTable($this);
	}
}

class TableExists extends Query
{
	protected function _run(array $params = array())
	{
		return $this->adapter->runTableExists($this);
	}
}

abstract class Field extends Query
{
	/**
	 * The column information
	 *
	 * @var Helper_TableColumn
	 */
	public $field = null;

	public function field($name, $type, $default = null, $allow_null = true)
	{
		$this->field = new Helper_TableColumn($name, $type, $default, $allow_null);

		return $this->field;
	}
}

class AddField extends Field
{
	protected function _run(array $params = array())
	{
		return $this->adapter->runAddField($this);
	}
}

class AlterField extends Field
{
	protected function _run(array $params = array())
	{
		return $this->adapter->runAlterField($this);
	}
}

class DropField extends Query
{
	public $field = '';

	protected function _run(array $params = array())
	{
		return $this->adapter->runDropField($this);
	}
}

class FieldExists extends Query
{
	public $field = '';

	protected function _run(array $params = array())
	{
		return $this->adapter->runSelect($this);
	}
}

class AddIndex extends Query
{
	public $index = '';
	public $unique = false;
	public $fields = array();

	protected function _run(array $params = array())
	{
		return $this->adapter->runAddIndex($this);
	}
}

class DropIndex extends Query
{
	public $index = '';

	protected function _run(array $params = array())
	{
		return $this->adapter->runDropIndex($this);
	}
}

class IndexExists extends Query
{
	public $index = '';

	protected function _run(array $params = array())
	{
		return $this->adapter->runIndexExists($this);
	}
}

class TableInfo extends Query
{
	protected function _run(array $params = array())
	{
		return $this->adapter->runTableInfo($this);
	}
}


/*
 * HELPER CLASSES
 */

class Helper_TableColumn
{
	const TYPE_SERIAL = 'SERIAL';
	const TYPE_TEXT = 'TEXT';
	const TYPE_MEDIUMTEXT = 'MEDIUMTEXT';
	const TYPE_BOOL = 'BOOLEAN';
	const TYPE_INT = 'INTEGER';
	const TYPE_INT_UNSIGNED = 'INTEGER UNSIGNED';
	const TYPE_MEDIUMINT = 'MEDIUMINT';
	const TYPE_MEDIUMINT_UNSIGNED = 'MEDIUMINT UNSIGNED';
	const TYPE_TINYINT = 'TINYINT';
	const TYPE_TINYINT_UNSIGNED = 'TINYINT UNSIGNED';
	const TYPE_SMALLINT = 'SMALLINT';
	const TYPE_SMALLINT_UNSIGNED = 'SMALLINT UNSIGNED';
	const TYPE_FLOAT = 'FLOAT';

	public static function TYPE_VARCHAR($length = 255) { return 'VARCHAR('.intval($length).')'; }

	public $name;
	public $type;
	public $default;
	public $allow_null;

	public $collation = '';

	public function __construct($name, $type, $default = null, $allow_null = true, $collation = '')
	{
		$this->name = $name;
		$this->type = $type;
		$this->default = $default;
		$this->allow_null = $allow_null;
		$this->collation = $collation;
	}
}
