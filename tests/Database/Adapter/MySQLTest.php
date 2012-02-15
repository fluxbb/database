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
 * @package		Database
 * @subpackage	Tests
 * @copyright	Copyright (c) 2011 FluxBB (http://fluxbb.org)
 * @license		http://www.gnu.org/licenses/lgpl.html	GNU Lesser General Public License
 */

namespace fluxbb\database\tests;

require_once dirname(__FILE__).'/../../../src/Database/Adapter.php';
require_once dirname(__FILE__).'/../AdapterTestCase.php';

class Adapter_MySQLTest extends AdapterTestCase
{
	public function createAdapter()
	{
		if (!in_array('mysql', \PDO::getAvailableDrivers())) {
            $this->markTestSkipped(
              'The MySQL driver cannot be loaded.'
            );
        }

		$conf = array(
			'dbname'	=> $_ENV['DB_MYSQL_DBNAME'],
			'host'		=> $_ENV['DB_MYSQL_HOST'],
			'username'	=> $_ENV['DB_MYSQL_USER'],
			'password'	=> $_ENV['DB_MYSQL_PASSWD'],
		);

		$adapter = \fluxbb\database\Adapter::factory('MySQL', $conf);

		$result = $adapter->query('SHOW TABLES');
		while ($table = $result->fetchColumn()) {
			$adapter->exec('DROP TABLE '.$table);
		}

		return $adapter;
	}
}
