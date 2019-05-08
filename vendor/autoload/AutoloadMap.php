<?php

/**
* 映射配置类
* @author 王智鹏（WAM）
* @datetime 2018-09-27
*/

$vendorDir = dirname(dirname(__FILE__));
$baseDir   = dirname($vendorDir);

return array(
	'SimpleModel'               => $vendorDir.'/simplemodel/SimpleModel.php',
	'VesselBox'                 => $vendorDir.'/vesselbox/VesselBox.php',
	'InstantAssign'             => $vendorDir.'/instantchat/InstantAssign.php',
	'InstantChat'               => $vendorDir.'/instantchat/InstantChat.php',
	'InstantChat\\InstantUser'  => $vendorDir.'/instantchat/extend/InstantUser.php', // 加载用户插件
	'InstantChat\\InstantGroup' => $vendorDir.'/instantchat/extend/InstantGroup.php', // 加载群/组插件
	'InstantChat\\InstantMessageWarehouse' => $vendorDir.'/instantchat/extend/InstantMessageWarehouse.php', // 加载消息仓库插件
	'InstantChat\\InstantUploadFile'       => $vendorDir.'/instantchat/extend/InstantUploadFile.php', // 加载文件插件
	'InstantChat\\InstantSend'             => $vendorDir.'/instantchat/extend/InstantSend.php', // 加载发送插件
);

?>
