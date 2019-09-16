<?php
namespace app\api\controller\drug;

use think\Controller;
use think\Request;
use app\api\controller\Send;
use app\api\controller\Api;
use think\Db;

/*
 * 经营企业出库相关业务操作
 */

class WarehouseOut extends Api
{
	use Send;

	/*
	 * 获取本企客户医院
	 */
	public function getHosList()
	{
		$params = input('');
		$companyId = $params['companyID'];
		$pageIndex = $params['pageIndex'];	//显示第几页
		$value = isset($params['value'])?$params['value']:'';
		if( !empty($value) ){
			$condition1[]=['ds.name','like','%'.$value.'%'];
			$condition1[]=['ds.name_pinyin','like','%'.strtoupper($value).'%'];

		}else{
			$condition1=[];
		}

		$alias = [ 'hospital'=>'ds','hospital_enterprise'=>'dh'];
		$join= [['hospital','ds.id=dh.hospital_id']];

		$condition[]=['dh.enterprise_id','=',$companyId];
		$where = $condition;

		//筛选判断
		$res = Db::table('hospital_enterprise')->alias($alias)->join($join)->where($where)->where(function($query) use ($condition1){
			$query->whereOr($condition1);
		})->field('ds.id hid,ds.name')->limit(($pageIndex-1)*30,30)->select();

		self::returnMsg(200,'success',$res);

	}
	/*
	 * 获取本企客户医院(出库筛选)
	 */
	public function getHospital()
	{
		$companyId = input('companyID');
		$sql = "select hos.name as text,hos.id as value from hospital_enterprise as he inner join hospital hos on hos.id= he.hospital_id and he.enterprise_id=? group by hos.id;";
		$hospitals = Db::query( $sql , [$companyId]);
		array_unshift($hospitals,['text'=>'全部','value'=>0]);
		self::returnMsg(200,'success',$hospitals);
	}

	/*
	 * 获取出库的入库药品
	 */
	public function getOutDrug()
	{
		$params = input('');
		$companyId = $params['companyID'];
		$pageIndex = $params['pageIndex'];	//显示第几页

		$value = isset($params['value'])?$params['value']:'';
		if( !empty($value) ){
			$condition1[]=['ds.generic_name','like','%'.$value.'%'];
			$condition1[]=['ds.generic_name_pinyin','like','%'.strtoupper($value).'%'];

		}else{
			$condition1=[];
		}

		$alias = [ 'drug_system'=>'ds','drug_warehouse_into'=>'dwi'];
		$join= [['drug_system','ds.system_number=dwi.drug_number']];

		$condition[]=['dwi.company_id','=',$companyId];
		$condition[]=['dwi.delete_flag','=',2];
		$condition[]=['dwi.surplus_amount','>',0];
		
		$where = $condition;
		//筛选判断
		$res = Db::table('drug_warehouse_into')->alias($alias)->join($join)->where($where)->where(function($query) use ($condition1){
			$query->whereOr($condition1);
		})->field('ds.system_number,ds.generic_name,ds.generic_name_pinyin,ds.specifications,ds.conversion_ratio,ds.production_enterprise,dwi.*')->order("dwi.creation_time desc")->limit(($pageIndex-1)*30,30)->select();	

		foreach ($res as $key => $value) {
			$res[$key]['into_time'] = date('Y-m-d',strtotime($value['into_time']));
		}
		self::returnMsg(200,'success',$res);

	}

	/*
	 * 验证是否是试用期、出库条数
	 */
	public function checkOut()
	{
		$params = input('');
		$companyId = $params['accountInfo']['userid'];
		$wallet = Db::name('pay_wallet')->where(['owner_id'=>$companyId])->find();
		if( $wallet['owner_state'] == 2 ){
			//获取还剩余条数的订单
			$condition[] =['wallet_id','=',$wallet['id']];
			$condition[] = ['state_flag','=',2];
			$condition[] =['out_surplus_amount','>',0];
			$outCount = Db::name('pay_order')->where( $condition )->field('out_surplus_amount')->order('out_expiration_time ASC')->select();
			
			if( count($outCount) ){
				$count = 0;
				foreach($outCount as $key=>$val){
					$count += $val['out_surplus_amount'];
 				}

			}else{
				//没有剩余条数，返回0条
				$count = 0;
			}
			$owner_state = 2;
			self::returnMsg(200,'success',['state'=>$owner_state,'count'=>$count]);

		}else{
			$owner_state = 1;
			self::returnMsg(200,'success',['state'=>$owner_state]);

		}

	}

