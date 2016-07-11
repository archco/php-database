# php_demo
First Repository

---

## class MysqliDatabase
mysqli class 를 이용하여 database control하는데 유용한 method를 제공

	include_once 'mysqlidatabase.php';
	use Cosmos\MysqliDatabase;
	
	$mysqli = new mysqli('localhost', 'my_user', 'my_password', 'my_db');
	$mdb = new MysqliDatabase($mysqli);
