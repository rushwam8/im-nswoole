<?php
namespace InstantChat;

use VesselBox;

/* 即时聊天系统 消息仓库类 处理函数
** 针对存储容器建立一套完整的存储机制/体系
*/
class InstantMessageWarehouse {
    
    // 基础数据结构
    private $base = [
        // 用户登录名
        'name',
        // 用户别名（昵称）
        'alias',
        // 待阅消息数
        'count',
        // 用户关联仓库ID
        'msg_house_id'
    ];
    
    // 扩展数据结构
    private $extends = [];

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
    
    // 生成初始化扩展数据结构标记
    private function encryInitExtendStructureSign ($name_id, $extend_id) {
        return MD5($extend_id.'init_structure_success'.$name_id);
    }
    
    // 生成消息仓库的扩展ID
    private function msgExtendClassID ($name_id, $class) {
        return MD5($name_id.'extend_class'.$class);
    }
    
    /* 更新用户基础数据结构 */
    final public function updateBaseStructure ($name_id) {
        
        if ($this->redis->exists($name_id)) {
            
            $base_data = $this->redis->hgetall($name_id);
            
            $md5  = $base_data['_md5_'];
            unset($base_data['_md5_']);
            
            $verify_md5 = $this->encryDataContent($base_data, $name_id.'base');
            
            if ($md5 === $verify_md5) {
                // 更新基础用户信息
                foreach ($this->base as $field) {
                    // 验证是否存在该字段
                    if (!$this->redis->hexists($name_id, $field)) {
                        $this->redis->hset($name_id, $field, '');
                    }
                }
                
                $base_data = $this->redis->hgetall($name_id);
                unset($base_data['_md5_']);
                
                // 数据校验参数
                $this->redis->hset($name_id, '_md5_', $this->encryDataContent($base_data, $name_id.'base'));
                
                return true;
            }

        }
        
        return false;
        
    }
   
    // 注册组、人唯一通信ID
    private function registerID ($sign, $salt, $prefix='') {
        return $prefix.MD5($this->socket_unique_id.'register'.$sign.$salt.$prefix);
    }
    
    // 加密数据内容
    private function encryDataContent ($data, $salt) {
        
        $json_data = json_encode($data);
        
        return MD5($salt.MD5($json_data.$salt));
        
    }
    
    /* 创建用户基础和扩展数据结构 */
    final public function createStructure ($name_id) {
        
        if (!$this->redis->exists($name_id)) {
            // 初始化基础用户信息
            foreach ($this->base as $field) {
                // 验证是否是新用户
                $this->redis->hset($name_id, $field, '');
            }
            
            $base_data = $this->redis->hgetall($name_id);
            
            // 数据校验参数
            $this->redis->hset($name_id, '_md5_', $this->encryDataContent($base_data, $name_id.'base'));
            
            return true;
        }
        
        return false;
        
    }
    
    // 编辑消息仓库本身数据
    final public function modifyMsgHouseInfo ($subject, $params=[]) {
        
        $subject_id = $this->registerID($subject, $this->scope, $this->prefix_scope);
        
        $base_data = $this->redis->hgetall($name_id);
        
        $md5  = $base_data['_md5_'];
        unset($base_data['_md5_']);

        $verify_md5 = $this->encryDataContent($base_data, $name_id.'base');
        
        if ($md5 === $verify_md5) {
            
            $this->specialDataDispense($name_id, $params);
            
            // 修改用户源级数据
            foreach ($params as $key => $val) {
                if ($this->redis->exists($name_id)) {
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
            unset($base_data['_md5_']);

            // 数据校验参数
            $this->redis->hset($name_id, '_md5_', $this->encryDataContent($base_data, $name_id.'base'));
            
            return true;
        }
        
        return false;
    }
    
    // 推入消息仓库
    final public function pushMessageWarehouse ($name, $msgbody) {
        
        $name_id = $this->InstantUser->existsUser($name);
        
        if ($name_id !== false) {
            // 获取消息仓库ID
            $warehouse_id = $this->registerID($name_id, $this->scope, $this->prefix_scope);
            $this->redis->lpush($warehouse_id, json_encode($msgbody));
            
            return true;
        }
            
        return false;
    }
    
    // 推出消息仓库
    final public function pullMessageWarehouse ($name, $limit = 0) {
        
        $msgbody = [];

        $name_id = $this->InstantUser->existsUser($name);
        
        if ($name_id !== false) {
            
            // 获取消息仓库ID
            $warehouse_id = $this->registerID($name_id, $this->scope, $this->prefix_scope);

            // 消息总数
            $total_limit = $this->redis->llen($warehouse_id);
            
            if ($limit <= 0 || $total_limit < $limit) {
                $limit = $total_limit;
            }
            
            for ($i = 0 ;$i < $limit; $i++) {
                $msgbody[] = $this->redis->rpop($warehouse_id);
            }

        }
        
        return $msgbody;
            
    }
}
