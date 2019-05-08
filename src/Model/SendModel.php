<?php
namespace Model;

use SimpleModel;

class SendModel {

	protected $database = 'instant_chat';

	public function test () {

		$chat_user = SimpleModel::table('chat_user')->select(SimpleModel::raw('xxoo, xxzx'), SimpleModel::raw('a123'))->groupBy('username', 'password')->having('username', '>', '123')->orhaving('username', '>', '1')->orhavingRaw('username = 1 AND password = ?', ['xx'])->where([
			['rushwam', '=', 'wam'],
			['rushwam22', '=', 'wam22']
		])->where(SimpleModel::raw('xxoo = 1'))->whereRaw('username = "123" AND password = "123123"')->whereRaw('username = "4444" AND password = "33333"')->orWhereRaw('username = ? AND password = ?', [1, '1'])->orderByRaw('aa desc, xxx desc');

		$x = SimpleModel::table('message')->select('PK_id', 'from', 'to')->leftjoin('chat_user', function ($query) {
			$query->on('from', '=', 'aaa');
		})->rightjoin('chat_user', function ($query) {
			$query->on('from2', '=', 'aaa');
			$query->on('from2', '=', 'aaa');
			$query->on('from2', '=', 'aaa');
			$query->orOn(function ($query) {
				$query->on('PK_id', 'a');
				$query->orOn('PK_id', 'b');
				$query->on(function ($query) {
					$query->on('PK_id', 'a1');
					$query->orOn('PK_id', 'b2');
					$query->on('PK_id', 'c3');
				});
			});
		})->join('chat_user', 'PK_id', '=', 'PK_id')->join('chat_user', 'PK_id', 'PK_id')->join('chat_user', function ($query) {
				$query->on('PK_id', '1');
				$query->on('PK_id', '12');
				$query->orOn('PK_id', '12');
				$query->orOn(function ($query) {
					$query->on('PK_id', '111');
					$query->orOn('PK_id', '222');
					$query->on('PK_id', '333');
				});
		})->where('PK_id', '1')->where('PK_id', '1')->orwhere('PK_id', '12')->orwhere(function ($query) {
			$query->where(SimpleModel::raw('xxoo = 1'));
			$query->orwhere('PK_id', '12');
			$query->orWhereRaw('username = ? AND password = ?', [1, '1']);
			$query->where(function ($query) {
				$query->where('PK_id', '12');
				$query->whereIn('PK_id', ['1',2,3,4, 'axs']);
				$query->whereNull('PK_id');
				$query->whereNotNull('PK_id');
			});
		})->crossJoin($chat_user)->union($chat_user)->unionAll($chat_user)->orderBy('PK_id', 'asc')->orderBy('from', 'desc')->take(10)->skip(1)->count();

		print_r(SimpleModel::printSql());

	}

}


