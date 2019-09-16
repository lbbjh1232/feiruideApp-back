<?php
namespace app\api\controller\drug;

use think\Controller;
use think\Request;
use app\api\controller\Send;
use app\api\controller\Api;
use think\Db;

/*
 * 本经营企业票据相关业务操作
 */

class ComTicket extends Api
{
	use Send;
	/*
	 * 获取本企入库药品列表
	 */
	public function index()
	{
		$params = input('');
		$pageIndex = (int)input('pageIndex');				//每次加载的条数，每次更新30条	
		$companyId = input('companyID');

		if( isset( $params['key'] ) ){
			//筛选查询
			$drugBatchNumber = $params['drugBatchNumber'];					//药品批号
			$drugName = $params['drugName'];								//药品通用名
			$fromType = $params['fromTypeCurrentValue'];					//来源类型
			$timeEnd = $params['timeEnd'];									//开票起始时间
			$timeStart = $params['timeStart'];								//开票终止时间
			$firstTicket = $params['firstTicketCurrentValue'];				//一票上传情况
			$orderField = $params['orderFieldCurrentValue'];				//排序字段:开票时间、创建时间
			$orderMethod = $params['orderValueCurrentValue'];				//排序方法：升序、降序

			if( $orderField == 1 ){
				$sqlField = "order by i_creation_time ";
			}else{
				$sqlField = "order by dwi.into_time ";
			}

			if( $orderMethod == 1 ){
				$sqlMethod = " ASC ";
			}else{
				$sqlMethod = " DESC ";
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

			$timeSql = '';
			if( !empty($timeStart) && empty($timeEnd)){
				
				$timeStart = strtotime($timeStart[0]);
				$timeSql = " and dwi.into_time >= '".date('Y-m-d H:i:s',$timeStart)."'";
			}

			if( empty($timeStart) && !empty($timeEnd)){
				
				$timeEnd = strtotime($timeEnd[0]);
				$timeSql = " and dwi.into_time <= '".date('Y-m-d H:i:s',$timeEnd)."'";
			}

			if( !empty($timeStart) && !empty($timeEnd)){
				
				$timeStart = strtotime($timeStart[0]);
				$timeEnd = strtotime($timeEnd[0]);
				$timeSql = " and dwi.into_time <= '".date('Y-m-d H:i:s',$timeEnd)."' and dwi.into_time >='".date('Y-m-d H:i:s',$timeStart)."'";
			}

			$sql =  "select ds.*,dwi.id into_id,dwi.into_number,dwi.into_count,dwi.send_number,dwi.surplus_amount,dwi.picture_type,dwi.photo_state,dwi.from_type,dwi.state i_state,dwi.into_time,dwi.creation_time i_creation_time,dwi.allot_count from drug_warehouse_into dwi inner join drug_system ds on dwi.drug_number = ds.system_number and dwi.company_id = ".$companyId." and dwi.delete_flag=2 ".$firstTicketSql.$drugBatchNumberSql.$timeSql.$fromTypeSql.$drugNameSql.$sqlField.$sqlMethod."limit 0,".$pageIndex * 30;

			$sqlMore =  "select ds.*,dwi.id into_id,dwi.into_number,dwi.into_count,dwi.send_number,dwi.surplus_amount,dwi.picture_type,dwi.photo_state,dwi.from_type,dwi.state i_state,dwi.into_time,dwi.creation_time i_creation_time,dwi.allot_count from drug_warehouse_into dwi inner join drug_system ds on dwi.drug_number = ds.system_number and dwi.company_id = ".$companyId." and dwi.delete_flag=2 ".$firstTicketSql.$drugBatchNumberSql.$timeSql.$fromTypeSql.$drugNameSql.$sqlField.$sqlMethod."limit ".($pageIndex * 30)." ,30";

			$res = Db::query( $sql );
			$more = Db::query( $sqlMore );
			//组合数据
			foreach ($res as $key => $value) {
				$res[$key]['sign'] = '';
				$res[$key]['into_time'] = date('Y-m-d',strtotime($value['into_time']));
			}
			if( empty($more) ){
				$more = '1';
			}else{
				$more= '2';
			}
			self::returnMsg(200,$more,$res);
			
		}else{
			//系统查询
			$sql = "select ds.*,dwi.id into_id,dwi.into_number,dwi.into_count,dwi.send_number,dwi.surplus_amount,dwi.picture_type,dwi.photo_state,dwi.from_type,dwi.state i_state,dwi.into_time,dwi.creation_time i_creation_time,dwi.allot_count from drug_warehouse_into dwi inner join drug_system ds on dwi.drug_number = ds.system_number and dwi.company_id = ".$companyId." and dwi.delete_flag=2 order by i_creation_time desc limit 0,".$pageIndex * 30;

			$sqlMore = "select ds.*,dwi.id into_id,dwi.into_number,dwi.into_count,dwi.send_number,dwi.surplus_amount,dwi.picture_type,dwi.photo_state,dwi.from_type,dwi.state i_state,dwi.into_time,dwi.creation_time i_creation_time,dwi.allot_count from drug_warehouse_into dwi inner join drug_system ds on dwi.drug_number = ds.system_number and dwi.company_id = ".$companyId." and dwi.delete_flag=2 order by i_creation_time desc limit ".($pageIndex * 30)." ,30";

			$res = Db::query( $sql );
			$more = Db::query( $sqlMore );
			//组合数据
			foreach ($res as $key => $value) {
				$res[$key]['sign'] = '';
				$res[$key]['into_time'] = date('Y-m-d',strtotime($value['into_time']));
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
			$res = Db::table('drug_warehouse_out')->where( $condition )->update( $data );
		}


		if( $params['state'] == 0  ){
			$condition[] = ['id','in',$params['outID']];
			$data['state'] = 0;
			$res = Db::table('drug_warehouse_out')->where( $condition )->update( $data );
		}

		self::returnMsg(200,'success',$res);


	}

	//获取票据图片
	public function intoTicket()
	{ 
		$intoId = input('intoid');
		$baseSql = "SELECT * FROM ( SELECT dwi.id,dwi.sid,dwi.`drug_number`,dr.picture_paths AS `report_photo`,dwi.price_photo,dwi.out_photo AS tongxing_one,dwi.invoice_photo AS invoice_one,dwi.out_photo_2 AS tongxing_two,dwi.invoice_photo_2 AS invoice_two,dwi.`ticket_allot_id`,DTA.neibu_src,DTA.jituan_src FROM drug_warehouse_into AS dwi  LEFT JOIN drug_report AS dr ON dwi.report_id=dr.id and dr.delete_flag=1  LEFT JOIN ( SELECT dta.id,dta.`allot_ticket_src` AS neibu_src,dta.`relation_company_id`,rc.relation_attest AS jituan_src FROM drug_ticket_allot AS dta LEFT JOIN relation_company AS rc ON dta.relation_company_id=rc.id WHERE dta.delete_flag=0 ) AS DTA ON dwi.`ticket_allot_id`=DTA.id) dd WHERE dd.id = ?;";

		$baseSrc = Db::query($baseSql,[$intoId]);
		$ticket = $baseSrc[0];					//基本票据记录

		//查询入库药品基本信息
		$infoSql = 'select * from drug_warehouse_into as DWI join drug_system as DS on DWI.drug_number = DS.system_number where DWI.id = ?';
		$info = Db::query($infoSql,[$intoId]);
		$info[0]['into_time'] = date('Y-m-d',strtotime($info[0]['into_time']));
		

		$drug_number = $ticket['drug_number'];	//药品编号
		$supplier_id = $ticket['sid'];			//生产厂家id

		$importSql = "SELECT agency_photo FROM drug_agency WHERE drug_number = ? AND `status` = 1 AND delete_flag = 1;";
		$importSrc = Db::query($importSql,[$drug_number]);	//总代证明记录
		
		$productionTradeSql = "SELECT productionTrade_photo FROM drug_production_trade WHERE supplier_id = ? AND `status` = 1 AND delete_flag = 1;";
		$productionTradeSrc = Db::query($productionTradeSql,[$supplier_id]);		//科工贸一体化证明记录

		//处理数据
		$res = [];
		$res['tongxing_one']  = $this->handleImage( $ticket['tongxing_one'] );
		$res['invoice_one'] = $this->handleImage( $ticket['invoice_one'] );

		$res['tongxing_two']  = $this->handleImage( $ticket['tongxing_two'] );
		$res['invoice_two'] = $this->handleImage( $ticket['invoice_two'] );

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

		//$mergeImage = array_merge( $this->mergeImage($ticket['tongxing_one']) , $this->mergeImage($ticket['invoice_one']) ,  $this->mergeImage($ticket['report_photo'])  , $this->mergeImage($ticket['price_photo']) , $this->mergeImage($ticket['neibu_src']) , $this->mergeImage($ticket['jituan_src']) , $this->mergeImage($merge['import']) , $this->mergeImage($merge['productionTrade']) );

		$return = ['category'=>$res,'info'=>$info[0]];

		self::returnMsg(200,'success',$return);
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