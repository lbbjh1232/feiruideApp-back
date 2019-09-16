<?php
namespace app\api\controller\drug;

use think\Controller;
use think\Request;
use app\api\controller\Send;
use think\Db;
use app\api\controller\Api;

/**
 * 本医院药品相关业务操作
 */

class HosDrug extends Api
{
	use Send;
	/*
	 * 获取本院药品列表
	 */
	public function index()
	{
		$condition = $this->paramsFilter(input(''));
		$pageIndex = (int)input('pageIndex');				//每次加载的条数，每次更新30条	
		$hospitalId = input('hospitalID');
		$ticketType = input('TicketTypeCurrentValue');
		
		if( !empty($ticketType) ){
			$condition[] = ['dh.votes_type','=',$ticketType];
		}

		$alias = [ 'drug_system'=>'ds','drug_hospital'=>'dh'];
		$join= [['drug_system','ds.system_number=dh.drug_number']];

		if( is_array($condition) ){
			$condition[]=['dh.hospital_id','=',$hospitalId];
			$where = $condition;
			//筛选判断
			$res = Db::table('drug_hospital')->alias($alias)->join($join)->where($where)->limit(0,$pageIndex * 30)->select();
			$more =Db::table('drug_hospital')->alias($alias)->join($join)->where($where)->limit($pageIndex * 30,30)->select();	//判断是否还有数据

		}elseif( !empty($condition) && !is_array($condition) ){

			//默认查询
			$sql = "SELECT * FROM ( select * from `drug_hospital` `dh` where `dh`.`hospital_id` = '".$hospitalId."' and `dh`.`delete_flag` = 2 ) as H INNER JOIN ( SELECT * FROM `drug_system` `ds` WHERE `ds`.`generic_name` LIKE '%".$condition."%' OR `ds`.`generic_name_pinyin` LIKE '%".strtoupper($condition)."%'  OR `ds`.`production_enterprise_pinyin` LIKE '%".strtoupper($condition)."%') AS S ON `S`.`system_number`=`H`.`drug_number`  LIMIT 0,".$pageIndex * 30;

			$sqlMore = "SELECT * FROM ( select * from `drug_hospital` `dh` where `dh`.`hospital_id` = '".$hospitalId."' and `dh`.`delete_flag` = 2 ) as H INNER JOIN ( SELECT * FROM `drug_system` `ds` WHERE `ds`.`generic_name` LIKE '%".$condition."%'  OR `ds`.`production_enterprise_pinyin` LIKE '%".strtoupper( $condition )."%') AS S ON `S`.`system_number`=`H`.`drug_number`  LIMIT ".($pageIndex * 30)." ,30";

			//搜索判断
			$res = Db::query($sql);
			$more = Db::query($sqlMore);	//判断是否还有数据
			
		}

		if( empty($more) ){
			$more = '1';
		}else{
			$more= '2';
		}
		self::returnMsg(200,$more,$res);
	}

/*
	 *	参数过滤，将默认为空的参数过滤
	 * 	@params array $data
	 * 	@return array $newData 
	 * 	@return string $searchData
	 * 	
	 */
	protected function paramsFilter($data = [])
	{
		//筛选查询
		//$newData['system_number'] = isset( $data['drugNumber'] ) ? $data['drugNumber'] : NULL;				//药品编号
		$newData['generic_name'] = isset( $data['drugCommonName'] ) ? $data['drugCommonName'] : NULL;				//药品通用名
		$newData['specifications'] = isset( $data['drugSpecifications'] ) ? $data['drugSpecifications'] : NULL;		//药品规格
		$newData['production_enterprise'] = isset( $data['drugManufacturer'] ) ? $data['drugManufacturer'] : NULL;	//药品生产企业
		$newData['approved_by'] = isset( $data['drugApprovalNumner'] ) ? $data['drugApprovalNumner'] : NULL;			//批准文号
		$newData['placeNumber'] = isset( $data['placeNumner'] ) ? $data['placeNumner'] : NULL;						//货位号

		$newData['base_medicine_category'] = isset( $data['JyCurrentValue'] ) ? $data['JyCurrentValue'] : NULL;	//基药类型
		$newData['procurement_categories'] = isset( $data['BuyCurrentValue'] ) ? $data['BuyCurrentValue'] : NULL;	//采购类型
		$newData['domestic'] = isset( $data['ChinaCurrentValue'] ) ? $data['ChinaCurrentValue'] : NULL;				//是否国产
		$newData['health_care'] = isset( $data['InsuranceCurrentValue'] ) ? $data['InsuranceCurrentValue'] : NULL;		//是否医保
		$newData['precious'] = isset( $data['IsExpensiveCurrentValue'] ) ? $data['IsExpensiveCurrentValue'] : NULL;		//是否贵重

		//搜索查询
		$searchData = isset( $data['searchValue'] ) ? $data['searchValue'] : NULL;	

		//删除空数据
		foreach($newData as $key => $value){
			if( empty($value) ){
				unset($newData[$key]);
			}
		}
		unset($data);	//释放内存

		if( empty($newData) && $searchData != '' ){
			return $searchData;
		}else{
			//组合查询语句
			$array = [];

			foreach ($newData as $key => $value) {
				// if( $key=='system_number'){
				// 	$array[] = ['ds.system_number','like' , '%'.$value.'%'];
				// }

				if( $key=='generic_name'){
					$array[] = ['ds.generic_name','like' , '%'.$value.'%'];
				}

				if( $key=='specifications'){
					$array[] = ['ds.specifications','like' , '%'.$value.'%'];
				}

				if( $key=='production_enterprise'){
					$array[] = ['ds.production_enterprise','like' , '%'.$value.'%'];
				}

				if( $key=='approved_by'){
					$array[] = ['ds.approved_by','like' , '%'.$value.'%'];
				}
				if( $key=='placeNumber'){
					$array[] = ['dh.place_number','like' , '%'.$value.'%'];
				}

				if( $key=='base_medicine_category'){
					$array[] = ['ds.base_medicine_category','=' ,$value];
				}

				if( $key=='procurement_categories'){
					$array[] = ['ds.procurement_categories','=' ,$value];
				}

				if( $key=='domestic'){
					$array[] = ['ds.domestic','=' ,$value];
				}

				if( $key=='health_care'){
					$array[] = ['ds.health_care','=' ,$value];
				}

				if( $key=='precious'){
					$array[] = ['ds.precious','=' ,$value];
				}
			}
			return $array;
		}		
	}
}