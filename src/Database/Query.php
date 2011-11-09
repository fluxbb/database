<?php

/**
* Copyright (C) 2011 FluxBB (http://fluxbb.org)
* License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
*/

/**
 * The base class for all database queries.
 *
 * @abstract
 */
abstract class Flux_Database_Query
{
	/**
	 * @var Flux_Database_Adapter
	 */
	public $adapter = null;

	protected $table = '';
	public $usePrefix = true;

	protected $run = false;

	public function __construct(Flux_Database_Adapter $adapter)
	{
		$this->adapter = $adapter;
	}

	public function setTable($table)
	{
		$this->table = $table;
	}

	public function getTable()
	{
		return $this->usePrefix ? $this->adapter->prefix.$this->table : $this->table;
	}

	public function run(array $params = array())
	{
		// This query type does not support multiple executions with different parameters
		if ($this->run && !empty($params))
		{
			throw new Exception('This query type does not support multiple executions with different parameter sets.');
		}

		$this->run = true;
		return $this->_run($params);
	}

	abstract protected function _run(array $params = array());
}

/**
 * The base class for all database queries that support
 * multiple calls with different queries.
 *
 * @abstract
 */
abstract class Flux_Database_Query_Multi extends Flux_Database_Query
{
	protected $handle = null;

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

	protected function _run(array $params = array())
	{ }

	/**
	 * Compile the query to be run.
	 *
	 * @return string
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
 *
 * @param string $sql
 * 		The plain SQL query to represent.
 */
class Flux_Database_Query_Direct extends Flux_Database_Query
{
	public $sql = '';

	protected function _run(array $params = array())
	{
		return $this->adapter->query($this->sql, $params);
	}

	public function getTable()
	{
		return '';
	}
}

/**
 * Represents a SELECT query. Used to fetch data from the database.
 *
 * @param array $fields
 * 		An array of field names to fetch.
 *
 * @param string $table
 * 		The table from which to select data.
 */
class Flux_Database_Query_Select extends Flux_Database_Query_Multi
{
	public $fields = array();
	public $distinct = false;

	public $group = array();
	public $order = array();
	public $joins = array();
	public $where = '';
	public $having = '';
	public $limit = 0;
	public $offset = 0;

	public function compile()
	{
		return $this->adapter->compileSelect($this);
	}

