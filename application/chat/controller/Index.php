<?php
namespace app\chat\controller;

use think\Controller;
use think\Db;
use think\facade\Request;
use app\api\controller\Send;
use Overtrue\Pinyin\Pinyin;
use lib\Imgcompress;
use app\api\controller\Api;
use getui\GeTui;

class Index extends Api
{
	use Send;

	// 个推测试
	public function pushTest()
	{	
		$getui = new GeTui();
		$res = $getui->pushMessageToSingle('b73cb8b103210232da459095fee7fc47');
		self::returnMsg(200,'成功',$res);

	}

	/*
	 * 检查为游客时，用户表是否存在
	 */
	public function checkNoneUser()
	{
		$account = input('uuid');
		$res = Db::name('user')->where('account',$account)->find();

		if( $res ){
			//存在则返回用户id
			self::returnMsg(200,'',['uid' => $res['id']]);

		}else{
			//不存在，则存入用户表
			$data['account'] = $account;
			$data['roleid'] = 12;
			$data['createtime'] = date('Y-m-d H:i:s');
			$data['name'] = '游客';
			$id = Db::name('user')->insertGetId($data);
			self::returnMsg(200,'',['uid' => $id]);

		}

	}

	/*
	 * 加载历史聊天记录,查询最近20条记录
	 */
	public function loadMessageList()
	{
		$params = input('');
		$fromid = $params['fromid'];
		$toid = $params['toid'];

		//将对方的记录变为已读
		$update = Db::name('app_chat_record')->where(['sender_id'=>$toid,'receive_id'=>$fromid,'is_read'=>0])->update(['is_read'=>1]);

		// 查询双方的聊天记录

		$count = Db::name('app_chat_record')->where(' (sender_id = :fromid and receive_id = :toid and sender_delete_flag = 0) || (sender_id = :fromid1 and receive_id = :toid1 and receive_delete_flag = 0)',['fromid'=>$fromid,'toid'=>$toid,'fromid1'=>$toid,'toid1'=>$fromid])->order('id')->count();

		if( $count > 20 ){

			$res = Db::name('app_chat_record')->where(' (sender_id = :fromid and receive_id = :toid and sender_delete_flag = 0 ) || (sender_id = :fromid1 and receive_id = :toid1 and receive_delete_flag = 0)',['fromid'=>$fromid,'toid'=>$toid,'fromid1'=>$toid,'toid1'=>$fromid])->order('id')->limit($count-20,20)->select();
		}else{

			$res = Db::name('app_chat_record')->where(' (sender_id = :fromid and receive_id = :toid and sender_delete_flag = 0) || (sender_id = :fromid1 and receive_id = :toid1 and receive_delete_flag = 0)',['fromid'=>$fromid,'toid'=>$toid,'fromid1'=>$toid,'toid1'=>$fromid])->order('id')->select();
		}

		// 组合聊天记录
		$back = [];
		if(!empty($res)){

			foreach ($res as $key => $value) {
				$type = '';
				$sender = '';
				switch ($value['type']) {
					case '1':
						$type = 'text';
						break;
					case '2':
						$type = 'img';
						break;
					case '3':
						$type = 'sound';
						break;
				}

				if($value['sender_id'] == $fromid){
					$sender = 'self';

				}else{
					$sender = 'zs';

				}

				$time = $this->timeSwitch($value['send_time']);
				if($key != 0){

					$pretime = $this->timeSwitch($res[$key-1]['send_time']);
					if($pretime == $time){
						$time = '';
					}

				}

				$back[] = [
					'type' => $type,
					'sender' => $sender,
					'content' => str_replace(["\r","\n","\r\n"], '<br/>', $value['content']),
					'time' => $time
				];

			}

		}
		
		
		self::returnMsg(200,'',$back);
		
		
	}

	/*
	 * 查询超级管理员账号id
	 */
	public function checkAdminId()
	{
		$res = Db::name('user')->where(['roleid'=>1,'status'=>1,'deptid'=>27,'id'=>1])->field('id,name,avatar')->orderRaw('rand()')->find();
		$res['avatar'] = config('image_url').'avatar/'.$res['avatar'];
		self::returnMsg(200,'',['aid'=>$res]);
	}

