<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件


/*
 *  curl请求函数
 *	@params $url：访问的URL
 *  @params $post post数据(不填则为GET)，
 *  @params $cookie 提交的$cookies,
 *  @params $returnCookie 是否返回$cookies
 */
 function curl_request($url,$post='',$cookie='',$returnCookie=0){ 
	  $curl = curl_init();
	  curl_setopt($curl, CURLOPT_URL, $url);
	  curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
	  curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
	  curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
	  curl_setopt($curl, CURLOPT_REFERER, "https://test.yp.feiruide.cn");
	  if($post){
		 curl_setopt($curl, CURLOPT_POST, 1);
		 curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
	  }
	 if($cookie) {
		curl_setopt($curl, CURLOPT_COOKIE, $cookie);
	 }
	 curl_setopt($curl, CURLOPT_HEADER, $returnCookie);
	 curl_setopt($curl, CURLOPT_TIMEOUT, 10);
	 curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	 curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	 $data = curl_exec($curl);
	 if (curl_errno($curl)) {             return curl_error($curl);
	 }         curl_close($curl);
	 if($returnCookie){
		list($header, $body) = explode("\r\n\r\n", $data, 2);
		 preg_match_all("/Set\-Cookie:([^;]*);/", $header, $matches);             $info['cookie']  = substr($matches[1][0], 1);
		$info['content'] = $body;            return $info;
	}else{
		return $data;
	}
}


/**
 * 二维数组根据某个字段排序
 * @param array $array 要排序的数组
 * @param string $keys   要排序的键字段
 * @param string $sort  排序类型  SORT_ASC     SORT_DESC 
 * @return array 排序后的数组
 */
function arraySort($array, $keys, $sort = SORT_DESC) {
    $keysValue = [];
    foreach ($array as $k => $v) {
        $keysValue[$k] = $v[$keys];
    }
    array_multisort($keysValue, $sort, $array);
    return $array;
}

