# php-db
A lightweight wrapper around PHP::PDO , providing basic SQL abstraction and a more compact API.

License: [LGPL - GNU Lesser General Public License](http://www.gnu.org/licenses/lgpl.html)

## Supported drivers
 * [Any supported by PDO](http://uk3.php.net/manual/en/pdo.drivers.php)

## Example usage
	// Open a new database - sqlite in memory will do - using the sqlite dialect obviously
	$db = new Database('sqlite::memory:', array('debug' => true), 'sqlite');

	$db->start_transaction();

	// Select some rubbish as a test...
	$query = new SelectQuery();
	$query->fields = array('1 AS one', '(1+1) AS two', 'current_time AS time', ':1 AS little_bobby_tables');

	// Execute the query, passing some rubbish as a parameter
	$params = array(':1' => 'Robert\'); DROP TABLE Students;--');
	$result = $db->query($query, $params);

	// Display our results
	print_r($result);

	// Free the result
	unset ($result);

	$db->commit_transaction();

	// Close the database
	unset ($db);

## Example output
	Array
	(
		[0] => Array
			(
				[one] => 1
				[two] => 2
				[time] => 17:09:06
				[little_bobby_tables] => Robert'); DROP TABLE Students;--
			)

	)
