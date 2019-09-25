<?php
namespace app\api\controller\user;

use think\Controller;
use think\Requset;
use think\Db;
use think\facade\Cache;
use app\api\controller\Send;
use Zhenggg\Huyi\EasyHuyi;
use app\api\controller\token\Token;
use app\api\controller\Api;

class User extends Api
{
	use Send;

	// 注册用户clientID
	public function saveClientId()
	{
		$cid = input('cid');
		$version = input('version');
		$token = input('token');
		$vendor = strtoupper(input('vendor'));
		
		//判断设备是否已经注册
		$res = Db::name('app_device_register')->where('client_id',$cid)->field('id')->find();

		if(empty($res)){
			//未注册则进行注册
			$res = Db::name('app_device_register')->insertGetId(['client_id'=>$cid,'version'=>$version,'token'=>$token,'vendor'=>$vendor,'register_time'=>date('Y-m-d H:i:s')]);
		}else{
			$res = $res['id'];
		}
		self::returnMsg(200,'成功',$res);
		
	}

	// 账号登录
	public function login()
	{
		
		//检测登录数据
		// $this->checkSms( input('') );

		$params = [
			'account'=>input('account'),
			'password'=>input('password'),
		];

		$deviceid = input('deviceId');
		$clientid = input('clientId');

		$request = curl_request( config('mp_login') , $params );
		$data = json_decode($request,true);

		if( $data['code']==200 ){
			$login = $data['data'];
			$cid = $login['id'];
			// 查询详细信息
			$login = Db::name('user')->alias('U')->where('U.id',$cid)->join('hospital HOS','U.hospital_id = HOS.id and U.roleid in (7,11)','left')->join('company COM','U.company_id = COM.id and U.roleid = 6','left')->join('supplier SUP','U.supplier_id = SUP.id and U.roleid = 9','left')->join('dept DEP','U.wjw_dept_id = DEP.id and U.roleid = 8','left')->join('role RO','U.roleid = RO.id')->fieldRaw('U.*,IFNULL(HOS.name,IFNULL(COM.name,IFNULL(SUP.name,DEP.simplename))) as comp_name,RO.name as role_name')->find();
		}else{
			$login = [];
		}
		
		if( !empty($login) )
		{
			$userid = '';	//机构id
			
			$rolename = $login['role_name'];
			$username = $login['name'];
			$company = empty($login['comp_name'])?'':$login['comp_name'];
			$avatar = $login['avatar'];
			//医疗机构
			if( $login['roleid'] == 7){
				$userid = $login['hospital_id'];	//医院id

			}
			//经营企业
			if( $login['roleid'] == 6){
				$userid = $login['company_id'];		//经营企业id
			}
			//临床科室
			if( $login['roleid'] == 11){
				$userid = $login['hospital_id'];		//医院id
			}
			
			//注册用户(未身份认证)
			if( $login['roleid'] == 12){
				$userid = $login['company_id'];		//企业id为空
			}

			//生产企业
			if( $login['roleid'] == 9 ){
				$userid = $login['supplier_id'];		//生产企业id
			}

			// 卫健委
			if( $login['roleid'] == 8 ){
				$userid = $login['wjw_dept_id'];
			}

			// 超级管理员
			if( $login['roleid'] == 1 ){
				$userid = $login['deptid'];
			}

			
			//检查账号类型和状态 
			$this->checkAccount($login);

			// 生成登录缓存信息token
			$token = Token::token(['id'=>$login['id'],'device_id'=>$deviceid]);

			// 用户绑定设备
			DB::name('user')->where('id',$login['id'])->update(['app_register_id'=>$clientid]);

			$backData = [
				'id'	=>	$login['id'],	//账号ID
				'roleid' =>	$login['roleid'],
				'userid' => $userid,
				'rolename' => $rolename,
				'username' => $username,
				'comp_name' => $company,
				'avatar' =>config('image_url').'avatar/'.$avatar,
				'token' => $token
			];
			
			self::returnMsg(200,'登录成功',$backData);
			
		}else{
			self::returnMsg(201,'账号或密码错误');
		}


	}

