<?php
namespace app\api\controller;

use think\Controller;
use think\Request;
use app\api\controller\Send;
use app\api\controller\Oauth;
use Zhenggg\Huyi\EasyHuyi; 

/**
 * api 入口文件基类，需要控制权限的控制器都应该继承该类
 */
class Api
{	
	use Send;
	/**
     * @var \think\Request Request实例
     */
    protected $request;

    protected $clientInfo;
 

	/**
	 * 构造方法
	 * @param Request $request Request对象
	 */
	public function __construct(Request $request)
	{
		$this->request = $request;
		$this->init();
		
	}

	/**
	 * 初始化
	 * 检查请求类型，数据格式等
	 */
	public function init()
	{	

		//所有ajax请求的options预请求都会直接返回200，如果需要单独针对某个类中的方法，可以在路由规则中进行配置
		if($this->request->isOptions()){
			self::returnMsg(200,'success');
		}

		
		//配置不要鉴权的方法白名单
		if(!in_array(strtolower($this->request->module()).'/'.strtolower($this->request->controller()).'/'.strtolower($this->request->action()),config('allow_method'))){
			
			//授权处理
			$oauth = app('app\api\controller\Oauth');   //tp5.1容器，直接绑定类到容器进行实例化
    		return $this->clientInfo = $oauth->authenticate();
		}

	}

	 // 短信发送
    public function sms($content,$mobile)
    {
        $config = [
            'APIID' => 'C38084286',
            'APIKEY' => '43dac5467cf5fac78a6908e828944b5f',
        ];
        //发送短信验证码/通知
        $huyi = new EasyHuyi( $config );
        $getSms = $huyi->sms()->content($content)->send($mobile);
    }

	/**
	 * 空方法
	 */
	public function _empty()
    {
        self::returnMsg(404, 'empty method!');
    }
}