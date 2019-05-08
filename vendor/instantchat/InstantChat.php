<?php
// WebSocket Chat Main Server

/* 即时聊天整合套件 王智鹏(WAM) CreteDate:2018-3-24 */
class InstantChat {

    // socket 服务对象
    private $server;
    
    // redis 操作对象
    private $redis;

    // redis 访问密码
    private $redis_host = '127.0.0.1';

    // redis 访问密码
    private $redis_pwd  = '';

    // redis 访问库
    private $redis_db   = 10;

    // 错误信息
    private $err_result = [];

    // socket 服务运行起始时间
    private $create_time;
    
    // socket 唯一
    private $socket_unique_id;
    
    // 有关联的uid
    private $relate_suid = [];
    
    // 插件对象存储体
    private $plugins = [];

    // 域管理
    private $scopes = [];

    // 默认操作入口目录
    private $default_app = 'Api';

    // 根目录
    private $base_dir;

    // 初始化swoole socket server
    public function __construct ($listen_ip, $listen_port, $sign, $scope='') {

        $this->base_src_dir     = dirname(dirname(dirname(__FILE__))).'/src';

        $this->socket_unique_id = MD5(strtolower($sign).$listen_ip.$listen_port.strtoupper($scope));
        
        $this->server = new \swoole_websocket_server($listen_ip, $listen_port);

        $this->server->set([
            'worker_num' => 2,
            'task_worker_num' => 4,
            'heartbeat_check_interval' => 4,
            'heartbeat_idle_time'      => 10,
            'daemonize' => 1,
            'websocket_subprotocol'    => 'InstantChat'
        ]);
        
        $this->create_time = time();

        $this->initRely();
        
        if (count($this->err_result) > 0) {
           print_r($this->err_result);
           die('Resource Init Fail');
        }
        
        //redis 标识该服务类型已经启动
        $this->redis->set(MD5($this->socket_unique_id), 1);
        
    }

    // 设置webserver的配置参数
    public function setServerConfig ($configs=[]) {
        $this->server->set($configs);
    }
    
    // 获得对象的Uid
    public function GetSUid () {
        return $this->socket_unique_id?:null;
    }
        
    // 设置关联对象的SUid
    public function SetRelateSUid ($alias, $suid) {
        $this->relate_suid[$alias] = $suid;
    }

    // 初始化应用资源
    private function initResource () {

        $user_list = $this->redis->keys('u_*');

        $instant_user = \InstantChat\InstantUser::getInstance('normal', [
            'socket_unique_id' => $this->socket_unique_id,
            'redis'            => $this->redis,
            'prefix_scope'     => 'u_',
            'scope'            => 'normal'
        ]);

        foreach ($user_list as $u_key) {

            $name   = $this->redis->hget($u_key, 'name');
            $online = $this->redis->hget($u_key, 'online');
            
            if ($online == 1) {
                $instant_user->modifyUserInfo($name, [
                    'online' => 0
                ]);
            }
        }

        VesselBox::removeBox('InstantChat\InstantUser', 'normal');

    }

    // 初始化依赖程序
    private function initRely () {

        $result = [];

        $this->redis = $this->initRedis();

        $this->initResource();

        return $result;
    }

    // INIT REDIS
    private function initRedis () {

        if (class_exists('Redis', false)) {

            $redis = new \Redis();

            if (!$redis->connect($this->redis_host, 6379)){

                $this->err_result[] = 'Redis Service Fail';

            } else {

                if (!empty($this->redis_pwd)) {
                    $redis->auth($this->redis_pwd);
                }

                if ($this->redis_db > 0) {
                    $redis->select($this->redis_db);
                }

                return $redis;

            }

        } else {

            $this->err_result[] = 'Redis Extend Not Found';

        }

        return false;

    }
    
    // 获得socket资源绑定
    private function getSocketBind ($fd, $scope='normal', $isdel=false) {
        
        // 获取绑定ID
        $bind_id = MD5($this->socket_unique_id.'bind'.$scope.$fd);
        
        $bind_data = $this->redis->hgetall($bind_id);
        
        if ($isdel === true) {
            $this->redis->del($bind_id);
        }
        
        return $bind_data;
    }
    
    // socket资源绑定
    private function socketBind ($fd, $params, $scope='normal') {
        
        // 获取绑定ID
        $bind_id = MD5($this->socket_unique_id.'bind'.$scope.$fd);
        
        $this->redis->del($bind_id);
        
        foreach ($params as $key => $val) {
            $this->redis->hset($bind_id, $key, $val);
        }
        
        return $bind_id;
    }
    