	/*
	 * 出库添加
	 */
	public function warehouseOut()
	{
		$params = input('');
		//组合数据
		//拼接随货同行单、发票 2票
		$billImage = $this->ticketUrlSplice($params['billImage']);			//随货同行单
		$invoiceImage = $this->ticketUrlSplice($params['invoiceImage']);	//发票
	
		$userid = $params['accountInfo']['id'];				//用户id
		$hid = $params['currentPro']['hid'];				//医院id
		$sendNumber = $params['sendNumber'];				//出库发货单号
		$invoceDate = $params['intoTime'];					//开票日期
		$creationTime = date('Y-m-d H:i:s');				//创建时间、更新时间
		$outType = $params['outType'];						//出库类型 1、常规出库 
		$companyId = $params['accountInfo']['userid'];		//企业id

		//获取药品清单
		$drugs = $params['drugs'];
		$data = [];
		$right = 0;
		$fail = 0;

		foreach ($drugs as $key => $value) {
			$intoId = $value['id'];								//入库id
			$surplus_amount = $value['surplus_amount'];			//剩余数量
			$outCount = $value['out_count'];					//出库数量
			$pictureType = $value['picture_type'];

			$data['user_id'] = $userid;
			$data['hospital_id'] = $hid;
			$data['into_id'] = $intoId;
			$data['send_number'] = $sendNumber;
			$data['drug_number'] = $value['system_number'];
			$data['out_no'] = $value['into_number'];
			$data['out_count'] = $outCount;
			$data['invoice_date'] = $invoceDate;
			$data['out_type'] = $outType;
			$data['creation_time'] = $creationTime;
			$data['last_update_time'] = $creationTime;
			$data['price'] = $value['out_price'];
			$data['due_time'] = $value['valideTime'];

			//判断剩余条数，扣除条数，如果剩余条数不够，则不能添加出库
			//获取钱包
			$wallet = Db::name('pay_wallet')->where(['owner_id'=>$companyId])->find();
			if( $wallet['owner_state'] == 2 ){
				//非试用期，需扣出库条数
				//获取还剩余条数的订单
				$condition[] =['wallet_id','=',$wallet['id']];
				$condition[] = ['state_flag','=',2];
				$condition[] =['out_surplus_amount','>',0];
				$result = Db::name('pay_order')->where( $condition )->field('id,out_surplus_amount')->order('out_expiration_time ASC')->find();
				
				if( !empty($result) ){
					//存在剩余数量，则扣除一条
					Db::name('pay_order')->where([ 'id' => $result['id'] ])->setDec('out_surplus_amount');
					//添加出库
					$res = Db::name('drug_warehouse_out')->insertGetId( $data );
				}else{
					//没有剩余条数,则不能出库
					$res = false;
				}
				
			}else{
				//试用期客户，无需扣出库条数
				//添加出库
				$res = Db::name('drug_warehouse_out')->insertGetId( $data );
			}
			
			if($res){
				
				//出库成功后，添加出库票据表
				if($pictureType == 1){
					// 一票
					$data1['out_photo'] = $billImage;
					$data1['invoice_photo'] = $invoiceImage;
					
					$data2['out_photo'] = $billImage;
					$data2['invoice_photo'] = $invoiceImage;
				}

				if($pictureType == 2){
					// 二票
					$data1['out_photo'] = $value['out_photo'];
					$data1['invoice_photo'] = $value['invoice_photo'];
					$data1['out_photo_2'] = $billImage;
					$data1['invoice_photo_2'] = $invoiceImage;

					$data2['out_photo'] = $value['out_photo'];
					$data2['invoice_photo'] = $value['invoice_photo'];
					$data2['out_photo_2'] = $billImage;
					$data2['invoice_photo_2'] = $invoiceImage;
				}

				if($pictureType == 3){
					// 三票
					$data1['out_photo'] = $value['out_photo'];
					$data1['invoice_photo'] = $value['invoice_photo'];
					$data1['out_photo_2'] =$value['out_photo_2'];
					$data1['invoice_photo_2'] = $value['invoice_photo_2'];
					$data1['out_photo_3'] = $billImage;
					$data1['invoice_photo_3'] = $invoiceImage;

					$data2['out_photo'] = $value['out_photo'];
					$data2['invoice_photo'] = $value['invoice_photo'];
					$data2['out_photo_2'] =$value['out_photo_2'];
					$data2['invoice_photo_2'] = $value['invoice_photo_2'];
					$data2['out_photo_3'] = $billImage;
					$data2['invoice_photo_3'] = $invoiceImage;
				}

				$data1['out_id'] = $res;
				$data1['report_id'] = $value['report_id'];
				$data1['ticket_allot_id'] = $value['ticket_allot_id'];

				$data1['price_photo'] = $value['price_photo'];
				$data1['other_photo'] = $value['other_photo'];

				$result = Db::name('drug_warehouse_out_ticket')->insert( $data1 );

				//更新入库数据
				$data2['surplus_amount'] = (int)$surplus_amount - (int)$outCount;			//剩余数量减掉出库数量
				$data2['state'] = $value['state']==0 ? 3 : ( $value['state']==1 ? 1 : 3 );	//改变入库状态
				$data2['last_update_time'] =date('Y-m-d H:i:s');
				$condition1['id'] = $intoId;
				$update = Db::name('drug_warehouse_into')->where($condition1)->update( $data2 );
				++$right;

			}else{
				++$fail;
			}


		}

		if( $right > 0){
			self::returnMsg(200,'',['right'=>$right,'fail'=>$fail]);
		}else{
			self::returnMsg(201,'出库失败');
		}

	}

