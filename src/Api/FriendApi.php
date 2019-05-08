<?php
namespace Api;

use InstantAssign;
use InstantChat\InstantSend;
use InstantChat\InstantMessageWarehouse;
use InstantChat\InstantUser;
use InstantChat\InstantGroup;

class FriendApi extends InstantAssign {

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

	}

	/* 获取我的好友列表 */
	protected function mylist () {

		$friend_list = $this->instant_user->pullFriendList($this->args['owner']);

		$this->serv->push($this->fd, json_encode([
			'header' => [
			    'mode' => $this->mode
			],
			'body'     => [
			    'msg'  => 'SUCCESS',
			    'data' => $friend_list,
			    'code' => 5
			]
		]));
	}

	/* 添加我的好友 */
	protected function add () {

		if($this->instant_user->addFriend($this->args['owner'], $this->args['friend_name'])) {
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