// 小程序获取access_token
function getAccessToken(){
	$appid = 'wxfbb16a7b5aea4afb';
	$appsecret = '17fcf8ad5193f640a22ab2899af9d824';
	$res = curl_request('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$appid.'&secret='.$appsecret);
	$res = json_decode($res,true);
	if( isset($res['errcode']) ){
		getAccessToken();
	}
	return $res['access_token'];
}

//设置首营上传命名规则
function buildFirstFileName($type){
		switch ( (int)$type ) {
			case 1:
				$prefix = '营业执照';
				break;
			case 2:
				$prefix = '生产或经营许可证';
				break;
			case 3:
				$prefix = 'GMPGSP证书';
				break;
			case 4:
				$prefix = '';
				break;
			case 5:
				$prefix = '质量保证协议';
				break;
			case 6:
				$prefix = '采购人员委托书';
				break;
			case 7:
				$prefix = '销售人员委托书' ;
				break;
			case 8:
				$prefix = '上一年企业年度报告';
				break;
			case 9:
				$prefix = '印章备案件';
				break;
			case 10:
				$prefix = '开户许可及开票资料';
				break;
			case 11:
				$prefix = '随货同行单（空白原单盖公章和出库专用章）';
				break;
			case 12:
				$prefix = '质量体系调查表';
				break;
			case 13:
				$prefix = '执业许可证';
				break;
			case 14:
				$prefix = '';
				break;
			case 21:
				$prefix = '药品注册批件及附件';
				break;
			case 22:
				$prefix = '生产企业营业执照';
				break;
			case 23:
				$prefix = '生产企业GMP';
				break;
			case 24:
				$prefix = '质量协议';
				break;
			case 25:
				$prefix = '包材备案批件';
				break;
			case 26:
				$prefix = '省检报告';
				break;
			case 27:
				$prefix = '包装、标签、说明书（实物）';
				break;
			case 28:
				$prefix = '物价批文';
				break;
			// 新药申报
			case 31:
				$prefix = '说明书';
				break;
			case 32:
				$prefix = '用药指南';
				break;
			case 33:
				$prefix = '药品申报信息表';
				break;
			case 34:
				$prefix = '新药申报承诺书';
				break;
			case 35:
				$prefix = '药品质量保证承诺书';
				break;
			case 36:
				$prefix = '廉洁准入承诺书';
				break;
			case 37:
				$prefix = '厂家委托说明书';
				break;
			
			default:
				$prefix = '默认自定义';
			
		}

		$filename = $prefix.'_'.md5(microtime(true));
		return $filename;

}

//获取中文首字母
function getfirstchar($s0){   
    $fchar = ord($s0{0});
    if($fchar >= ord("A") and $fchar <= ord("z") )return strtoupper($s0{0});
    $s1 = iconv("utf-8","gbk", $s0);
    $s2 = iconv("gbk","utf-8", $s1);
    if($s2 == $s0){$s = $s1;}else{$s = $s0;}
    $asc = ord($s{0}) * 256 + ord($s{1}) - 65536;
    if($asc >= -20319 and $asc <= -20284) return "A";
    if($asc >= -20283 and $asc <= -19776) return "B";
    if($asc >= -19775 and $asc <= -19219) return "C";
    if($asc >= -19218 and $asc <= -18711) return "D";
    if($asc >= -18710 and $asc <= -18527) return "E";
    if($asc >= -18526 and $asc <= -18240) return "F";
    if($asc >= -18239 and $asc <= -17923) return "G";
    if($asc >= -17922 and $asc <= -17418) return "H";
    if($asc >= -17922 and $asc <= -17418) return "I";
    if($asc >= -17417 and $asc <= -16475) return "J";
    if($asc >= -16474 and $asc <= -16213) return "K";
    if($asc >= -16212 and $asc <= -15641) return "L";
    if($asc >= -15640 and $asc <= -15166) return "M";
    if($asc >= -15165 and $asc <= -14923) return "N";
    if($asc >= -14922 and $asc <= -14915) return "O";
    if($asc >= -14914 and $asc <= -14631) return "P";
    if($asc >= -14630 and $asc <= -14150) return "Q";
    if($asc >= -14149 and $asc <= -14091) return "R";
    if($asc >= -14090 and $asc <= -13319) return "S";
    if($asc >= -13318 and $asc <= -12839) return "T";
    if($asc >= -12838 and $asc <= -12557) return "W";
    if($asc >= -12556 and $asc <= -11848) return "X";
    if($asc >= -11847 and $asc <= -11056) return "Y";
    if($asc >= -11055 and $asc <= -10247) return "Z";
    return '0';
   
}
//获取字符串中文首字母
function pinyin_long($zh){  //获取整条字符串所有汉字拼音首字母
    $ret = "";
    $s1 = iconv("utf-8","gbk", $zh);
    $s2 = iconv("gbk","utf-8", $s1);
    if($s2 == $zh){$zh = $s1;}
    for($i = 0; $i < strlen($zh); $i++){
        $s1 = substr($zh,$i,1);
        $p = ord($s1);
        if($p > 160){
            $s2 = substr($zh,$i++,2);
            $ret .= getfirstchar( iconv("gbk","utf-8", $s2) );
        }else{
            $ret .= $s1;
        }
    }
    return $ret;
}

/*
 * 判断一个中文字符
 */
function isOneChinese($string){
	$number=ord($string);//得到字符的ASCII码

	if($number>=45217&&$number<=55359)  {
	  	$china = false;

	}else{
	  	$china = true;

	}

	if(strlen($string) == 3 && $china ){
		return true;
	}else{
		return false;
	}
}

// 二维数组取差集
function array_diff_assoc2_deep($array1, $array2) {
    $ret = array();
    foreach ($array1 as $k => $v) {
        if (!isset($array2[$k])) $ret[$k] = $v;
        else if (is_array($v) && is_array($array2[$k])) $ret[$k] = array_diff_assoc2_deep($v, $array2[$k]);
        else if ($v !=$array2[$k]) $ret[$k] = $v;
        else
        {
            unset($array1[$k]);
        }
 
    }
    return $ret;
}