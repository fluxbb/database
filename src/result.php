<?php

/**
* Copyright (C) 2010 Jamie Furness (http://www.jamierf.co.uk)
* License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
*/

class DatabaseResult
{
	private $result;
	private $db;

	public function __construct($result, $db)
	{
		$this->result = $result;
		$this->db = $db;
	}

	public function affected_rows()
	{
		return $this->db->affected_rows($this->result);
	}

	public function has_rows()
	{
		return $this->db->has_rows($this->result);
	}

	public function fetch_row()
	{
		return $this->db->fetch_row($this->result);
	}

	public function fetch_assoc()
	{
		return $this->db->fetch_assoc($this->result);
	}

	public function fetch_single()
	{
		$row = $this->fetch_row();
		if ($row === null)
			return null;

		return $row[0];
	}

	public function __destruct()
	{
		$this->db->free($this->result);
	}
}
