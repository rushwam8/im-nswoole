<?php
namespace InstantChat;

use VesselBox;

/* 即时聊天系统 用户类 处理函数 */
class InstantUser {

    // 基础数据结构
    private $base = [
        // 用户登录名
        'name',
        // 用户别名（昵称）
        'alias',
        // 用户头像
        'chat_head',
        // 在线状态 0 离线 1 在线
        'online',
        // 在线类型 0 正常 1 忙碌 2 离开 3 隐身
        'online_type',
        // 用户状态 0 未激活 1 正常 2 封禁 3 异常
        'status',
        // 多地登录 0 不允许 1 允许
        'multi',
        // 独立登录密码
        'passwd',
        // 密码验证规则
        'passwdrule',
        // 性别 0 保密 1 男 2 女
        'gender',
        // 级别
        'level',
        // 创建时间
        'create_time',
        // 上次登录时间(含多端)
        'last_login_time',
        // 经验值
        'experience'
    ];
    
    // 扩展数据结构
    private $extends = [
        // 用户资源绑定库扩展数据结构
        'resource_lib' => [
            // 资源创建日期
            'create_time',
            // 资源有效期 0 无期限
            'expire_time'
        ],
        // 用户好友列表数据结构
        'friend_list' => [
            // 好友创建日期
            'create_time',
            // 好友加入时间
            'join_time',
            // 好友名称
            'friend_name',
            // 好友别名
            'friend_alias',
            // 好友标识码
            'friend_identifying',
            // 好友来源平台标识ID
            'friend_source_sign_id',
            // 好友互动标记（0 正常 1 不接受消息 2 不接受任何消息）
            'friend_interact_sign',
            // 好友上下线标记（0 正常 1 上线提醒 2 下线提醒 3 两者皆有）
            'friend_onoffline_sign',
            // 好友展现标记（0 正常 1 始终对其隐身 2 始终对其在线）
            'friend_show_sign',
            // 好友关系标记（0 正常 1 黑名单）
            'friend_sign',
            // 好友状态（0 正常 1 不可用）
            'friend_status',
            // 数据结构意义（不会被纳入初始化参数）
            '_meaning' => '用户好友列表',
            // 主键
            '@pk'      => 'friend_name'
        ],
        // 用户群/组列表数据结构
        'group_list' => [
            // 群/组加入时间
            'join_time',
            // 群/组名称
            'group_name',
            // 群/组别名
            'group_alias',
            // 群/组标识码
            'group_identifying',
            // 群/组来源平台标识ID
            'group_source_sign_id',
            // 群组互动标记（0 正常 1 接收并提醒消息 2 接收不提醒消息 3 不接收任何消息）
            'friend_interact_sign',
            // 数据结构意义（不会被纳入初始化参数）
            '_meaning' => '用户群/组列表'
        ],
        // 用户审核信息数据结构
        'audit_list' => [
            // 申请发起方
            'audit_from',
            // 审核类型 (1 好友申请 2 入群申请 ...)
            'audit_type',
            // 申请的对象
            'audit_to',
            // 申请状态（0 待审核 1 通过审核 2 拒绝审核[删除该记录] 3 审核黑名单）
            'audit_status',
            // 数据结构意义（不会被纳入初始化参数）
            '_meaning' => '用户审核信息列表'
        ]
    ];

    // 防止克隆对象
    private function __clone(){}

    // 防止实例化对象
    private function __construct(){}

    /* vesselbox 接口函数 */
    final static public function VesselBoxInterface () {
        return new self();
    }
    
    // 单例模式
    static public function getInstance($scope, $rely=[]) {

        return VesselBox::initBox(__CLASS__, $scope, $rely)?:VesselBox::giveBox(__CLASS__, $scope);

    }
    
    // 用户好友标识码(用于验证是否属于某平台信息)
    private function identifying ($name_id) {
        return MD5($this->socket_unique_id.'identifying'.$name_id.$this->prefix_scope);
    }
    
