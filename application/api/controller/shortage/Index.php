<?php
namespace app\api\controller\shortage;

use think\Controller;
use think\Request;
use app\api\controller\Send;
use app\api\controller\Api;
use Zhenggg\Huyi\EasyHuyi; 
use think\Db;

// 短缺药品相关业务操作
class Index extends Api
{
	use Send;

	// 查询当前有效的短缺药品
	public function getShortage()
	{
		$params = input('');
		$pageIndex = $params['pageIndex'];

		if( isset( $params['key'] ) ){
			//筛选查询
			if( !empty($params['drugName']) ){
				$condition[] =['ds.drug_name','like','%'.$params['drugName'].'%' ];
			}

			if( !empty($params['hosName']) ){
				$condition[] =['hs.name','like','%'.$params['hosName'].'%' ];
			}
			
		}

		$condition[] = ['ds.state','in',[0,3]];
		$condition[] = ['ds.delete_flag','=',1];

		$res = Db::name('drug_shortage')->alias('ds')->where( $condition )->join('hospital hs','ds.hospital_id = hs.id')->field('ds.*,hs.name')->order('ds.close_time asc')->limit( 0,30*$pageIndex)->select();

		$more = Db::name('drug_shortage')->alias('ds')->where( $condition )->join('hospital hs','ds.hospital_id = hs.id')->field('ds.*,hs.name')->order('ds.close_time asc')->limit( ($pageIndex * 30),30)->select();
		//组合数据
		foreach ($res as $key => $value) {
			$res[$key]['sign'] = false;
		}

		if( empty($more) ){
			$more = '1';
		}else{
			$more= '2';
		}
		self::returnMsg(200,$more,$res);

	}

	// 查询当前有效的短缺药品
	public function getMyShortage()
	{
		$params = input('');
		$pageIndex = $params['pageIndex'];
		$hospital_id = $params['hospitalId'];

		if( isset( $params['key'] ) ){
			//筛选查询
			if( !empty($params['drugName']) ){
				$condition[] =['ds.drug_name','like','%'.$params['drugName'].'%' ];
			}

			if( $params['state'] !=5 ){
				$condition[] =['ds.state','=',$params['state'] ];
			}
			
		}
		$condition[] = ['ds.hospital_id','=',$hospital_id];
		$condition[] = ['ds.delete_flag','=',1];

		$res = Db::name('drug_shortage')->alias('ds')->where( $condition )->join('hospital hs','ds.hospital_id = hs.id')->field('ds.*,hs.name')->order('ds.create_time asc')->limit( 0,30*$pageIndex)->select();

		$more = Db::name('drug_shortage')->alias('ds')->where( $condition )->join('hospital hs','ds.hospital_id = hs.id')->field('ds.*,hs.name')->order('ds.create_time asc')->limit( ($pageIndex * 30),30)->select();
		//组合数据
		foreach ($res as $key => $value) {
			$res[$key]['sign'] = false;
			$res[$key]['choose'] = false;
		}

		if( empty($more) ){
			$more = '1';
		}else{
			$more= '2';
		}
		self::returnMsg(200,$more,$res);

	}

	// 查询短缺提供信息
	public function checkProvide()
	{
		$params = input('');
		
		if( $params['userFlag'] == 6 ){
			//如果是经营企业，则只显示本企业的提供
			$condition['dsp.shortage_id'] = $params['sid'];
			$condition['dsp.delete_flag'] = 1;
			$condition['dsp.c_or_h'] = $params['companyId'];
			$condition['dsp.role_id'] = 6;
			$res = Db::name('drug_shortage_provide')->alias('dsp')->where( $condition )->join('company com','dsp.c_or_h=com.id')->field('dsp.*,com.name')->select();
		}

		if( $params['userFlag'] == 7 ){
			//医院
			//角色为6的并表是 hospital 为7并表company
			$condition1['dsp.shortage_id'] = $params['sid'];
			$condition1['dsp.delete_flag'] = 1;

			$res1 = Db::name('drug_shortage_provide')->alias('dsp')->where( $condition1 )->where(['dsp.role_id'=>6])->join('company com','dsp.c_or_h=com.id')->field('dsp.*,com.name')->select();

			$res2 = Db::name('drug_shortage_provide')->alias('dsp')->where( $condition1 )->where(['dsp.role_id'=>7])->join('hospital hos','dsp.c_or_h=hos.id')->field('dsp.*,hos.name')->select();

			$res = array_merge($res1,$res2);
			$res = arraySort($res,'create_time');
		}

		//组合数据
		foreach ($res as $key => $value) {
			$res[$key]['sign'] = false;
		}
		$more = '1';
		self::returnMsg(200,$more,$res);
	}

