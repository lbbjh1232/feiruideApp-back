<?php
namespace app\api\validate;

use think\Validate;
/**
 * 生成token参数验证器
 */
class Token extends Validate
{
	
	protected $rule = [
        'wxCode'       =>  'require',
        'timestamp'   =>  'number|require'
    ];

    protected $message  =   [
        'wxCode.require'    => '登录验证码不能为空',
        'timestamp.number' => '时间戳格式错误' 
    ];
}