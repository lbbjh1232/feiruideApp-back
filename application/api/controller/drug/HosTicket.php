<?php
namespace app\api\controller\drug;

use think\Controller;
use think\Request;
use app\api\controller\Send;
use think\Db;
use app\api\controller\Api;

/*
 * 本医院票据相关业务操作
 */

class HosTicket extends Api
{
	use Send;
	/*
	 * 获取本院出库票据列表
	 */
	public function index()
	{
		$params = input('');
		$pageIndex = (int)input('pageIndex');				//每次加载的条数，每次更新30条	
		$hospitalId = input('hospitalID');

		if( isset( $params['key'] ) ){
			//筛选查询
			$drugBatchNumber = $params['drugBatchNumber'];					//药品批号
			$drugName = $params['drugName'];								//药品通用名
			$fromType = $params['fromTypeCurrentValue'];					//来源类型
			$jyCompanyId = $params['jyCompanyCurrentValue'];				//经营企业id
			$outStatus = $params['outStatusCurrentValue'];					//查验状态
			$outType = $params['outTypeCurrentValue'];						//出库类型
			$timeEnd = $params['timeEnd'];									//开票起始时间
			$timeStart = $params['timeStart'];								//开票终止时间
			$firstTicket = $params['firstTicketCurrentValue'];				//一票上传情况
			$orderField = $params['orderFieldCurrentValue'];				//排序字段:开票时间、创建时间
			$orderMethod = $params['orderValueCurrentValue'];				//排序方法：升序、降序

			if( $orderField == 1 ){
				$sqlField = "order by OUT_H.creation_time ";
			}else{
				$sqlField = "order by OUT_H.invoice_date ";
			}

			if( $orderMethod == 1 ){
				$sqlMethod = "ASC";
			}else{
				$sqlMethod = "DESC";
			}

			if( $jyCompanyId != 0 ){
				$jyCompanySql = " and dwi.company_id = '".$jyCompanyId."'";
			}else{
				$jyCompanySql='';
			}

			if( !empty($drugName) ){
				$drugNameSql = " and ( ds.generic_name like '%".$drugName."%'"." OR ds.generic_name_pinyin like '%".strtoupper($drugName)."%' )";
			}else{
				$drugNameSql='';
			}

			if( $fromType!=2  ){
				$fromTypeSql = "and dwi.from_type = '".$fromType."'";
			}else{
				$fromTypeSql='';
			}

			if( $firstTicket!=2  ){

				if( $firstTicket == 1 ){
					$firstTicketSql = "and dwi.photo_state = '".$firstTicket."'";
				}else{
					$firstTicketSql = "and dwi.photo_state <> '1' ";
				}
				
			}else{
				$firstTicketSql='';
			}

			if( !empty($drugBatchNumber) ){
				$drugBatchNumberSql = " and dwi.into_number = '".$drugBatchNumber."'";
			}else{
				$drugBatchNumberSql='';
			}

			if( $outStatus != 2 ){
				$outStatusSql = " and state = '".$outStatus."'";
			}else{
				$outStatusSql='';
			}

			if( !empty($outType) ){
				$outTypeSql = " and out_type = '".$outType."'";
			}else{
				$outTypeSql='';
			}

			$timeSql = '';
			if( !empty($timeStart) && empty($timeEnd)){
				
				$timeStart = strtotime($timeStart);
				$timeSql = " and invoice_date >= '".date('Y-m-d H:i:s',$timeStart)."'";
			}

			if( empty($timeStart) && !empty($timeEnd)){
				
				$timeEnd = strtotime($timeEnd);
				$timeSql = " and invoice_date <= '".date('Y-m-d H:i:s',$timeEnd)."'";
			}

			if( !empty($timeStart) && !empty($timeEnd)){
				
				$timeStart = strtotime($timeStart);
				$timeEnd = strtotime($timeEnd);
				$timeSql = " and invoice_date <= '".date('Y-m-d H:i:s',$timeEnd)."' and invoice_date >='".date('Y-m-d H:i:s',$timeStart)."'";
			}

			$sql =  "select * from (select * from drug_warehouse_out where hospital_id = '".$hospitalId."' and delete_flag = '2' ".$outStatusSql.$outTypeSql.$timeSql.") as OUT_H INNER JOIN ( select ds.generic_name,ds.specifications,ds.conversion_ratio,ds.production_enterprise,dwi.into_number,dwi.id into_id,dwi.from_type,dwi.picture_type,com.`name` company_name from (drug_warehouse_into as dwi INNER JOIN drug_system as ds on dwi.drug_number = ds.system_number ".$drugNameSql." ) INNER JOIN company as com on dwi.company_id=com.id ".$jyCompanySql.$fromTypeSql.$drugBatchNumberSql.$firstTicketSql." ) AS INTO_H on OUT_H.into_id = INTO_H.into_id ".$sqlField.$sqlMethod." LIMIT 0,".$pageIndex * 30;

			$sqlMore = "select * from (select * from drug_warehouse_out where hospital_id = '".$hospitalId."' and delete_flag = '2' ".$outStatusSql.$outTypeSql.$timeSql.") as OUT_H INNER JOIN ( select ds.generic_name,ds.specifications,ds.conversion_ratio,ds.production_enterprise,dwi.into_number,dwi.id into_id,dwi.from_type,dwi.picture_type,com.`name` company_name from (drug_warehouse_into as dwi INNER JOIN drug_system as ds on dwi.drug_number = ds.system_number ".$drugNameSql." ) INNER JOIN company as com on dwi.company_id=com.id ".$jyCompanySql.$fromTypeSql.$drugBatchNumberSql.$firstTicketSql." ) AS INTO_H on OUT_H.into_id = INTO_H.into_id ".$sqlField.$sqlMethod." LIMIT ".($pageIndex * 30)." ,30";

			$res = Db::query( $sql );
			$more = Db::query( $sqlMore );
			//组合数据
			foreach ($res as $key => $value) {
				$res[$key]['sign'] = '';
				$res[$key]['invoice_date'] = date('Y-m-d',strtotime($value['invoice_date']));
				$res[$key]['creation_time'] = date('Y-m-d',strtotime($value['creation_time']));
			}
			if( empty($more) ){
				$more = '1';
			}else{
				$more= '2';
			}
			self::returnMsg(200,$more,$res);
			
		}else{
			//系统查询
			$sql = "select * from (select * from drug_warehouse_out where hospital_id = '".$hospitalId."' and delete_flag = '2') as OUT_H LEFT JOIN ( select ds.generic_name,ds.specifications,ds.conversion_ratio,ds.production_enterprise,dwi.into_number,dwi.id into_id,dwi.from_type,dwi.picture_type,com.`name` company_name from (drug_warehouse_into as dwi INNER JOIN drug_system as ds on dwi.drug_number = ds.system_number) INNER JOIN company as com on dwi.company_id=com.id ) AS INTO_H on OUT_H.into_id = INTO_H.into_id order by OUT_H.creation_time DESC LIMIT 0,".$pageIndex * 30;

			$sqlMore = "select * from (select * from drug_warehouse_out where hospital_id = '".$hospitalId."' and delete_flag = '2') as OUT_H LEFT JOIN ( select ds.generic_name,ds.specifications,ds.conversion_ratio,ds.production_enterprise,dwi.into_number,dwi.id into_id,dwi.from_type,dwi.picture_type,com.`name` company_name from (drug_warehouse_into as dwi INNER JOIN drug_system as ds on dwi.drug_number = ds.system_number) INNER JOIN company as com on dwi.company_id=com.id ) AS INTO_H on OUT_H.into_id = INTO_H.into_id order by OUT_H.creation_time DESC LIMIT ".($pageIndex * 30)." ,30";

			$res = Db::query( $sql );
			$more = Db::query( $sqlMore );
			//组合数据
			foreach ($res as $key => $value) {
				$res[$key]['sign'] = '';
				$res[$key]['invoice_date'] = date('Y-m-d',strtotime($value['invoice_date']));
				$res[$key]['creation_time'] = date('Y-m-d',strtotime($value['creation_time']));
			}
			if( empty($more) ){
				$more = '1';
			}else{
				$more= '2';
			}
			self::returnMsg(200,$more,$res);
		}
	}

