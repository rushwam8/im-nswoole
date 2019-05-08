<?php

class SimpleModel {

	private $db;

	static private $host       = '127.0.0.1';

	static private $username   = 'root';

	static private $password   = '';

	static private $port       = '3306';

	static private $database   = '';

	static private $charset    = 'utf8mb4';

	static private $instance;

	/* mysql字段标识映射 */
	static private $mysql_field_flag = [
		'1'     => 'NOT_NULL',
		'2'     => 'PRI_KEY',
		'4'     => 'UNIQUE_KEY',
		'8'     => 'MULTIPLE_KEY',
		'16'    => 'BLOB',
		'32'    => 'UNSIGNED',
		'64'    => 'ZEROFILL',
		'512'   => 'AUTO_INCREMENT',
		'1024'  => 'TIMESTAMP',
		'2048'  => 'SET',
		'32768' => 'GROUP',
		'16384' => 'PART_KEY',
		'256'   => 'ENUM',
		'128'   => 'BINARY',
		'4096'  => 'NO_DEFAULT_VALUE',
		'8192'  => 'ON_UPDATE_NOW'
	];

	/* mysql字段类型映射 */
	static private $mysql_field_type = [
		'0'   => 'DECIMAL',
		'1'   => 'CHAR',
		'2'   => 'SHORT',
		'3'   => 'LONG',
		'4'   => 'FLOAT',
		'5'   => 'DOUBLE',
		'6'   => 'NULL',
		'7'   => 'TIMESTAMP',
		'8'   => 'LONGLONG',
		'9'   => 'INT24',
		'10'  => 'DATE',
		'11'  => 'TIME',
		'12'  => 'DATETIME',
		'13'  => 'YEAR',
		'14'  => 'NEWDATE',
		'247' => 'INTERVAL',
		'248' => 'SET',
		'249' => 'TINY_BLOB',
		'250' => 'MEDIUM_BLOB',
		'251' => 'LONG_BLOB',
		'252' => 'BLOB',
		'253' => 'VAR_STRING',
		'254' => 'STRING',
		'255' => 'GEOMETRY',
		'245' => 'JSON',
		'246' => 'NEWDECIMAL',
		'16'  => 'BIT'
	];

	/* 当前表字段 */
	private $current_table_fields = [];

	private $passfunc;

	/* 函数通行token值 */
	public $pass_token      = null;

	/* 函数通行token MD5值 */
	private $pass_token_md5 = null;

	/* 函数通行验证对象 */
	public $pass_obj        = null;

	/* 预处理参数 */
	private $ready_sql = [
		'table'  => '',
		'field'  => '*',
		'data'   => [],
		'where'  => [],
		'join'   => [],
		'having' => [],
		'group'  => [],
		'order'  => [],
		'take'   => null,
		'skip'   => null,
		'union'  => [],
		'lock'   => []
	];

	/* 待执行sql语句 */
	private $exec_sql     = [];

	/* sql语句的数据信息 */
	private $exec_data    = [];

	/* sql语句错误信息 */
	static private $exec_error  = false;

	/* 主体SQL语句加括号 */
	private $paren  = false;

	/* 是否执行 */
	private $exec   = false;

	/* 通行函数 */
	private $pass_func = [];

	/* RAW存储 */
	private $raw = [];

	private function __construct () {}

	private function __clone () {}

	/* 解析参数值 */
	private function _analysisSqlValue ($values) {

		$array_flag = is_array($values);

		if (!$array_flag) {
			$values = [$values];
		}

		foreach ($values as $idx => $value) {

			if (gettype($value) == 'string') {

				$value = trim($value);

				if (preg_match('/^`[^`]*`/i', $value) == 0) {
					$values[$idx] = '\''.$value.'\'';
				}
			}
		}

		if (!$array_flag) {
			$values = implode('', $values);
		}

		return $values;
	}

	/* 初始化内部通行函数 */
	private function _initPassFunc () {

		/* 返回SQL语句 */
		$this->pass_func['returnSql'] = function () {
			return $this->_combineSelectSQL();
		};

		/* 返回RAW */
		$this->pass_func['raw'] = function ($original='', $value=[]) {

			$original = isset($this->raw['original'])?$this->raw['original']:$original;
			$value    = isset($this->raw['value'])?$this->raw['value']:$value;

			$this->raw['original'] = null;
			$this->raw['value']    = null;

			if (is_string($original)) {

				$original = str_replace('?', '%s', trim($original), $num);

				foreach ($value as $key => $val) {
					
					$value[$key] = $this->_analysisSqlValue($val);
					
				}
				
				if ($num > 0 && is_array($value)) {
					$original = call_user_func_array('sprintf', array_merge([$original], $value));
				}

				return $original;

			}

		};
		
	}

	/* 初始化mysql常量信息 未使用暂保留 待优化 */
	private function initMySqlConstant () {

        $constants = get_defined_constants(true);

        $mysqli_constants = $constants['mysqli'];
        
        foreach ($mysqli_constants as $orgtype => $typesign) {
        	if (preg_match('/^MYSQLI_TYPE_(.*)/', $orgtype, $typename)) {
        		$mysql_field_type[$typesign] = $typename[1];
        	} else if (preg_match('/MYSQLI_(.*)_FLAG$/', $orgtype, $typename)) {
        		$mysql_field_flag[$typesign] = $typename[1];
        	}
    	}
	}

