# fluxbb-database
A lightweight wrapper around [PHP::PDO](http://www.php.net/manual/en/book.pdo.php), providing both SQL abstraction and an extensible query interface.

Abstraction can be split into 2 different types - driver abstraction, and SQL syntax abstraction. The SQL syntax abstraction we perform has 2 goals:
 * Allowing portability between different DBMS.
 * Allowing queries to be easily modified by hooks and/or filters before execution.

## Supported drivers
 * [Any supported by PDO](http://www.php.net/manual/en/pdo.drivers.php)

## Supported dialects
 * MySQL
 * SQLite 3
 * PostgreSQL, from 8.4

## License
[LGPL - GNU Lesser General Public License](http://www.gnu.org/licenses/lgpl.html)

## Documentation
[API and use](http://fluxbb.org/docs/modules/database)
