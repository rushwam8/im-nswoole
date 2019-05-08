<?php

/**
* 程序入口文件
* @author 王智鹏（WAM）
* @datetime 2018-09-27
*/

date_default_timezone_set('Asia/Shanghai');

require_once('./vendor/AutoloadClass.php');

$autoload_class = new AutoloadClass();

$autoload_class->run();

$init = new InstantChat("0.0.0.0", 9502, 'Test');

$init->open(function ($server, $req, $reid) {
	
    $server->push($req->fd, json_encode([
        'header' => [],
        'body'     => [
            'reid' => $reid,
            'code' => 1
        ]
    ]));
    
    echo "connection open: {$req->fd}\n";
});

$init->start();

?>