	/*
	 * 获取本企出库记录
	 */
	public function getComOut()
	{
		$params = input('');
		$pageIndex = (int)input('pageIndex');				//每次加载的条数，每次更新30条	
		$companyId = input('companyID');
		
		if( isset( $params['key'] ) ){

			//筛选查询
			$drugBatchNumber = $params['drugBatchNumber'];					//药品批号
			$drugName = $params['drugName'];								//药品通用名
			$fromType = $params['fromTypeCurrentValue'];					//来源类型
			$hospitalId = $params['hospitalId'];							//医院id
			$outStatus = $params['outStatusCurrentValue'];					//查验状态
			$outType = $params['outTypeCurrentValue'];						//出库类型
			$timeEnd = $params['timeEnd'];									//开票起始时间
			$timeStart = $params['timeStart'];								//开票终止时间
			$firstTicket = $params['firstTicketCurrentValue'];				//一票上传情况
			$orderField = $params['orderFieldCurrentValue'];				//排序字段:开票时间、创建时间
			$orderMethod = $params['orderValueCurrentValue'];				//排序方法：升序、降序

			if( $orderField == 1 ){
				$sqlField = " order by OUT_H.creation_time ";
			}else{
				$sqlField = " order by OUT_H.invoice_date ";
			}

			if( $orderMethod == 1 ){
				$sqlMethod = " ASC ";
			}else{
				$sqlMethod = " DESC ";
			}

			if( $hospitalId != 0 ){
				$hospitalSql = " and OUT_H.hospital_id = '".$hospitalId."'";
			}else{
				$hospitalSql='';
			}

			if( !empty($drugName) ){
				$drugNameSql = " and ( ds.generic_name like '%".$drugName."%'"." OR ds.generic_name_pinyin like '%".strtoupper($drugName)."%' )";
			}else{
				$drugNameSql='';
			}

			if( $fromType != 2  ){
				$fromTypeSql = " and dwi.from_type = '".$fromType."'";
			}else{
				$fromTypeSql='';
			}

			if( $firstTicket!=2  ){

				if( $firstTicket == 1 ){
					$firstTicketSql = " and dwi.photo_state = '".$firstTicket."'";
				}else{
					$firstTicketSql = " and dwi.photo_state <> '1' ";
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

			$sql = "select OUT_H.*,INTO_H.*,hos.name hos_name from (select * from drug_warehouse_out where delete_flag = '2' ".$outStatusSql.$outTypeSql.$timeSql.") as OUT_H INNER JOIN ( select ds.generic_name,ds.specifications,ds.conversion_ratio,ds.production_enterprise,dwi.company_id,dwi.into_number,dwi.id into_id,dwi.from_type,dwi.picture_type,dwi.surplus_amount,dwi.allot_count,dwi.into_count  from drug_warehouse_into as dwi INNER JOIN drug_system as ds on dwi.drug_number = ds.system_number ".$drugNameSql.$fromTypeSql.$firstTicketSql.$drugBatchNumberSql." ) AS INTO_H on OUT_H.into_id = INTO_H.into_id  INNER JOIN hospital as hos on OUT_H.hospital_id = hos.id ".$hospitalSql." where INTO_H.company_id = ".$companyId. $sqlField.$sqlMethod." LIMIT 0,".$pageIndex * 30;

			 $sqlMore = "select OUT_H.*,INTO_H.*,hos.name hos_name from (select * from drug_warehouse_out where delete_flag = '2' ".$outStatusSql.$outTypeSql.$timeSql.") as OUT_H INNER JOIN ( select ds.generic_name,ds.specifications,ds.conversion_ratio,ds.production_enterprise,dwi.company_id,dwi.into_number,dwi.id into_id,dwi.from_type,dwi.picture_type,dwi.surplus_amount,dwi.allot_count,dwi.into_count  from drug_warehouse_into as dwi INNER JOIN drug_system as ds on dwi.drug_number = ds.system_number ".$drugNameSql.$fromTypeSql.$firstTicketSql.$drugBatchNumberSql." ) AS INTO_H on OUT_H.into_id = INTO_H.into_id  INNER JOIN hospital as hos on OUT_H.hospital_id = hos.id ".$hospitalSql." where INTO_H.company_id = ".$companyId. $sqlField.$sqlMethod." LIMIT ".($pageIndex * 30)." ,30";

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
			$sql = "select OUT_H.*,INTO_H.*,hos.name hos_name from (select * from drug_warehouse_out where delete_flag = '2') as OUT_H INNER JOIN ( select ds.generic_name,ds.specifications,ds.conversion_ratio,ds.production_enterprise,dwi.company_id,dwi.into_number,dwi.id into_id,dwi.from_type,dwi.picture_type,dwi.surplus_amount,dwi.allot_count,dwi.into_count from drug_warehouse_into as dwi INNER JOIN drug_system as ds on dwi.drug_number = ds.system_number ) AS INTO_H on OUT_H.into_id = INTO_H.into_id  INNER JOIN hospital as hos on OUT_H.hospital_id = hos.id  where INTO_H.company_id = ".$companyId." order by OUT_H.creation_time DESC LIMIT 0,".$pageIndex * 30;

			$sqlMore = "select OUT_H.*,INTO_H.*,hos.name hos_name from (select * from drug_warehouse_out where delete_flag = '2') as OUT_H INNER JOIN ( select ds.generic_name,ds.specifications,ds.conversion_ratio,ds.production_enterprise,dwi.company_id,dwi.into_number,dwi.id into_id,dwi.from_type,dwi.picture_type,dwi.surplus_amount,dwi.allot_count,dwi.into_count  from drug_warehouse_into as dwi INNER JOIN drug_system as ds on dwi.drug_number = ds.system_number ) AS INTO_H on OUT_H.into_id = INTO_H.into_id  INNER JOIN hospital as hos on OUT_H.hospital_id = hos.id  where INTO_H.company_id = ".$companyId." order by OUT_H.creation_time DESC LIMIT ".($pageIndex * 30)." ,30";

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
	 * 获取医院驳回的出库记录
	 */
	public function getRejectOut()
	{
		$params = input('');
		$pageIndex = (int)input('pageIndex');				//每次加载的条数，每次更新30条	
		$companyId = input('companyID');
		
		if( isset( $params['key'] ) ){

			//筛选查询
			$drugBatchNumber = $params['drugBatchNumber'];					//药品批号
			$drugName = $params['drugName'];								//药品通用名
			$fromType = $params['fromTypeCurrentValue'];					//来源类型
			$hospitalId = $params['hospitalId'];							//医院id
			$outType = $params['outTypeCurrentValue'];						//出库类型
			$timeEnd = $params['timeEnd'];									//开票起始时间
			$timeStart = $params['timeStart'];								//开票终止时间
			$firstTicket = $params['firstTicketCurrentValue'];				//一票上传情况
			$orderField = $params['orderFieldCurrentValue'];				//排序字段:开票时间、创建时间
			$orderMethod = $params['orderValueCurrentValue'];				//排序方法：升序、降序

			if( $orderField == 1 ){
				$sqlField = " order by OUT_H.creation_time ";
			}else{
				$sqlField = " order by OUT_H.invoice_date ";
			}

			if( $orderMethod == 1 ){
				$sqlMethod = " ASC ";
			}else{
				$sqlMethod = " DESC ";
			}

			if( $hospitalId != 0 ){
				$hospitalSql = " and OUT_H.hospital_id = '".$hospitalId."'";
			}else{
				$hospitalSql='';
			}

			if( !empty($drugName) ){
				$drugNameSql = " and ( ds.generic_name like '%".$drugName."%'"." OR ds.generic_name_pinyin like '%".strtoupper($drugName)."%' )";
			}else{
				$drugNameSql='';
			}

			if( $fromType!=2  ){
				$fromTypeSql = " and dwi.from_type = '".$fromType."'";
			}else{
				$fromTypeSql='';
			}

			if( $firstTicket!=2  ){

				if( $firstTicket == 1 ){
					$firstTicketSql = " and dwi.photo_state = '".$firstTicket."'";
				}else{
					$firstTicketSql = " and dwi.photo_state <> '1' ";
				}
				
			}else{
				$firstTicketSql='';
			}

			if( !empty($drugBatchNumber) ){
				$drugBatchNumberSql = " and dwi.into_number = '".$drugBatchNumber."'";
			}else{
				$drugBatchNumberSql='';
			}

			
			$outStatusSql = " and state = 3 ";
			

			if( !empty($outType) ){
				$outTypeSql = " and out_type = '".$outType."'";
			}else{
				$outTypeSql='';
			}

			$timeSql = '';
			if( !empty($timeStart) && empty($timeEnd)){
				
				$timeStart = strtotime($timeStart[0]);
				$timeSql = " and invoice_date >= '".date('Y-m-d H:i:s',$timeStart)."'";
			}

			if( empty($timeStart) && !empty($timeEnd)){
				
				$timeEnd = strtotime($timeEnd[0]);
				$timeSql = " and invoice_date <= '".date('Y-m-d H:i:s',$timeEnd)."'";
			}

			if( !empty($timeStart) && !empty($timeEnd)){
				
				$timeStart = strtotime($timeStart[0]);
				$timeEnd = strtotime($timeEnd[0]);
				$timeSql = " and invoice_date <= '".date('Y-m-d H:i:s',$timeEnd)."' and invoice_date >='".date('Y-m-d H:i:s',$timeStart)."'";
			}

			$sql = "select OUT_H.*,INTO_H.*,hos.name hos_name from (select * from drug_warehouse_out where delete_flag = '2' ".$outStatusSql.$outTypeSql.$timeSql.") as OUT_H INNER JOIN ( select ds.generic_name,ds.specifications,ds.conversion_ratio,ds.production_enterprise,dwi.company_id,dwi.into_number,dwi.id into_id,dwi.from_type,dwi.picture_type,dwi.surplus_amount,dwi.allot_count,dwi.into_count  from drug_warehouse_into as dwi INNER JOIN drug_system as ds on dwi.drug_number = ds.system_number ".$drugNameSql.$fromTypeSql.$firstTicketSql.$drugBatchNumberSql." ) AS INTO_H on OUT_H.into_id = INTO_H.into_id  INNER JOIN hospital as hos on OUT_H.hospital_id = hos.id ".$hospitalSql." where INTO_H.company_id = ".$companyId. $sqlField.$sqlMethod." LIMIT 0,".$pageIndex * 30;

			 $sqlMore = "select OUT_H.*,INTO_H.*,hos.name hos_name from (select * from drug_warehouse_out where delete_flag = '2' ".$outStatusSql.$outTypeSql.$timeSql.") as OUT_H INNER JOIN ( select ds.generic_name,ds.specifications,ds.conversion_ratio,ds.production_enterprise,dwi.company_id,dwi.into_number,dwi.id into_id,dwi.from_type,dwi.picture_type,dwi.surplus_amount,dwi.allot_count,dwi.into_count  from drug_warehouse_into as dwi INNER JOIN drug_system as ds on dwi.drug_number = ds.system_number ".$drugNameSql.$fromTypeSql.$firstTicketSql.$drugBatchNumberSql." ) AS INTO_H on OUT_H.into_id = INTO_H.into_id  INNER JOIN hospital as hos on OUT_H.hospital_id = hos.id ".$hospitalSql." where INTO_H.company_id = ".$companyId. $sqlField.$sqlMethod." LIMIT ".($pageIndex * 30)." ,30";

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
			$sql = "select OUT_H.*,INTO_H.*,hos.name hos_name from (select * from drug_warehouse_out where delete_flag = '2'  and state = 3 ) as OUT_H INNER JOIN ( select ds.generic_name,ds.specifications,ds.conversion_ratio,ds.production_enterprise,dwi.company_id,dwi.into_number,dwi.id into_id,dwi.from_type,dwi.picture_type,dwi.surplus_amount,dwi.allot_count,dwi.into_count  from drug_warehouse_into as dwi INNER JOIN drug_system as ds on dwi.drug_number = ds.system_number ) AS INTO_H on OUT_H.into_id = INTO_H.into_id  INNER JOIN hospital as hos on OUT_H.hospital_id = hos.id  where INTO_H.company_id = ".$companyId." order by OUT_H.creation_time DESC LIMIT 0,".$pageIndex * 30;

			$sqlMore = "select OUT_H.*,INTO_H.*,hos.name hos_name from (select * from drug_warehouse_out where delete_flag = '2'  and state = 3 ) as OUT_H INNER JOIN ( select ds.generic_name,ds.specifications,ds.conversion_ratio,ds.production_enterprise,dwi.company_id,dwi.into_number,dwi.id into_id,dwi.from_type,dwi.picture_type,dwi.surplus_amount,dwi.allot_count,dwi.into_count  from drug_warehouse_into as dwi INNER JOIN drug_system as ds on dwi.drug_number = ds.system_number ) AS INTO_H on OUT_H.into_id = INTO_H.into_id  INNER JOIN hospital as hos on OUT_H.hospital_id = hos.id  where INTO_H.company_id = ".$companyId." order by OUT_H.creation_time DESC LIMIT ".($pageIndex * 30)." ,30";

			$res = Db::query( $sql );
			//echo json_encode($res);exit;
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
	 * 删除出库记录
	 */
	public function delDrugOut()
	{
		$params = input('currentValue');
		$fromType = $params['from_type'];				//来源类型：0、生产企业 1、集团公司
		$outCount = $params['out_count'];				//出库数量
		$surplus_amount = $params['surplus_amount'];	//剩余数量
		$allotCount = $params['allot_count'];			//内部调拨数量
		$intoCount = $params['into_count'];				//入库数量
		$outId = $params['id'];							//出库id
		$intoId = $params['into_id'];					//入库id

		$condition['id'] = $outId;
		$condition1['id'] = $intoId;

		//删除出库
		$del = Db::name('drug_warehouse_out')->where($condition)->update(['delete_flag'=>1,'delete_time'=>date('Y-m-d H:i:s')]);
		if($del){
			if( $fromType == 1 ){
				//如果是集团公司
				$data['surplus_amount'] = (int)$surplus_amount + (int)$outCount;		//将剩余数量恢复到出库前
				if( $data['surplus_amount'] == $allotCount ){
					$data['state'] = 0;		//恢复到未出库状态
				}

			}else{
				//如果是生产企业
				$data['surplus_amount'] = (int)$surplus_amount + (int)$outCount;		//将剩余数量恢复到出库前
				if( $data['surplus_amount'] == $intoCount ){
					$data['state'] = 0;		//恢复到未出库状态
				}
			}

			//恢复入库
			$backTo = Db::name('drug_warehouse_into')->where($condition1)->update( $data );
			self::returnMsg(200,'删除成功',$del);
		}else{
			self::returnMsg(201,'删除失败');
		}
	}

	/*
	 * 修改出库记录
	 */
	public function editDrugOut()
	{
		$params = input('');
		$outCount = $params['outCount'];
		$hospital_id = $params['hospitalInfo']['hid'];
		//$fromType = $params['from_type'];
		$surplusCount = $params['surplusCount'];			//原剩余数量
		$oldOutCount = $params['oldOutCount'];				//原出库数量 
            

		if( is_array( $params['outTime'] ) ){
			$outTime = $params['outTime'][0];

		}else{
			$outTime = $params['outTime'];

		}
		$condition['id'] = $params['outId'];
		$condition1['id'] = $params['intoId'];

		$data1['surplus_amount'] = (int)$surplusCount + (int)$oldOutCount - (int)$outCount;
		$data['hospital_id'] = $hospital_id;
		$data['out_count'] = $outCount;
		$data['invoice_date'] = $outTime;

		//更新出库
		$res = Db::name('drug_warehouse_out')->where( $condition )->update( $data );
		//改变出库剩余数量
		$result = Db::name('drug_warehouse_into')->where( $condition1 )->update( $data1 );

		if($res){
			self::returnMsg(200,'修改成功');

		}else{
			self::returnMsg(201,'修改失败');
		}

	}

	/*
	 * 驳回解决并通知
	 */
	public function rejectToHos()
	{
		$params = input('');
		$outId = $params['outId'];
		//改变出库状态
		$res = Db::name('drug_warehouse_out')->where(['id'=>$outId])->update(['state'=>0]);
		if($res){
			self::returnMsg(200,'发送成功');
		}else{
			self::returnMsg(200,'发送失败');
		}
	}

	/*
	 * 票据拼接组合
	 * @params Array $images
	 * @return String $str
	 */
	protected function ticketUrlSplice($images)
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

	

}