	/* 选择执行的表 */
	final static public function table ($table) {

        self::$instance = new self();

        if (!isset(self::$instance->db)) {
			self::$instance->db = mysqli_connect(self::$host, self::$username, self::$password, self::$database, self::$port);
		}

		if (mysqli_connect_errno()) {
		    printf("Connect failed: %s\n", mysqli_connect_error());
		} else {
			if(self::$instance->setCharset(self::$charset)){
				mysqli_ping(self::$instance->db);
			}
		}

		$table = explode(' ', $table);
		
		$table = trim($table[0]);

		if ($result = mysqli_query(self::$instance->db, 'SELECT * from '.$table)) {

			$column = 0;

			while ($field_info = mysqli_fetch_field($result)) {

				self::$instance->current_table_fields[$column] = [
					'name'   => $field_info->orgname,
					'column' => $column+1,
					'type'   => self::$mysql_field_type[$field_info->type],
					'flag'   => [],
				];

				foreach (self::$mysql_field_flag as $flag => $flagname) {
					if ($field_info->flags & $flag) {
						self::$instance->current_table_fields[$column]['flag'][] = $flagname;
					}
				}

				++$column;

		    }

		    mysqli_free_result($result);

		}

		self::$exec_error = false;

		self::$instance->ready_sql['table'] = $table;

		self::$instance->_initPassFunc();

		self::$instance->pass_token_md5 = MD5(rand(1, 99999999).$table);

		return self::$instance;

	}

	/* 输出原生字符串 此方法可以优化 */
	final static public function raw ($original, $value=[]) {

		$original_raw = new self();

		$original_raw->raw['original'] = $original;
		$original_raw->raw['value']    = $value;

		return $original_raw;
		
	}

	/* 组合成SELECT SQL语句 */
	private function _combineSelectSQL () {
		
		$this->exec_sql = [];

		$sql_arr = [];

		if ($this->paren === true) {
			$sql_arr[] = '(';
		}
		
		$sql_arr[] = sprintf('SELECT %s FROM %s', $this->ready_sql['field'], $this->ready_sql['table']);
		$sql_arr[] = $this->_join();
		$sql_arr[] = $this->_where();
		$sql_arr[] = $this->_group();
		$sql_arr[] = $this->_having();
		$sql_arr[] = $this->_order();
		$sql_arr[] = $this->_limit();
		$sql_arr[] = $this->_lock();

		if ($this->paren === true) {
			$sql_arr[] = ')';
		}

		$sql_arr[] = $this->_union();

		foreach ($sql_arr as $arr) {
			if (!empty($arr)) {
				$this->exec_sql[] = $arr;
			}
		}

		return implode('', $this->exec_sql);
	}

	/* 组合成INSERT SQL语句 */
	private function _combineInsertSQL () {

		$this->exec_sql = [];

		$sql_arr = [];

		if ($this->paren === true) {
			$sql_arr[] = '(';
		}
		
		$sql_arr[] = sprintf('INSERT INTO %s (%s) VALUES %s', $this->ready_sql['table'], $this->ready_sql['field'], implode(', ', $this->ready_sql['data']));

		if ($this->paren === true) {
			$sql_arr[] = ')';
		}

		foreach ($sql_arr as $arr) {
			if (!empty($arr)) {
				$this->exec_sql[] = $arr;
			}
		}

		return implode('', $this->exec_sql);
	}

	/* 组合成UPDATE SQL语句 */
	private function _combineUpdateSQL () {

		$this->exec_sql = [];

		$sql_arr = [];

		if ($this->paren === true) {
			$sql_arr[] = '(';
		}
		
		$sql_arr[] = sprintf('UPDATE %s SET %s', $this->ready_sql['table'], implode(', ', $this->ready_sql['data']));
		$sql_arr[] = $this->_where();

		if ($this->paren === true) {
			$sql_arr[] = ')';
		}

		foreach ($sql_arr as $arr) {
			if (!empty($arr)) {
				$this->exec_sql[] = $arr;
			}
		}

		return implode('', $this->exec_sql);
	}

	/* 组合成DELETE SQL语句 */
	private function _combineDeleteSQL () {

		$this->exec_sql = [];

		$sql_arr = [];

		$where   = $this->_where();

		if (!empty($where)) {

			if ($this->paren === true) {
				$sql_arr[] = '(';
			}
			
			$sql_arr[] = sprintf('DELETE FROM %s', $this->ready_sql['table']);
					
			$sql_arr[] = $where;
			
			if ($this->paren === true) {
				$sql_arr[] = ')';
			}

			foreach ($sql_arr as $arr) {
				if (!empty($arr)) {
					$this->exec_sql[] = $arr;
				}
			}
		}

		return implode('', $this->exec_sql);
	}

	/* public通行token验证 */
	final public function passTokenCheck ($method, $data) {

		$flag = null;

		if ($this->pass_obj instanceof $this) {

			if (MD5($this->pass_token_md5.$this->pass_obj->pass_token) === MD5($this->pass_token_md5.$this->pass_token)) {
				
				if (isset($this->pass_func[$method])) {
					$flag = call_user_func_array($this->pass_func[$method], $data);
				}

			}

			$this->pass_obj   = null;

			$this->pass_token = null;

		}

		return $flag;
	}

	/* 调用对象的通行函数 */
	private function _callPassFunc ($object, $method, $data=[]) {
		
		$this->pass_token  = $object->pass_token = MD5(rand(0, 9999999).$method.serialize($data));

		$object->pass_obj  = $this;

		return call_user_func_array([$object, 'passTokenCheck'], [$method, $data]);

	}

	/* 输出SQL语句 */
	final static public function printSql () {

		if (self::$instance instanceof self) {

			if (!empty(self::$instance->exec_sql)) {

				return [implode('', self::$instance->exec_sql), self::$instance->exec_data];

			}

		}

	}

	/* 输出SQL语句错误信息 */
	final static public function printError () {

		return self::$exec_error;

	}

