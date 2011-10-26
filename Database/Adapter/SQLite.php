<?php

/**
 * SQL Dialect for SQLite
 *
 * Copyright (C) 2011 FluxBB (http://fluxbb.org)
 * License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
 */

class Flux_Database_Adapter_SQLite extends Flux_Database_Adapter
{
	public function generateDsn()
	{
		if (!isset($this->options['file'])) {
			throw new Exception('No filename specified for SQLite database.');
		}

		return 'sqlite:'.$this->options['file'];
	}

	public function setNames($charset)
	{
		$sql = 'PRAGMA encoding = '.$this->quote($charset);
		if ($this->exec($sql) === false)
			return;

		$this->charset = $charset;
	}

	public function runTruncate(Flux_Database_Query_Truncate $query)
	{
		if (empty($query->table))
			throw new Exception('A TRUNCATE query must have a table specified.');

		// Reset sequence counter
		$sql = 'DELETE FROM sqlite_sequence WHERE name = '.$this->quote($query->table).';';
		$sql .= 'DELETE FROM '.$query->table;

		return $this->prepare($sql);
	}

	public function runTableExists(Flux_Database_Query_TableExists $query)
	{
		$sql = 'SELECT 1 FROM sqlite_master WHERE name = \''.$query->table.'\' AND type=\'table\'';
		return (bool) $this->query($sql)->fetchColumn();
	}

	public function runIndexExists(Flux_Database_Query_IndexExists $query)
	{
		$sql = 'SELECT 1 FROM sqlite_master WHERE name = \''.$query->table.'_'.$query->index.'\' AND tbl_name = \''.$query->table.'\' AND type=\'index\'';
		return (bool) $this->query($sql)->fetchColumn();
	}

	public function runDropIndex(Flux_Database_Query_DropIndex $query)
	{
		$sql = 'DROP INDEX '.$query->table.'_'.$query->index;
		return $this->exec($sql);
	}

	public function runDropField(Flux_Database_Query_DropField $query)
	{
		$table = $this->runTableInfo($data);

		$now = time();
		$tmptable = str_replace('CREATE TABLE '.$query->table.' (', 'CREATE TABLE '.$query->table.'_t'.$now.' (', $table['sql']);
		$this->exec($tmptable);

		$this->exec('INSERT INTO '.$query->table.'_t'.$now.' SELECT * FROM '.$query->table);

		unset($table['columns'][$query->field]);
		$new_columns = array_keys($table['columns']);

		$new_table = 'CREATE TABLE '.$query->table.' (';

		foreach ($table['columns'] as $cur_column => $column_details)
			$new_table .= "\n".$cur_column.' '.$column_details.',';

		if (isset($table['unique']))
			$new_table .= "\n".$table['unique'].',';

		if (isset($table['primary_key']))
			$new_table .= "\n".$table['primary_key'].',';

		$new_table = trim($new_table, ',')."\n".');';

		// Drop old table
		$this->exec('DROP TABLE '.$query->table);

		// Create new table
		$this->exec($new_table);

		// Recreate indexes
		if (!empty($table['indices']))
		{
			foreach ($table['indices'] as $cur_index)
				if (!preg_match('%\('.preg_quote($field_name, '%').'\)%', $cur_index))
					$this->exec($cur_index);
		}

		// Copy content back
		$this->exec('INSERT INTO '.$query->table.' SELECT '.implode(', ', $new_columns).' FROM '.$query->table.'_t'.$now);

		$this->exec('DROP TABLE '.$query->table.'_t'.$now);

		// TODO: Handle query errors
		return true;
	}

	public function runAddIndex(Flux_Database_Query_AddIndex $query)
	{
		$sql = 'CREATE '.($query->unique ? 'UNIQUE ' : '').'INDEX '.$query->table.'_'.$query->index.' ON '.$query->table.'('.implode(',', $query->fields).')';
		return $this->exec($sql);
	}

	public function runTableInfo(Flux_Database_Query_TableInfo $query)
	{
		$result = $this->query('SELECT sql FROM sqlite_master WHERE tbl_name = \''.$query->table.'\' ORDER BY type DESC');

		$rows = $result->fetchAll(PDO::FETCH_ASSOC);
		$num_rows = count($rows);

		if ($num_rows == 0)
			return;

		$table = array(
			'indices'	=> array(),
			'sql'		=> '',
			'columns'	=> array(),
		);
		foreach ($rows as $cur_index)
		{
			if (empty($cur_index['sql']))
				continue;

			if (empty($table['sql']))
				$table['sql'] = $cur_index['sql'];
			else
				$table['indices'][] = $cur_index['sql'];
		}

		// Work out the columns in the table currently
		$result = $this->query('PRAGMA table_info('.$query->table.')');
		foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row)
		{
			$table['columns'][$row['name']] = $row;
		}

		// TODO: Primary key etc.
		return $table;
	}

	protected function compileColumnSerial($name)
	{
		return $name.' INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT';
	}

	protected function compileLimitOffset($limit, $offset)
	{
		$sql = '';

		if ($offset > 0 && $limit == 0)
			$limit = -1;

		if ($limit > 0)
			$sql .= ' LIMIT '.intval($limit);

		if ($offset > 0)
			$sql .= ' OFFSET '.intval($offset);

		return $sql;
	}
}
