<?php
namespace app\api\controller\drug;

use think\Controller;
use think\Requset;
use think\Db;
use app\api\controller\Send;

// 获取挂网药品
class NetDrug
{
	use send;

	//获取挂网药品
	public function index()
	{
		$condition = $this->paramsFilter(input(''));
		$pageIndex = (int)input('pageIndex');				//每次加载的条数，每次更新30条	

		if( is_array($condition) ){
			//筛选判断
			$condition[]=['state','=',1];
			$condition[]=['source','in',[1,2]];
			$res = Db::table('drug_system')->where( $condition )->limit(0,$pageIndex * 30)->select();

			$more = Db::table('drug_system')->where( $condition )->limit($pageIndex * 30,30)->select();	//判断是否还有数据

		}elseif( !empty($condition) && !is_array($condition) ){
			
			//搜索判断
			$searchCondition[] = ['generic_name','like','%'.$condition.'%'];
			$searchCondition[] = ['generic_name_pinyin','like','%'.strtoupper($condition).'%'];
			$searchCondition[] = ['production_enterprise_pinyin','like','%'.strtoupper( $condition ).'%'];
			$searchCondition[] = ['production_enterprise','like','%'.$condition.'%'];

			$res = Db::table('drug_system')->where( function($query){
					$query->where(['state'=>1,'source'=>1]);

				})->where( function($query) use ($searchCondition){
					$query->whereOr($searchCondition);
					
				} )->limit(0,$pageIndex * 30)->select();

			$more = Db::table('drug_system')->where( function($query){
					$query->where(['state'=>1,'source'=>1]);
				
				})->where( function($query) use ($searchCondition){
					$query->whereOr($searchCondition);

				} )->limit($pageIndex * 30,30)->select();	//判断是否还有数据
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
		$newData['system_number'] = isset( $data['drugNumber'] ) ? $data['drugNumber'] : NULL;				//药品编号
		$newData['generic_name'] = isset( $data['drugCommonName'] ) ? $data['drugCommonName'] : NULL;				//药品通用名
		$newData['specifications'] = isset( $data['drugSpecifications'] ) ? $data['drugSpecifications'] : NULL;		//药品规格
		$newData['production_enterprise'] = isset( $data['drugManufacturer'] ) ? $data['drugManufacturer'] : NULL;	//药品生产企业
		$newData['approved_by'] = isset( $data['drugApprovalNumner'] ) ? $data['drugApprovalNumner'] : NULL;			//批准文号

		$newData['base_medicine_category'] = isset( $data['JyCurrentValue'] ) ? $data['JyCurrentValue'] : NULL;	//基药类型
		$newData['procurement_categories'] = isset( $data['BuyCurrentValue'] ) ? $data['BuyCurrentValue'] : NULL;	//采购类型
		$newData['domestic'] = isset( $data['ChinaCurrentValue'] ) ? $data['ChinaCurrentValue'] : NULL;				//是否国产
		$newData['health_care'] = isset( $data['InsuranceCurrentValue'] ) ? $data['InsuranceCurrentValue'] : NULL;		//是否医保
		$newData['precious'] = isset( $data['IsExpensiveCurrentValue'] ) ? $data['IsExpensiveCurrentValue'] : NULL;		//是否贵重

		$searchData = isset( $data['searchValue'] ) ? $data['searchValue'] : NULL;	//搜索查询

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

				if( $key=='system_number'){
					$array[] = ['system_number','like' , '%'.$value.'%'];
				}

				if( $key=='generic_name'){
					$array[] = ['generic_name','like' , '%'.$value.'%'];
				}

				if( $key=='specifications'){
					$array[] = ['specifications','like' , '%'.$value.'%'];
				}

				if( $key=='production_enterprise'){
					$array[] = ['production_enterprise','like' , '%'.$value.'%'];
				}

				if( $key=='approved_by'){
					$array[] = ['approved_by','like' , '%'.$value.'%'];
				}

				if( $key=='base_medicine_category'){
					$array[] = ['base_medicine_category','=' ,$value];
				}

				if( $key=='procurement_categories'){
					$array[] = ['procurement_categories','=' ,$value];
				}

				if( $key=='domestic'){
					$array[] = ['domestic','=' ,$value];
				}

				if( $key=='health_care'){
					$array[] = ['health_care','=' ,$value];
				}

				if( $key=='precious'){
					$array[] = ['precious','=' ,$value];
				}
			}
			return $array;
		}		
	}




}