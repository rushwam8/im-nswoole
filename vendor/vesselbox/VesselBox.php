<?php

/* 静态化容器盒子类 */
class VesselBox {
    
	// 变量容器
	private $obj_vessel = [];
    
	// 变量容器
	private $var_vessel = [];
    
	static private $instance;
    
    /* 类的接口名 */
    static private $interface_name = 'VesselBoxInterface';
    
	/* 初始化容器盒子 */
	static public function initBox ($classname, $encryption='', $rely=[]) {
		
		if (!self::$instance instanceof self) {
            self::$instance = new self();
        }
        
		$scopemd5 = MD5($classname.'Vessel'.MD5($encryption).'BOX');
        
		$flag = false;
        
        /* 判断盒子资源是否存在 */
        if (!isset(self::$instance->obj_vessel[$scopemd5])) {

            $classname = '\\'.$classname;
            
            $interface_name = self::$interface_name;

            if (method_exists($classname, $interface_name)) {

                $scopemd5_var = MD5($scopemd5.'Var');
                
                $result = $classname::$interface_name();

                self::$instance->var_vessel[$scopemd5_var] = [];

                if ($result instanceof $classname) {

                    /* 追加属性 */
                    foreach ($rely as $varname => $value) {
                        $result->$varname = $value;
                    }

                    self::$instance->var_vessel[$scopemd5_var] = array_keys($rely);

                    $flag   = self::$instance->obj_vessel[$scopemd5] = $result;

                    $result = null;
                
                }
            }
        }

        return $flag;

	}
    
    /* 获得容器资源 */
    static public function giveBox ($classname, $encryption='') {
        
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }
        
        $scopemd5 = MD5($classname.'Vessel'.MD5($encryption).'BOX');

        /* 判断盒子资源是否存在 */
        return isset(self::$instance->obj_vessel[$scopemd5])?self::$instance->obj_vessel[$scopemd5]:null;

    }
    
    /* 删除容器资源 */
    static public function removeBox ($classname, $encryption='') {
        
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }
        
        $scopemd5 = MD5($classname.'Vessel'.MD5($encryption).'BOX');

        $flag = false;
        
        /* 判断盒子资源是否存在 */
        if (isset(self::$instance->obj_vessel[$scopemd5])) {
            
            $scopemd5_var = MD5($scopemd5.'Var');

            self::$instance->obj_vessel[$scopemd5] = null;
            unset(self::$instance->obj_vessel[$scopemd5]);

            self::$instance->var_vessel[$scopemd5_var] = null;
            unset(self::$instance->var_vessel[$scopemd5_var]);

            $flag = true;

        }

        return $flag;

    }

    /* 更新容器资源 */
    static public function updateBox ($classname, $encryption='', $rely=[], $only=true) {

        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }
        
        $scopemd5 = MD5($classname.'Vessel'.MD5($encryption).'BOX');

        $flag = false;
        
        /* 判断盒子资源是否存在 */
        if ((is_array($rely) || $rely === null) && isset(self::$instance->obj_vessel[$scopemd5])) {

            $scopemd5_var = MD5($scopemd5.'Var');

            $result = self::$instance->obj_vessel[$scopemd5];

            if ($only === true) {
                foreach (self::$instance->var_vessel[$scopemd5_var] as $varname) {
                    unset($result->$varname);
                }
            }

            foreach ($rely as $varname => $value) {
                $result->$varname = $value;
            }

            if ($only === true) {
                self::$instance->var_vessel[$scopemd5_var] = array_keys($rely);
            } else {
                self::$instance->var_vessel[$scopemd5_var] = array_unique(array_merge(self::$instance->var_vessel[$scopemd5_var], array_keys($rely)));
            }

            self::$instance->obj_vessel[$scopemd5] = $result;

            $flag = true;

        }

        return $flag;
        
    }

}

?>
