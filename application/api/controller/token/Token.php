<?php
namespace app\api\controller\token;

use think\Request;
use app\api\controller\Send;
use app\api\controller\Oauth;
use think\facade\Cache;

/**
 * 生成token
 */
class Token
{
	use Send;

	/**
	 * 请求时间差
	 */
	public static $timeDif = 10000;
	public static $accessTokenPrefix = 'accessToken_';
	public static $expires = 60*60*24*30;  //登录缓存信息时间一个月
	
	 
	/**
	 * 生成token
	 */
	public static function token($res)
	{
		
		try {
			$accessToken = self::setAccessToken($res);  
			return $accessToken;

		} catch (Exception $e) {
			self::returnMsg(201,'fail',$e);
			
		}
	}

	
	

	/**
     * 设置AccessToken
     * @param $clientInfo array 含用户id 、设备id 、
     * @return int
     */
    protected static function setAccessToken($clientInfo)
    {
        
        $accessTokenInfo = [
            'expires_time'  => time() + self::$expires,      //过期时间时间戳
            'client'        => $clientInfo,//用户信息
        ];
		$returnToUser = base64_encode($accessTokenInfo['expires_time']).'.'.base64_encode(json_encode($accessTokenInfo['client']));
        self::saveAccessToken($clientInfo['id'], $accessTokenInfo);  //保存本次token
        return $returnToUser; //返回给前端
    }

   
    /**
     * 生成AccessToken
     * @return string
     */
    protected static function buildAccessToken($lenght = 32)
    {
        //生成AccessToken
        $str_pol = "1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789abcdefghijklmnopqrstuvwxyz";
		return substr(str_shuffle($str_pol), 0, $lenght);

    }

    /**
     * 存储token
     * @param $uid 用户id
     * @param $accessTokenInfo 
     */
    protected static function saveAccessToken($uid, $accessTokenInfo)
    {
        //存储accessToken
        cache(self::$accessTokenPrefix . $uid, $accessTokenInfo, self::$expires);
    }

   
}