	// 查询是否提供药品
	public function isProvide()
	{
		$params = input('');
		$condition['shortage_id'] = $params['sid'];
		$condition['c_or_h'] = $params['companyId'];
		$condition['delete_flag'] = 1;
		$condition['role_id'] = $params['roleId'];
		
		$res = Db::name('drug_shortage_provide')->where( $condition )->find();

		if( !empty($res) ){
			self::returnMsg(201,'你已提供该短缺药品');
		}else{
			self::returnMsg(200,'success');
		}
	}

	// 查询我的提供
	public function getMyProvide()
	{
		$params = input('');
		//echo json_encode($params);exit;
		$pageIndex = $params['pageIndex'];
		if( isset($params['key']) ){
			//筛选查询
			if( !empty($params['hosName']) ){
				$hosSql = ' AND DS.hos_name LIKE "%'.$params['hosName'].'%" ';

			}else{
				$hosSql = '';

			}

			if( !empty($params['drugName']) ){
				$drugSql = ' AND dsp.drug_name LIKE "%'.$params['drugName'].'%" ';

			}else{
				$drugSql = '';

			}

			if( $params['state'] != 2 ){
				$stateSql = ' AND dsp.state = '.$params['state'];

			}else{
				$stateSql = '';

			}

			$sql = 'SELECT * FROM drug_shortage_provide AS dsp LEFT JOIN ( SELECT ds.id AS ds_id,hos.`name` AS hos_name,ds.is_appoint_drug,ds.is_appoint_supplier FROM drug_shortage AS ds INNER JOIN hospital AS hos ON ds.hospital_id=hos.`id` WHERE ds.delete_flag=1) AS DS ON dsp.shortage_id=DS.ds_id WHERE dsp.delete_flag=1 AND dsp.role_id = ? AND dsp.c_or_h=? '.$hosSql.$drugSql.$stateSql.' limit 0,'.($pageIndex*30).';';

			$moreSql = 'SELECT * FROM drug_shortage_provide AS dsp LEFT JOIN ( SELECT ds.id AS ds_id,hos.`name` AS hos_name,ds.is_appoint_drug,ds.is_appoint_supplier FROM drug_shortage AS ds INNER JOIN hospital AS hos ON ds.hospital_id=hos.`id` WHERE ds.delete_flag=1) AS DS ON dsp.shortage_id=DS.ds_id WHERE dsp.delete_flag=1 AND dsp.role_id = ? AND dsp.c_or_h=? '.$hosSql.$drugSql.$stateSql.' limit '.($pageIndex*30).',30;';

			$res = Db::query( $sql , [ $params['roleId'] , $params['companyId'] ] );
			$more = Db::query( $moreSql , [ $params['roleId'] , $params['companyId'] ] );

		}else{
			//系统查询
			$condition['dsp.role_id'] = $params['roleId'];
			$condition['dsp.c_or_h'] = $params['companyId'];
			$condition['dsp.delete_flag'] = 1;
			$res = Db::name('drug_shortage_provide')->alias('dsp')->join('drug_shortage ds','dsp.shortage_id=ds.id')->where( $condition )->field('dsp.*,ds.is_appoint_drug,ds.is_appoint_supplier')->limit(0,$pageIndex*30)->select();

			$more = Db::name('drug_shortage_provide')->alias('dsp')->join('drug_shortage ds','dsp.shortage_id=ds.id')->where( $condition )->field('dsp.*,ds.is_appoint_drug,ds.is_appoint_supplier')->limit( $pageIndex*30,30)->select();

		}
		//组合数据
		foreach ($res as $key => $value) {
			$res[$key]['sign'] = false;
			$res[$key]['choose'] = false;
		}

		if( empty($more) ){
			$more = '1';
		}else{
			$more= '2';
		}
		self::returnMsg(200,$more,$res);

	}

	//改变我的提供（删除、确认配送）
	public function modifyProvide()
	{
		$params = input('');
		//操作来自删除
		if( $params['sign'] == 1 ){
			$condition['id'] = $params['pid'];
			$res = Db::name('drug_shortage_provide')->where( $condition )->update(['delete_flag'=>0]);
			if( $res ){
				//改变发布状态
				$short = Db::name('drug_shortage')->where(['id'=>$params['sid']])->find();
				$feedback_count = $short['feedback_count'];
				$state = $short['state'];
				$data['feedback_count'] = $feedback_count > 0 ? ($feedback_count-1) : 0;
				switch ( (int)$state ) {
					case 3:
						if( $feedback_count == 1 ){
							$data['state'] = 0;
						}
						break;
				}

				Db::name('drug_shortage')->where(['id'=>$params['sid']])->update( $data );
				self::returnMsg(200,'删除成功');
			}else{
				self::returnMsg(201,'删除失败');
			}
		}

		// 操作来自确认配送
		if( $params['sign'] == 2 ){
			$condition['id'] = $params['pid'];
			$data['delivery_info'] = $params['deliveryInfo'];
			$data['state'] = 2;
			$res =Db::name('drug_shortage_provide')->where( $condition )->update( $data );

			if( $res ){
				self::returnMsg(200,'确认成功');

			}else{
				self::returnMsg(201,'确认失败');

			}
		}



	}

