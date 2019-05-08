<?php
namespace InstantChat;

use VesselBox;

/* 即时聊天系统 组/群类 处理函数
** 
*/
class InstantGroup {
    
    // 基础数据结构
    private $base = [
        // 群/组名称
        'name',
        // 创建时间
        'create_time',
        // 房间人数上限（0 为不限制）
        'upper_limit',
        // 房间当前人数
        'headcount',
        // 房主
        'owner',
        // 群/组级别（1为最低等级）
        'level',
        // 类型（0 群 1 组）
        'type',
        // 群/组密码
        'passwd',
        // 群/组状态（0 任意加入 1 审核加入 2 密码加入 3 不可加入）
        'status',
        // 锁定发送标识（0 不锁定 1 锁定）
        'lock'
    ];
    
    // 扩展数据结构
    private $extends = [
        // 群/组成员列表数据结构
        'member_list' => [
            // 成员加入时间
            'join_time',
            // 成员名称
            'member_name',
            // 成员别名
            'member_alias',
            // 成员标记（0 正常 1 禁言）
            'member_sign',
            // 成员禁言到期时间（日期）
            'member_shutup_expire',
            // 成员状态（0 正常 1 黑名单）
            'member_status',
            // 数据结构意义（不会被纳入初始化参数）
            '_meaning' => '群/组成员列表'
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

    // 群/组标识码(用于验证是否属于某平台信息)
    private function identifying ($group_id) {
        return MD5($this->socket_unique_id.'identifying'.$group_id.$this->prefix_scope);
    }
    
    // 加密数据内容
    private function encryDataContent ($data, $salt) {
        
        $json_data = json_encode($data);
        
        return MD5($salt.MD5($json_data.$salt).'group');
        
    }
    
    // 生成初始化扩展数据结构标记
    private function encryInitExtendStructureSign ($group_id, $extend_id) {
        return MD5($extend_id.'init_structure_success'.$group_id);
    }
    
    // 群/组密码生成
    private function groupPasswdVerify ($group_id, $passwd) {
        return MD5($group_id.'passwd'.$passwd.'group');
    }
    
    /* 删除扩展数据结构 */
    final public function removeExtendStructure ($group_name, $class) {

        $group_id = $this->registerID($group_name, $this->scope, $this->prefix_scope);
        
        // 生成扩展ID
        $extend_id = $this->groupExtendClassID($group_id, $class);
        
        /* 删除扩展数据结构 */
        if ($this->redis->del($extend_id)) {
            return true;
        }
        
        return false;
        
    }
    
    /* 更新群/组扩展数据结构 */
    final public function updateExtendStructure ($group_name, $class, $extend_data_id, $params) {
        
        $group_id   = $this->registerID($group_name, $this->scope, $this->prefix_scope);
        
        // 生成扩展ID
        $extend_id = $this->groupExtendClassID($group_id, $class);
        
        // 验证是否是新群/组属性
        if ($this->redis->exists($extend_id)) {

            // 校验创建合法性
            if (($verify = $this->isInitCodeInvalid($group_id, $extend_id)) !== false) {

                // 校验数据头是否存在
                if ($this->redis->hexists($extend_id, $extend_data_id)) {

                    $extend_data = json_decode($this->redis->hget($extend_id, $extend_data_id), true)?:[];
                    
                    $extend_data_md5  = isset($extend_data['_md5_'])?$extend_data['_md5_']:'';
                    unset($extend_data['_md5_']);
                    
                    // 校验数据内容是否被非法篡改
                    if (!empty($extend_data_md5) && $extend_data_md5 == $this->encryDataContent($extend_data, $verify.$extend_data_id)) {

                        if (!is_null($params)) {
                            // 只存储结构内指定的数据
                            foreach ($this->extends[$class] as $exfield) {
                                if ($exfield != '_meaning') {
                                    if (isset($params[$exfield])) {
                                        $extend_data[$exfield] = $params[$exfield];
                                    }
                                }
                            }

                            $new_md5 = $this->encryDataContent($extend_data, $verify.$extend_data_id);

                            // 验证是否被修改过
                            if ($new_md5 != $extend_data_md5) {
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
    
    /* 创建群/组扩展数据结构 */
    final public function createExtendStructure ($group_name, $class, $extend_data_id, $params) {
        
        $group_id   = $this->registerID($group_name, $this->scope, $this->prefix_scope);
        
        // 生成扩展ID
        $extend_id = $this->groupExtendClassID($group_id, $class);
        
        // 验证是否是新群/组属性
        if ($this->redis->exists($extend_id)) {
            
            // 校验创建合法性
            if (($verify = $this->isInitCodeInvalid($group_id, $extend_id)) !== false) {

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
                    
                    $new_md5 = $this->encryDataContent($extend_data, $verify.$extend_data_id);
                    
                    $extend_data['_md5_'] = $new_md5;
                    $this->redis->hset($extend_id, $extend_data_id, json_encode($extend_data));
                    
                    return true;

                }
            }
        }
        
        return false;
        
    }
    
    /* 更新群/组基础数据结构 */
    private function updateBaseStructure ($group_id) {
        
        if ($this->redis->exists($group_id)) {
            
            $base_data = $this->redis->hgetall($group_id);
            
            $base_md5  = $base_data['_md5_'];
            unset($base_data['_md5_']);
            
            $verify_md5 = $this->encryDataContent($base_data, $group_id.'base');
            
            if ($base_md5 == $verify_md5) {
                // 更新基础群/组信息
                foreach ($this->base as $field) {
                    // 验证是否存在该字段
                    if (!$this->redis->hexists($group_id, $field)) {
                        $this->redis->hset($group_id, $field, '');
                    }
                }
                
                $base_data = $this->redis->hgetall($group_id);
                unset($base_data['_md5_']);
                
                // 数据校验参数
                $this->redis->hset($group_id, '_md5_', $this->encryDataContent($base_data, $group_id.'base'));
                
                return true;
            }

        }
        
        return false;
        
    }
   
    
    /* 创建群/组基础和扩展数据结构 */
    private function createStructure ($group_id, $rule=1) {
        
        if (!$this->redis->exists($group_id)) {
            // 初始化基础群/组信息
            foreach ($this->base as $field) {
                // 验证是否是新群/组
                $this->redis->hset($group_id, $field, '');
            }
            
            $base_data = $this->redis->hgetall($group_id);
            
            // 数据校验参数
            $this->redis->hset($group_id, '_md5_', $this->encryDataContent($base_data, $group_id.'base'));
            
            // 初始化扩展群/组信息
            foreach ($this->extends as $class => $extend) {

                foreach ($extend as $k => $field) {

                    // 生成扩展ID
                    $extend_id = $this->groupExtendClassID($group_id, $class);

                    // 验证是否是新群/组属性
                    if (!$this->redis->exists($extend_id)) {
                        $this->redis->hset($extend_id, 'init', $this->encryInitExtendStructureSign($group_id, $extend_id));
                    }

                }
            }
            
            return true;
        }
        
        return false;
        
    }
    
    // 获取群/组扩展数据结构
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
    
    // 初始化群/组
    final public function initGroup ($group_name, $owner, $alias, $type, $password='') {
        
        $group_id = $this->registerID($group_name, $this->scope, $this->prefix_scope);

        if ($this->redis->exists($group_id)) {
            return false;
        }
        
        $this->createStructure($group_id);
        
        // 群/组 数据结构
        $this->modifyGroupInfo($group_name, [
            // 群/组名
            'name'          => $group_name,
            // 群/组别名
            'alias'         => $alias,
            // 创建时间
            'create_time'   => time(),
            // 房间人数上限
            'upper_limit'   => 0,
            // 房间当前人数
            'headcount'     => 0,
            // 房主
            'owner'         => $owner,
            // 群/组级别（1为最低等级）
            'level'         => 1,
            // 类型（0 群 1 组）
            'type'          => $type,
            // 群/组密码
            'passwd'        => !empty($password)?$this->groupPasswdVerify($group_id, $password):'',
            // 群/组状态（0 待设置 1 任意加入 2 审核加入 3 密码加入 4 不可加入）
            'status'        => !empty($password)?3:0,
            // 锁定发送标识（0 不锁定 1 锁定）
            'lock'          => 0
        ]);
        
        $time = time();
        
        // 组成员信息
        $group_member_data = [
            // 成员加入时间
            'join_time'     => $time,
            // 成员名称
            'member_name'   => $owner,
            // 成员别名
            'member_alias'  => $owner,
            // 成员标记（0 正常 1 禁言）
            'member_sign'   => 0,
            // 成员禁言到期时间（日期）
            'member_shutup_expire' => 0,
            // 成员状态（0 正常 1 黑名单）
            'member_status' => 0
        ];

        // 获取用户和群组关联ID
        $extend_data_id = $this->userAndGroupRuleID($group_id, $owner);
        
        if ($this->createExtendStructure($group_name, 'member_list', $extend_data_id, $group_member_data)) {
            
            $modify_data = [
                'headcount'  => 1
            ];

            if ($this->modifyGroupInfo($group_name, $modify_data)) {

                $group_data = [
                    // 群/组加入时间
                    'join_time'            => $time,
                    // 群/组名称
                    'group_name'           => $group_name,
                    // 群/组名称
                    'group_alias'          => $group_name,
                    // 群/组标识码
                    'group_identifying'    => $this->identifying($group_id),
                    // 群/组来源平台标识ID
                    'group_source_sign_id' => $this->socket_unique_id,
                    // 群组互动标记（0 正常 1 接收并提醒消息 2 接收不提醒消息 3 不接收任何消息）
                    'friend_interact_sign' => 0
                ];
                
                if($this->InstantUser->createExtendStructure($owner, 'group_list', $extend_data_id, $group_data)) {
                    $this->InstantUser->updateExtendStructure($owner, 'group_list', $extend_data_id, $group_data);
                }
            }
        }
        
        return $group_id;

    }

    // 重置群/组
    final public function resetGroup ($group_name, $datas) {

        $md5 = $datas['md5'];
        unset($datas['md5']);

        $verify_md5 = MD5(json_encode($datas).$datas['back_id']);

        if ($md5 !== $verify_md5) {
            return false;
        }
        
        $group_id = $this->registerID($group_name, $this->scope, $this->prefix_scope);
                    
        $member_list = $this->acquireGroupExtendInfo($group_name, 'member_list', null, null, false);
        
        if (!empty($member_list)) {

            foreach ($member_list as $member_json) {
                
                $member_data = json_decode($member_json, true);
                
                // 获取用户和群组关联ID
                $extend_data_id = $this->userAndGroupRuleID($group_id, $member_data['member_name']);
                
                // 删除
                $this->updateExtendStructure($group_name, 'member_list', $extend_data_id, null);
                
                // 删除用户群/组关系
                $this->InstantUser->updateExtendStructure($member_data['member_name'], 'group_list', $extend_data_id, null);
                
            }
            
        }
        
        // 删除自身扩展数据
        if ($this->removeExtendStructure($group_name, 'member_list')) {
            $this->redis->del($group_id);
        }

        $this->createStructure($group_id);
        
        // 群/组 数据结构
        $this->modifyGroupInfo($group_name, $datas['base_data']);
        
        foreach ($datas['extends'] as $extend_key => $extend) {

            if (array_key_exists($extend_key, $this->extends)) {

                foreach ($extend as $only_name => $extend_data) {
                    
                    if ($this->createExtendStructure($group_name, $extend_key, $only_name, $extend_data)) {
                        
                        if ($extend_key == 'member_list') {
                            
                            // 获取用户和群组关联ID
                            $extend_data_id = $this->userAndGroupRuleID($group_id, $extend_data['member_name']);

                            $group_data = [
                                // 群/组加入时间
                                'join_time'            => $extend_data['join_time'],
                                // 群/组名称
                                'group_name'           => $group_name,
                                // 群/组名称
                                'group_alias'          => $group_name,
                                // 群/组标识码
                                'group_identifying'    => $this->identifying($group_id),
                                // 群/组来源平台标识ID
                                'group_source_sign_id' => $this->socket_unique_id,
                                // 群组互动标记（0 正常 1 接收并提醒消息 2 接收不提醒消息 3 不接收任何消息）
                                'friend_interact_sign' => 0
                            ];
                            
                            if($this->InstantUser->createExtendStructure($extend_data['member_name'], 'group_list', $extend_data_id, $group_data)) {
                                $this->InstantUser->updateExtendStructure($extend_data['member_name'], 'group_list', $extend_data_id, $group_data);
                            }
                        }
                    }
                }

                if ($extend_key == 'member_list') {

                    $this->modifyGroupInfo($group_name, ['headcount'  => count($extend)]);

                }
                
            }
        }

        
        return $group_id;

    }

    // 备份群/组
    final public function backGroup ($group_name) {

        $group_id    = $this->registerID($group_name, $this->scope, $this->prefix_scope);

        $extend_list = $this->getExtendStructure();

        $extends     = [];

        foreach ($extend_list as $extend_key => $extend_name) {

            $extends[$extend_key] = $this->acquireGroupExtendInfo($group_name, $extend_key, null, null, false)?:[];
            
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

        $base_data = $this->redis->hgetall($group_id);

        $back_data = [
            'back_id'     => $group_id,
            'back_name'   => $group_name,
            'base_data'   => $base_data,
            'extends'     => $extends
        ];

        $back_data['md5'] = MD5(json_encode($back_data).$group_id);
        
        return $back_data;
    }


    // 群/组是否存在
    final public function existsGroup ($group_name) {

        $group_id = $this->registerID($group_name, $this->scope, $this->prefix_scope);

        if ($this->redis->exists($group_id)) {
            return $group_id;
        }

        return false;

    }
    
    // 判断初始化参数是否有效
    private function isInitCodeInvalid ($group_id, $extend_id) {

        // 获得校验参数
        $verify_code = $this->encryInitExtendStructureSign($group_id, $extend_id);
        $verify = $this->redis->hget($extend_id, 'init');
        
        // 校验创建合法性
        if ($verify_code == $verify) {
            return $verify;
        }
        
        return false;
        
    }
    
    // 判断用户是否加入过群/组
    private function isUserGroupExists ($group_id, $name) {
        
        $extend_id = $this->groupExtendClassID($group_id, 'member_list');
        
        // 校验创建合法性
        if (($verify = $this->isInitCodeInvalid($group_id, $extend_id)) !== false) {
            
            $extend_data_id = $this->userAndGroupRuleID($group_id, $name);
            
            // 判断群组成员是否存在
            if ($this->redis->hexists($extend_id, $extend_data_id)) {

                $member_data = json_decode($this->redis->hget($extend_id, $extend_data_id), true)?:[];
                
                $member_md5  = isset($member_data['_md5_'])?$member_data['_md5_']:'';
                unset($member_data['_md5_']);

                if (!empty($member_md5) && $member_md5 == $this->encryDataContent($member_data, $verify.$extend_data_id)) {
                    return true;
                }
                
            }
            
        }
        
        return false;
        
    }
    
    // 生成群/组的扩展ID
    private function groupExtendClassID ($group_id, $class) {
        return MD5($group_id.'extend_class'.$class);
    }
    
    // 生成用户和群/组的规则ID
    private function userAndGroupRuleID ($group_id, $name) {
        // 获取群/组ID
        return MD5($group_id.$this->scope.$name.'user_member');
    }
    
    // 是否在群
    final public function inGroup ($group_name, $name) {
        
        $group_id = $this->registerID($group_name, $this->scope, $this->prefix_scope);

        $result   = false;
        
        if ($this->redis->exists($group_id)) {
            
            if ($result = $this->isUserGroupExists($group_id, $name)) {
                
                if($name == $this->acquireGroupInfo($group_name, 'owner', null)){
                    
                    $result = 'owner';
                    
                }
            }
        }
        
        return $result;

    }

    // 加入组
    final public function joinGroup ($group_name, $name) {
        
        // 获取群/组ID
        $group_id = $this->registerID($group_name, $this->scope, $this->prefix_scope);
        
        if ($this->redis->exists($group_id)) {
            
            if ($name != $this->acquireGroupInfo($group_name, 'owner', null)) {
                
                // 是否加入过群/组
                if ($this->isUserGroupExists($group_id, $name) === false) {
                    
                    // 群组人数上限
                    $group_upper_limit = $this->acquireGroupInfo($group_name, 'upper_limit', null);
                    
                    // 获取群组当前成员人数
                    $current_count = ($this->acquireGroupExtendInfo($group_name, 'member_list', null, null, true) - 1);
                    
                    // 获取用户和群组关联ID
                    $extend_data_id = $this->userAndGroupRuleID($group_id, $name);
                    
                    if ($group_upper_limit == 0 || ($group_upper_limit > 0 && $group_upper_limit > $current_count)) {
                        
                        $time = time();
                        
                        // 组成员信息
                        $group_member_data = [
                            // 成员加入时间
                            'join_time'     => $time,
                            // 成员名称
                            'member_name'   => $name,
                            // 成员别名
                            'member_alias'  => $name,
                            // 成员标记（0 正常 1 禁言）
                            'member_sign'   => 0,
                            // 成员禁言到期时间（日期）
                            'member_shutup_expire' => 0,
                            // 成员状态（0 正常 1 黑名单）
                            'member_status' => 0
                        ];
                        
                        if ($this->createExtendStructure($group_name, 'member_list', $extend_data_id, $group_member_data)) { 
                            
                            ++$current_count;
                            
                            $modify_data = [
                                'headcount'  => $current_count
                            ];
                            
                            if ($this->modifyGroupInfo($group_name, $modify_data)) {
                                
                                $group_data = [
                                    // 群/组加入时间
                                    'join_time'            => $time,
                                    // 群/组名称
                                    'group_name'           => $group_name,
                                    // 群/组名称
                                    'group_alias'          => $group_name,
                                    // 群/组标识码
                                    'group_identifying'    => $this->identifying($group_id),
                                    // 群/组来源平台标识ID
                                    'group_source_sign_id' => $this->socket_unique_id,
                                    // 群组互动标记（0 正常 1 接收并提醒消息 2 接收不提醒消息 3 不接收任何消息）
                                    'friend_interact_sign' => 0
                                ];
                                
                                if($this->InstantUser->createExtendStructure($name, 'group_list', $extend_data_id, $group_data)) {
                                    $this->InstantUser->updateExtendStructure($name, 'group_list', $extend_data_id, $group_data);
                                }
                            }
                            
                        }

                        return $group_id;
                    }
                        
                }
            }
        }

        return false;
    }
    
    // 退出组
    final public function quitGroup ($group_name, $name) {
            
        // 获取房间ID
        $group_id = $this->registerID($group_name, $this->scope, $this->prefix_scope);
        
        if ($this->redis->exists($group_id)) {
            
            if ($name != $this->acquireGroupInfo($group_name, 'owner', null)) {
                
                // 是否加入过群/组
                if ($this->isUserGroupExists($group_id, $name) !== false) {
                    
                    // 获取群组当前成员人数
                    $current_count = ($this->acquireGroupExtendInfo($group_name, 'member_list', null, null, true) - 1);
                    
                    // 获取用户和群组关联ID
                    $extend_data_id = $this->userAndGroupRuleID($group_id, $name);
                    
                    --$current_count;
                    
                    $modify_data = [
                        'headcount' => $current_count
                    ];
                    
                    // 修改群/组参数
                    if ($this->modifyGroupInfo($group_name, $modify_data)) {
                        
                        // 删除
                        $this->updateExtendStructure($group_name, 'member_list', $extend_data_id, null);
                        
                        // 删除用户群/组关系
                        $this->InstantUser->updateExtendStructure($name, 'group_list', $extend_data_id, null);
                        
                        return true;

                    }
                }
            }
        }
            
        return false;
    }

    // 移交组
    final public function turnGroup ($group_name, $owner, $to) {
        
        // 获取房间ID
        $group_id = $this->registerID($group_name, $this->scope, $this->prefix_scope);
        
        if ($this->redis->exists($group_id)) {
            
            // 校验拥有者
            if ($owner == $this->acquireGroupInfo($group_name, 'owner', null) && $owner != $to) {
                
                if ($this->isUserGroupExists($group_id, $to)) {
                    
                     $this->modifyGroupInfo($group_name, [
                        'owner' => $to
                    ]);
                    
                    return true;
                }

            }
                
        }
            
        return false;
        
    }

    // 解散组
    final public function removeGroup ($group_name, $owner) {
        
        // 获取房间ID
        $group_id = $this->registerID($group_name, $this->scope, $this->prefix_scope);
        
        if ($this->redis->exists($group_id)) {
            
            // 校验拥有者
            if ($owner == $this->acquireGroupInfo($group_name, 'owner', null)) {
                
                if ($this->isUserGroupExists($group_id, $owner)) {

                    $socket_ids = $this->getGroupUserResourceID($group_name);
                    
                    $member_list = $this->acquireGroupExtendInfo($group_name, 'member_list', null, null, false);
                    
                    if (!empty($member_list)) {

                        foreach ($member_list as $member_json) {
                            
                            $member_data = json_decode($member_json, true);
                            
                            // 获取用户和群组关联ID
                            $extend_data_id = $this->userAndGroupRuleID($group_id, $member_data['member_name']);
                            
                            // 删除
                            $this->updateExtendStructure($group_name, 'member_list', $extend_data_id, null);
                            
                            // 删除用户群/组关系
                            $this->InstantUser->updateExtendStructure($member_data['member_name'], 'group_list', $extend_data_id, null);
                            
                        }
                        
                    }
                    
                    // 删除自身扩展数据
                    if ($this->removeExtendStructure($group_name, 'member_list')) {
                        $this->redis->del($group_id);
                        return $socket_ids;
                    }
                }
            }
        }
        return false;
    }
    
    // 获取群/组本身数据
    final public function acquireGroupInfo ($group_name, $key, $default=null) {
        
        $group_id = $this->registerID($group_name, $this->scope, $this->prefix_scope);
        
        if ($this->redis->exists($group_id)) {
            return $this->redis->hget($group_id, $key)?:$default;
        }

        return false;

    }
    
    // 获取群/组扩展数据
    final public function acquireGroupExtendInfo ($group_name, $class, $extend_data_id=null, $default=null, $len=false) {
        
        $group_id  = $this->registerID($group_name, $this->scope, $this->prefix_scope);
        
        $extend_id = $this->groupExtendClassID($group_id, $class);
        
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
    
    // 编辑群/组本身数据
    final public function modifyGroupInfo ($group_name, $params=[]) {
        
        $group_id = $this->registerID($group_name, $this->scope, $this->prefix_scope);
        
        $base_data = $this->redis->hgetall($group_id);
        
        $base_md5  = $base_data['_md5_'];
        unset($base_data['_md5_']);

        $verify_md5 = $this->encryDataContent($base_data, $group_id.'base');
        
        if ($base_md5 === $verify_md5) {
            
            // 修改群/组源级数据
            foreach ($params as $key => $val) {
                if ($this->redis->exists($group_id)) {
                    if (is_array($val)) {
                        $arr  = json_decode($this->redis->hget($group_id, $key), true)?:[];
                        $val  = json_encode(array_merge($arr, $val));
                        $this->redis->hset($group_id, $key, $val);
                    } else {
                        $this->redis->hset($group_id, $key, $val);
                    }
                }
            }
            
            $base_data = $this->redis->hgetall($group_id);
            unset($base_data['_md5_']);

            // 数据校验参数
            $this->redis->hset($group_id, '_md5_', $this->encryDataContent($base_data, $group_id.'base'));
            
            return true;
        }
        
        return false;
    }
    
    // 获得群/组所有资源ID
    final public function getGroupUserResourceID ($group_name) {
        
        $group_id = $this->registerID($group_name, $this->scope, $this->prefix_scope);
        
        if ($this->redis->exists($group_id) && $this->redis->hget($group_id, 'lock') == 0) {
            
            $members_list = $this->acquireGroupExtendInfo($group_name, 'member_list', null, null, false);
            
            // 公共资源库ID
            $common_resource = MD5($this->socket_unique_id.'common_resource');
            
            $sockets_ids = [];
            
            // 判断资源库是否存在
            if (!empty($members_list)) {
                
                foreach ($members_list as $member_key => $member_json) {
                    
                    $member_data = json_decode($member_json, true);
                    
                    // 有效性
                    if ($member_data['member_status'] == 0) {
                        $sockets_ids = array_merge($sockets_ids, $this->InstantUser->getUserResourceID($member_data['member_name']));
                    }
                }
            }

            return array_unique($sockets_ids);
            
        }
        
        return [];
    }
    
    // 获得群/组所有资源信息
    final public function getGroupUserResourceInfo ($group_name) {
        
        $group_id = $this->registerID($group_name, $this->scope, $this->prefix_scope);
        
        if ($this->redis->exists($group_id) && $this->redis->hget($group_id, 'lock') == 0) {
            
            $members_list = $this->acquireGroupExtendInfo($group_name, 'member_list', null, null, false);
            
            // 公共资源库ID
            $common_resource = MD5($this->socket_unique_id.'common_resource');
            
            $result = [
                'sockets_ids'  => [],
                'offline_list' => []
            ];
            
            // 判断资源库是否存在
            if (!empty($members_list)) {
                
                foreach ($members_list as $member_key => $member_json) {
                    
                    $member_data = json_decode($member_json, true);
                    
                    $online = $this->InstantUser->acquireUserInfo($member_data['member_name'], 'online');
                    
                    if ($online == '1') {
                        // 有效期
                        if ($member_data['member_status'] == 0) {
                            $result['sockets_ids'] = array_merge($result['sockets_ids'], $this->InstantUser->getUserResourceID($member_data['member_name']));
                        }
                    } else {
                        $result['offline_name'][] = $member_data['member_name'];
                    }       
                }
                $result['sockets_ids'] = array_unique($result['sockets_ids']);
            }

            return $result;
            
        }
        
        return [];
    }
    
    // 获得群/组的用户列表
    final public function getGroupUserInfo ($group_name) {
        
        $group_id = $this->registerID($group_name, $this->scope, $this->prefix_scope);
        
        if ($this->redis->exists($group_id) && $this->redis->hget($group_id, 'lock') == 0) {
            
            $members_list = $this->acquireGroupExtendInfo($group_name, 'member_list', null, null, false);
            
            $member_datas = [];
            
            // 判断资源库是否存在
            if (!empty($members_list)) {
                
                foreach ($members_list as $member_key => $member_json) {
                    
                    $member_data = json_decode($member_json, true);
                    unset($member_data['member_sign'], $member_data['member_shutup_expire'], $member_data['_md5_']);
                    
                    if ($member_data['member_status'] == 0) {
                        unset($member_data['member_status']);
                        $member_datas[] = $member_data;
                    }
                }
                
                return $member_datas;
                
            }
            
        }
        
        return [];
    }
    
}