    // 判断初始化参数是否有效
    private function isInitCodeInvalid ($name_id, $extend_id) {
        
        // 获得校验参数
        $verify_code = $this->encryInitExtendStructureSign($name_id, $extend_id);
        $verify = $this->redis->hget($extend_id, 'init');
        
        // 校验创建合法性
        if ($verify_code == $verify) {
            return $verify;
        }
        
        return false;
        
    }
    
    // 加密数据内容
    private function encryDataContent ($data, $salt, $rule=0) {
        
        $json_data = json_encode($data);
        
        $result = null;
        
        if ($rule == 1) {
            $result = MD5($salt.MD5($json_data.$salt).$rule);
        }
        
        return $result;
        
    }
    
    // 生成初始化扩展数据结构标记
    private function encryInitExtendStructureSign ($name_id, $extend_id) {
        return MD5($extend_id.'init_structure_success'.$name_id);
    }
    
    // 用户密码生成
    private function userPasswdVerify ($name_id, $passwd, $rule=0) {
        $result = null;
        if ($rule == 1) {
            $result = MD5($name_id.'passwd'.$passwd.$rule);
        }
        return $result;
    }
    
    // 生成用户的扩展ID
    private function userExtendClassID ($name_id, $class) {
        return MD5($name_id.'extend_class'.$class);
    }
    
    /* 删除扩展数据结构 */
    final public function removeExtendStructure ($name, $class) {
        
        $name_id   = $this->registerID($name, $this->scope, $this->prefix_scope);
        
        // 生成扩展ID
        $extend_id = $this->userExtendClassID($name_id, $class);
        
        /* 删除扩展数据结构 */
        if ($this->redis->del($extend_id)) {
            return true;
        }
        
        return false;
        
    }
    
