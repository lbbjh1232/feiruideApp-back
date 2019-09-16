<?php
namespace app\api\controller\report;

use think\Controller;
use think\Request;
use app\api\controller\Send;
use app\api\controller\Api;
use think\Db;

// 药检报告
class Index extends Api
{
	use Send;

	// 获取所有的药检报告列表
	public function reportList()
	{
		$params = input('');
		$pageIndex = $params['pageIndex'];

		$mySql = '';
		if(isset($params['companyid'])){
			$mySql = ' and DR.role_id = "'.$params['roleid'].'" and DR.dept_id = "'.$params['companyid'].'"';
		}

		// 筛选判断
		$nameSql = '';
		if( isset($params['drugName']) && !empty($params['drugName']) ){
			$nameSql = ' and (DS.generic_name like "%'.$params['drugName'].'%" or DS.generic_name_pinyin like "%'.strtoupper($params['drugName']).'%") ';
		}

		$specSql = '';
		if( isset($params['drugSpecifications']) && !empty($params['drugSpecifications']) ){
			$specSql = ' and DS.specifications like "%'.$params['drugSpecifications'].'%" ';
		}

		$prodSql = '';
		if( isset($params['drugProducer']) && !empty($params['drugProducer']) ){
			$prodSql = ' and (DS.production_enterprise like "%'.$params['drugProducer'].'%" or DS.production_enterprise_pinyin like "%'.strtoupper($params['drugProducer']).'%") ';
		}

		$batchSql = '';
		if( isset($params['drugBatchNumber']) && !empty($params['drugBatchNumber']) ){
			$batchSql = ' and DR.batch_number like "%'.$params['drugBatchNumber'].'%" ';
		}

		$approvedSql = '';
		if( isset($params['drugApprove']) && !empty($params['drugApprove']) ){
			$approvedSql = ' and DS.approved_by like "%'.$params['drugApprove'].'%" ';
		}

		$sql = "select REP.*,IFNULL(IFNULL(COM.name,HOS.name),SUP.name) as company_name from (select DR.*,DS.generic_name,DS.specifications,DS.system_number,DS.production_enterprise,DS.approved_by from drug_report as DR INNER JOIN drug_system as DS ON DR.drug_id = DS.id where DR.delete_flag = 1 ".$nameSql.$specSql.$prodSql.$batchSql.$approvedSql.$mySql." ) as REP LEFT JOIN company as COM ON REP.dept_id = COM.id and REP.role_id = 6 LEFT JOIN hospital as HOS ON REP.dept_id = HOS.id and REP.role_id = 7 LEFT JOIN supplier as SUP ON REP.dept_id = SUP.id and REP.role_id = 9 order by REP.created desc limit 0 ,".(30*$pageIndex);

		$moreSql = "select REP.*,IFNULL(IFNULL(COM.name,HOS.name),SUP.name) as company_name from (select DR.*,DS.generic_name,DS.specifications,DS.system_number,DS.production_enterprise,DS.approved_by from drug_report as DR INNER JOIN drug_system as DS ON DR.drug_id = DS.id where DR.delete_flag = 1 ".$nameSql.$specSql.$prodSql.$batchSql.$approvedSql.$mySql." ) as REP LEFT JOIN company as COM ON REP.dept_id = COM.id and REP.role_id = 6 LEFT JOIN hospital as HOS ON REP.dept_id = HOS.id and REP.role_id = 7 LEFT JOIN supplier as SUP ON REP.dept_id = SUP.id and REP.role_id = 9 order by REP.created desc limit ".(30*$pageIndex).",30";

		$res = Db::query($sql);
		$more = Db::query($moreSql);

		foreach ($res as $key => $value) {
			$res[$key]['picture_paths'] = $this->imageHandle( $value['picture_paths']);
			$res[$key]['sign'] = false;
		}

		if( empty($more) ){
			$more = '1';
		}else{
			$more= '2';
		}

		self::returnMsg(200,$more,$res);

	}

	// 组合图片路径
	protected function imageHandle($src)
	{
		if( !empty($src) ){
			$arrayImages = explode(',' , $src);
			$arr = [];
			foreach ($arrayImages as $key => $value) {
				$url = config('ticket_url').$value;

				if( stripos($value, '.pdf') !== false ){
					$arr[] = ['key'=>2,'url'=>$url,'save'=>$value];

				}else{
					$arr[] = ['key'=>1,'url'=>$url,'save'=>$value];

				}

			}
 			return $arr;

		}else{

			return [];
		}
	}

