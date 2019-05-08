<?php
namespace Api;

use InstantAssign;
use InstantChat\InstantSend;
use InstantChat\InstantMessageWarehouse;
use InstantChat\InstantUser;
use InstantChat\InstantGroup;

class SendApi extends InstantAssign {

	private $api_scope = 'normal';

	private $user_prefix_scope  = 'u_';

	private $group_prefix_scope = 'g_';

	private $msg_prefix_scope   = 'msg_';

	/* 预处理函数 */
	protected function __pretreatment () {

		$this->instant_user = InstantUser::getInstance($this->api_scope, [
			'socket_unique_id' => $this->socket_unique_id,
			'redis'            => $this->redis,
			'prefix_scope'     => $this->user_prefix_scope,
			'scope'            => $this->scope
		]);

		$instant_msghouse = InstantMessageWarehouse::getInstance($this->api_scope, [
			'socket_unique_id' => $this->socket_unique_id,
			'redis'            => $this->redis,
			'scope'            => $this->scope,
			'InstantUser'      => $this->instant_user,
			'prefix_scope'     => $this->msg_prefix_scope
		]);

		$instant_group = InstantGroup::getInstance($this->api_scope, [
			'socket_unique_id' => $this->socket_unique_id,
			'redis'            => $this->redis,
			'scope'            => $this->scope,
			'InstantUser'      => $this->instant_user,
			'prefix_scope'     => $this->group_prefix_scope
		]);

		$this->instant_send = InstantSend::getInstance($this->api_scope, [
			'socket_unique_id' => $this->socket_unique_id,
			'redis'            => $this->redis,
			'serv'             => $this->serv,
			'InstantMessage'   => $instant_msghouse,
			'InstantUser'      => $this->instant_user,
			'InstantGroup'     => $instant_group,
			'scope'            => $this->scope
		]);
	}

	/* 发送个人 */
	protected function personage () {
		
		$this->serv->push($this->fd, json_encode([
	        'header'   => [],
	        'body'     => [
	            'msg'  => 'SUCCESS',
	            'code' => 6
	        ]
	    ]));
    	
	    // 发送个体消息
	    $this->instant_send->send(
	    		$this->mode
	    		, $this->args['from']
	    		, $this->args['to']
	    		, $this->args['message']
	    		, true
	    	);
	}

	/* 发送群/组 */
	protected function group () {

		$this->serv->push($this->fd, json_encode([
            'header'   => [],
            'body'     => [
                'msg'  => 'SUCCESS',
                'code' => 6
            ]
        ]));
        
        // 发送群/组消息
        $this->instant_send->sendGroup($this->mode, $this->args['from'], $this->args['to'], $this->args['message'], true);

	}

}

?>
