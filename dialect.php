<?php

/**
* Copyright (C) 2011 FluxBB (http://fluxbb.org)
* License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
*/

class SQLDialect
{
	const SET_NAMES = 'SET NAMES %s';

	protected $prefix;

	public function __construct($prefix)
	{
		$this->prefix = $prefix;
	}

	public function compile(DatabaseQuery $query)
	{
		if ($query instanceof SelectQuery)
			return $this->select($query);

		if ($query instanceof InsertQuery)
			return $this->insert($query);

		if ($query instanceof UpdateQuery)
			return $this->update($query);

		if ($query instanceof DeleteQuery)
			return $this->delete($query);

		throw new Exception('Unsupported query type: '.get_class($query));
	}

	protected function select(SelectQuery $query)
	{
		if (empty($query->fields))
			throw new Exception('A SELECT query must select at least 1 field.');

		$sql = 'SELECT '.implode(', ', $query->fields);

		if (!empty($query->table))
			$sql .= ' FROM '.$this->prefix.$query->table;

		// TODO: joins
		// TODO: where

		if (!empty($query->group))
			$sql .= $this->group($query->group);

		// TODO: having

		if (!empty($query->order))
			$sql .= $this->order($query->order);

		$sql .= $this->limit_offset($query->limit, $query->offset);

		return $sql;
	}

	protected function insert(InsertQuery $query)
	{
		if (empty($query->table))
			throw new Exception('An INSERT query must have a table specified.');

		if (empty($query->values))
			throw new Exception('An INSERT query must contain at least 1 value.');

		return 'INSERT INTO '.$this->prefix.$query->table.' ('.implode(', ', array_keys($query->values)).') VALUES ('.implode(', ', array_values($query->values)).')';
	}

	protected function update(UpdateQuery $query)
	{
		if (empty($query->table))
			throw new Exception('An UPDATE query must have a table specified.');

		if (empty($query->values))
			throw new Exception('An UPDATE query must contain at least 1 value.');

		$updates = array();
		foreach ($query->values as $key => $value)
			$updates[] = $key.'='.$value;

		$sql = 'UPDATE '.$this->prefix.$query->table.' SET '.implode(', ', $updates);

		// TODO: where

		if (!empty($query->order))
			$sql .= $this->order($query->order);

		$sql .= $this->limit_offset($query->limit, $query->offset);

		return $sql;
	}

	protected function delete(DeleteQuery $query)
	{
		if (empty($query->table))
			throw new Exception('A DELETE query must have a table specified.');

		$sql = 'DELETE FROM '.$this->prefix.$query->table;

		// TODO: where

		if (!empty($query->order))
			$sql .= $this->order($query->order);

		$sql .= $this->limit_offset($query->limit, $query->offset);

		return $sql;
	}

	protected function group($group)
	{
		return ' GROUP BY '.implode(', ', $group);
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
			$sql .= ' LIMIT '.$limit;

		if ($offset > 0)
			$sql .= ' OFFSET '.$offset;

		return $sql;
	}
}
