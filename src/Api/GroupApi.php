<?php
namespace Api;

use InstantAssign;
use InstantChat\InstantGroup;
use InstantChat\InstantUser;

class GroupApi extends InstantAssign {

	private $api_scope = 'normal';

	private $user_prefix_scope  = 'u_';

	private $group_prefix_scope = 'g_';

	/* 预处理函数 */
	protected function __pretreatment () {

		$this->instant_user = InstantUser::getInstance($this->api_scope, [
			'socket_unique_id' => $this->socket_unique_id,
			'redis'            => $this->redis,
			'prefix_scope'     => $this->user_prefix_scope,
			'scope'            => $this->scope
		]);

		$this->instant_group = InstantGroup::getInstance($this->api_scope, [
			'socket_unique_id' => $this->socket_unique_id,
			'redis'            => $this->redis,
			'scope'            => $this->scope,
			'InstantUser'      => $this->instant_user,
			'prefix_scope'     => $this->group_prefix_scope
		]);
		
		$this->instant_user->InstantGroup = $this->instant_group;

	}

	/* 创建群/组 */
	protected function create () {
        
		// 创建群/组
        $group_id = $this->instant_group->initGroup($this->args['group_name'], $this->args['owner'], $this->args['group_name'], 0, '', $this->user_prefix_scope);

        if ($group_id) {
            
            $this->serv->push($this->fd, json_encode([
                'header' => [],
                'body'     => [
                    'msg'    => 'SUCCESS',
                    'data' => [
                        'group_name' => $this->args['group_name']
                    ],
                    'code' => 30
                ]
            ]));
            
        } else {
                
            $this->serv->push($this->fd, json_encode([
                'header' => [],
                'body'     => [
                    'msg'    => 'Create group Fail',
                    'code' => -1
                ]
            ]));
                
        }
	}

	/* 加入群/组 */
	protected function join () {

		$group_id = $this->instant_group->joinGroup($this->args['group_name'], $this->args['participant'], $this->user_prefix_scope);
        
        if ($group_id) {
            $this->serv->push($this->fd, json_encode([
                'header' => [],
                'body'     => [
                    'msg'  => 'SUCCESS',
                    'data' => [
                        'group_name' => $this->args['group_name'],
                        'group_id'   => $group_id
                    ],
                    'code' => 31
                ]
            ]));
        } else {
            $this->serv->push($this->fd, json_encode([
                'header' => [
                    'mode' => $this->mode,
                ],
                'body'     => [
                    'msg'  => 'Join Group fail',
                    'code' => -1
                ]
            ]));
        }

	}

	/* 转交群/组 */
	protected function turn () {

		$result = $this->instant_group->turnGroup($this->args['group_name'], $this->args['owner'], $this->args['participant']);
                    
		if ($result) {
		    $this->serv->push($this->fd, json_encode([
		        'header' => [],
		        'body'     => [
		            'msg'    => 'SUCCESS',
		            'code' => 33
		        ]
		    ]));
		} else {
		    $this->serv->push($this->fd, json_encode([
		        'header' => [
		            'mode' => $this->mode,
		        ],
		        'body'     => [
		            'msg'    => 'Turn Group fail',
		            'code' => -1
		        ]
		    ]));
		}
	}

	/* 退出群/组 */
	protected function quit () {

		$result = $this->instant_group->quitGroup($this->args['group_name'], $this->args['participant']);
        
        if ($result) {
            $this->serv->push($this->fd, json_encode([
                'header' => [],
                'body'     => [
                    'msg'    => 'SUCCESS',
                    'code' => 32
                ]
            ]));
        } else {
            $this->serv->push($this->fd, json_encode([
                'header' => [
                    'mode' => $this->mode,
                ],
                'body'     => [
                    'msg'    => 'Quit Group fail',
                    'code' => -1
                ]
            ]));
        }
	}

	/* 解散群/组 */
	protected function remove () {

		$result = $this->instant_group->removeGroup($this->args['group_name'], $this->args['owner'], $this->user_prefix_scope);
        
        if ($result !== false) {
        	
        	foreach ($result as $fd) {
        		if ($this->serv->exist($fd)) {
	        		$this->serv->push($fd, json_encode([
		                'header' => [],
		                'body'     => [
		                    'msg'    => 'SUCCESS',
		                    'code' => 34
		                ]
		            ]));
        		}
        	}

        } else {
            $this->serv->push($this->fd, json_encode([
                'header' => [
                    'mode' => $this->mode,
                 ],
                 'body'     => [
                     'msg'  => 'Remove Group fail',
                     'code' => -1
                 ]
            ]));
        }

	}

	/* 获取我的群/组列表 */
	protected function mylist () {
		
		$group_list = $this->instant_user->pullGroupList($this->args['owner']);
		
		$this->serv->push($this->fd, json_encode([
		    'header' => [
		        'mode' => $this->mode
		    ],
		    'body'     => [
		        'msg'  => 'SUCCESS',
		        'data' => $group_list,
		        'code' => 5
		    ]
		]));
	}

	/* 获取群/组成员列表*/
	protected function userlist () {
		
		$member_list = $this->instant_group->getGroupUserInfo($this->args['group_name']);

        $this->serv->push($this->fd, json_encode([
            'header' => [
                'mode' => $this->mode
            ],
            'body'     => [
                'msg'  => 'SUCCESS',
                'data' => $member_list,
                'code' => 5
            ]
        ]));
    }
}

?>
