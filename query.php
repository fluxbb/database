<?php

/**
* Copyright (C) 2011 FluxBB (http://fluxbb.org)
* License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
*/

/**
 * The base class for all database queries. Holds both the SQL
 * for the query and a PDOStatement object once compiled.
 *
 * @abstract
 */
abstract class DatabaseQuery
{
	public $sql = null;
	public $statement = null;
}

/**
 * Represents a plain SQL query which bypasses the abstraction layer.
 *
 * @param string $sql
 * 		The plain SQL query to represent.
 */
class DirectQuery extends DatabaseQuery
{
	public function __construct($sql)
	{
		$this->sql = $sql;
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
class SelectQuery extends DatabaseQuery
{
	public $fields;
	public $table;

	public $group;
	public $order;
	public $joins;
	public $where;
	public $having;
	public $limit;
	public $offset;

	public function __construct($fields, $table = null)
	{
		$this->fields = $fields;
		$this->table = $table;

		$this->group = array();
		$this->order = array();
		$this->joins = array();
		$this->where = '';
		$this->having = '';
		$this->limit = 0;
		$this->offset = 0;
	}
}

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
abstract class QueryJoin
{
	public $type;
	public $table;
	public $on;

	public function __construct($type, $table)
	{
		$this->type = $type;
		$this->table = $table;

		$this->on = '';
	}
}

/**
 * Represents an INNER JOIN.
 *
 * @param string $table
 * 		The table on which we are joining.
 */
class InnerJoin extends QueryJoin {
	public function __construct($table)
	{
		parent::__construct('INNER JOIN', $table);
	}
}

/**
 * Represents a LEFT JOIN.
 *
 * @param string $table
 * 		The table on which we are joining.
 */
class LeftJoin extends QueryJoin {
	public function __construct($table)
	{
		parent::__construct('LEFT JOIN', $table);
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
class InsertQuery extends DatabaseQuery
{
	public $values;
	public $table;

	public function __construct($values, $table)
	{
		$this->values = $values;
		$this->table = $table;
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
class UpdateQuery extends DatabaseQuery
{
	public $values;
	public $table;

	public $order;
	public $where;
	public $limit;
	public $offset;

	public function __construct($values, $table)
	{
		$this->values = $values;
		$this->table = $table;

		$this->order = array();
		$this->where = '';
		$this->limit = 0;
		$this->offset = 0;
	}
}

/**
 * Represents a DELETE query. Used to delete a subset of data in a chosen table.
 *
 * @param string $table
 * 		The table from which to delete data.
 */
class DeleteQuery extends DatabaseQuery
{
	public $table;

	public $order;
	public $where;
	public $limit;
	public $offset;

	public function __construct($table)
	{
		$this->table = $table;

		$this->order = array();
		$this->where = '';
		$this->limit = 0;
		$this->offset = 0;
	}
}

/**
 * Represents a TRUNCATE query. Used to delete all data in a chosen table.
 *
 * @param string $table
 * 		The table from which to delete data.
 */
class TruncateQuery extends DatabaseQuery
{
	public $table;

	public function __construct($table)
	{
		$this->table = $table;
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
class ReplaceQuery extends DatabaseQuery
{
	public $values;
	public $table;
	public $keys;

	public function __construct($values, $table, $keys)
	{
		$this->values = $values;
		$this->table = $table;
		$this->keys = is_array($keys) ? $keys : array($keys);
	}
}

// TODO: Below here are utility queries

class TableColumn
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

	public function __construct($name, $type, $default = null, $key = null)
	{
		$this->name = $name;
		$this->type = $type;
		$this->default = $default;
		$this->key = $key;
	}
}

class CreateTableQuery extends DatabaseQuery
{
	public $table;
	public $columns;

	public function __construct($table, $columns)
	{
		$this->table = $table;
		$this->columns = $columns;
	}
}

class DropTableQuery extends DatabaseQuery
{
	public $table;

	public function __construct($table)
	{
		$this->table = $table;
	}
}