	/* 组合where */
	private function _where () {

		$where_arr = [];

		if (count($this->ready_sql['where']) > 0) {

			$where_arr[] = ' WHERE ';

			$connector    = false;

			$connector_raw_count = 0;

			$idx_num = 1;
			
			foreach ($this->ready_sql['where'] as $index => $where) {

				if ($connector_raw_count === 0) {

					if (is_array($where)) {

						if ($where[0] !== true) {

							$where_arr[$idx_num] = trim($where[0]).' '.trim($where[1]).($where[2]?' '.trim($where[2]):'');

							if ($connector == true) {
								$where_arr[$idx_num] = ' '.trim($where[3]).' '.trim($where_arr[$idx_num]);
							}

							$connector = true;

						} else {

							if ($where[1] === 2) {

								if ($connector === true) {
									$where_arr[$idx_num] = $this->ready_sql['where'][$index+1].$this->ready_sql['where'][$index+2];
								} else {
									$where_arr[$idx_num] = $this->ready_sql['where'][$index+2];
									$connector = true;
								}

								$connector_raw_count = 2;

							}

						}

					} else if (is_string($where)) {

						$where_arr[$idx_num] = $where;

						$connector = false;

					}

					++$idx_num;

				} else {

					--$connector_raw_count;

				}
			}

		}
		
		return implode('', $where_arr);
	}

	/* 组合having */
	private function _having () {

		$having_arr = [];

		if (count($this->ready_sql['having']) > 0) {

			$having_arr[] = ' HAVING ';

			$connector = false;

			$connector_raw_count = 0;

			$idx_num = 1;

			foreach ($this->ready_sql['having'] as $index => $where) {

				if ($connector_raw_count === 0) {

					if (is_array($where)) {
						
						if ($where[0] !== true) {

							$having_arr[$idx_num] = trim($where[0]).' '.trim($where[1]).($where[2]?' '.trim($where[2]):'');

							if ($connector == true) {
								$having_arr[$idx_num] = ' '.trim($where[3]).' '.trim($having_arr[$idx_num]);
							}

							$connector = true;

						} else {

							if ($where[1] === 1) {

								$having_arr[$idx_num] = $this->ready_sql['having'][$index+1];

								$connector_raw_count = 1;

								if ($connector === false) {
									$connector = true;
								}

							} else if ($where[1] === 2) {

								if ($connector === true) {
									$having_arr[$idx_num] = $this->ready_sql['having'][$index+1].$this->ready_sql['having'][$index+2];
								} else {
									$having_arr[$idx_num] = $this->ready_sql['having'][$index+2];
									$connector = true;
								}

								$connector_raw_count = 2;

							}

						}

					} else if (is_string($where)) {

						$having_arr[$idx_num] = $where;

						$connector = false;

					}

					++$idx_num;

				} else {

					--$connector_raw_count;

				}
			}
		}

		return implode('', $having_arr);
	}

	/* 组合join */
	private function _join () {

		$join_arr = [];

		if (count($this->ready_sql['join']) > 0) {

			$connector = false;

			$connector_raw_count = 0;

			$idx_num = 1;

			foreach ($this->ready_sql['join'] as $index => $where) {

				if ($connector_raw_count === 0) {

					if (is_array($where)) {
						
						if ($where[0] !== true) {

							$join_arr[$idx_num] = trim($where[0]).' '.trim($where[1]).($where[2]?' '.trim($where[2]):'');

							if ($connector == true) {
								$join_arr[$idx_num] = ' '.trim($where[3]).' '.trim($join_arr[$idx_num]);
							}

							$connector = true;

						} else {

							if ($where[1] === 1) {

								$join_arr[$idx_num] = $this->ready_sql['join'][$index+1];

								$connector_raw_count = 1;

								if ($connector === false) {
									$connector = true;
								}

							} else if ($where[1] === 2) {

								if ($connector === true) {
									$join_arr[$idx_num] = $this->ready_sql['join'][$index+1].$this->ready_sql['join'][$index+2];
								} else {
									$join_arr[$idx_num] = $this->ready_sql['join'][$index+2];
									$connector = true;
								}

								$connector_raw_count = 2;

							}

						}

					} else if (is_string($where)) {

						$join_arr[$idx_num] = $where;

						$connector = false;

					}

					++$idx_num;

				} else {

					--$connector_raw_count;

				}
			}
		}

		return implode('', $join_arr);
	}

	/* 组合union */
	private function _union () {

		$union_arr = [];

		foreach ($this->ready_sql['union'] as $union) {
			$union_arr[] = ' '.$union[1].' '.'('.$union[0].')';
		}

		return implode('', $union_arr);
		
	}

	/* 组合union */
	private function _order () {

		$order_arr = [];

		$order_raw = false;

		if (count($this->ready_sql['order']) > 0) {

			$order_arr[] = ' ORDER BY ';

			if (is_bool($this->ready_sql['order'][0])) {
				if (!empty($this->ready_sql['order'][1])) {
					$order_arr[] = $this->ready_sql['order'][1];
				} else {
					$order_arr = [];
				}
			} else {
				$order_arr[] = implode(', ', $this->ready_sql['order']);
			}

		}

		return implode('', $order_arr);
		
	}

	/* 组合group */
	private function _group () {

		$group_arr = [];

		if (count($this->ready_sql['group']) > 0) {

			$group_arr[] = ' GROUP BY ';

			$group_arr[] = implode(', ', $this->ready_sql['group']);

		}

		return implode('', $group_arr);
		
	}

	/* 组合limit */
	private function _limit () {

		$limit_arr = [];

		if (!is_null($this->ready_sql['take'])) {

			$limit_arr[] = ' LIMIT ';

			if (!empty($this->ready_sql['skip']) && $this->ready_sql['skip'] > 0) {
				$limit_arr[] = trim($this->ready_sql['skip']).',';
			}

			$limit_arr[] = trim($this->ready_sql['take']);

		}

		return implode('', $limit_arr);
	}

	/* 组合行锁 */
	private function _lock () {
		return !empty($this->ready_sql['lock'])?$this->ready_sql['lock']:'';
	}

