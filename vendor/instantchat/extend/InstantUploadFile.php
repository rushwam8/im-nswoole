<?php
namespace InstantChat;

use VesselBox;

/* 即时聊天文件处理
** 
*/
class InstantUploadFile {
    
    // 防止克隆对象
    private function __clone(){}

    // 防止实例化对象
    private function __construct(){}

    /* vesselbox 接口函数 */
    final static public function VesselBoxInterface () {
        return new self();
    }
    
    // 单例模式
    final static public function getInstance($scope, $rely=[]) {

        return VesselBox::initBox(__CLASS__, $scope, $rely)?:VesselBox::giveBox(__CLASS__, $scope);

    }
    
    // 获得公共文件存储ID
    final public function getCommonFileSaveID () {
        return MD5($this->socket_unique_id.'filesave'.$this->scope);
    }
    
    /* 文件切片操作 */
    final public function fileCutSlice ($file, $slice_size=1024, $return_arr=false, $expire_time=3600, $dbindex=1) {
        
        
        if ($dbindex == 0) {
            $dbindex = 1;
        }
        
        $file_resource = fopen($file, 'r');
        
        $file_slices = [];
        
        $file_slice_sign = MD5($this->getCommonFileSaveID().$file.$this->socket_unique_id);
        
        if($file_resource){
            while(!feof($file_resource)) {
                $file_slices[] = fread($file_resource, $slice_size);
            }
        }
        
        if ($return_arr === true) {
            return $file_slices;
        }
        
        $file_slice_len = count($file_slices);
        
        if ($file_slice_len > 0) {
            
            $this->redis->select($dbindex);
            
            $this->redis->hset($file_slice_sign, 'tlen', $file_slice_len);
            
            foreach ($file_slices as $index => $slice) {
                $this->redis->hset($file_slice_sign, $index, $slice);
            }
            
            $this->redis->expire($file_slice_sign, $expire_time);
            
            $this->redis->select(0);
            
            return $file_slice_sign;
        }
        
        return false;
        
    }
    
    /* 文件切片组合操作 */
    final public function fileCutSliceCombine ($file_slice_sign, $expire_time = 0, $dbindex=1) {
        
        if ($dbindex == 0) {
            $dbindex = 1;
        }
        
        $this->redis->select($dbindex);
        
        $file_bin = '';
        
        if ($this->redis->exists($file_slice_sign)) {
            
            $tlen = $this->redis->hget($file_slice_sign, 'tlen');
            
            for ($i = 0; $i < $tlen; $i++) {
                $file_bin .= $this->redis->hget($file_slice_sign, $i);
            }
            
            if ($expire_time == 0) {
                $this->redis->del($file_slice_sign);
            } else {
                $this->redis->expire($file_slice_sign, $expire_time);
            }
            
        }
        
        $this->redis->select(0);
        
        return $file_bin;
        
    }
}