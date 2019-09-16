<?php
namespace app\api\controller;

use app\api\controller\Send;
use think\Exception;
use think\facade\Request;
use think\facade\Cache;
use think\Db;

/**
 * API鉴权验证
 */
class Oauth
{
    use Send;
    
    /**
     * accessToken存储前缀
     *
     * @var string
     */
    public static $accessTokenPrefix = 'accessToken_';

    /**
     * 过期时间秒数
     *
     * @var int
     */
    public static $expires = 60*60*24*30;

    /**
     * 认证授权 通过用户信息和路由
     * @param Request $request
     * @return \Exception|UnauthorizedException|mixed|Exception
     * @throws UnauthorizedException
     */
    final function authenticate()
    {     
        self::certification(self::getClient());

    }

    /**
     * 获取用户信息
     * @param Request $request
     * @return $this
     * @throws UnauthorizedException
     */
    public static function getClient()
    {   
        //获取头部信息
        try {
			
            $authorization = Request::header('auth');   //获取请求中的auth字段
            $authorization = explode(" ", $authorization);        //explode分割，获取后面一窜base64加密数据
            $authorizationInfo  = explode(".",$authorization[1]);  

            $clientInfo['client'] = json_decode(base64_decode($authorizationInfo[1]),true);
            $clientInfo['expires_time'] = base64_decode($authorizationInfo[0]);
           
            return $clientInfo;

        } catch (Exception $e) {
            self::returnMsg(203,'未验证权限');

        }
    }

    /**
     * 获取用户信息后 验证权限
     * @return mixed
     */
    public static function certification($data = []){
		
        $getCacheAccessToken = Cache::get(self::$accessTokenPrefix . $data['client']['id']);  //获取用户缓存信息

        //没有登录信息或已经过期
        if(empty($getCacheAccessToken) || $data['expires_time'] < time() ){
            self::returnMsg(203,'登录已失效,请重新登录');
        }

        // 信息存在，验证设备是否统一，不统一则被剔除
        if($getCacheAccessToken['client']['device_id'] != $data['client']['device_id']){
            self::returnMsg(203,'当前账号已在其他设备登录,请重新登录');
        }

        self::bindDevice();


        return $data;
    }

    /*
     * 绑定用户设备
     */
    
    public static function bindDevice()
    {
        $uid =input('device_uid');
        $cid = input('device_cid');

        if( !empty($uid) && !empty($cid) ){
            //未绑定，则进行绑定
            $update = Db::name('user')->where('id',$uid)->update(['app_register_id'=>$cid]);
        }

        return ;

    }


}