	/*
	 * 查验票据
	 */
	public function checkTicket()
	{
		$params = input('');
		$res = 0;
		if( $params['state'] == 3 ){
			$condition[] = ['id','in',$params['outID']];
			$data['state'] = 3;
			$data['reason'] = $params['reason'];
			$res = Db::table('drug_warehouse_out')->where( $condition )->update( $data );
		}

		if( $params['state'] == 1  ){
			$condition[] = ['id','in',$params['outID']];
			$data['state'] = 1;
			//获取入库id集合
			$getIntoIds = Db::table('drug_warehouse_out')->where( $condition )->column('into_id');
			//去重
			$into_ids = array_unique( $getIntoIds );
			//改变出库查验通过状态
			$res = Db::table('drug_warehouse_out')->where( $condition )->update( $data );
			//改变入库记录状态
			$setIntoState = Db::table('drug_warehouse_into')->where('id','in',$into_ids)->update(['state'=>1]);
		}


		if( $params['state'] == 0  ){
			$condition[] = ['id','in',$params['outID']];
			$data['state'] = 0;
			$res = Db::table('drug_warehouse_out')->where( $condition )->update( $data );
		}

		self::returnMsg(200,'success',$res);


	}

	//获取票据图片
	public function getDetailTicket()
	{ 
		$outId = input('outid');
		$baseSql = "SELECT dwo_t.out_id,dwo.id,dwo_t.out_photo_2 AS tongxing_two,dwo_t.invoice_photo_2 AS invoice_two,dwo_t.out_photo_3 AS tongxing_three,dwo_t.invoice_photo_3 AS invoice_three,dwo_t.`price_photo`,ALLOT.tongxing_one,ALLOT.invoice_one,ALLOT.neibu_src,ALLOT.jituan_src,ALLOT.report_photo,ALLOT.user_id,ALLOT.drug_number,ALLOT.sid FROM drug_warehouse_out AS dwo   LEFT JOIN drug_warehouse_out_ticket AS dwo_t ON dwo.id = dwo_t.out_id  LEFT JOIN ( SELECT dwi.id,dwi.sid,dwi.`user_id`,dwi.`drug_number`,dr.picture_paths AS `report_photo`,dwi.out_photo AS tongxing_one,dwi.invoice_photo AS invoice_one,dwi.`ticket_allot_id`,DTA.neibu_src,DTA.jituan_src FROM drug_warehouse_into AS dwi  LEFT JOIN drug_report AS dr ON dwi.report_id=dr.id and dr.delete_flag=1  LEFT JOIN ( SELECT dta.id,dta.`allot_ticket_src` AS neibu_src,dta.`relation_company_id`,rc.relation_attest AS jituan_src FROM drug_ticket_allot AS dta LEFT JOIN relation_company AS rc ON dta.relation_company_id=rc.id WHERE dta.delete_flag=0 ) AS DTA ON dwi.`ticket_allot_id`=DTA.id ) AS ALLOT ON dwo.`into_id`=ALLOT.id  WHERE dwo.id = ?;";

		$baseSrc = Db::query($baseSql,[$outId]);
		$ticket = $baseSrc[0];					//基本票据记录

		// 查询药品出库信息
		$infoSql = "select DS.* ,DWO.out_type,DWO.out_count,DWO.creation_time out_time,DWO.price,DWO.due_time,DWIC.picture_type,DWIC.from_type,DWIC.into_number,DWIC.company_name from drug_warehouse_out as DWO join ( select DWI.*,COM.name as company_name from drug_warehouse_into as DWI join company as COM on DWI.company_id = COM.id ) as DWIC on DWO.into_id = DWIC.id join drug_system DS on DWO.drug_number = DS.system_number where DWO.id = ?;";
		$info = Db::query($infoSql,[$outId]);

		$drug_number = $ticket['drug_number'];	//药品编号
		$user_id = $ticket['user_id'];			//经营企业用户id
		$supplier_id = $ticket['sid'];			//生产厂家id

		$importSql = "SELECT agency_photo FROM drug_agency WHERE drug_number = ? AND `status` = 1 AND delete_flag = 1;";
		$importSrc = Db::query($importSql,[$drug_number]);	//进口总代证明记录
		
		$productionTradeSql = "SELECT productionTrade_photo FROM drug_production_trade WHERE supplier_id = ? AND `status` = 1 AND delete_flag = 1;";
		$productionTradeSrc = Db::query($productionTradeSql,[$supplier_id]);		//科工贸一体化证明记录

		//处理数据
		$res = [];
		$res['tongxing_one']  = $this->handleImage( $ticket['tongxing_one'] );
		$res['tongxing_two']  = $this->handleImage( $ticket['tongxing_two'] );
		$res['tongxing_three']  = $this->handleImage( $ticket['tongxing_three'] );

		$res['invoice_one'] = $this->handleImage( $ticket['invoice_one'] );
		$res['invoice_two'] = $this->handleImage( $ticket['invoice_two'] );
		$res['invoice_three'] = $this->handleImage( $ticket['invoice_three'] );

		$res['price_photo'] = $this->handleImage( $ticket['price_photo'] );
		$res['report_photo'] = $this->handleImage( $ticket['report_photo'] );

		$res['neibu_src'] = $this->handleImage( $ticket['neibu_src'] );
		$res['jituan_src'] = $this->handleImage( $ticket['jituan_src'] );

		
		if( !empty($importSrc) ){
			$res['import'] = $this->handleImage( $importSrc[0]['agency_photo'] );
			$merge['import'] = $importSrc[0]['agency_photo'];
		}else{
			$res['import'] = '';
			$merge['import'] = '';
		}

		if( !empty($productionTradeSrc) ){
			$res['productionTrade'] = $this->handleImage( $productionTradeSrc[0]['productionTrade_photo'] );
			$merge['productionTrade'] = $productionTradeSrc[0]['productionTrade_photo'];
		}else{
			$res['productionTrade'] = '';
			$merge['productionTrade'] = '';
		}

		//$mergeImage = array_merge( $this->mergeImage($ticket['tongxing_one']) , $this->mergeImage($ticket['invoice_one']) , $this->mergeImage($ticket['tongxing_two']) , $this->mergeImage($ticket['invoice_two']) ,  $this->mergeImage($ticket['report_photo'])  , $this->mergeImage($ticket['price_photo']) , $this->mergeImage($ticket['neibu_src']) , $this->mergeImage($ticket['jituan_src']) , $this->mergeImage($merge['import']) , $this->mergeImage($merge['productionTrade']) );

		$return = ['category'=>$res,'info'=>$info[0]];

		self::returnMsg(200,'success',$return);
	}

