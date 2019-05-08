<?php
namespace Api;

use InstantAssign;
use InstantChat\InstantUploadFile;
use InstantChat\InstantUser;

class UploadApi extends InstantAssign {

	private $api_scope = 'normal';

	private $user_prefix_scope  = 'u_';
	
	/* 预处理函数 */
	protected function __pretreatment () {

		$this->instant_uploadfile = InstantUploadFile::getInstance($this->api_scope, [
			'socket_unique_id' => $this->socket_unique_id,
			'redis'            => $this->redis,
			'scope'            => $this->scope
		]);

		$this->instant_user      = InstantUser::getInstance($this->api_scope, [
			'socket_unique_id' => $this->socket_unique_id,
			'redis'            => $this->redis,
			'prefix_scope'     => $this->user_prefix_scope,
			'scope'            => $this->scope
		]);

	}

	/* 上传文件 */
	protected function file () {

		// 上传文件
	    if (isset($this->request->files)) {

	    	$size        = 1024;

	    	$expire      = 120;
	        
	        $files       = $this->request->files;

	        $file_slices = [];
        	
	        foreach ($files as $val) {
	            $file_slices[MD5($val['tmp_name'])] = [
	            	'name'     => $val['name']
	            	, 'slices' => $this->instant_uploadfile->fileCutSlice($val['tmp_name'], $size, false, $expire)
	            	, 'type'   => $val['type']
	            	, 'expire' => $expire
	            ];
	        }
	        
	        $to_socket = $this->instant_user->getUserResourceID($this->request->post['to']);

	        foreach ($file_slices as $slices_sign) {
	            foreach ($to_socket as $socket_id) {
	            	if ($this->serv->exist($socket_id)) {
	                	$this->serv->push($socket_id, json_encode([
		                    'header' => [],
		                    'body'     => [
		                        'msg'    => 'SUCCESS',
		                        'data'   => $slices_sign, 
		                        'code'   => 8
		                    ]
		                ]));
	            	}
	            }
	        }
	    }
	}

	/* 下载文件 */
	protected function download () {

		if (isset($this->request->get))  {
        	
        	$data = $this->formatData(['type', 'filetype', 'slice_id', 'file_name'], $this->request->get);
	        
	        if ($data['type'] == 'download') {

	        	$content = $this->instant_uploadfile->fileCutSliceCombine($data['slice_id'], 120);

	            if (!empty($content)) {
	                $this->response->header('Content-Type', $data['filetype']);
	                $this->response->header("Content-Disposition","attachment; filename=".$data['file_name']);
	                $this->response->write($content);
	            } else {
	                $this->response->header('Content-Type', 'text/html;charset=utf-8');
	                $this->response->write('文件不存在');
	            }

	            $this->response->end();
	        }
	    }

	}

}

?>