	public function run(array $params = array())
	{
		$stmt = parent::run($params);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function innerJoin($key, $table, $on)
	{
		$j = new Flux_Database_Query_Join_Inner($this, $table);
		$j->on = $on;

		$this->joins[$key] = $j;
		return $j;
	}

	public function leftJoin($key, $table, $on)
	{
		$j = new Flux_Database_Query_Join_Left($this, $table);
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
class Flux_Database_Query_Insert extends Flux_Database_Query_Multi
{
	public $values = array();

	public function compile()
	{
		return $this->adapter->compileInsert($this);
	}

	public function run(array $params = array())
	{
		$stmt = parent::run($params);
		return $stmt->rowCount();
	}
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
class Flux_Database_Query_Update extends Flux_Database_Query_Multi
{
	public $values = array();

	public $order = array();
	public $where = '';
	public $limit = 0;
	public $offset = 0;

	public function compile()
	{
		return $this->adapter->compileUpdate($this);
	}

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
class Flux_Database_Query_Delete extends Flux_Database_Query_Multi
{
	public $order = array();
	public $where = '';
	public $limit = 0;
	public $offset = 0;

	public function compile()
	{
		return $this->adapter->compileDelete($this);
	}

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
class Flux_Database_Query_Replace extends Flux_Database_Query
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
abstract class Flux_Database_Query_Join
{
	protected $query = null;
	public $type = '';
	protected $table = '';
	public $on = '';

	public function __construct(Flux_Database_Query_Select $query, $type, $table)
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
class Flux_Database_Query_Join_Inner extends Flux_Database_Query_Join {
	public function __construct(Flux_Database_Query_Select $query, $table)
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
class Flux_Database_Query_Join_Left extends Flux_Database_Query_Join {
	public function __construct(Flux_Database_Query_Select $query, $table)
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
class Flux_Database_Query_Truncate extends Flux_Database_Query
{
	protected function _run(array $params = array())
	{
		return $this->adapter->runTruncate($this);
	}
}

class Flux_Database_Query_CreateTable extends Flux_Database_Query
{
	public $fields = array();
	
	public $indices = array();

	protected function _run(array $params = array())
	{
		return $this->adapter->runCreateTable($this);
	}

	public function field($name, $type, $default = null, $allow_null = true, $key = null)
	{
		$c = new Flux_Database_Query_Helper_TableColumn($name, $type, $default, $allow_null, $key);
		$this->fields[] = $c;

		return $c;
	}
	
	public function index($name, array $columns)
	{
		$this->indices[] = array('name' => $name, 'columns' => $columns);
	}
}

class Flux_Database_Query_RenameTable extends Flux_Database_Query
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

class Flux_Database_Query_DropTable extends Flux_Database_Query
{
	protected function _run(array $params = array())
	{
		return $this->adapter->runDropTable($this);
	}
}

class Flux_Database_Query_TableExists extends Flux_Database_Query
{
	protected function _run(array $params = array())
	{
		return $this->adapter->runTableExists($this);
	}
}

abstract class Flux_Database_Query_Field extends Flux_Database_Query
{
	/**
	 * The column information
	 *
	 * @var Flux_Database_Query_Helper_TableColumn
	 */
	public $field = null;

	public function field($name, $type, $default = null, $allow_null = true, $key = null)
	{
		$this->field = new Flux_Database_Query_Helper_TableColumn($name, $type, $default, $allow_null, $key);

		return $this->field;
	}
}

class Flux_Database_Query_AddField extends Flux_Database_Query_Field
{
	protected function _run(array $params = array())
	{
		return $this->adapter->runAddField($this);
	}
}

class Flux_Database_Query_AlterField extends Flux_Database_Query_Field
{
	protected function _run(array $params = array())
	{
		return $this->adapter->runAlterField($this);
	}
}

class Flux_Database_Query_DropField extends Flux_Database_Query
{
	public $field = '';

	protected function _run(array $params = array())
	{
		return $this->adapter->runDropField($this);
	}
}

class Flux_Database_Query_FieldExists extends Flux_Database_Query
{
	public $field = '';

	protected function _run(array $params = array())
	{
		return $this->adapter->runSelect($this);
	}
}

class Flux_Database_Query_AddIndex extends Flux_Database_Query
{
	public $index = '';
	public $unique = false;
	public $fields = array();

	protected function _run(array $params = array())
	{
		return $this->adapter->runAddIndex($this);
	}
}

class Flux_Database_Query_DropIndex extends Flux_Database_Query
{
	public $index = '';

	protected function _run(array $params = array())
	{
		return $this->adapter->runDropIndex($this);
	}
}

class Flux_Database_Query_IndexExists extends Flux_Database_Query
{
	public $index = '';

	protected function _run(array $params = array())
	{
		return $this->adapter->runIndexExists($this);
	}
}

class Flux_Database_Query_TableInfo extends Flux_Database_Query
{
	protected function _run(array $params = array())
	{
		return $this->adapter->runTableInfo($this);
	}
}


/*
 * HELPER CLASSES
 */

class Flux_Database_Query_Helper_TableColumn
{
	const TYPE_SERIAL = 'SERIAL';
	const TYPE_TEXT = 'TEXT';
	const TYPE_BOOL = 'BOOLEAN';
	const TYPE_UINT = 'INTEGER UNSIGNED';
	const TYPE_INT = 'INTEGER';

	public static function TYPE_VARCHAR($length = 255) { return 'VARCHAR('.intval($length).')'; }

	const KEY_UNIQUE = 'UNIQUE';
	const KEY_PRIMARY = 'PRIMARY KEY';

	public $name;
	public $type;
	public $default;
	public $key;
	public $allow_null;

	public function __construct($name, $type, $default = null, $allow_null = true, $key = null)
	{
		$this->name = $name;
		$this->type = $type;
		$this->default = $default;
		$this->key = $key;
		$this->allow_null = $allow_null;
	}
}