	/* 配置字段 */
	final public function select () {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$arr = [];

			$argv = func_get_args();

			foreach ($argv as $idx => $arg) {

				if ($arg instanceof $this) {

					$arr[] = trim($this->_callPassFunc($arg, 'raw'));

					$argv[$idx] = null;

				} else if (is_string($arg)) {

					$arg = explode(',', $arg);

					$arr[] = trim($arg[0]);

				}
			}

			$this->ready_sql['field'] = implode(', ', $arr);

			return $this;

		}
		
	}

	/* 配置字段 原生 */
	final public function selectRaw ($original, $value=[]) {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$this->ready_sql['field'] = trim($this->_callPassFunc($this, 'raw', [
				$original, $value
			]));

			return $this;

		}
		
	}

	/* 设置WHERE条件 */
	final public function where ($field, $operation='', $value='') {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$argc = func_num_args();

			if ($argc == 3) {
				$this->ready_sql['where'][] = [
					$field, $operation, $this->_analysisSqlValue($value), 'AND'
				];
			} else if ($argc == 2) {
				$this->ready_sql['where'][] = [
					$field, '=', $this->_analysisSqlValue($operation), 'AND'
				];
			} else if ($argc == 1) {
				if (is_object($field)) {
					
					if ($field instanceof $this) {

						$this->ready_sql['where'][] = [true, 2];

						$this->ready_sql['where'][] = ' AND ';

						$this->ready_sql['where'][] = $this->_callPassFunc($field, 'raw');

						$field = null;

					} else {

						$passfunc = $this->passfunc;

						$this->passfunc = [
							'where', 'orWhere', 'whereIn', 'whereNotIn'
							, 'whereNull', 'whereNotNull', 'orWhereIn', 'orWhereNotIn', 'orWhereNull', 'orWhereNotNull'
							, 'orWhereRaw', 'whereRaw'
						];

						$this->ready_sql['where'][] = ' AND ';

						$this->ready_sql['where'][] = '(';

						$field($this);

						$this->ready_sql['where'][] = ')';

						$this->passfunc = $passfunc;

					}

				} else if (is_array($field)) {

					foreach ($field as $where) {
						$where[3] = 'AND';
						$this->ready_sql['where'][] = $where;
					}

				}
			}

			return $this;

		}
	}

	/* 设置AND ON条件 */
	final public function on ($leftfield, $operation='', $rightfield='') {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$argc = func_num_args();
			
			if ($argc == 3) {
				$this->ready_sql['join'][] = [
					$leftfield, $operation, $rightfield, 'AND'
				];
			} else if ($argc == 2) {
				$this->ready_sql['join'][] = [
					$leftfield, '=', $operation, 'AND'
				];
			} else if ($argc == 1) {

				if (is_object($leftfield)) {

					if ($leftfield instanceof $this) {

						$this->ready_sql['join'][] = [true, 2];

						$this->ready_sql['join'][] = $this->_callPassFunc($leftfield, 'raw');

						$leftfield = null;

					} else {

						$passfunc = $this->passfunc;

						$this->passfunc = [
							'on', 'orOn'
						];

						$this->ready_sql['join'][] = ' AND ';

						$this->ready_sql['join'][] = '(';

						$leftfield($this);

						$this->ready_sql['join'][] = ')';

						$this->passfunc = $passfunc;

					}
				}
			}

			return $this;

		}
	}

	/* 设置OR ON条件 */
	final public function orOn ($leftfield, $operation='', $rightfield='') {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$argc = func_num_args();
			
			if ($argc == 3) {
				$this->ready_sql['join'][] = [
					$leftfield, $operation, $rightfield, 'OR'
				];
			} else if ($argc == 2) {
				$this->ready_sql['join'][] = [
					$leftfield, '=', $operation, 'OR'
				];
			} else if ($argc == 1) {
				if (is_object($leftfield)) {

					if ($leftfield instanceof $this) {

						$this->ready_sql['join'][] = [true, 2];

						$this->ready_sql['join'][] = ' OR ';

						$this->ready_sql['join'][] = $this->_callPassFunc($leftfield, 'raw');

						$leftfield = null;

					} else {

						$passfunc = $this->passfunc;

						$this->passfunc = [
							'on', 'orOn'
						];

						$this->ready_sql['join'][] = ' OR ';

						$this->ready_sql['join'][] = '(';

						$leftfield($this);

						$this->ready_sql['join'][] = ')';

						$this->passfunc = $passfunc;

					}
				}
			}

			return $this;

		}

	}

	/* 设置WHERE OR条件 */
	final public function orWhere ($field, $operation='', $value='') {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$argc = func_num_args();

			if ($argc == 3) {
				$this->ready_sql['where'][] = [
					$field, $operation, $this->_analysisSqlValue($value), 'OR'
				];
			} else if ($argc == 2) {
				$this->ready_sql['where'][] = [
					$field, '=', $this->_analysisSqlValue($operation), 'OR'
				];
			} else if ($argc == 1) {
				if (is_object($field)) {

					if ($field instanceof $this) {

						$this->ready_sql['where'][] = [true, 2];

						$this->ready_sql['where'][] = ' OR ';

						$this->ready_sql['where'][] = $this->_callPassFunc($field, 'raw');

						$field = null;

					} else {

						$passfunc = $this->passfunc;

						$this->passfunc = [
							'where', 'orWhere', 'whereIn', 'whereNotIn'
							, 'whereNull', 'whereNotNull', 'orWhereIn', 'orWhereNotIn', 'orWhereNull', 'orWhereNotNull'
							, 'orWhereRaw', 'whereRaw'
						];

						$this->ready_sql['where'][] = ' OR ';

						$this->ready_sql['where'][] = '(';

						$field($this);

						$this->ready_sql['where'][] = ')';

						$this->passfunc = $passfunc;

					}

				} else if (is_array($field)) {
					
					foreach ($field as $where) {
						$where[3] = 'AND';
						$this->ready_sql['where'][] = $where;
					}

				}
			}

			return $this;

		}

	}

	/* 设置WHERE AND 原生条件 */
	final public function whereRaw ($original, $value=[]) {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			if (is_string($original)) {

				$this->ready_sql['where'][] = [true, 2];

				$this->ready_sql['where'][] = ' AND ';

				$this->ready_sql['where'][] = $this->_callPassFunc($this, 'raw', [
					$original, $value
				]);

			}

			return $this;

		}

	}

	/* 设置WHERE OR 原生条件 */
	final public function orWhereRaw ($original, $value=[]) {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			if (is_string($original)) {

				$this->ready_sql['where'][] = [true, 2];

				$this->ready_sql['where'][] = ' OR ';

				$this->ready_sql['where'][] = $this->_callPassFunc($this, 'raw', [
					$original, $value
				]);

			}

			return $this;

		}

	}

	/* 设置IN条件 */
	final public function whereIn ($field, $in) {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			if (empty($combine_symbol)) {
				$combine_symbol = 'AND';
			}

			$this->ready_sql['where'][] = [
				$field, 'IN', '('.implode(', ', $this->_analysisSqlValue($in)).')', 'AND'
			];

			return $this;

		}

	}

	/* 设置OR IN条件 */
	final public function orWhereIn ($field, $in) {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			if (empty($combine_symbol)) {
				$combine_symbol = 'AND';
			}

			$this->ready_sql['where'][] = [
				$field, 'IN', '('.implode(', ', $this->_analysisSqlValue($in)).')', 'OR'
			];

			return $this;

		}

	}

	/* 设置NOT IN条件 */
	final public function whereNotIn ($field, $operation='', $value='') {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$this->ready_sql['where'][] = [
				$field, 'NOT IN', '('.implode(', ', $this->_analysisSqlValue($in)).')', 'AND'
			];

			return $this;

		}

	}

	/* 设置OR NOT IN条件 */
	final public function orWhereNotIn ($field, $in) {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$this->ready_sql['where'][] = [
				$field, 'IN', '('.implode(', ', $this->_analysisSqlValue($in)).')', 'OR'
			];

			return $this;

		}

	}

	/* 设置IS NULL AND条件 */
	final public function whereNull ($field) {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$this->ready_sql['where'][] = [
				$field, 'IS NULL', null, 'AND'
			];

			return $this;

		}

	}

	/* 设置IS NULL OR条件 */
	final public function orWhereNull ($field) {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$this->ready_sql['where'][] = [
				$field, 'IS NULL', null, 'OR'
			];

			return $this;

		}

	}

	/* 设置NOTNULL AND条件 */
	final public function whereNotNull ($field) {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$this->ready_sql['where'][] = [
				$field, 'IS NOT NULL', null, 'AND'
			];

			return $this;

		}

	}

	/* 设置OR NOT NULL条件 */
	final public function orWhereNotNull ($field) {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$this->ready_sql['where'][] = [
				$field, 'IS NOT NULL', null, 'OR'
			];

			return $this;

		}

	}

	/* 配置获取多少条数据 */
	final public function take ($take) {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$this->ready_sql['take'] = $take;

			return $this;

		}
	}

	/* 配置开始获取的位置 */
	final public function skip ($skip) {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$this->ready_sql['skip'] = $skip;

			return $this;

		}

	}

	/* 内连接 */
	final public function join ($table, $leftfield, $operation='', $rightfield='') {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$argc = func_num_args();

			$this->ready_sql['join'][] = ' JOIN '.$table;

			$this->ready_sql['join'][] = ' ON ';

			if ($argc == 4) {
				$this->ready_sql['join'][] = [
					$leftfield, $operation, $rightfield, ''
				];
			} else if ($argc == 3) {
				$this->ready_sql['join'][] = [
					$leftfield, '=', $operation, ''
				];
			} else if ($argc == 2) {
				if (is_object($leftfield)) {

					$passfunc = $this->passfunc;

					$this->passfunc = [
						'on', 'orOn', 'where', 'orWhere', 'whereIn', 'whereNotIn'
						, 'whereNull', 'whereNotNull', 'orWhereIn', 'orWhereNotIn', 'orWhereNull', 'orWhereNotNull'
						, 'orWhereRaw', 'whereRaw'
					];

					$leftfield($this);

					$this->passfunc = $passfunc;

				}
			}

			return $this;
		}

	}

	/* 左连接 */
	final public function leftJoin ($table, $leftfield, $operation='', $rightfield='') {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$argc = func_num_args();

			$this->ready_sql['join'][] = ' LEFT JOIN '.$table;

			$this->ready_sql['join'][] = ' ON ';

			if ($argc == 4) {
				$this->ready_sql['join'][] = [
					$leftfield, $operation, $rightfield, ''
				];
			} else if ($argc == 3) {
				$this->ready_sql['join'][] = [
					$leftfield, '=', $operation, ''
				];
			} else if ($argc == 2) {
				if (is_object($leftfield)) {

					$passfunc = $this->passfunc;

					$this->passfunc = [
						'on', 'orOn', 'where', 'orWhere', 'whereIn', 'whereNotIn'
						, 'whereNull', 'whereNotNull', 'orWhereIn', 'orWhereNotIn', 'orWhereNull', 'orWhereNotNull'
						, 'orWhereRaw', 'whereRaw'
					];

					$leftfield($this);

					$this->passfunc = $passfunc;

				}
			}

			return $this;
		}

	}

	/* 右连接 */
	final public function rightJoin ($table, $leftfield, $operation='', $rightfield='') {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$argc = func_num_args();

			$this->ready_sql['join'][] = ' RIGHT JOIN '.$table;

			$this->ready_sql['join'][] = ' ON ';

			if ($argc == 4) {
				$this->ready_sql['join'][] = [
					$leftfield, $operation, $rightfield, ''
				];
			} else if ($argc == 3) {
				$this->ready_sql['join'][] = [
					$leftfield, '=', $operation, ''
				];
			} else if ($argc == 2) {
				if (is_object($leftfield)) {

					$passfunc = $this->passfunc;

					$this->passfunc = [
						'on', 'orOn', 'where', 'orWhere', 'whereIn', 'whereNotIn'
						, 'whereNull', 'whereNotNull', 'orWhereIn', 'orWhereNotIn', 'orWhereNull', 'orWhereNotNull'
						, 'orWhereRaw', 'whereRaw'
					];

					$leftfield($this);

					$this->passfunc = $passfunc;

				}
			}

			return $this;
		}

	}

	/* 交叉连接 */
	final public function crossJoin ($table) {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$sql = $this->_callPassFunc($table, 'returnSql');

			if (!empty($sql)) {

				$table = '('.$sql.')';

				$this->ready_sql['join'][] = ' CROSS JOIN '.trim($table);

				return $this;

			}
		}

	}

	/* 查询联合 */
	final public function union ($table) {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$sql = $this->_callPassFunc($table, 'returnSql');

			if (!empty($sql)) {

				if ($this->paren == false) {
					$this->paren = true;
				}

				$this->ready_sql['union'][] = [$sql, 'UNION'];

				return $this;

			}
		}

	}

	/* 查询联合ALL */
	final public function unionAll ($table) {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$sql = $this->_callPassFunc($table, 'returnSql');

			if (!empty($sql)) {

				if ($this->paren == false) {
					$this->paren = true;
				}

				$this->ready_sql['union'][] = [$sql, 'UNION ALL'];

				return $this;

			}

		}

	}

	/* 排序 */
	final public function orderBy ($field, $sort) {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$this->ready_sql['order'][] = trim($field).' '.trim($sort);

			return $this;

		}

	}

	/* 排序 原生 */
	final public function orderByRaw ($original, $value=[]) {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			if (is_string($original)) {

				$this->ready_sql['order'] = [true, $this->_callPassFunc($this, 'raw', [
					$original, $value
				])];

			}

			return $this;

		}

	}

	/* 聚合查询 */
	final public function groupBy () {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$argv = func_get_args();

			if (isset($argv[0]) && $argv[0] instanceof $this) {

				$this->ready_sql['group'] = [trim($this->_callPassFunc($argv[0], 'raw'))];

				$argv[0] = null;

			} else {

				foreach ($argv as $field) {
					if (is_string($field)) {
						$field = explode(',', $field);
						$this->ready_sql['group'][] = trim($field[0]);
					}
				}

			}

			return $this;
		}

	}

	/* 设置OR 原生条件 */
	final public function havingRaw ($original, $value=[]) {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			if (is_string($original)) {

				$this->ready_sql['having'][] = [true, 2];

				$this->ready_sql['having'][] = ' OR ';

				$this->ready_sql['having'][] = $this->_callPassFunc($this, 'raw', [
					$original, $value
				]);

			}

			return $this;

		}

	}

	/* 筛选查询 */
	final public function having ($field, $operation='', $value='') {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			$argc = func_num_args();

			if ($argc == 3) {
				$this->ready_sql['having'][] = [
					$field, $operation, $value, 'AND'
				];
			} else if ($argc == 2) {
				$this->ready_sql['having'][] = [
					$field, '=', $operation, 'AND'
				];
			} else if ($argc == 1) {
				if (is_object($field)) {

					if ($field instanceof $this) {

						$this->ready_sql['having'][] = [true, 2];

						$this->ready_sql['having'][] = ' AND ';

						$this->ready_sql['having'][] = $this->_callPassFunc($field, 'raw');

						$field = null;

					} else {

						$passfunc = $this->passfunc;

						$this->passfunc = [
							'having', 'orHaving'
						];

						$this->ready_sql['having'][] = ' AND ';

						$this->ready_sql['having'][] = '(';

						$field($this);

						$this->ready_sql['having'][] = ')';

						$this->passfunc = $passfunc;

					}

				}
			}

			return $this;

		}

	}

	/* 设置OR 原生条件 */
	final public function orHavingRaw ($original, $value=[]) {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {

			if (is_string($original)) {

				$this->ready_sql['having'][] = [true, 2];

				$this->ready_sql['having'][] = ' OR ';

				$this->ready_sql['having'][] = $this->_callPassFunc($this, 'raw', [
					$original, $value
				]);

			}

			return $this;

		}

	}

	/* 筛选查询 */
	final public function orHaving ($field, $operation='', $value='') {

		if (is_null($this->passfunc) || in_array(__FUNCTION__, $this->passfunc)) {
			
			$argc = func_num_args();
			
			if ($argc == 3) {
				$this->ready_sql['having'][] = [
					$field, $operation, $value, 'OR'
				];
			} else if ($argc == 2) {
				$this->ready_sql['having'][] = [
					$field, '=', $operation, 'OR'
				];
			} else if ($argc == 1) {
				if (is_object($field)) {

					if ($field instanceof $this) {

						$this->ready_sql['having'][] = [true, 2];

						$this->ready_sql['having'][] = ' OR ';

						$this->ready_sql['having'][] = $this->_callPassFunc($field, 'raw');

						$field = null;

					} else {

						$passfunc = $this->passfunc;

						$this->passfunc = [
							'having', 'orHaving'
						];

						$this->ready_sql['having'][] = ' OR ';

						$this->ready_sql['having'][] = '(';

						$field($this);

						$this->ready_sql['having'][] = ')';

						$this->passfunc = $passfunc;

					}
				}
			}

			return $this;

		}

	}


	/* 指定数据库 */
	final public function selectDb ($database) {
		return mysqli_select_db($this->db, $database);
	}

	/* 指定字符集 */
	final public function setCharset ($charset) {
		return mysqli_set_charset($this->db, $charset);
	}

	/* 开启事务 */
	final public function startTransaction ($autocommit=false) {
		return mysqli_begin_transaction($this->db);
	}

	/* 提交事务 */
	final public function commitTransaction () {
		return mysqli_commit($this->db);
	}

	/* 回滚事务 */
	final public function rollbackTransaction () {
		return mysqli_rollback($this->db);
	}

	/* 多行查询 */
	final public function get ($fields=['*']) {

		$arr = [];

		call_user_func_array([$this, 'field'], $fields);

		$sql = $this->_combineSelectSQL();
		
		if ($result = mysqli_query($this->db, $sql, MYSQLI_USE_RESULT)) {
			while ($row = mysqli_fetch_assoc($result)) {
				$arr[] = $row;
			}
			mysqli_free_result($result);
		}

		$this->_destory_mysqli();

		return $arr;

	}

	/* 单行查询 */
	final public function first ($fields=['*']) {

		$arr = [];

		call_user_func_array([$this, 'select'], $fields);

		$this->take(1);

		$this->skip(0);

		$sql = $this->_combineSelectSQL();
		
		if ($result = mysqli_query($this->db, $sql, MYSQLI_STORE_RESULT)) {
			while ($row = mysqli_fetch_assoc($result)) {
				$arr = $row;
				break;
			}
			mysqli_free_result($result);
		}

		$this->_destory_mysqli();
		
		return $arr;
	}

	/* 获取单列的某个字段值 */
	final public function value ($field) {

		$val = '';

		call_user_func_array([$this, 'select'], $field);

		$this->take(1);
		
		$this->skip(0);

		$sql = $this->_combineSelectSQL();
		
		if ($result = mysqli_query($this->db, $sql, MYSQLI_STORE_RESULT)) {
			while ($row = mysqli_fetch_assoc($result)) {
				$val = $row[$field];
				break;
			}
			mysqli_free_result($result);
		}
		
		$this->_destory_mysqli();

		return $val;
	}

	/* 获取单列的数组 */
	final public function pluck ($field, $fieldkey=false) {

		$pluck = [];

		if (is_string($fieldkey)) {
			$field = [$field, $fieldkey];
		}

		call_user_func_array([$this, 'select'], $field);

		$sql = $this->_combineSelectSQL();
		
		if ($result = mysqli_query($this->db, $sql, MYSQLI_USE_RESULT)) {
			while ($row = mysqli_fetch_assoc($result)) {
				if (is_string($fieldkey)) {
					$pluck[$row[$fieldkey]] = $row[$field];
				} else {
					$pluck[] = $row[$field];
				}
			}
			mysqli_free_result($result);
		}

		$this->_destory_mysqli();
		
		return $pluck;
	}

	/* 获取单列的数组 */
	final public function max ($field) {

		$max = 0;

		$max_field = 'MAX('.$field.')';

		$this->ready_sql['field'] = $max_field;

		$sql = $this->_combineSelectSQL();
		
		if ($result = mysqli_query($this->db, $sql, MYSQLI_STORE_RESULT)) {
			while ($row = mysqli_fetch_assoc($result)) {
				$max = $row[$max_field];
				break;
			}
			mysqli_free_result($result);
		}

		$this->_destory_mysqli();

		return $max;
	}

	/* 获取单列的数组 */
	final public function min ($field) {

		$min = 0;

		$min_field = 'MIN('.$field.')';

		$this->ready_sql['field'] = $min_field;
		
		$sql = $this->_combineSelectSQL();
		
		if ($result = mysqli_query($this->db, $sql, MYSQLI_STORE_RESULT)) {
			while ($row = mysqli_fetch_assoc($result)) {
				$min = $row[$min_field];
				break;
			}
			mysqli_free_result($result);
		}

		$this->_destory_mysqli();

		return $min;
	}

	/* 获取单列的数组 */
	final public function avg ($field) {

		$avg = 0;

		$max_field = 'AVG('.$field.')';

		$this->ready_sql['field'] = $max_field;
		
		$sql = $this->_combineSelectSQL();
		
		if ($result = mysqli_query($this->db, $sql, MYSQLI_STORE_RESULT)) {
			while ($row = mysqli_fetch_assoc($result)) {
				$avg = $row[$max_field];
				break;
			}
			mysqli_free_result($result);
		}

		$this->_destory_mysqli();

		return $avg;
	}

	/* 获取单列的数组 */
	final public function sum ($field) {

		$sum = '';

		$max_field = 'SUM('.$field.')';

		$this->ready_sql['field'] = $max_field;
		
		$sql = $this->_combineSelectSQL();
		
		if ($result = mysqli_query($this->db, $sql, MYSQLI_STORE_RESULT)) {
			while ($row = mysqli_fetch_assoc($result)) {
				$sum = $row[$max_field];
				break;
			}
			mysqli_free_result($result);
		}

		$this->_destory_mysqli();

		return $sum;
	}

	/* 查询数量 */
	final public function count ($field='*') {

		$count = 0;

		$count_field = 'COUNT('.$field.')';

		$this->ready_sql['field'] = $count_field;

		$sql = $this->_combineSelectSQL();
		
		if ($result = mysqli_query($this->db, $sql, MYSQLI_STORE_RESULT)) {
			while ($row = mysqli_fetch_assoc($result)) {
				$count = $row[$count_field];
				break;
			}
			mysqli_free_result($result);
		}

		$this->_destory_mysqli();

		return $count;
	}

	/* 插入 */
	final public function insert ($datas) {

		$this->ready_sql['data']  = [];

		$this->ready_sql['field'] = '';

		$result = false;

		if (is_array($datas)) {

			$values = [];

			// 检测是否是多维数组
			if (count($datas) === count($datas, COUNT_RECURSIVE)) {
				
				$field   = array_keys($datas);

				$values  = array_values($datas);

				$prepare = array_map(function () {
					return '?';
				}, $values);

				$this->ready_sql['data']  = ['('.implode(', ', $prepare).')'];

				$this->ready_sql['field'] = implode(', ', $field);

			} else {

				foreach ($datas as $data) {

					$value = [];

					$field = array_keys($data);

					$value = array_values($data);

					$prepare = array_map(function () {
						return '?';
					}, $value);

					$this->ready_sql['data'][] = '('.implode(', ', $prepare).')';

					if (empty($this->ready_sql['field'])) {
						$this->ready_sql['field']  = implode(', ', $field);
					}

					$values = array_merge($values, $value);

				}

			}

			$sql = trim($this->_callPassFunc($this, 'raw', [
				$this->_combineInsertSQL(), $values
			]));

			$prepare_sql = mysqli_prepare($this->db, $sql);

			if ($prepare_sql) {

				mysqli_stmt_execute($prepare_sql);

				$result = mysqli_stmt_affected_rows($prepare_sql);

				mysqli_stmt_close($prepare_sql);

			}

			$this->exec_data = $values;

		}

		$this->_destory_mysqli();

		return $result;


	}

	/* 插入后返回ID */
	final public function insertGetId ($datas) {

		$this->ready_sql['data']  = [];

		$this->ready_sql['field'] = '';

		$result = false;

		if (is_array($datas)) {

			$values = [];

			// 检测是否是多维数组
			if (count($datas) === count($datas, COUNT_RECURSIVE)) {
				
				$field   = array_keys($datas);

				$values  = array_values($datas);

				$prepare = array_map(function () {
					return '?';
				}, $values);

				$this->ready_sql['data']  = ['('.implode(', ', $prepare).')'];

				$this->ready_sql['field'] = implode(', ', $field);

			} else {

				foreach ($datas as $data) {

					$value = [];

					$field = array_keys($data);

					$value = array_values($data);

					$prepare = array_map(function () {
						return '?';
					}, $value);

					$this->ready_sql['data'][] = '('.implode(', ', $prepare).')';

					if (empty($this->ready_sql['field'])) {
						$this->ready_sql['field']  = implode(', ', $field);
					}
					
					$values = array_merge($values, $value);

				}

			}

			$sql = trim($this->_callPassFunc($this, 'raw', [
				$this->_combineInsertSQL(), $values
			]));
			
			$prepare_sql = mysqli_prepare($this->db, $sql);

			if ($prepare_sql) {

				mysqli_stmt_execute($prepare_sql);

				$result = mysqli_stmt_affected_rows($prepare_sql);

				mysqli_stmt_close($prepare_sql);

				if ($result > 0) {

					$result = mysqli_insert_id($this->db);

				}

			}

			$this->exec_data = $values;

		}

		$this->_destory_mysqli();

		return $result;

	}

	/* 更新 */
	final public function update ($data) {

		$this->ready_sql['data']  = [];

		$this->ready_sql['field'] = '';

		$result = false;

		if (is_array($data)) {

			$values = [];

			// 检测是否是多维数组
			if (count($data) === count($data, COUNT_RECURSIVE)) {
				
				$field  = array_keys($data);

				$values = array_values($data);

				$prepare = array_map(function ($val) {
					return $val.' = ?';
				}, $field);

				$this->ready_sql['data'] = [implode(', ', $prepare)];

			}

			$sql = trim($this->_callPassFunc($this, 'raw', [
				$this->_combineUpdateSQL(), $values
			]));
			
			$prepare_sql = mysqli_prepare($this->db, $sql);

			if ($prepare_sql) {

				mysqli_stmt_execute($prepare_sql);

				$result = mysqli_stmt_affected_rows($prepare_sql);

				mysqli_stmt_close($prepare_sql);

			}

			$this->exec_data = $values;

		}

		$this->_destory_mysqli();

		return $result;

	}

	/* 删除 */
	final public function delete () {

		$result = false;

		$sql = $this->_combineDeleteSQL();
		
		$prepare_sql = mysqli_prepare($this->db, $sql);
		
		if ($prepare_sql) {

			mysqli_stmt_execute($prepare_sql);

			$result = mysqli_stmt_affected_rows($prepare_sql);
			
			mysqli_stmt_close($prepare_sql);

		}

		$this->_destory_mysqli();

		return $result;
	}

	/* 清空表 */
	final public function truncate () {

		$result = false;
		
		$prepare_sql = mysqli_prepare($this->db, sprintf('TRUNCATE TABLE %s', $this->ready_sql['table']));

		if ($prepare_sql) {

			mysqli_stmt_execute($prepare_sql);

			$result = mysqli_stmt_affected_rows($prepare_sql);

			mysqli_stmt_close($prepare_sql);

		}

		$this->_destory_mysqli();

		return $result;
	}

	/* 共享锁 悲观锁 */
	final public function sharedLock () {
		$this->ready_sql['lock'] = 'LOCK IN SHARE MODE';
	}

	/* 标准锁 悲观锁 */
	final public function lockForUpdate () {
		$this->ready_sql['lock'] = 'FOR UPDATE';
	}

	/* 销毁 */
	final public function __destory () {
		$this->_destory_mysqli();
	}

	/* 销毁 */
	private function _destory_mysqli () {

		if (mysqli_errno($this->db)) {
			self::$exec_error = mysqli_error($this->db);
		}

		mysqli_close($this->db);
	}

}
