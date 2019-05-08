<?php

/**
* 自动加载类
* @author 王智鹏（WAM）
* @datetime 2018-09-27
*/
class AutoloadClass {

    /* 加载插件目录 */
    private $configuration;

    /* 根目录 */
    private $base_dir;

    /* 加载逻辑目录 */
    private $load_logic_dir = [
        'Api', 'Controller', 'Model'
    ];

    /**
    * 构造函数
    * $configuration 配置类引用规则变量 
    */
    public function __construct () {

        $this->base_dir = dirname(dirname(__FILE__));

        $autoload_map = require_once($this->base_dir.'/vendor/autoload/AutoloadMap.php');

        $this->configuration = $autoload_map;

    }

    /* 执行加载任务 */
    public function run () {

        foreach ($this->configuration as $visit_alias => $class_path) {

            if (file_exists($class_path)) {
                
                if (!class_exists($visit_alias, false)) {
                    require_once($class_path);
                }

            }

        }

        $src_dir = $this->base_dir.'/src';

        $files_path = [];

        foreach ($this->load_logic_dir as $dir) {
            $files_path = array_merge($files_path, $this->readDir($src_dir.'/'.$dir));
        }

        $files_path = array_merge($this->readDir($src_dir, false), $files_path);

        foreach ($files_path as $php_file) {
            require_once($php_file);
        }


    }

    /* 读取目录php文件 */
    private function readDir ($dir, $recursion=true) {
        
        $file_array = [];

        if (is_dir($dir)) {

            $dir_handle = opendir($dir);

            while ($result = readdir($dir_handle)) {

                if ($result != '.' && $result != '..') {

                    if (is_dir($dir.'/'.$result) && $recursion) {
                        $file_array = array_merge($file_array, $this->readDir($dir.'/'.$result));
                    } else {
                        if (preg_match('/\.php$/i', $result)) {
                            $file_array[] = $dir.'/'.$result;
                        }
                    }

                }

            }

        }

        return $file_array;

    }

}

?>
