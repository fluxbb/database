<?php

/**
 * SQL Dialect for MySQL
 * 
 * Copyright (C) 2011 FluxBB (http://fluxbb.org)
 * License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
 */

class SQLDialect_MySQL extends SQLDialect
{
	const DEFAULT_ENGINE = 'MyISAM';

	protected $engine;

	public function __construct($db, $args = array())
	{
		parent::__construct($db, $args);

		$this->engine = isset($args['engine']) ? $args['engine'] : self::DEFAULT_ENGINE;
	}

	protected function create_table(CreateTableQuery $query)
	{
		$sql = parent::create_table($query);

		if (!empty($this->engine))
			$sql .= ' ENGINE = '.$this->db->quote($this->engine);

		if (!empty($this->charset))
			$sql .= ' CHARSET = '.$this->db->quote($this->charset);

		return $sql;
	}
}