	/*
	 * 检查账号类型和状态
	 * @params array $account 
	 */
	
	protected function checkAccount( $account=[] )
	{
		if( !in_array((int)$account['roleid'] , [1,6,7,8,9,11,12]) ){
			self::returnMsg(201,'该类型账号暂未开放');
		}
		if( $account['status'] == 2 ){
			self::returnMsg(201,'该账号已冻结');
		}
		if( $account['status'] == 3 ){
			self::returnMsg(201,'该账号不存在');
		}
	}

	/*
	 * 验证短信验证码是否有效
	 * @params array $account 
	 */
	private function checkSms( $inputs=[] )
	{
		$smsCode = $inputs['smsCode'];
		$session_name = "feiruideApp".$inputs['phone'];
		$session_value = Cache::get($session_name);
		
		if( !$session_value ){
			self::returnMsg(201,'验证码已失效');
		}

		if( $session_value['mobile_code'] != $smsCode ){
			self::returnMsg(201,'验证码错误');
		}

	}

	// 查询app最新版本
	public function checkVersion()
	{
		$res = Db::name('settings')->where('from',1)->find();
		self::returnMsg(200,'',$res);

	}

	// 下载app
	public function downloadApp()
	{
		$filename = 'C:/phpStudy/PHPTutorial/WWW/yxt.apk';

		$file = fopen($filename, 'r');

		Header("Expires: 0");

		Header("Pragma: public");

		Header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

		Header("Cache-Control: public");

		Header("Content-Length: ". filesize($filename));

		Header("Content-Type: application/octet-stream");

		Header("Content-Disposition: attachment; filename=yaoxietong.apk");

		readfile($filename);
	}

	/*
	 * 发送短信验证码
	 */
	public function sendSms()
	{
		$config = [
			'APIID' => 'C38084286',
    		'APIKEY' => '43dac5467cf5fac78a6908e828944b5f',
		];

		$mobile_code = $this->random(4,1); //生成短信验证码
		$mobile = input('mobile');	
		$content = "您的验证码是：".$mobile_code."。请不要把验证码泄露给其他人。"; 
		$this->defendSms( $mobile );

		//发送短信验证码/通知
		$huyi = new EasyHuyi( $config );
		$getSms = $huyi->sms()->content($content)->send($mobile);

		if( $getSms['code'] == 4085 )
		{
			self::returnMsg(201,'此手机号已超过5条,请明天再试');
		}

		if( $getSms['code'] == 2 )
		{
			//设置cahce缓存
			$session_name = "feiruideApp".$mobile;
			$params = [
				'mobile'	=>	$mobile,
				'mobile_code'	=>	$mobile_code,
				'sms_send_time'	=>	time()
			];
			cache($session_name,$params,90);
			self::returnMsg(200,'发送成功');
		}else{
			self::returnMsg(201,'发送失败,请稍后再试');
		}
	}

	/*
	 * 限制频繁盗刷短信
	 */
	private function defendSms($mobile)
	{
		$session_name = 'feiruideApp'.$mobile;
		$session_value = Cache::get($session_name);
		if( $session_value && ($session_value['sms_send_time'] + 10 ) > time() )
		{
			self::returnMsg(201,'操作过于频繁');
		}
	}

	/*
	 * 获取随机整数
	 */ 
	private function random($length = 6 , $numeric = 0) {
		PHP_VERSION < '4.2.0' && mt_srand((double)microtime() * 1000000);
		if($numeric) {
			$hash = sprintf('%0'.$length.'d', mt_rand(0, pow(10, $length) - 1));
		} else {
			$hash = '';
			$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789abcdefghjkmnpqrstuvwxyz';
			$max = strlen($chars) - 1;
			for($i = 0; $i < $length; $i++) {
				$hash .= $chars[mt_rand(0, $max)];
			}
		}
		return $hash;
	}


	



}