	/*
	 * 加载消息列表数据
	 */
	public function getChatList()
	{
		
		$fromid= input('fromid');
		// 查询我的角色
		$roleid = Db::name('user')->where('id',$fromid)->field('roleid')->find();

		//查询超级管理员(客服账号)
		$adminIds = Db::name('user')->where(['roleid'=>1,'status'=>1,'deptid'=>27])->field('id')->select();
		$admin = [];
		foreach ($adminIds as $key => $value) {
			$admin[] = $value['id'];
		}

		//查询我的聊天记录
		$user = Db::name('app_chat_record')->where( '(sender_id = :fromid and sender_delete_flag = 0) || (receive_id = :toid and receive_delete_flag = 0)',['fromid'=>$fromid,'toid'=>$fromid])->field('id,sender_id,receive_id')->order('id desc')->group('sender_id,receive_id')->select();
		

		//$user = arraySort($user, 'id');

		$ids = [];
		// 筛选聊天对象id集合
		foreach ($user as $key => $value) {

			if( $value['sender_id'] == $fromid){
				$ids[] = $value['receive_id'];
			}

			if( $value['receive_id'] == $fromid){
				$ids[] = $value['sender_id'];
			}

		}

		$ids = array_unique($ids);	//去重的对象id

		// 筛选出有聊天记录的超级管理员
		$admin = array_intersect($admin,$ids);

		// 正式用户查询与好友的聊天记录,除管理员之外
		if($roleid['roleid'] != 12 && $roleid['roleid'] !=1 ){

			$array = $this->getFriendIds($fromid);
			$ids = array_intersect($ids, $array);
			//将客服重新加入
			$ids = array_merge($ids,$admin);
		}
	
		//查询用户信息
		$userInfo = DB::name('user')->where('id','in',$ids)->field('id,avatar,name')->orderRaw('find_in_set(id,"'.implode($ids, ',').'")')->select();

		foreach ($userInfo as $key => $value) {
			$userInfo[$key]['avatar'] = config('image_url').'avatar/'.$userInfo[$key]['avatar'];
			$userInfo[$key]['info'] = $this->getLastRecord($fromid,$value['id']);
			$userInfo[$key]['count'] = $this->getSingleCount($fromid,$value['id']);
			$userInfo[$key]['order'] = $userInfo[$key]['info']['order'];
		}

		self::returnMsg(200,'',[ 'userinfo' => arraySort($userInfo,'order'), 'totlecount' => $this->getCount( $fromid,$roleid['roleid']) ]);

	}

	/*
	 * 查询我的好友id集
	 * $fromid 我的用户id
	 */
	public function getFriendIds($fromid)
	{
		$array = [];  //好友id
		$res = Db::name('app_user_relation')->where(['type'=>1])->where(function($query) use ($fromid){
				$query->whereOr(['request_id'=>$fromid,'receive_id'=>$fromid]);

		})->field('request_id,receive_id')->select();

		foreach ($res as $key => $value) {
			if($value['request_id'] == $fromid){
				$array[] = $value['receive_id'];
			}

			if($value['receive_id'] == $fromid){
				$array[] = $value['request_id'];
			}
		}
		return $array;

	}

	/*
	 * 查询最后一条记录和时间
	 * params int $fromid 用户自己的id
	 * params int $toid   聊天对象的id
	 */
	public function getLastRecord($fromid,$toid)
	{
		$record = Db::name('app_chat_record')->where(' (sender_id = :fromid and receive_id = :toid and sender_delete_flag = 0) || (sender_id = :fromid1 and receive_id = :toid1 and receive_delete_flag = 0)',['fromid'=>$fromid,'toid'=>$toid,'fromid1'=>$toid,'toid1'=>$fromid])->order('id desc')->find();

		if( !empty($record)) {

			return [
				'time' => $this->timeSwitch($record['send_time']),
				'content' => $record['type'] == 2 ? '[图片]' : ($record['type'] == 3?'[语音]' :$record['content']),
				'order'	=>$record['send_time']
			];

		}else{

			return [];

		}
		

	}

	/*
	 * 查询单个用户的未读消息条数
	 * params int $fromid 用户自己的id
	 * params int $toid   聊天对象的id
	 */
	public function getSingleCount($fromid,$toid)
	{
		$count = Db::name('app_chat_record')->where('sender_id = :toid and receive_id = :fromid and receive_delete_flag = 0 and is_read = 0',['toid'=>$toid,'fromid'=>$fromid])->count();
		return $count;
	}
	


