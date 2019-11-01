<?php
namespace app\api\controller\user;

use think\Controller;
use think\Requset;
use think\Db;

class Other extends Controller
{
	// app下载引导页
	public function shareApp()
	{
		// 查询app版本
		$res = Db::name('settings')->where('from',1)->field('app_version')->find();
		$this->assign('version',$res['app_version']);
		return $this->fetch('/shareApp');
	}












}