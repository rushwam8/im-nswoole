<?php
namespace InstantChat;

use VesselBox;

/* 即时聊天发送处理
** 
*/
class InstantSend {
    
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

    // 发送个体消息
    final public function send ($mode, $from, $to, $msg, $message_warehouse=true) {
        
        $online = $this->InstantUser->acquireUserInfo($to, 'online', null);
        
        if ($online !== false) {
            
            $time = time();
            
            // 离线
            if ($online != '1' && $message_warehouse === true && !empty($this->InstantMessage)) {

                $body = [
                    'from'      => $from,
                    'to'        => $to,
                    'message'   => $msg,
                    'send_time' => date('Y-m-d H:i:s', $time),
                    'mode'      => $mode
                ];
                
                $this->InstantMessage->pushMessageWarehouse($to, $body);
                
            } else {
                
                // 获取接收方的socket_id
                $to_socketids   = $this->InstantUser->getUserResourceID($to);
                
                // 获取发送方的socket_id
                $from_socketids = $this->InstantUser->getUserResourceID($from);
                
                if (!empty($to_socketids)) {
                    
                    foreach ($to_socketids as $sid) {
                        if ($this->serv->exist($sid)) {
                            $this->serv->push($sid, json_encode([
                                'header' => [
                                    'mode' => $mode
                                ],
                                'body'     => [
                                    'msg'  => 'SUCCESS',
                                    'data' => [
                                        'message'   => $msg,
                                        'from'      => $from,
                                        'send_time' => date('Y-m-d H:i:s', $time)
                                    ],
                                    'code' => 5
                                ]
                            ]));
                        }
                    }
                    
                    return true;
                    
                } else {
                    
                    // 找不到发送对象
                    foreach ($from_socketids as $sid) {
                        if ($this->serv->exist($sid)) {
                            $this->serv->push($sid, json_encode([
                                'header' => [],
                                'body'     => [
                                    'msg'    => 'Send message fail for not found to recipient',
                                    'code' => -1
                                ]
                            ]));
                        }
                    }
                    
                }
            }
        }
        
        return false;
    }
    
    // 发送群/组消息
    final public function sendGroup ($mode, $from, $to, $msg, $message_warehouse=true) {
        
        $gather = $this->InstantGroup->getGroupUserResourceInfo($to);
        
        $time = time();
        
        if ($message_warehouse === true && !empty($this->InstantMessage)) {
            
            foreach ($gather['offline_list'] as $user) {
                
                $body['from']      = $from;
                $body['to']        = $to;
                $body['message']   = $msg;
                $body['send_time'] = $time;
                $body['mode']      = $mode;
                
                $this->InstantMessage->pushMessageWarehouse($user, $body);
                
            }
        }
        
        if (!empty($gather['sockets_ids'])) {
            
            foreach ($gather['sockets_ids'] as $sid) {
                if ($this->serv->exist($sid)) {
                    $this->serv->push($sid, json_encode([
                        'header' => [
                            'mode' => $mode
                        ],
                        'body'     => [
                            'msg'    => 'SUCCESS',
                            'data' => [
                                'message'    => $msg,
                                'from'       => $from,
                                'group_name' => $to,
                                'send_time'  => date('Y-m-d H:i:s', $time)
                            ],
                            'code' => 5
                        ]
                    ]));
                }
            }
            
            return true;
            
        }
        
        return false;
    }
    
}