    /* 更新用户扩展数据结构 */
    final public function updateExtendStructure ($name, $class, $extend_data_id, $params) {
        
        $name_id   = $this->registerID($name, $this->scope, $this->prefix_scope);
        $name_rule = $this->redis->hget($name_id, '_rule_');
        
        // 生成扩展ID
        $extend_id = $this->userExtendClassID($name_id, $class);
        
        // 验证是否是新用户属性
        if ($this->redis->exists($extend_id)) {

            // 校验创建合法性
            if (($verify = $this->isInitCodeInvalid($name_id, $extend_id))) {
                
                // 校验数据头是否存在
                if ($this->redis->hexists($extend_id, $extend_data_id)) {

                    $json = $this->redis->hget($extend_id, $extend_data_id);

                    $extend_data = json_decode($json, true)?:[];
                    $extend_md5 = $extend_data['_md5_'];
                    unset($extend_data['_md5_']);
                    
                    // 校验数据内容是否被非法篡改
                    if ($extend_md5 == $this->encryDataContent($extend_data, $verify.$extend_data_id, $name_rule)) {
                        
                        if (!is_null($params)) {
                            
                            // 只存储结构内指定的数据
                            foreach ($this->extends[$class] as $exfield) {
                                if ($exfield != '_meaning') {
                                    if (isset($params[$exfield])) {
                                        $extend_data[$exfield] = $params[$exfield];
                                    }
                                }
                            }

                            $new_md5 = $this->encryDataContent($extend_data, $verify.$extend_data_id, $name_rule);

                            // 验证是否被修改过
                            if ($new_md5 != $extend_md5) {
                                $extend_data['_md5_'] = $new_md5;
                                $this->redis->hset($extend_id, $extend_data_id, json_encode($extend_data));
                            }
                            
                        } else {
                            $this->redis->hdel($extend_id, $extend_data_id);
                        }

                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /* 创建用户扩展数据结构 */
    final public function createExtendStructure ($name, $class, $extend_data_id, $params) {
        
        $name_id   = $this->registerID($name, $this->scope, $this->prefix_scope);
        $name_rule = $this->redis->hget($name_id, '_rule_');
        
        // 生成扩展ID
        $extend_id = $this->userExtendClassID($name_id, $class);
        
        // 验证是否是新用户属性
        if ($this->redis->exists($extend_id)) {

            // 校验创建合法性
            if (($verify = $this->isInitCodeInvalid($name_id, $extend_id))) {
                
                // 校验数据头是否存在
                if (!$this->redis->hexists($extend_id, $extend_data_id)) {
                    
                    $extend_data = [];
                    
                    // 只存储结构内指定的数据
                    foreach ($this->extends[$class] as $exfield) {
                        if ($exfield != '_meaning') {
                            if (isset($params[$exfield])) {
                                $extend_data[$exfield] = $params[$exfield];
                            }
                        }
                    }
                    
                    $new_md5 = $this->encryDataContent($extend_data, $verify.$extend_data_id, $name_rule);

                    $extend_data['_md5_'] = $new_md5;
                    $this->redis->hset($extend_id, $extend_data_id, json_encode($extend_data));

                    return true;
                }
            }
        }
        
        return false;
    }
    
    /* 更新用户基础数据结构 */
    final public function updateBaseStructure ($name_id) {
        
        if ($this->redis->exists($name_id)) {
            
            $base_data = $this->redis->hgetall($name_id);
            
            $md5  = $base_data['_md5_'];
            $rule = $base_data['_rule_'];
            unset($base_data['_rule_'], $base_data['_md5_']);
            
            $verify_md5 = $this->encryDataContent($base_data, $name_id.'base'.$rule, $rule);
            
            if ($md5 === $verify_md5) {
                // 更新基础用户信息
                foreach ($this->base as $field) {
                    // 验证是否存在该字段
                    if (!$this->redis->hexists($name_id, $field)) {
                        $this->redis->hset($name_id, $field, '');
                    }
                }
                
                $base_data = $this->redis->hgetall($name_id);
                unset($base_data['_rule_'], $base_data['_md5_']);
                
                // 数据校验参数
                $this->redis->hset($name_id, '_rule_', $rule);
                $this->redis->hset($name_id, '_md5_', $this->encryDataContent($base_data, $name_id.'base'.$rule, $rule));
                
                return true;
            }

        }
        
        return false;
        
    }
   
    
    /* 创建用户基础和扩展数据结构 */
    final public function createStructure ($name_id, $rule=1) {
        
        if (!$this->redis->exists($name_id)) {
            // 初始化基础用户信息
            foreach ($this->base as $field) {
                // 验证是否是新用户
                $this->redis->hset($name_id, $field, '');
            }
            
            $base_data = $this->redis->hgetall($name_id);
            
            // 数据校验参数
            $this->redis->hset($name_id, '_rule_', $rule);
            $this->redis->hset($name_id, '_md5_', $this->encryDataContent($base_data, $name_id.'base'.$rule, $this->redis->hget($name_id, '_rule_')));
            
            // 初始化扩展用户信息
            foreach ($this->extends as $class => $extend) {

                foreach ($extend as $k => $field) {

                    // 生成扩展ID
                    $extend_id = $this->userExtendClassID($name_id, $class);

                    // 验证是否是新用户属性
                    if (!$this->redis->exists($extend_id)) {
                        $this->redis->hset($extend_id, 'init', $this->encryInitExtendStructureSign($name_id, $extend_id));
                    }

                }
            }
            
            return true;
        }
        
        return false;
        
    }
    
    // 获取用户扩展数据结构
    final public function getExtendStructure () {
        
        $structure = [];
        
        foreach ($this->extends as $k => $extend) {
            
            // 判断哪些可开放的读取结构
            if (isset($extend['_meaning']) && !empty($extend['_meaning'])) {
                $structure[$k] = $extend['_meaning'];
            }
            
        }
        
        return $structure;
    }
    
    // 注册组、人唯一通信ID
    private function registerID ($sign, $salt, $prefix='') {
        return $prefix.MD5($this->socket_unique_id.'register'.$sign.$salt.$prefix);
    }
    
    // 用户是否存在
    final public function existsUser ($name) {

        $name_id = $this->registerID($name, $this->scope, $this->prefix_scope);

        if ($this->redis->exists($name_id)) {
            return $name_id;
        }

        return false;

    }
    
    // 校验用户账户
    final public function verifyUser ($name, $password) {
        
        $name_id = $this->registerID($name, $this->scope, $this->prefix_scope);
        
        if ($this->redis->exists($name_id)) {
            
            $source_passwd = $this->acquireUserInfo($name, 'passwd', null);
            $source_passwdrule = $this->acquireUserInfo($name, 'passwdrule', null);
            
            // 校验账户密码
            if ($this->userPasswdVerify($name_id, $password, $source_passwdrule) == $source_passwd) {
                return true;   
            }
            
        }
        return false;
    }
    
    // 初始化用户
    final public function initUser ($name, $alias, $password, $passwdrule=1) {
        
        $name_id = $this->registerID($name, $this->scope, $this->prefix_scope);
        
        if ($this->redis->exists($name_id)) {
            return false;
        }
        
        $this->createStructure($name_id, $passwdrule);
        
        // 用户 数据结构
        $this->modifyUserInfo($name, [
            // 用户登录名
            'name'            => $name,
            // 用户别名（昵称）
            'alias'           => $name,
            // 用户头像
            'chat_head'       => '',
            // 在线状态 0 离线 1 在线
            'online'          => 0,
            // 在线状态 0 正常 1 忙碌 2 离开 3 隐身
            'online_type'     => 0,
            // 用户状态 0 未激活 1 正常 2 封禁 3 异常
            'status'          => 1,
            // 绑定resource_ids
            'resource_ids'    => '',
            // 多地登录 0 不允许 1 允许
            'multi'           => 0,
            // 独立登录密码
            'passwd'          => $password,
            // 密码验证规则
            'passwdrule'      => $passwdrule,
            // 性别 0 保密 1 男 2 女
            'gender'          => 0,
            // 级别
            'level'           => 1,
            // 创建时间
            'create_time'     => time(),
            // 上次登录时间(含多端)
            'last_login_time' => '',
            // 经验值
            'experience'      => 0
        ]);
        
        return $name_id;

    }

    // 重置群/组
    final public function resetUser ($name, $datas) {

        $md5 = $datas['md5'];
        unset($datas['md5']);
        
        $verify_md5 = MD5(json_encode($datas).$datas['back_id']);

        if ($md5 !== $verify_md5) {
            return false;
        }
        
        $name_id = $this->registerID($name, $this->scope, $this->prefix_scope);
        
        // 删除自身扩展数据
        $this->removeExtendStructure($name, 'group_list');
        $this->removeExtendStructure($name, 'friend_list');
        $this->removeExtendStructure($name, 'audit_list');
        $this->redis->del($name_id);

        $this->createStructure($name_id, $datas['base_data']['passwdrule']);
        
        // 用户 数据结构
        $this->modifyUserInfo($name, $datas['base_data'], true);
        
        foreach ($datas['extends'] as $extend_key => $extend) {

            if (array_key_exists($extend_key, $this->extends)) {

                foreach ($extend as $only_name => $extend_data) {
                    
                    $this->createExtendStructure($name, $extend_key, $only_name, $extend_data);
                    
                }
            }
        }
        
        return $name_id;

    }

    // 备份用户
    final public function backUser ($name) {

        $name_id = $this->registerID($name, $this->scope, $this->prefix_scope);

        $extend_list = $this->getExtendStructure();

        $extends     = [];

        foreach ($extend_list as $extend_key => $extend_name) {

            $extends[$extend_key] = $this->acquireUserExtendInfo($name, $extend_key, null, null, false)?:[];
            
            foreach ($extends[$extend_key] as $md5 => $extend_json) {
                
                $extend_data = json_decode($extend_json, true);
                
                if (isset($this->extends[$extend_key]['@pk']) && !empty($this->extends[$extend_key]['@pk'])) {
                    unset($extends[$extend_key][$md5]);
                    $extends[$extend_key][$extend_data[$this->extends[$extend_key]['@pk']]] = $extend_data;
                } else {
                    $extends[$extend_key][$md5] = $extend_data;
                }
            }

        }

        $base_data = $this->redis->hgetall($name_id);

        $back_data = [
            'back_id'     => $name_id,
            'back_name'   => $name,
            'base_data'   => $base_data,
            'extends'     => $extends
        ];

        $back_data['md5'] = MD5(json_encode($back_data).$name_id);

        return $back_data;
    }
    
    // 获取用户本身数据
    final public function acquireUserInfo ($name, $key, $default=null) {
        
        $name_id = $this->registerID($name, $this->scope, $this->prefix_scope);
        
        if ($this->redis->exists($name_id)) {
            return $this->redis->hget($name_id, $key)?:$default;
        }

        return false;

    }
    
    // 获取用户扩展数据
    final public function acquireUserExtendInfo ($name, $class, $extend_data_id=null, $default=null, $len=false) {
        
        $name_id   = $this->registerID($name, $this->scope, $this->prefix_scope);
        
        $extend_id = $this->userExtendClassID($name_id, $class);
        
        if ($this->redis->exists($extend_id)) {
            if (is_null($extend_data_id)) {
                $result = null;
                if ($len === false) {
                    $result = $this->redis->hgetall($extend_id);
                    unset($result['init']);
                } else {
                    $result = $this->redis->hlen($extend_id);
                }
                return $result;
            } else {
                return $this->redis->hget($extend_id, $extend_data_id)?:$default;
            }
        }

        return false;

    }
    
    /* 特殊数据结构做处理 */
    private function specialDataDispense ($name_id, &$params) {
        
        if (!empty($params['passwd']) && !empty($params['passwdrule'])) {
            $params['passwd'] = $this->userPasswdVerify($name_id, $params['passwd'], $params['passwdrule']);
        }
        
    }
    
    // 编辑用户本身数据
    final public function modifyUserInfo ($name, $params=[], $reset=false) {
        
        $name_id = $this->registerID($name, $this->scope, $this->prefix_scope);
        
        if ($this->redis->exists($name_id)) {
            $base_data = $this->redis->hgetall($name_id);
            
            $md5  = $base_data['_md5_'];
            $rule = $base_data['_rule_'];
            unset($base_data['_rule_'], $base_data['_md5_']);

            $verify_md5 = $this->encryDataContent($base_data, $name_id.'base'.$rule, $rule);
            
            if ($md5 === $verify_md5) {

                if ($reset === false) {
                    $this->specialDataDispense($name_id, $params);
                }
                
                if ($this->redis->exists($name_id)) {

                    // 修改用户源级数据
                    foreach ($params as $key => $val) {
                        
                        if (is_array($val)) {
                            $arr  = json_decode($this->redis->hget($name_id, $key), true)?:[];
                            $val  = json_encode(array_merge($arr, $val));
                            $this->redis->hset($name_id, $key, $val);
                        } else {
                            $this->redis->hset($name_id, $key, $val);
                        }
                    }
                }

                $base_data = $this->redis->hgetall($name_id);
                unset($base_data['_rule_'], $base_data['_md5_']);
                
                // 数据校验参数
                $this->redis->hset($name_id, '_rule_', $rule);
                $this->redis->hset($name_id, '_md5_', $this->encryDataContent($base_data, $name_id.'base'.$rule, $rule));

                return true;
            }
        }
        
        return false;
    }
    
    /* 拉取好友列表 */
    final public function pullFriendList ($name) {
        
        $name_id = $this->existsUser($name);
        
        $friend_datas = [];
        
        if ($name_id !== false) {
            
            $friend_list = $this->acquireUserExtendInfo($name, 'friend_list', null, null, false);
            
            foreach ($friend_list as $friend_json) {
                
                $friend_data = json_decode($friend_json, true)?:[];
                
                if ($friend_data['friend_status'] == 0) {
                    
                    unset($friend_data['_md5_'], $friend_data['friend_identifying'], $friend_data['friend_source_sign_id']);
                    
                    // 好友是自己的标识（扩展用）
                    if ($friend_data['friend_name'] == $name) {
                        $friend_data['self'] = 1;
                    }
                    
                    $friend_data['online']      = $this->acquireUserInfo($friend_data['friend_name'], 'online', null)?:0;
                    $friend_data['online_type'] = $this->acquireUserInfo($friend_data['friend_name'], 'online_type', null)?:0;
                    
                    $online_status = [
                        0 => '离线',
                        1 => '在线'
                    ];
                    
                    $online_type_status = [
                        0 => '正常',
                        1 => '忙碌', 
                        2 => '离开', 
                        3 => '隐身'
                    ];
                    
                    if ($friend_data['online'] == '1') {
                        $friend_data['online_type'] = $online_type_status[$friend_data['online_type']];
                    } else {
                        $friend_data['online_type'] = '-';
                    }

                    $friend_data['online'] = $online_status[$friend_data['online']];
                    
                    $friend_datas[] = $friend_data;
                }
            }
            
        }
        
        return $friend_datas;
    }
    
    /* 拉取群/组列表 */
    final public function pullGroupList ($name) {
        
        $name_id = $this->existsUser($name);
        
        $group_datas = [];
        
        if ($name_id !== false) {
            
            $group_list = $this->acquireUserExtendInfo($name, 'group_list', null, null, false);
            
            foreach ($group_list as $group_json) {
                $group_data = json_decode($group_json, true)?:[];
                unset($group_data['_md5_'], $group_data['group_identifying'], $group_data['group_source_sign_id']);
                
                $group_data['extend'] = [
                    'upper_limit' => $this->InstantGroup->acquireGroupInfo($group_data['group_name'], 'upper_limit', 0),
                    'headcount'   => $this->InstantGroup->acquireGroupInfo($group_data['group_name'], 'headcount', null),
                    'type'        => ($this->InstantGroup->acquireGroupInfo($group_data['group_name'], 'type', null)==0?'群':'组')
                ];
                
                $group_datas[] = $group_data;
            }
            
        }
        
        return $group_datas;
    }
    
    // 获得用户所有资源ID
    final public function getUserResourceID ($name) {
        
        $name_id = $this->registerID($name, $this->scope, $this->prefix_scope);
        
        // 公共资源库ID
        $common_resource = MD5($this->socket_unique_id.'common_resource');
        
        $resource_list = $this->acquireUserExtendInfo($name, 'resource_lib', null, null, false);

        $sockets_ids = [];
        
        // 判断资源库是否存在
        if (!empty($resource_list)) {
            
            foreach ($resource_list as $resource_id => $resource_json) {
                
                $resource_data = json_decode($resource_json, true);
                
                // 有效期
                if ($resource_data['expire_time'] > 0) {
                    if ($resource_data['expire_time'] < time() && $this->redis->hexists($common_resource, $resource_id)) {
                        $sockets_ids[] = $this->redis->hget($common_resource, $resource_id);
                    } else {
                        $this->updateExtendStructure($name, 'resource_lib', $resource_id, null);
                        $this->redis->hdel($common_resource, $resource_id);
                    }
                } else {
                    $sockets_ids[] = $this->redis->hget($common_resource, $resource_id);
                }
            }
        }

        return array_unique($sockets_ids);
    }
    
    // 添加好友
    final public function addFriend ($name, $friend_name) {
        
        $name_id = $this->existsUser($name);
        $friend_name_id = $this->existsUser($friend_name);
        
        if ($name_id !== false && $friend_name_id !== false) {
            
            $friend_info = json_decode($this->acquireUserExtendInfo($name, 'friend_list', $friend_name_id, null, false), true)?:null;
            
            if (is_null($friend_info) || $friend_info['friend_status'] == 1) {
            
                $time = time();

                $friend_data = [
                    // 好友创建日期
                    'create_time' => $time,
                    // 好友加入时间
                    'join_time'   => $time,
                    // 好友名称
                    'friend_name' => $friend_name,
                    // 好友别名
                    'friend_alias'=> $friend_name,
                    // 好友标识码
                    'friend_identifying'    => $this->identifying($friend_name_id),
                    // 好友来源平台标识ID
                    'friend_source_sign_id' => $this->socket_unique_id,
                    // 好友互动标记（0 正常 1 不接受消息 2 不接受任何消息）
                    'friend_interact_sign'  => 0,
                    // 好友上下线标记（0 正常 1 上线提醒 2 下线提醒 3 两者皆有）
                    'friend_onoffline_sign' => 0,
                    // 好友展现标记（0 正常 1 始终对其隐身 2 始终对其在线）
                    'friend_show_sign' => 0,
                    // 好友关系标记（0 正常 1 黑名单）
                    'friend_sign'      => 0,
                    // 好友状态（0 正常 1 不可用）
                    'friend_status'    => 0
                ];
                
                $name_data = [
                    // 好友创建日期
                    'create_time' => $time,
                    // 好友加入时间
                    'join_time'   => $time,
                    // 好友名称
                    'friend_name' => $name,
                    // 好友别名
                    'friend_alias'=> $name,
                    // 好友标识码
                    'friend_identifying'    => $this->identifying($name_id),
                    // 好友来源平台标识ID
                    'friend_source_sign_id' => $this->socket_unique_id,
                    // 好友互动标记（0 正常 1 不接受消息 2 不接受任何消息）
                    'friend_interact_sign'  => 0,
                    // 好友上下线标记（0 正常 1 上线提醒 2 下线提醒 3 两者皆有）
                    'friend_onoffline_sign' => 0,
                    // 好友展现标记（0 正常 1 始终对其隐身 2 始终对其在线）
                    'friend_show_sign' => 0,
                    // 好友关系标记（0 正常 1 黑名单）
                    'friend_sign'      => 0,
                    // 好友状态（0 正常 1 不可用）
                    'friend_status'    => 0
                ];

                $result = $this->createExtendStructure($name, 'friend_list', $friend_name_id, $friend_data);
                $this->createExtendStructure($friend_name, 'friend_list', $name_id, $name_data);
                
                // 创建好友信息到列表
                if (!$result) {
                    unset($friend_data['create_time'], $name_data['create_time']);
                    $result = $this->updateExtendStructure($name, 'friend_list', $friend_name_id, $friend_data);
                    $this->updateExtendStructure($friend_name, 'friend_list', $name_id, $name_data);
                }

                return $result;
            }
        }
        
        return false;
    }
    
    // 移除好友
    final public function removeFriend ($name, $friend_name) {
        
        $name_id = $this->existsUser($name);
        $friend_name_id = $this->existsUser($friend_name);
        
        if ($name_id !== false && $friend_name_id !== false) {
            
            $time = time();
            
            $friend_info = json_decode($this->acquireUserExtendInfo($name, 'friend_list', $friend_name_id, null, false), true)?:null;
            
            if (!is_null($friend_info) && $friend_info['friend_status'] == 0) {
                   
                $friend_data = [
                    // 好友状态（0 正常 1 不可用）
                    'friend_status'    => 1
                ];

                $this->updateExtendStructure($name, 'friend_list', $friend_name_id, $friend_data);
                $this->updateExtendStructure($friend_name, 'friend_list', $name_id, $friend_data);

                return true;
            }
        }
        
        return false;
    }

    // 获得socket资源绑定
    final public function getSocketBind ($fd, $isdel=false) {
        
        // 获取绑定ID
        $bind_id = MD5($this->socket_unique_id.'bind'.$this->scope.$fd);
        
        $bind_data = $this->redis->hgetall($bind_id);
        
        if ($isdel === true) {
            $this->redis->del($bind_id);
        }
        
        return $bind_data;
    }
    
    // socket资源绑定
    private function socketBind ($fd, $params) {
        
        // 获取绑定ID
        $bind_id = MD5($this->socket_unique_id.'bind'.$this->scope.$fd);
        
        $this->redis->del($bind_id);
        
        foreach ($params as $key => $val) {
            $this->redis->hset($bind_id, $key, $val);
        }
        
        return $bind_id;
    }

    // 分配socket资源给用户
    final public function allotSocketResourceUser ($name_id, $resource_id, $fd, $name) {

        // 公共资源库ID
        $common_resource = MD5($this->socket_unique_id.'common_resource');
        
        if (!$this->redis->exists($name_id) || !$this->redis->hexists($common_resource, $resource_id)) {
            return false;
        }
        
        // 创建用户资源数据
        $resource_data = [
            'create_time' => time(),
            'expire_time' => 0
        ];
        
        $this->createExtendStructure($name, 'resource_lib', $resource_id, $resource_data);
        
        $params = [
            'name'        => $name,
            'name_id'     => $name_id,
            'resource_id' => $resource_id
        ];
        
        // socket资源绑定参数
        $this->socketBind($fd, $params);
        
        return true;

    }


    
}