	// 获取要上传药检报告的本院或本经营企业或本生产企业的药品
	public function getReportDrug()
	{
		$params = input('');
		$pageIndex = $params['pageIndex'];

		$roleid = $params['roleid'];
		$companyid = $params['companyid'];

		// 如果是医疗机构
		if( $roleid == 7 ){
			$condition['DH.hospital_id'] = $companyid;
			$condition['DH.delete_flag'] = 2;

			$condition1 = [];
			if(isset($params['value']) && !empty($params['value']) ){

				$condition1[] = ['DS.generic_name','like','%'.$params['value'].'%'];
				$condition1[] = ['DS.generic_name_pinyin','like','%'.strtoupper($params['value']).'%'];

			}
			$res = Db::name('drug_hospital')->alias('DH')->where( $condition )->join('drug_system DS','DS.system_number = DH.drug_number')->field('DS.*')->where( $condition )->where(function($query) use ($condition1){

					$query->whereOr($condition1);

			})->limit( ($pageIndex - 1)*30 , 30 )->select();
		}

		// 如果是经营企业
		if( $roleid == 6 ){
			$condition['DSE.enterprise_id'] = $companyid;
			$condition['DSE.delete_flag'] = 2;

			$condition1 = [];
			if(isset($params['value']) && !empty($params['value']) ){

				$condition1[] = ['DS.generic_name','like','%'.$params['value'].'%'];
				$condition1[] = ['DS.generic_name_pinyin','like','%'.strtoupper($params['value']).'%'];

			}
			$res = Db::name('drug_sales_enterprise')->alias('DSE')->join('drug_system DS','DSE.drug_number = DS.system_number')->field('DS.*')->where( $condition )->where(function($query) use ($condition1){

					$query->whereOr($condition1);

			})->limit( ($pageIndex - 1)*30 , 30 )->select();

		}

		// 如果是生产企业
		if( $roleid == 9 ){
			$condition['supplier_id'] = $companyid;
			$condition['source'] = 1;
			$condition['state'] = 1;

			$condition1 = [];
			if( isset($params['value']) && !empty($params['value']) ){

				$condition1[] = ['generic_name','like','%'.$params['value'].'%'];
				$condition1[] = ['generic_name_pinyin','like','%'.strtoupper($params['value']).'%'];

			}
			$res = Db::name('drug_system')->where( $condition )->where(function($query) use ($condition1){

					$query->whereOr($condition1);

			})->limit( ($pageIndex - 1)*30 , 30 )->select();

		}
		
		self::returnMsg(200,'',$res);

	}

	// 添加、修改药检报告
	public function addReport()
	{
		$params = input('');
		if( isset($params['eid']) ){
			// 修改
			$data['batch_number'] = $params['batch'];
			$data['picture_paths'] = $this->urlSplice($params['report']);
			$data['updated'] = date('Y-m-d H:i:s');
			$condition['id'] = $params['eid'];

			try{
				$res = Db::name('drug_report')->where( $condition )->update( $data );

			}catch(\Exception $e){

				self::returnMsg(201,'系统已存在该批号的药检报告');

			}

			self::returnMsg(200,'修改成功');

		}else{

			$data['user_id'] = $params['userid'];
			$data['role_id'] = $params['roleid'];

			$data['dept_id'] = $params['companyid'];
			$data['drug_id'] = $params['drugid'];

			$data['batch_number'] = $params['batch'];
			$data['picture_paths'] = $this->urlSplice($params['report']);

			$data['created'] = date('Y-m-d H:i:s');
			$data['updated'] = date('Y-m-d H:i:s');

			// 添加
			try{
				$res = Db::name('drug_report')->insert( $data );

			}catch(\Exception $e){

				self::returnMsg(201,'系统已存在该批号的药检报告');

			}

			self::returnMsg(200,'添加成功');
		}
	}

	// 获取要修改的药检报告信息
	public function getSingleReport()
	{
		$params = input('');
		$condition['DR.id'] = $params['id'];

		$res = Db::name('drug_report')->alias('DR')->where( $condition )->join('drug_system DS','DS.id = DR.drug_id')->field('DR.*,DS.generic_name')->find();
		$res['picture_paths'] = $this->imageHandle1($res['picture_paths']);
		self::returnMsg(200,'',$res);
	}

	// 删除药检报告
	public function delReport()
	{
		$params = input('');
		$condition['id'] = $params['id'];

		$res = Db::name('drug_report')->where( $condition )->update(['delete_flag'=>0,'deleted'=>date('Y-m-d H:i:s')]);

		if( $res ){
			self::returnMsg(200,'删除成功');
		}else{
			self::returnMsg(201,'删除失败');
		}
	}

	/*
	 * 图片拼接组合
	 * @params Array $images
	 * @return String $str
	 */
	protected function urlSplice($images)
	{
		$str = '';
		$count = count($images);
		if( is_array($images) ){
			foreach ($images as $key => $value) {
				if( $key == $count-1 ){
					$str .= $value['save'];
				}else{
					$str .= $value['save'].',';
				}
			}
		}
		return $str;
	}

	// 图片路径分解
	protected function imageHandle1($src)
	{
		if( !empty($src) ){
			$arrayImages = explode(',' , $src);
			$arr = [];

			foreach ($arrayImages as $key => $value) {
				if( stripos($value, '.pdf') !== false ){
					// pdf
					$arr[] = [
						'uid'  => $key,
						'url'  => $value,
						'key'  => 2
					];
				}else{
					// 非pdf
					$arr[] = [
						'uid'  => $key,
						'url'  => $value,
						'key'  => 1
					];
					
				}
			}
 			return $arr;

		}else{

			return [];
		}
	}


}