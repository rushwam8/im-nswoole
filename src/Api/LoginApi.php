<?php
namespace Api;

use InstantAssign;
use InstantChat\InstantUser;
use InstantChat\InstantMessageWarehouse;
use Model\SendModel;

class LoginApi extends InstantAssign {

	private $api_scope = 'normal';

	private $user_prefix_scope = 'u_';

	/* 预处理函数 */
	protected function __pretreatment () {
        
		$this->instant_user = InstantUser::getInstance($this->api_scope, [
			'socket_unique_id' => $this->socket_unique_id,
			'redis'            => $this->redis,
			'prefix_scope'     => $this->user_prefix_scope,
			'scope'            => $this->scope
		]);
		
		$this->instant_msghouse = InstantMessageWarehouse::getInstance($this->api_scope, [
			'socket_unique_id' => $this->socket_unique_id,
			'redis'            => $this->redis,
			'scope'            => $this->scope,
			'InstantUser'      => $this->instant_user,
			'prefix_scope'     => 'msg_'
		]);

	}

	/* 聊天用户注册 */
	protected function register () {
		
		if (!empty($this->instant_user->initUser($this->args['name'], $this->args['name'], $this->args['passwd'], 1))) {
			$this->serv->push($this->fd, json_encode([
                'header' => [
                	'mode' => $this->mode
                ],
                'body'     => [
                    'msg'  => 'SUCCESS',
                    'code' => 4
                ]
            ]));
		} else {
			$this->serv->push($this->fd, json_encode([
                'header' => [
                    'mode' => $this->mode
                ],
                'body'     => [
                    'msg'  => 'Name already',
                    'code' => -1
                ]
            ]));
		}

	}

	/* 聊天用户登录 */
	protected function login () {

		$class    = $this->header['CLASS'];
                
        $name_id  = '';
        
        // 用户不存在
        if (($name_id = $this->instant_user->existsUser($this->args['name'])) === false) {

            $this->serv->push($this->fd, json_encode([
                'header' => [
                    'mode' => $this->mode,
                ],
                'body'     => [
                    'msg'  => 'User Not Exists',
                    'code' => -1
                ]
            ]));
            
        } else {

        	$time = time();
            
            $last_login_time = json_decode($this->instant_user->acquireUserInfo($this->args['name'], 'last_login_time', ''), true)?:[];
            
            $last_login_time[$class] = isset($last_login_time[$class])?$last_login_time[$class]:0;
            
            $pwd_flag = false;
            
            $passwd = $this->instant_user->acquireUserInfo($this->args['name'], 'passwd', '');
            
            // 自动检测断线重新登录
            if (!empty($this->header['EXEMPTSIGN'])) {
                
                $exempt_sign = $this->header['EXEMPTSIGN'];
                
                $verify_sign = MD5($passwd.'exempt'.$this->args['name'].$last_login_time[$class].$class);
                
                if ($exempt_sign == $verify_sign) {
                    $pwd_flag = true;
                }
                
            } else {
                    
                if ($this->instant_user->verifyUser($this->args['name'], $this->args['passwd'])) {
                    $pwd_flag = true;
                }
                
            }
            
            if ($pwd_flag) {
                
                // 公共资源库ID
                $common_resource = MD5($this->socket_unique_id.'common_resource');
                
                $last_login_time[$class] = $time;
                
                $this->instant_user->modifyUserInfo($this->args['name'], [
                    'online' => 1,
                    'last_login_time' => json_encode($last_login_time)
                ]);
                
                $resource_list = $this->instant_user->acquireUserExtendInfo($this->args['name'], 'resource_lib', null, null, false)?:[];
                
                /* 清除用户残留资源 */
                foreach ($resource_list as $resource_id => $resource_json) {
                    
                    if ($resource_id != 'init') {
                        
                        $resource_data = json_decode($resource_json, true);
                        
                        $overtime = time() - $resource_data['create_time'];
                        
                        // 自动清除多余资源信息
                        if ($overtime > 10 || ($resource_data['expire_time'] > 0 && time() >= $resource_data['expire_time'])) {
                            $this->redis->hdel($common_resource, $resource_id);
                            $this->instant_user->updateExtendStructure($this->args['name'], 'resource_lib', $resource_id, null);
                        }
                        
                    }
                    
                }
                
                if ($this->instant_user->allotSocketResourceUser($name_id, $this->resource_id, $this->fd, $this->args['name'])){

                    $this->serv->push($this->fd, json_encode([
                        'header' => [
                            'EXEMPTSIGN' => MD5($passwd.'exempt'.$this->args['name'].$time.$class)
                        ],
                        'body'     => [
                            'msg'    => 'SUCCESS',
                            'data' => [
                                'name' => $this->args['name']
                            ],
                            'code' => 3
                        ]
                    ]));

                    // 判断用户资源数
                    $friend_list = $this->instant_user->pullFriendList($this->args['name']);

                    foreach ($friend_list as $friend) {

                        $fr_fds = $this->instant_user->getUserResourceID($friend['friend_name']);
                        
                        foreach ($fr_fds as $fr_fd) {

                            if ($this->serv->exist($fr_fd) && $this->fd != $fr_fd) {
                                
                                $this->serv->push($fr_fd, json_encode([
                                    'header' => [],
                                     'body'     => [
                                         'data'  => [
                                            // 1 一般好友, 2 特别关注好友
                                            'type'        => 1,
                                            'name'        => $this->args['name'],
                                            'online'      => '在线',
                                            'online_type' => '正常',
                                         ],
                                         'msg'  => '',
                                         'code' => 10
                                     ]
                                ]));

                            }
                        }
                    }
                    
                    // 拉取用户未读消息仓库信息
                    $unread_msg = $this->instant_msghouse->pullMessageWarehouse($this->args['name'], 0);
                    
                    if (count($unread_msg) > 0) {
                        
                        $msg_list = [];
                        foreach ($unread_msg as $val) {
                            if (($result = json_decode($val, true))) {
                                $msg_list[] = $result;
                            }
                        }

                        // 发送未读消息列表
                        $this->serv->push($this->fd, json_encode([
                            'header' => [],
                            'body'     => [
                                'msg'    => 'SUCCESS',
                                'data' => [
                                    'msglist' => $msg_list
                                ],
                                'code' => 40
                            ]
                        ]));
                    }
                        
                } else {

                    $this->serv->push($this->fd, json_encode([
                        'header' => [
                            'mode' => $this->mode,
                        ],
                        'body'     => [
                            'msg'    => 'Resource allot fail',
                            'code' => -1
                        ]
                    ]));
                }

            } else {
                $this->serv->push($this->fd, json_encode([
                    'header' => [
                        'mode' => $this->mode,
                    ],
                    'body'     => [
                        'msg'  => 'Login fail',
                        'type' => $this->mode,
                        'code' => -1
                    ]
                ]));
            }
        }
	}

}

