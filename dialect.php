<?php

/**
* Copyright (C) 2011 FluxBB (http://fluxbb.org)
* License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
*/

class SQLDialect
{
	protected $db;

	public function __construct($db, $args = array())
	{
		$this->db = $db;
	}

	public final function compile(DatabaseQuery $query)
	{
		$type = get_class($query);
		switch ($type)
		{
			// Regular queries
			case 'SelectQuery': return $this->select($query);
			case 'InsertQuery': return $this->insert($query);
			case 'UpdateQuery': return $this->update($query);
			case 'DeleteQuery': return $this->delete($query);
			case 'TruncateQuery': return $this->truncate($query);
			case 'ReplaceQuery': return $this->replace($query);

			// Utility queries
			case 'CreateTableQuery': return $this->create_table($query);
			case 'RenameTableQuery': return $this->rename_table($query);
			case 'DropTableQuery': return $this->drop_table($query);

			// For direct queries we already have the SQL
			case 'DirectQuery':
				return $query->sql;

			default:
				throw new Exception('Unsupported query type: '.$type);
		}
	}

	public function set_names($charset)
	{
		return 'SET NAMES '.$this->db->quote($charset);
	}

	protected function select(SelectQuery $query)
	{
		if (empty($query->fields))
			throw new Exception('A SELECT query must select at least 1 field.');

		$sql = 'SELECT '.($query->distinct ? 'DISTINCT ' : '').implode(', ', $query->fields);

		if (!empty($query->table))
			$sql .= ' FROM '.$this->db->prefix.$query->table;

		if (!empty($query->joins))
			$sql .= $this->join($query->joins);

		if (!empty($query->where))
			$sql .= $this->where($query->where);

		if (!empty($query->group))
			$sql .= $this->group($query->group);

		if (!empty($query->having))
			$sql .= $this->having($query->having);

		if (!empty($query->order))
			$sql .= $this->order($query->order);

		if ($query->limit > 0 || $query->offset > 0)
			$sql .= $this->limit_offset($query->limit, $query->offset);

		return $sql;
	}

	protected function insert(InsertQuery $query)
	{
		if (empty($query->table))
			throw new Exception('An INSERT query must have a table specified.');

		if (empty($query->values))
			throw new Exception('An INSERT query must contain at least 1 value.');

		return 'INSERT INTO '.$this->db->prefix.$query->table.' ('.implode(', ', array_keys($query->values)).') VALUES ('.implode(', ', array_values($query->values)).')';
	}

	protected function update(UpdateQuery $query)
	{
		if (empty($query->table))
			throw new Exception('An UPDATE query must have a table specified.');

		if (empty($query->values))
			throw new Exception('An UPDATE query must contain at least 1 value.');

		$updates = array();
		foreach ($query->values as $key => $value)
			$updates[] = $key.' = '.$value;

		$sql = 'UPDATE '.$this->db->prefix.$query->table.' SET '.implode(', ', $updates);

		if (!empty($query->where))
			$sql .= $this->where($query->where);

		if (!empty($query->order))
			$sql .= $this->order($query->order);

		if ($query->limit > 0 || $query->offset > 0)
			$sql .= $this->limit_offset($query->limit, $query->offset);

		return $sql;
	}

	protected function delete(DeleteQuery $query)
	{
		if (empty($query->table))
			throw new Exception('A DELETE query must have a table specified.');

		$sql = 'DELETE FROM '.$this->db->prefix.$query->table;

		if (!empty($query->where))
			$sql .= $this->where($query->where);

		if (!empty($query->order))
			$sql .= $this->order($query->order);

		if ($query->limit > 0 || $query->offset > 0)
			$sql .= $this->limit_offset($query->limit, $query->offset);

		return $sql;
	}

	protected function truncate(TruncateQuery $query)
	{
		if (empty($query->table))
			throw new Exception('A TRUNCATE query must have a table specified.');

		return 'TRUNCATE TABLE '.$this->db->prefix.$query->table;
	}

	protected function replace(ReplaceQuery $query)
	{
		if (empty($query->table))
			throw new Exception('A REPLACE query must have a table specified.');

		if (empty($query->values))
			throw new Exception('A REPLACE query must contain at least 1 value.');

		$sql = 'REPLACE INTO '.$this->db->prefix.$query->table.' ('.implode(', ', array_keys($query->values)).') VALUES ('.implode(', ', array_values($query->values)).')';
	}

	protected function create_table(CreateTableQuery $query)
	{
		$columns = array();
		foreach ($query->columns as $column)
			$columns[] = $this->column_definition($column);

		return 'CREATE TABLE '.$query->table.' ('.implode(', ', $columns).')';
	}

	protected function rename_table(RenameTableQuery $query)
	{
		return 'ALTER TABLE '.$query->table.' RENAME TO '.$query->new_name;
	}

	protected function drop_table(DropTableQuery $query)
	{
		return 'DROP TABLE '.$query->table;
	}

	protected function column_definition(TableColumn $column)
	{
		if ($column->type === TableColumn::TYPE_SERIAL)
			return $this->column_serial($column->name);

		$sql = $column->name.' '.$this->column_type($column->type);

		if (!empty($column->default))
			$sql .= ' DEFAULT '.$column->default;

		if (!empty($column->key))
			$sql .= ' '.$column->key;

		return $sql;
	}

	protected function column_type($type)
	{
		return $type;
	}

	protected function column_serial($name)
	{
		return $name.' INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY';
	}

	protected function join($joins)
	{
		$sql = '';

		foreach ($joins as $join)
		{
			$sql .= ' '.$join->type.' '.$this->db->prefix.$join->table;
			if (!empty($join->on))
				$sql .= ' ON '.$this->conditions($join->on);
		}

		return $sql;
	}

	protected function where($where)
	{
		return ' WHERE '.$this->conditions($where);
	}

	protected function group($group)
	{
		return ' GROUP BY '.implode(', ', $group);
	}

	protected function having($having)
	{
		return ' HAVING '.$this->conditions($having);
	}

	protected function conditions($conditions)
	{
		return '('.$conditions.')';
	}

	protected function order($order)
	{
		return ' ORDER BY '.implode(', ', $order);
	}

	protected function limit_offset($limit, $offset)
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
}