	// 查询我的提供的发布源
	public function checkReFromPro()
	{
		$params = input('');
		$res = Db::name('drug_shortage')->alias('ds')->join('hospital hos','ds.hospital_id=hos.id')->where([ 'ds.id' => $params['sid'] ])->field('ds.*,hos.name')->find();
		if( $res ){
			self::returnMsg(200,'success',$res);

		}else{
			self::returnMsg(201,'fail');
		}
	}

	//修改我的提供提交
	public function proEditSubmit()
	{
		$params = input('');
		$condition['id'] = $params['editId'];
		$data['drug_name'] = $params['drugName'];
		$data['drug_spec'] = $params['drugSpec'];
		$data['drug_pack'] = $params['drugPack'];
		$data['supplier_name'] = $params['drugSupplier'];
		$data['price'] = $params['drugPrice'];
		$data['count'] = $params['drugCount'];
		$data['phone'] = $params['drugPhone'];
		$data['content'] = $params['drugMark'];
		$data['update_time'] = date('Y-m-d H:i:s');
		$res = Db::name('drug_shortage_provide')->where( $condition )->update( $data );
		if( $res ){
			self::returnMsg(200,'修改成功');
		}else{
			self::returnMsg(201,'修改失败');
		}
	}

	// 确认采购短缺
	public function sureBuy()
	{
		$params = input('');
		$condition['id'] = $params['pid'];
		$res = Db::name('drug_shortage_provide')->where( $condition )->update(['state'=>1]);

		if( $res ){
			//发送短信给提供人
			$content = '短缺药品：'.$params['hos_name'].'已采纳你提交的 '.$params['drugname'].' 供货信息。';
			//$this->sms($content,$params['phone']);

			self::returnMsg(200,'操作成功');

		}else{
			self::returnMsg(201,'操作失败');

		}
	}

	//获取挂网药品
	public function proNetDrug()
	{
		$params = input('');
		$pageIndex = $params['pageIndex'];
		$searchCondition = [];

		if( isset($params['value']) ){
			$searchCondition[] =['generic_name','like','%'.$params['value'].'%'];
			$searchCondition[] =['generic_name_pinyin','like','%'.strtoupper($params['value']).'%'];
		}

		$res = Db::table('drug_system')->where( function($query){
				$query->where(['state'=>1,'source'=>1]);

			})->where( function($query) use ($searchCondition){
				$query->whereOr($searchCondition);
				
			} )->limit( 30*($pageIndex-1),30)->select();

		self::returnMsg(200,'success',$res);
	}


	// 短缺供应信息提交
	public function proSubmit()
	{
		$params = input('');
		$data['user_id'] = $params['userId'];
		$data['role_id'] = $params['roleId'];
		$data['c_or_h'] = $params['companyId'];
		$data['shortage_id'] = $params['shortId'];
		$data['drug_name'] = $params['drugName'];
		$data['drug_spec'] = $params['drugSpec'];
		$data['drug_pack'] = $params['drugPack'];
		$data['supplier_name'] = $params['drugSupplier'];
		$data['price'] = $params['drugPrice'];
		$data['count'] = $params['drugCount'];
		$data['phone'] = $params['drugPhone'];
		$data['content'] = $params['drugMark'];
		$data['create_time'] = date('Y-m-d H:i:s');
		//插入数据库
		$res = Db::name('drug_shortage_provide')->insert( $data );
		if( $res ){
			//改变短缺状态
			$result = Db::name('drug_shortage')->where([ 'id' => $params['shortId'] ])->find();
			if( $result['state'] === 0 ){
				$data1['state'] = 3;
			}
			$data1['feedback_count'] = $result['feedback_count'] + 1;
			Db::name('drug_shortage')->where([ 'id' => $params['shortId'] ])->update( $data1 );

			//发送短信
			//查询企业名称
			if( $params['roleId'] == 7 ){
				$p = Db::name('hospital')->where([ 'id' => $params['companyId'] ])->find();
			}else{
				$p = Db::name('company')->where([ 'id' => $params['companyId'] ])->find();
			}
			$content = '短缺药品：'.$p['name'].' 提供了你发布的短缺药品'.' '.$params['shortName'].' '.'，快去查看吧。';
			//$this->sms($content,$params['mobile']);

			self::returnMsg(200,'提交成功');

		}else{
			self::returnMsg(201,'提交失败');

		}

	}

