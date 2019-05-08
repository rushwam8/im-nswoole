<?php

class InstantAssign {

	protected $serv;

	protected $redis;

	protected $socket_unique_id;

	protected $header;

	protected $args;

	protected $mode;

	protected $fd;

	protected $resource_id;

	protected $request;

	protected $response;

	protected $scope = 'normal';

	/* WebSocket 参数处理/分发 */
	final public function dispense ($redis, $server, $socket_unique_id, $method, $fd, $reid, $mode, $header, $body) {
		
		$this->redis = $redis;
		
		$this->serv  = $server;

		$this->socket_unique_id = $socket_unique_id;

		$this->args   = $body;

		$this->header = $header;

		$this->mode = $mode;

		$this->fd   = $fd;

		$this->resource_id      = $reid;
		
		if (method_exists($this, $method)) {
			
			$method_type = new \ReflectionMethod($this, $method);
			
			// 确认方法是受保护的
			if ($method_type->isProtected() === true) {
				$this->__pretreatment();
				$this->__recycle($this->$method());
			}
		}
	}
	
	/* HTTP 参数处理/分发 */
	final public function dispenseHttp ($redis, $server, $socket_unique_id, $method, $request, $response) {

		$this->redis            = $redis;
		
		$this->serv             = $server;

		$this->socket_unique_id = $socket_unique_id;

		$this->request          = $request;

		$this->response         = $response;
		
		if (method_exists($this, $method)) {
			
			$method_type = new \ReflectionMethod($this, $method);
			
			// 确认方法是受保护的
			if ($method_type->isProtected() === true) {
				$this->__pretreatment();
				$this->__recycle($this->$method());
			}
		}
	}
	
	/* HTTP 参数处理/分发 */
	final public function disconnect ($redis, $server, $socket_unique_id, $method, $request, $response) {

		$this->redis = $redis;

		$this->serv  = $server;

		$this->socket_unique_id = $socket_unique_id;

		$this->request  = $request;

		$this->response = $response;
		
		if (method_exists($this, $method)) {
			
			$method_type = new \ReflectionMethod($this, $method);
			
			// 确认方法是受保护的
			if ($method_type->isProtected() === true) {
				$this->__pretreatment();
				$this->__recycle($this->$method());
			}
		}
	}

	/* 格式化数据 */
	final protected function formatData ($format, $datas, $exclude=[]) {
		
		$result = [];
		
		foreach ($format as $key => $val) {
			if (is_numeric($key)) {
				if (isset($datas[$val]) && !in_array($datas[$val], $exclude)) {
					$result[$val] = $datas[$val];
				} else {
					$result[$val] = '';
				}
			} else {
				if (isset($datas[$key]) && !in_array($datas[$key], $exclude)) {
					$result[$val] = $datas[$key];
				} else {
					$result[$val] = '';
				}
			}
		}
		
		return $result;
	}

	/* 预处理函数 */
	protected function __pretreatment () {}

	/* 回收函数 */
	protected function __recycle ($result) {}

}

?>