	/*
	 * 查询总未读消息条数
	 * params int $fromid 用户自己的id
	 */
	protected function getCount($fromid,$roleid)
	{
		if($roleid != 12 && $roleid != 1){
			$condition[] = ['receive_id','=',$fromid];
			$condition[] = ['receive_delete_flag','=',0];
			$condition[] = ['is_read','=',0];
			$condition[] = ['sender_id','in',$this->getFriendIds($fromid)];

			$count = Db::name('app_chat_record')->where($condition)->count();


		}else{
			$count = Db::name('app_chat_record')->where(['receive_id'=>$fromid,'receive_delete_flag'=>0,'is_read'=>0])->count();

		}

		return $count;

	}

	/*
	 * 删除聊天记录
	 */
	public function deleteRecored()
	{
		$params = input('');
		$fromid = $params['fromid'];
		$toid = $params['toid'];

		// 删除自己发送的记录
		Db::name('app_chat_record')->where(['sender_id'=>$fromid,'receive_id'=>$toid,'sender_delete_flag'=>0])->update(['sender_delete_flag'=>1]);
		//删除别人发送的记录
		Db::name('app_chat_record')->where(['sender_id'=>$toid,'receive_id'=>$fromid,'receive_delete_flag'=>0])->update(['receive_delete_flag'=>1]);
		self::returnMsg(200,'',$params);

	}

	/*
	 * 对话中图片、录音上传
	 */
	public function chatImgUpload()
	{
		// 获取表单上传文件 例如上传了001.jpg
		
	    $params = input('');
	    $file = $_FILES['upload'];
	    $ext = explode('.', $file['name'])[1];
	    $size = $file['size']; //字节
	    $filename = time().'_'.substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 10).'.'.$ext;
	    