    // 创建用户
    private function createUser ($fd, $resource_id, $name, $alias, $password, $passwordrule=1, $scope='') {
        
        $name_id = $this->plugins['user']->initUser($name, $alias, $password, $passwordrule, $scope);
        
        if ($name_id !== false) {
            return true;
        }
        
        return false;
        
    }
    
    // 注册组、人唯一通信ID
    private function registerID ($sign, $salt, $prefix='') {
        return $prefix.MD5($this->socket_unique_id.'register'.$sign.$salt.$prefix);
    }
    
    // 创建socket资源
    private function createSocketResource ($fd, $resource_id='') {
        
        // 公共资源库ID
        $common_resource = MD5($this->socket_unique_id.'common_resource');
        
        if (empty($resource_id)) {
            //    创建sign
            $sign = MD5($this->socket_unique_id.$fd.'sign'.microtime(true).rand(1000, 99999999));
            // 创建userid
            $resource_id = $this->registerID($fd, $sign, 'resource');
        }
        
        // socketid
        $this->redis->hset($common_resource, $resource_id, $fd);
        
        return $resource_id;

    }
    
    // 监听端口HTTP请求
    public function request () {

        $this->server->on('request', function ($request, $response) {

            $redis = $this->initRedis();

            $uri = isset($request->get['s'])?$request->get['s']:'';

            if (!empty($uri)) {
                $target_dir = explode('@', $uri);
                
                if (count($target_dir) == 2) {
                    $this->default_app = ucwords($target_dir[0]);
                }

                $target_path = explode('/', $target_dir[count($target_dir)-1]);
                $target_path_len = count($target_path);
                for ($i = 0; $i < $target_path_len; $i++) {
                    if ($i < $target_path_len-1) {
                        $target_path[$i] = ucwords($target_path[$i]);
                    }
                }

                $target_path_str = str_replace('\\', '/', dirname(implode('/', $target_path)));
                $target_method   = basename(implode('/', $target_path));
                
                $target_dir  = dirname(dirname(implode('/', $target_path)));
                if ($target_dir == '.' || $target_dir == '..') {
                    $target_dir = '';
                }

                $target_file_name = basename(dirname(implode('/', $target_path)));

                if (is_dir($this->base_src_dir.'/'.$this->default_app.'/'.$target_dir)) {

                    $target_file = $this->base_src_dir.'/'.$this->default_app.'/'.$target_dir.'/'.$target_file_name.ucwords($this->default_app).'.php';
                    if (empty($target_dir)) {
                        $target_file = $this->base_src_dir.'/'.$this->default_app.'/'.$target_file_name.ucwords($this->default_app).'.php';
                    }

                    if (file_exists($target_file)) {
                        $namespace_file = '\\'.$this->default_app.'\\'.$target_path_str.ucwords($this->default_app);
                        (new $namespace_file())->dispenseHttp($redis, $this->server, $this->socket_unique_id, $target_method, $request, $response);
                    }

                }
            }
        });

    }
    
    // 监听websokcet连接
    public function open ($function) {
        $this->server->on('open', function ($serv, $req) use($function) {
            $reid = $this->createSocketResource($req->fd);
            $function($serv, $req, $reid);
        });
    }
    
    // 监听websocket task事件
    private function task () {
        
        $this->server->on('task', function ($serv, $taskId, $workerId, $data) {

            $redis = $this->initRedis();

            if($redis){
                
                (new $data['namespace_file']())->dispense($redis, $serv, $this->socket_unique_id, $data['data']['method'], $data['fd'], $data['data']['reid'], $data['data']['mode'], $data['data']['header'], $data['data']['body']);

            }
        });

        $this->server->on('finish', function ($serv, $taskId, $data) {
            
        });

    }

