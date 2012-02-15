# fluxbb-database ![Build status](https://secure.travis-ci.org/fluxbb/database.png?branch=master)
A lightweight wrapper around [PHP::PDO](http://www.php.net/manual/en/book.pdo.php), providing both SQL abstraction and an extensible query interface.

The SQL syntax abstraction we perform has 2 goals:

 * Allowing portability between different DBMS.
 * Allowing queries to be easily modified by hooks and/or filters before execution.

## Documentation
[On our website](http://fluxbb.org/docs/v2.0/modules/database)

## Supported drivers / dialects
 * MySQL
 * SQLite 3
 * PostgreSQL, from 8.4

Theoretically, it is easy (and planned for the future) to add new adapters (if [ supported by PDO](http://www.php.net/manual/en/pdo.drivers.php)), although some SQL abstraction might have to be rewritten.

## License
[LGPL - GNU Lesser General Public License](http://www.gnu.org/licenses/lgpl.html)
