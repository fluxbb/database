# fluxbb-database
A lightweight wrapper around [PHP::PDO](http://www.php.net/manual/en/book.pdo.php), providing basic SQL abstraction and a more compact API.

Abstraction can be split into 2 different types - driver abstraction, and SQL syntax abstraction. The SQL syntax abstraction we perform has 2 goals:
 * Allowing portability between different DBMS.
 * Allowing queries to be easily modified by hooks and/or filters before execution.

## Supported drivers
 * [Any supported by PDO](http://www.php.net/manual/en/pdo.drivers.php)

## Supported dialects
 * MySQL
 * SQLite
 * PostgreSQL

## License
[LGPL - GNU Lesser General Public License](http://www.gnu.org/licenses/lgpl.html)

## Database class overview

	Database {
		string $prefix
		string $charset

		__construct( string $dsn [, array $args = array()] )
		void set_names( string $charset )
		void set_prefix( string $prefix )
		string quote( string $str )
		mixed query( DatabaseQuery $query [, array $params = array()] )
		string insert_id( void )
		bool start_transaction( void )
		bool commit_transaction( void )
		bool rollback_transaction( void )
		bool in_transaction( void )
		array get_debug_queries( void )
		string get_version( void )
	}

## Regular Query structures

### SELECT

	$query = new SelectQuery(array('tid' => 't.id AS tid', 'time' => 't.time', 'fieldname' => 't.fieldname', 'uid' => 'u.id AS uid', 'username' => 'u.username'), 'topics AS t');
	
	$query->joins['u'] = new InnerJoin('users AS u');
	$query->joins['u']->on = 'u.id = t.user_id';
	
	$query->where = 't.time > :now';
	$query->group_by = array('tid' => 't.id');
	$query->order_by = array('time' => 't.time DESC');
	$query->limit = 25;
	$query->offset = 100;

Will compile to something along the lines of:

	SELECT t.id AS tid, t.time, t.fieldname, u.id AS uid, u.username FROM topics AS t INNER JOIN users AS u ON (u.id = t.user_id) WHERE (t.time > :now) GROUP BY t.id ORDER BY t.time DESC LIMIT 25 OFFSET 100

### UPDATE

	$query = new UpdateQuery(array('user_id' => ':user_id'), 'topics');
	$query->where = 'id > :tid';
	$query->order_by = array('id' => 'id DESC');
	$query->limit = 1;
	$query->offset = 100;

Will compile to something along the lines of:

	UPDATE topics SET user_id = :user_id WHERE id > :tid ORDER BY id DESC LIMIT 1 OFFSET 100

### INSERT

	$query = new InsertQuery(array('time' => ':now', 'fieldname' => ':fieldname', 'user_id' => ':user_id'), 'topics');

Will compile to something along the lines of:

	INSERT INTO topics(time, fieldname, user_id) VALUES (:now, :fieldname, :user_id)

### REPLACE

	$query = new ReplaceQuery(array('id' => ':tid', 'time' => ':now', 'fieldname' => ':fieldname', 'user_id' => ':user_id'), 'topics', 'id');

Will compile to something along the lines of:

	REPLACE INTO topics(id, time, fieldname, user_id) VALUES(:tid, :now, :fieldname, :user_id)

### DELETE

	$query = new DeleteQuery('topics');
	$query->where = 'time < :now';
	$query->order_by = array('time' => 'time DESC');
	$query->limit = 25;
	$query->offset = 100;

Will compile to something along the lines of:

	DELETE FROM topics WHERE time < :now ORDER BY time DESC LIMIT 25 OFFSET 100

### TRUNCATE

	$query = new TruncateQuery('topics');

Will compile to something along the lines of:

	TRUNCATE TABLE topics
