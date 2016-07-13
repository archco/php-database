<?php 
/**
 * MysqliDatabase
 * database control 하는데 유용한 method 제공하는 class
 *
 * @author cosmos <archcoster@gmail.com>
 * @license MIT
 */
namespace cosmos\database;

class MysqliDatabase
{
	const VERSION = '0.1';
	
	/** @var string Error message. */
	public $error;
	/** @var string recently SQL. */
	public $sql;
	/** @var mysqli mysqli object. */
	protected $_mysqli;

	/**
	 * __construct
	 * 
	 * @param	mysqli	$mysqli	mysqli object
	 */
	function __construct(\mysqli $mysqli) {
		$this->_mysqli = $mysqli;
	}

	/**
	 * stat 
	 * 현재 mysqli system 상태를 return
	 * @return	string	server상태에 대한 서술. 에러면 FALSE.
	 */
	public function stat() {
		return $this->_mysqli->stat();
	}

	/**
	 * insert 
	 * 해당 table 에 하나의 row 를 insert.
	 * @param	string	$table	table_name
	 * @param	array	$values	content values (associative array)
	 * @return	integer		insert_id, 에러면 -1
	 */
	public function insert($table, $values) {
		// generate query
		$str_col = $str_val = $query = "";
		$str_col = "`".implode("`,`", $this->_sanitize(array_keys($values)))."`";
		$str_val = "'".implode("','", $this->_sanitize(array_values($values)))."'";
		$query = "INSERT INTO {$table} ($str_col) VALUES ($str_val)";
		$this->sql = $query;
		// execute
		$db = $this->_mysqli;
		if($db->query($query) === true) {
			return $db->insert_id;
		}else {
			$this->error = $db->error;
			return -1;
		}
	}

	/**
	 * delete
	 * 해당하는 rows 를 table 에서 삭제.
	 * @param	string	$table	table name
	 * @param	string	$whereClause	WHERE를 제외한 WHERE clause, null이면 모든 rows 삭제(주의)
	 * @param	array	$whereArgs	whereClause에서 ?를 사용했을시의 arguments (numeric array)
	 * @return	integer	영향받은 row의 수, 0이면 해당사항없음, 에러면 -1
	 */
	public function delete($table, $whereClause, $whereArgs) {
		// generate query
		$whereClause = $this->_makeWhere($whereClause, $whereArgs);
		$query = "DELETE FROM {$table}{$whereClause}";
		$this->sql = $query;
		// execute
		$db = $this->_mysqli;
		if($db->query($query) == true) {
			return $db->affected_rows;
		}else {
			$this->error = $db->error;
			return -1;
		}
	}

	/**
	 * update
	 * 해당하는 rows 를 update.
	 * @param	string	$table	table name
	 * @param	array	$values	content values (associative array)
	 * @param	string	$whereClause WHERE를 제외한 WHERE clause, null 로할시 모든 rows가 수정됨(주의!)
	 * @param	array	$whereArgs	whereClause에서 ?를 사용할 시의 argument (numeric array)
	 * @return	integer		영향받은 row의 수, 0은 없음, -1은 에러
	 */
	public function update($table, $values, $whereClause, $whereArgs) {
		// generate query
		$query = $set = $where = "";
		$tmp_col = $this->_sanitize(array_keys($values));
		$tmp_val = $this->_sanitize(array_values($values));
		foreach ($tmp_col as $key => $value) {
			$set .= "`".$tmp_col[$key]."`='".$tmp_val[$key]."'";
			if(count($tmp_col) != $key + 1) {
				$set .= ",";
			}
		}
		$where = $this->_makeWhere($whereClause, $whereArgs);
		$query = "UPDATE {$table} SET {$set}{$where}";
		$this->sql = $query;
		// execute
		$db = $this->_mysqli;
		if($db->query($query) === true) {
			return $db->affected_rows;
		}else {
			$this->error = $db->error;
			return -1;
		}
	}