	/*
	 * 获取本院经营企业
	 */
	public function getHosCompany()
	{
		$hospitalId = input('hospitalID');
		$sql = "select company.name as text,company.id as value from hospital_company as hc inner join company on hc.company_id=company.id and hc.hospital_id=?;";
		$company = Db::query( $sql , [$hospitalId]);
		array_unshift($company,['text'=>'全部','value'=>0]);
		self::returnMsg(200,'success',$company);
	}

	/*
	 * 返回结果图片处理
	 * $params Sting $src
	 */
	protected function handleImage( $src )
	{
		if( !empty($src) ){
			$arrayImages = explode(',' , $src);
			$container = [];
			foreach ($arrayImages as $key => $value) {
				$url = config('ticket_url').$value;
				if( stripos($value, '.pdf') !== false ){
					$container[] = ['key'=>2,'url'=>$url];
				}else{
					$container[] = ['key'=>1,'url'=>$url];
				}
			}
 			return $container;

		}else{
			return '';
		}
	}

	/*
	 * 组合路径处理
	 * $params Sting $src
	 */
	protected function mergeHandleImage( $src )
	{
		if( !empty($src) ){
			$arrayImages = explode(',' , $src);
			foreach ($arrayImages as $key => $value) {
				$arrayImages[$key] = config('ticket_url').$value;
			}
 			return $arrayImages;

		}else{
			return '';
		}
	}

	/*
	 * 组合预览图片路径
	 * @params String  $src
	 */
	protected function mergeImage( $src )
	{
		$data = $this->mergeHandleImage($src);

		if( !empty($data) && is_array($data) ){

			$array = [];
			foreach ($data as $key => $value) {

				if( stripos($value,'.pdf') === false ){
					$array[] = $value;
				}
			}
			return $array;

		}else{
			return [];
		}
	}
}