<?php
namespace Api;

use InstantAssign;
use InstantChat\InstantUser;

class MsgbackApi extends InstantAssign {

	private $api_scope = 'normal';

	private $user_prefix_scope  = 'u_';

	/* 预处理函数 */
	protected function __pretreatment () {

		$this->instant_user = InstantUser::getInstance($this->api_scope, [
			'socket_unique_id' => $this->socket_unique_id,
			'redis'            => $this->redis,
			'prefix_scope'     => $this->user_prefix_scope,
			'scope'            => $this->scope
		]);

	}

	/* 输入中状态 */
	protected function ing () {

		$online = $this->instant_user->acquireUserInfo($this->args['friend_name'], 'online', null);
        
        if ($online !== false && $online === '1') {

			// 获取接收方的socket_id
            $to_socketids   = $this->instant_user->getUserResourceID($this->args['friend_name']);
            
            if (!empty($to_socketids)) {
                
                foreach ($to_socketids as $sid) {
                    if ($this->serv->exist($sid)) {
                        $this->serv->push($sid, json_encode([
							'header' => [
							    'mode' => $this->mode
							],
							'body'     => [
							    'msg'  => 'SUCCESS',
							    'data' => [
							    	'from'   => $this->args['from'],
							    	'status' => ($this->args['status']==1)?'ing':'out'
							    ],
							    'code' => 16
							]
						]));
                    }
                }
                
            }

		}

	}

	/* 添加我的好友 */
	protected function add () {

		if ($this->instant_user->addFriend($this->args['owner'], $this->args['friend_name'])) {
	        $this->serv->push($this->fd, json_encode([
	            'header' => [
	                'mode' => $this->mode
	            ],
	            'body'     => [
	                'msg'  => 'SUCCESS',
	                'code' => 5
	            ]
	        ]));
	    } else {
	        $this->serv->push($this->fd, json_encode([
	            'header' => [],
	            'body'     => [
	                'msg'    => 'Add friend Fail',
	                'code' => -1
	            ]
	        ]));
	    }

	}

	/* 删除我的好友 */
	protected function remove () {

		if($this->instant_user->removeFriend($this->args['owner'], $this->args['friend_name'])) {
            $this->serv->push($this->fd, json_encode([
                'header' => [
                    'mode' => $this->mode
                ],
                'body'     => [
                    'msg'  => 'SUCCESS',
                    'code' => 5
                ]
            ]));
        } else {
            $this->serv->push($this->fd, json_encode([
                'header' => [],
                'body'     => [
                    'msg'    => 'Remove friend Fail',
                    'code' => -1
                ]
            ]));
        }

	}

}

?>