    // 监听websocket 发送事件
    private function message () {
        
        $this->server->on('message', function ($serv, $frame) {
            
            if ($frame->data == 'KEEP6E82C5C4B5A66F449CBF5F69BA6793C4') {
                return null;
            }

            $this->initRedis();
            
            $data = json_decode($frame->data, true)?:null;

            if (!empty($data)) {
                $mode     = $data['header']['MODE'];
                $reid     = $data['header']['REID'];
                
                $body     = $data['body'];

                $token    = $data['token'];

                if (!empty($mode)) {
                    $target_dir = explode('@', $mode);
                    
                    if (count($target_dir) == 2) {
                        $this->default_app = ucwords($target_dir[0]);
                    }

                    $target_path = explode('/', $target_dir[count($target_dir)-1]);
                    $target_path_len = count($target_path);
                    for ($i = 0; $i < $target_path_len; $i++) {
                        if ($i < $target_path_len-1) {
                            $target_path[$i] = ucwords($target_path[$i]);
                        }
                    }

                    $target_path_str = str_replace('\\', '/', dirname(implode('/', $target_path)));
                    $target_method   = basename(implode('/', $target_path));
                    
                    $target_dir  = dirname(dirname(implode('/', $target_path)));
                    if ($target_dir == '.' || $target_dir == '..') {
                        $target_dir = '';
                    }

                    $target_file_name = basename(dirname(implode('/', $target_path)));

                    if (is_dir($this->base_src_dir.'/'.$this->default_app.'/'.$target_dir)) {

                        $target_file = $this->base_src_dir.'/'.$this->default_app.'/'.$target_dir.'/'.$target_file_name.ucwords($this->default_app).'.php';
                        if (empty($target_dir)) {
                            $target_file = $this->base_src_dir.'/'.$this->default_app.'/'.$target_file_name.ucwords($this->default_app).'.php';
                        }

                        if (file_exists($target_file)) {

                            $namespace_file = '\\'.$this->default_app.'\\'.$target_path_str.ucwords($this->default_app);

                            $task_data = [
                                'namespace_file' => $namespace_file,
                                'fd'             => $frame->fd,
                                'data'           => [
                                    'method' => $target_method,
                                    'body'   => $body,
                                    'mode'   => $mode,
                                    'header' => $data['header'],
                                    'reid'   => $reid
                                ]
                                
                            ];

                            $serv->task($task_data);
                            
                        }
                    }
                }
            }
        });
    }
        
    // 监听websocket 关闭事件
    public function close () {

        $this->server->on('close', function ($serv, $fd, $reactorId) {
            
            // 由服务器发起的关闭连接操作
            if ($reactorId < 0) {
                echo '系统断开';
            } else {
                echo '客户端断开';
            }

            $instant_user = \InstantChat\InstantUser::getInstance('normal', [
                'socket_unique_id' => $this->socket_unique_id,
                'redis'            => $this->redis,
                'prefix_scope'     => 'u_',
                'scope'            => 'normal'
            ]);
            
            if (!empty($instant_user)) {

                // 判断socket连接是否存在，回收用户资源
                if ($this->server->exist($fd)) {

                    // 获取socket资源绑定参数信息
                    $bind_params = $instant_user->getSocketBind($fd, true);
                    
                    // 公共资源库ID
                    $common_resource = MD5($this->socket_unique_id.'common_resource');
                    
                    if (!empty($bind_params)) {
                        
                        // 将该资源抛出用户的资源池
                        $instant_user->updateExtendStructure($bind_params['name'], 'resource_lib', null, null);
                        
                        // 删除被回收的资源存储
                        $this->redis->hdel($common_resource, $bind_params['resource_id']);
                        
                        // 判断用户资源数
                        $friend_list = $instant_user->pullFriendList($bind_params['name']);

                        foreach ($friend_list as $friend) {

                            $fr_fds = $instant_user->getUserResourceID($friend['friend_name']);
                            
                            foreach ($fr_fds as $fr_fd) {

                                if ($this->server->exist($fr_fd) && $fd != $fr_fd) {
                                    
                                    $this->server->push($fr_fd, json_encode([
                                        'header' => [],
                                         'body'     => [
                                             'data'  => [
                                                // 1 一般好友, 2 特别关注好友
                                                'type'        => 1,
                                                'name'        => $bind_params['name'],
                                                'online'      => '离线',
                                                'online_type' => '-',
                                             ],
                                             'msg'  => '',
                                             'code' => 9
                                         ]
                                    ]));

                                }
                            }
                        }

                        $instant_user->modifyUserInfo($bind_params['name'], [
                            'online' => 0
                        ]);
                    }
                }
            }
        });

    }
    
    // 监听websocket 关闭事件
    public function shutdown () {
        $this->server->on('shutdown', function () {
            
        });
    }

    // 启动socket服务
    public function start () {
        
        $this->task();

        $this->message();

        $this->request();

        $this->close();

        $this->server->start();
        
    }

    public function __destory () {
        
    }

}

?>