	/**
	 * select_full
	 * select query 를 실행하여 나온 result set 을 연관배열로 return
	 * @param  string 	$table 	table name
	 * @param  array 	$columns 	column names (numeric array)
	 * @param  string|null 	$whereClause 	WHERE 를 제외한 WHERE clause, null 이면 모든 rows 선택
	 * @param  array|null 	$whereArgs 	whereClause 에서 ?를 사용하였다면 그 argument (numeric array)
	 * @param  string|null 	$groupBy 	GROUP BY 를 제외한 clause, null 이면 해당 없음
	 * @param  string|null 	$having 	HAVING 을 제외한 clause, null 이면 해당없음
	 * @param  string|null 	$orderBy 	ORDER BY 를 제외한 clause, null 이면 해당없음
	 * @param  string|null 	$limit 		LIMIT 를 제외한 clause, null 이면 해당없음
	 * @param  boolean 	$distinct 	(optional) DISTINCT 의 사용 여부 default = false
	 * @return array 	result-set (associative array)
	 */
	public function select_full($table, $columns, $whereClause, $whereArgs, $groupBy, $having, $orderBy, $limit, $distinct = false) {
		// generate query
		$query = "";
		$dist = $distinct ? " DISTINCT" : "";
		$col = implode(",", $columns);
		$where = $this->_makeWhere($whereClause, $whereArgs);
		$group = "";
		if(!is_null($groupBy)) {
			$group .= " GROUP BY ".$groupBy;
			if(!is_null($having)) {
				$group .= " HAVING ".$having;
			}
		}
		$order = is_null($orderBy) ? "" : " ORDER BY ".$orderBy;
		$lim = is_null($limit) ? "" : " LIMIT ".$limit;
		$query = "SELECT{$dist} {$col} FROM {$table}{$where}{$group}{$order}{$lim}";
		$this->sql = $query;
		// execute
		$db = $this->_mysqli;
		if($result = $db->query($query)) {
			$output = $result->fetch_all(MYSQLI_ASSOC);
		}else {
			$this->error = $db->error;
			$output = null;
		}
		return $output;
	}

	/**
	 * select_simple
	 * 
	 * @param  string 	$table 		table name
	 * @param  array 	$columns	column names (numeric array)
	 * @param  string|null 	$whereClause 	WHERE를 제외한 WHERE clause, null 이면 모든 rows 선택
	 * @param  array|null	$whereArgs	whereClause에서 ?를 사용하였다면 그 argument (numeric array)
	 * @param  string|null 	$orderBy 	ORDER BY 를 제외한 clause, null 이면 해당없음
	 * @return array	result-set (associative array)
	 */
	public function select_simple($table, $columns, $whereClause, $whereArgs, $orderBy) {
		return $this->select_full($table, $columns, $whereClause, $whereArgs, null, null, $orderBy, null, false);
	}

	/**
	 * execSQL
	 * sql query 를 실행.(주의! 보안위험)
	 * @param	string	$query	데이터에 주의해야함
	 * @return	boolean		query 실행 성공 여부
	 */
	public function execSQL($query) {
		if($this->_mysqli->query($query)) {
			return true;
		}else {
			$this->error = $this->_mysqli->error;
			return false;
		}
	}

	/**
	 * __call
	 * method Overroading
	 * @param	string	$method		method name
	 * @param	array	$arguments	numeric array
	 * @return	mixed	method's return
	 */
	public function __call($method, $arguments) {
		if($method == 'select') {
			if(count($arguments) == 9) {
				return call_user_func_array(array($this,'select_full'), $arguments);
			}else if(count($arguments) == 5) {
				return call_user_func_array(array($this,'select_simple'), $arguments);
			}
		}
	}

	/**
	 * _sanitize
	 * array 의 value 들을 escape string.
	 * @param	string|array	$input	input
	 * @return	string|array		output
	 */
	protected function _sanitize($input) {
		$db = $this->_mysqli;
		if(is_string($input)) {
			$input = $db->real_escape_string($input);
		}else if(is_array($input)) {
			foreach ($input as $key => $value) {
				$input[$key] = $db->real_escape_string($value);
			}
		}	
		return $input;
	}