	// 短缺发布信息提交
	public function reSubmit()
	{
		$params = input('');
		$data['user_id'] = $params['userId'];
		$data['hospital_id'] = $params['companyId'];
		$data['drug_name'] = $params['drugName'];
		$data['drug_spec'] = $params['drugSpec'];
		$data['drug_pack'] = $params['drugPack'];
		$data['supplier_name'] = $params['drugSupplier'];
		$data['drug_count'] = $params['drugCount'];
		$data['phone'] = $params['drugPhone'];
		$data['content'] = $params['drugMark'];
		$data['is_appoint_drug'] = $params['isLimitDrug'];
		$data['is_appoint_supplier'] = $params['isLimitSup'];
		$data['close_time'] = $params['closeTime'];
		$data['create_time'] = date('Y-m-d H:i:s');
		if( !empty( $params['drugNetPrice']) ){
			$data['net_price'] = $params['drugNetPrice'];
		}
		//插入数据库
		$res = Db::name('drug_shortage')->insert( $data );
		if( $res ){
			//发送短信
			//查询医院名称
			$p = Db::name('hospital')->where([ 'id' => $params['companyId'] ])->find();
			$content = '短缺药品需求：'.$p['name'].' 刚刚发布了新的药品'.' '.$params['drugName'].' 需求信息，快去短缺药品看看吧！';

			//获取经营期企业手机号码集合
			$condition[] = ['roleid','=',6];
			$condition[] = ['company_id','<>',$params['companyId']];
			$condition[] = ['phone','<>',''];
			$mobile = Db::name('user')->where( $condition )->group('company_id')->order('last_login_time desc')->field('phone')->select();
			//处理电话号码
			$arr = [];
			foreach ($mobile as $key => $value) {
				if( preg_match('/^1[0-9]{10}/', $value['phone']) ){
					$arr[] = $value['phone'];
				}
			}
			$mobile = implode(',', $arr);
			//$this->sms($content,$mobile);

			self::returnMsg(200,'发布成功');

		}else{
			self::returnMsg(201,'系统错误，发送失败');

		}

	}

	// 短缺发布信息修改提交
	public function reEditSubmit()
	{
		$params = input('');
		$data['drug_name'] = $params['drugName'];
		$data['drug_spec'] = $params['drugSpec'];
		$data['drug_pack'] = $params['drugPack'];
		$data['supplier_name'] = $params['drugSupplier'];
		$data['drug_count'] = $params['drugCount'];
		$data['phone'] = $params['drugPhone'];
		$data['content'] = $params['drugMark'];
		$data['is_appoint_drug'] = $params['isLimitDrug'];
		$data['is_appoint_supplier'] = $params['isLimitSup'];
		$data['close_time'] = $params['closeTime'];
		$data['update_time'] = date('Y-m-d H:i:s');
		if( !empty( $params['drugNetPrice']) ){
			$data['net_price'] = $params['drugNetPrice'];
		}
		$condition['id'] = $params['sid'];
		//插入数据库
		$res = Db::name('drug_shortage')->where( $condition )->update( $data );
		if( $res ){
			self::returnMsg(200,'修改成功');

		}else{
			self::returnMsg(201,'修改失败');

		}

	}

	// 关闭或删除短缺需求
	public function closeShortage()
	{
		$params = input('');
		$condition['id'] = $params['sid'];
		// 关闭需求
		if( $params['sign'] == 1 ){
			$data['state'] = 1;
			$data['update_time'] = date('Y-m-d H:i:s');
			$res = Db::name('drug_shortage')->where( $condition )->update( $data );
			if( $res ){
				self::returnMsg(200,'关闭成功');
			}else{
				self::returnMsg(201,'关闭失败');
			}	
		}

		//删除需求
		if( $params['sign'] == 2  ){
			$data['delete_flag'] = 0;
			$data['delete_time'] = date('Y-m-d H:i:s');
			$data['update_time'] = $data['delete_time'];
			$res = Db::name('drug_shortage')->where( $condition )->update( $data );
			if( $res ){
				self::returnMsg(200,'删除成功');
			}else{
				self::returnMsg(201,'删除失败');
			}	
		}
		
	}


















}