	    try{

	    	if( $size <= 1024*1024*8 ){

	    	 $dir = config('save_root').'chatImg';

	    	 if( !is_dir( $dir ) ){
			    	mkdir( $dir,0777,true );
			 }

			 if( move_uploaded_file( $file['tmp_name'] , $dir.'/'.$filename ) ){
			 	if($params['sign'] == 'img'){
			 		// 将图片超过1M进行压缩
				 	$percent = 1;

				 	if( $size >= 1024*1024*1 && $size < 1024*1024*2 ){
				 		$percent = 0.7;
				 	}

				 	if($size >= 1024*1024*2){
						$percent = 0.5;
				 	}

					$source = $dir.'/'.$filename;
				 	$compress = (new Imgcompress($source,$percent))->compressImg($source);
			 	}
			 	

			 	self::uploadBack(200,['url'=>config('image_url').'chatImg/'.$filename]);

			 }else{

			 	self::uploadBack(201,['err'=>'服务器异常，上传失败']);
			 	
			 }
	  	
		    }else{

			 	self::uploadBack(201,['err'=>'文件超过8M']);

		    }

	    }catch(\Exception $e){

			self::uploadBack(201,['err'=>'服务器异常，稍后再试']);


	    }

	}

	/*
	 * 获取单个用户信息
	 * @params $uid int 用户id
	 */
	
	public function getUserInfo($uid)
	{
		$info =   Db::name('user')->alias('U')->join('company COM','U.company_id = COM.id and U.roleid = 6','left')->join('hospital HOS','U.hospital_id = HOS.id and U.roleid = 7','left')->join('supplier SUP','U.supplier_id = SUP.id and U.roleid = 9','left')->join('dept DEP','U.wjw_dept_id = DEP.id and U.roleid = 8','left')->join('role RO','U.roleid = RO.id')->join('app_device_register ADR','U.app_register_id = ADR.id','left')->where('U.id',$uid)->fieldRaw('U.*,IFNULL(HOS.name,IFNULL(COM.name,IFNULL(SUP.name,DEP.simplename))) as comp_name,RO.name as role_name,ADR.client_id,ADR.token,ADR.version as system')->find();

		//判断图标是否存在
		if( !file_exists( config('save_root').'avatar/'.$info['avatar'] ) || empty($info['avatar']) ) {
			$info['avatar'] = '';
		}else{
			$info['avatar'] = config('image_url').'avatar/'.$value['avatar'];
		}

		return $info;

	}

	/*
	 *  聊天消息推送
	 */
	protected function pushChat($fromid,$toid,$content,$type)
	{
		$fromInfo = $this->getUserInfo($fromid);
		$toInfo = $this->getUserInfo($toid);

		$cid = $toInfo['client_id'];	//推送对象标识
		$title = $fromInfo['name'];		//发送者姓名
		$payload = [
			'type' => 'chat',
		];
		$logoUrl = $fromInfo['avatar'];
		$text = '';
		switch ( (int)$type) {
			case 2:
				$text = '[图片消息]';
				break;

			case 3:
				$text = '[语音消息]';
				break;
			
			default:
				$text = $content;
				break;
		}

		$getui = new GeTui();	//实例化个推
		$getui->pushMessageToSingle($cid,['title'=>$title,'content'=>$text,'payload'=>$payload],true);
		
	}


	/*
	 * 存储发送者对话记录
	 */
	public function saveMessage()
	{
		$params = input('');
		$data['sender_id'] = $params['fromid'];
		$data['receive_id'] = $params['toid'];
		$data['type'] = $params['type'];
		$data['content'] = $params['content'];
		$data['send_time'] = time();

		try{

			$getId = Db::name('app_chat_record')->insertGetId( $data );

			if( $getId ){
				//消息发送成功后，推送到用户
				$this->pushChat($params['fromid'],$params['toid'],$params['content'],$params['type']);

				self::returnMsg(200,'',[ 'id'=>$getId ,'time' => $this->timeSwitch($data['send_time']) ]);

			}else{
				self::returnMsg(201,'fail');
			}

		}catch(\Exception $e){

			self::returnMsg(201,'fail');

		}

	}

	/*
	 * 改变消息记录为已读
	 */
	public function changeIsRead()
	{
		$id = input('id');
		$res = Db::name('app_chat_record')->where('id',$id)->update(['is_read'=>1]);
		self::returnMsg(200,'');
	}

	/*
	 * 搜索好友
	 */
	public function searchUser()
	{
		$params = input('');
		$roleid = $params['accountInfo']['roleid'];		//角色id
		$uid = $params['accountInfo']['id'];			//用户id
		$id = $params['accountInfo']['roleid'];			//企业或单位id
		$value = $params['value'];						//搜索字段 企业名称(生产企业、经营企业、卫健委) ，医疗机构，手机号

		// 筛选搜索的字段，字段内容不能太简短，不能进行搜索
		$fields = ['公司','医院','中心医院','企业','制药','南充','南充市','成都','成都市','重庆','重庆市','人民医院','四川','四川省','生产','卫健委','有限公司','药品','医药','贸易','药业','控股','药材','集团','医疗','器械','保健院','妇幼','中医院','股份','科技','药厂','股份有限','股份有限公司','药业有限公司','制药厂','有限责任公司','医疗器械','医药器械'];

		if( in_array($value,$fields) || isOneChinese($value) ){
			self::returnMsg(201,'请搜索关键性词语');
		}

		// 搜索用户表
		$sql =  'select * from ( select U.*,IFNULL(HOS.name,IFNULL(COM.name,IFNULL(SUP.name,DEP.simplename))) as comp_name,RO.name as role_name from ( select * from user where roleid in (6,7,8,9) and status = 1 and id <> ? ) as U  left join company COM on U.company_id = COM.id and U.roleid = 6 left join hospital HOS on U.hospital_id = HOS.id and U.roleid = 7 left join supplier SUP on U.supplier_id = SUP.id and U.roleid = 9 left join dept DEP on U.wjw_dept_id = DEP.id and U.roleid = 8 inner join role RO on U.roleid = RO.id ) as USER where comp_name like "%'.$value.'%" or phone = ?';

		$res = Db::query($sql,[$uid,$value]);
		if(empty($res)){
			self::returnMsg(201,'查无此用户');
		}

		foreach ($res as $key => $value) {
			$res[$key]['avatar'] = config('image_url').'avatar/'.$value['avatar'];
		}
		self::returnMsg(200,'',$res);

	}

	/*
	 * 添加好友，验证关系状态
	 */
	public function checkRelationType()
	{
		$params = input('');
		$res = Db::name('app_user_relation')->where('(request_id = :myid and receive_id = :toid) || (request_id = :myid1 and receive_id = :toid1 )',['myid'=>$params['myuid'],'toid'=>$params['touid'],'myid1'=>$params['touid'],'toid1'=>$params['myuid']])->select();

		$agree = '';       //已同意
		$requesting = '';  //申请中
		if( !empty($res) ){
			foreach ($res as $key => $value) {

				if($value['type'] == 1){
					$agree = 1;
				}

				if($value['type'] == 0 && $value['request_id'] == $params['myuid']){
					$requesting = 1;
				}

			}

			// 判断是否存在好友或已申请
			if( !empty($agree) ){
				self::returnMsg(201,'好友已存在,勿重复添加');
			}

			if( !empty($requesting)){
				self::returnMsg(201,'已申请,勿重复申请');
			}

			self::returnMsg(200,'');


		}else{
			//不是好友或未申请
			self::returnMsg(200,'');

		}


	}

	/*
	 * 添加好友操作
	 */
	public function userAddRequst()
	{
		$params = input('');
		$myid = $params['myuid'];
		$toid = $params['touid'];

		// 查询是否存在申请记录
		$record = Db::name('app_user_relation')->where(['request_id'=>$myid,'receive_id'=>$toid])->find();
		if( empty($record) ){
			//未空则新增记录
			$data['request_id']  = $myid;
			$data['receive_id']  = $toid;
			$data['message'] = $params['msg'];
			$data['request_time'] = time();
			$data['type'] = 0;

			try{
				$insert =Db::name('app_user_relation')->insert( $data );
				if($insert){
					self::returnMsg(200,'发送成功');
				}else{
					self::returnMsg(201,'发送失败');
				}

			}catch(\Exception $e){
				self::returnMsg(201,'系统异常,稍后再试');

			}

			
		}else{
			//存在记录则更新记录
			$data['request_time'] = time();
			$data['type'] = 0;
			$data['message'] = $params['msg'];
			$data['is_check'] =0;
			$data['delete_flag'] =0;

			try{
				$update = Db::name('app_user_relation')->where('id',$record['id'])->update($data);
				
				if($update){
					self::returnMsg(200,'发送成功');
				}else{
					self::returnMsg(201,'发送失败');
				}

			}catch(\Exception $e){
				self::returnMsg(201,'系统异常,稍后再试');

			}

		}
	}

	/*
	 * 查询我的好友列表
	 */
	public function getUserList()
	{
		$myid = input('myid');

		$res = Db::name('app_user_relation')->where(['type'=>1])->where(function($query) use ($myid){
			$query->whereOr(['request_id'=>$myid,'receive_id'=>$myid]);

		})->field('request_id,receive_id')->select();

		$array = [];
		foreach ($res as $key => $value) {
			if($value['request_id'] == $myid){
				$array[] = $value['receive_id'];
			}

			if($value['receive_id'] == $myid){
				$array[] = $value['request_id'];
			}
		}


		// 查询新的朋友申请消息总数
		$count = Db::name('app_user_relation')->where(['receive_id'=>$myid,'type'=>0,'is_check'=>0])->count();

		// 判断是否存在好友
		if(!empty($array)){

			// 查询好友详细信息
			$sql =  'select U.*,IFNULL(HOS.name,IFNULL(COM.name,IFNULL(SUP.name,DEP.simplename))) as comp_name,RO.name as role_name from ( select * from user where  id in ( '.implode(',',$array).' ) ) as U  left join company COM on U.company_id = COM.id and U.roleid = 6 left join hospital HOS on U.hospital_id = HOS.id and U.roleid = 7 left join supplier SUP on U.supplier_id = SUP.id and U.roleid = 9 left join dept DEP on U.wjw_dept_id = DEP.id and U.roleid = 8 inner join role RO on U.roleid = RO.id ';

			$info = Db::query($sql);
			$arr = [];
			$pinyin = new Pinyin();
			// 按照首字母组合数据
			foreach ($info as $key => $value) {
				$py =  $this->getFirstLetter($value['name']);
				// 首字母分组
				$info[$key]['group'] = $py[0];
				$info[$key]['tag'] = $py[1];
				$info[$key]['avatar'] = config('image_url').'avatar/'.$value['avatar'];
				$arr[ $py[0] ][] = $info[$key];
			}

			ksort($arr);

		}else{
			$arr = [];

		}

		self::returnMsg(200,'',['list'=>$arr,'count'=>$count]);
		

	}

	/*
	 * 查询新的朋友列表
	 */
	public function getNewFriend()
	{
		$myid = input('myid');

		// 改变未查看状态为已查看
		$update = Db::name('app_user_relation')->where(['receive_id'=>$myid,'type'=>0,'is_check'=>0])->update(['is_check'=>1]);

		// 查询加我的好友列表，包括状态为申请中、已过期的
		$condition = [];
		$condition[] = ['AUR.receive_id','=',$myid];
		$condition[] = ['AUR.type','in',[0,1,3]];
		$condition[] = ['AUR.delete_flag','=',0];

		$res = Db::name('app_user_relation')->alias('AUR')->join('user U','AUR.request_id = U.id')->join('company COM','U.company_id = COM.id and U.roleid = 6','left')->join('hospital HOS','U.hospital_id = HOS.id and U.roleid = 7','left')->join('supplier SUP','U.supplier_id = SUP.id and U.roleid = 9','left')->join('dept DEP','U.wjw_dept_id = DEP.id and U.roleid = 8','left')->join('role RO','U.roleid = RO.id')->where($condition)->order('AUR.request_time desc')->fieldRaw('U.*,IFNULL(HOS.name,IFNULL(COM.name,IFNULL(SUP.name,DEP.simplename))) as comp_name,RO.name as role_name,AUR.type,AUR.message,AUR.id rid')->select();

		foreach ($res as $key => $value) {

			$res[$key]['avatar'] = config('image_url').'avatar/'.$value['avatar'];

		}

		self::returnMsg(200,'',$res);


	}

	/*
	 * 验证通过新朋友
	 */
	public function checkUser()
	{
		$rid = input('rid');
		try{

			$res = Db::name('app_user_relation')->where('id',$rid)->update(['type'=>1]);
			self::returnMsg(200,'验证成功');

		}catch(\Exception $e){
			self::returnMsg(201,'系统错误，稍后再试');
		}
		
	}

	/*
	 * 新的朋友记录删除
	 */
	public function newRecordDel()
	{
		$rid = input('rid');
		try{

			$res = Db::name('app_user_relation')->where('id',$rid)->update(['delete_flag'=>1]);
			self::returnMsg(200,'删除成功');

		}catch(\Exception $e){
			self::returnMsg(201,'系统错误，稍后再试');
		}
	}

	/*
	 * 删除好友
	 */
	public function friendDel()
	{
		$myid = input('myid');
		$toid = input('toid');

		try{

			// 删除好友关系表

			$res = Db::name('app_user_relation')->where(['type'=>1])->where('(request_id = :myid and receive_id = :toid) || (request_id = :myid1 and receive_id = :toid1)',['myid'=>$myid,'toid'=>$toid,'myid1'=>$toid,'toid1'=>$myid])->update(['type'=>2,'delete_flag'=>1]);

			// 删除自己发送的记录
			Db::name('app_chat_record')->where(['sender_id'=>$myid,'receive_id'=>$toid])->update(['sender_delete_flag'=>1,'receive_delete_flag'=>1]);

			//删除别人发送的记录
			Db::name('app_chat_record')->where(['sender_id'=>$toid,'receive_id'=>$myid])->update(['receive_delete_flag'=>1,'sender_delete_flag'=>1]);

			self::returnMsg(200,'');

		} catch (Exception $e) {
			self::returnMsg(201,'系统错误，稍后再试');

		}

	}

	/*
	 * 获取字符串首字母
	 */
	public function getFirstLetter($string)
	{
		$pinyin = new Pinyin();
		$tag = strtoupper( $pinyin->abbr($string ));

		//判断第一个字符是否是字母
		if( preg_match('/[a-zA-Z]/',$string[0])){

			return [strtoupper($string[0]),$tag];

		}
		
		$py =  strtoupper( $pinyin->abbr($string ));
		return [ empty($py) ? '#' : $py[0],$tag];

	}

	/*
	 * 发送时间转换
	 * @param $timestamps string
	 */
	public function timeSwitch($timestamps)
	{
		$timezero = strtotime(date('Y-m-d',time()));
		$content = date('m-d H:i',$timestamps);

		//前天
		if( $timestamps >= $timezero - 60*60*24*2){
			$content = '前天 '.date('H:i',$timestamps);
		}

		// 昨天
		if( $timestamps >= $timezero - 60*60*24  ){
			$content = '昨天 '.date('H:i',$timestamps);
		}

		//今天
		if( $timestamps >= $timezero ){
			$content = date('H:i',$timestamps);
		}

		return $content;


	}











}