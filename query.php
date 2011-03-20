<?php

/**
* Copyright (C) 2011 FluxBB (http://fluxbb.org)
* License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
*/

abstract class DatabaseQuery
{
	public $sql = null;
	public $statement = null;
}

class DirectQuery extends DatabaseQuery
{
	public function __construct($sql)
	{
		$this->sql = $sql;
	}
}

class SetNamesQuery extends DatabaseQuery
{
	public $charset;

	public function __construct($charset)
	{
		$this->charset = $charset;
	}
}

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
		$this->where = array();
		$this->having = array();
		$this->limit = 0;
		$this->offset = 0;
	}
}

abstract class QueryJoin
{
	public $type;
	public $table;
	public $on;

	public function __construct($type, $table)
	{
		$this->type = $type;
		$this->table = $table;

		$this->on = array();
	}
}

class InnerJoin extends QueryJoin {
	public function __construct($table)
	{
		parent::__construct('INNER JOIN', $table);
	}
}

class LeftJoin extends QueryJoin {
	public function __construct($table)
	{
		parent::__construct('LEFT JOIN', $table);
	}
}

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
		$this->where = array();
		$this->limit = 0;
		$this->offset = 0;
	}
}

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
		$this->where = array();
		$this->limit = 0;
		$this->offset = 0;
	}
}

class TruncateQuery extends DatabaseQuery
{
	public $table;

	public function __construct($table)
	{
		$this->table = $table;
	}
}

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