	/**
	 * _makeWhere
	 * WHERE clause 를 만들어 return
	 * @param	string	$whereClause	WHERE를 제외한 WHERE clause
	 * @param	array	$whereArgs	whereClause에서 ?를 사용하였으면 그 argument (numeric array)
	 * @return	string		WHERE clause, whereClause가 null이면 ""
	 */
	protected function _makeWhere($whereClause, $whereArgs) {
		$str = "";
		if(!is_null($whereClause)) {
			if(!is_null($whereArgs)) {
				foreach ($whereArgs as $key => $value) {
					$value = $this->_sanitize($value);
					$whereClause = preg_replace('/\?/', "'".$value."'", $whereClause, 1);
				}
			}
			$str = " WHERE ".$whereClause;
		}
		return $str;
	}

	/**
	 * insert_stmt
	 * 해당 table 에 하나의 row 를 insert.(use prepare statement) (미완성)
	 * @param	string	$table	table_name
	 * @param	string	$types	i: integer, d: double, s: string, b: blob
	 * @param	array	$values	content_value (associative array)
	 * @return	integer		insert_id, 에러면 -1
	 */
	public function insert_stmt($table, $types, $values) {
		$db = $this->_mysqli;
		$query = $this->_generateInsertPrepare($table, array_keys($values));
		$args = &$this->_refineParams($types, array_values($values));
		$params = [];
		// params reference 처리.
		foreach ($args as $key => $value) {
			if($key == 0) {
				$params[] = $args[$key];
				continue;
			}
			$params[] =& $args[$key];
		}

		$stmt = $db->prepare($query);
		call_user_func_array(array($stmt,"bind_param"), $params);

		if($stmt->execute() === true) {
			return $db->insert_id;
		}else {
			$this->error = $db->error;
			return -1;
		}
	}

	/* (미완성) */
	protected function _getResultUseStmt($query, $args = []) {
		$db = $this->_mysqli;
		$params = [];
		$result = [];

		// Reference 를 안썼을 경우 임시로 참조를 달아준다.
		for($i = 0; $i < count($args); $i++) {
			if($i == 0) {
				$params[] = $args[$i];
				continue;
			}
			$params[] =& $args[$i];
		}

		$stmt = $db->prepare($query);
		if(!empty($args)){
			call_user_func_array(array($stmt,"bind_param"), $params);
		}
		$stmt->execute();
		$r = $stmt->get_result();
		$result = $r->fetch_all(MYSQLI_ASSOC);
		$r->free();
		return $result;
	}

	/**
	 * _generateInsertPrepare
	 * insert_stmt() 용 INSERT query 를 return
	 * @param	string	$table	table_name
	 * @param	array	$columns	column_names (numeric array)
	 * @return	string		prepare_query
	 */
	protected function _generateInsertPrepare($table, $columns) {
		$tmp_col = "";
		$tmp_val = "";		
		$query = "";

		foreach ($columns as $key => $value) {
			$tmp_col .= "`".$value."`";
			$tmp_val .= "?";
			if(count($columns) !== $key+1){
				$tmp_col .= ",";
				$tmp_val .= ",";
			}
		}
		
		$query = "INSERT INTO {$table} ($tmp_col) VALUES ($tmp_val)";
		return $query;
	}

	/**
	 * _refineParams
	 * stmt->bind_param() 을 위한 parameter 정리
	 * @param	string	$types	value types (0 index)
	 * @param	array	$values	values (numeric array)
	 * @return	array		params with types (numeric arrray)
	 */
	protected function _refineParams($types, $values) {
		$array = [];
		$array[] = $types;
		foreach ($values as $key => $value) {
			$array[] = $value;
		}
		
		return $array;
	}
}
?>