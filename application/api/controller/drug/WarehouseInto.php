<?php
namespace app\api\controller\drug;

use think\Controller;
use think\Request;
use app\api\controller\Send;
use app\api\controller\Api;
use think\Db;
use lib\FileUpload;

/*
 * 经营企业入库相关业务操作
 */

class WarehouseInto extends Api
{
	use Send;
	/*
	 * 入库删除
	 */
	public function delDrugInto()
	{
		$params = input('');
		$condition['id'] = $params['intoid'];
		$data['delete_flag'] = 1;
		$data['delete_time'] = date('Y-m-d H:i:s',time());
		try{
			$res = Db::table('drug_warehouse_into')->where( $condition )->update($data);
			self::returnMsg(200,'success',$res);

		}catch(Exception $e){

			self::returnMsg(201,'系统有误，稍后再试');
		}
		
	}

	/*
	 * 入库修改
	 */
	public function editDrugInto()
	{
		$params = input('');
		$into_time = $params['editIntoTime'];
		

		$condition['id'] = $params['editIntoID'];
		$data['into_number'] = $params['editIntoNumber'];
		$data['into_count'] = $params['editIntoCount'];

		if($params['editOrginType']==1){

			$data['surplus_amount'] = $params['allotCount'] > 0 ? $params['allotCount'] : 0;

		}else{
			
			$data['surplus_amount'] = $params['editIntoCount'];
		}
		
		$data['into_time'] = $into_time;
		$data['from_type'] = $params['editOrginType'];
		$data['last_update_time'] = date('Y-m-d H:i:s',time());
		$data['picture_type'] = $params['editTicketType'];
		try{
			$res = Db::table('drug_warehouse_into')->where( $condition )->update($data);
			if($res){
				self::returnMsg(200,'success',$res);

			}else{

				throw new Exception("error", 1);
				
			}

		}catch(Exception $e){
			self::returnMsg(201,'修改失败');

		}

	}

	/*
	 * 获取本企生产企业
	 */
	public function getProducerList()
	{
		$params = input('');
		$companyId = $params['companyID'];
		$pageIndex = $params['pageIndex'];	//显示第几页
		$value = isset($params['value'])?$params['value']:'';
		if( !empty($value) ){
			$condition1[]=['su.name','like','%'.$value.'%'];
			$condition1[]=['su.name_pinyin','like','%'.strtoupper($value).'%'];

		}else{
			$condition1=[];
		}

		$alias = [ 'drug_system'=>'ds','drug_sales_enterprise'=>'dh','supplier'=>'su'];
		$join= [['drug_system','ds.system_number=dh.drug_number'],['supplier','ds.supplier_id=su.id']];

		$condition[]=['dh.enterprise_id','=',$companyId];
		$condition[]=['dh.delete_flag','=',2];
		$condition[]=['dh.state','=',1];
		$where = $condition;
		//筛选判断
		$res = Db::table('drug_sales_enterprise')->alias($alias)->join($join)->where($where)->where(function($query) use ($condition1){
			$query->whereOr($condition1);
		})->group('sid')->field('su.id sid,su.name')->limit(($pageIndex-1)*30,30)->orderRaw("convert(su.name using gbk) asc")->select();	

		self::returnMsg(200,'success',$res);
	}

	/*
	 * 获取本企业某一生产厂家的国产药品信息
	 */
	public function getDrugList()
	{
		$params = input('');
		$companyId = $params['companyID'];
		$pageIndex = $params['pageIndex'];	//显示第几页
		$sid = $params['sid'];
		switch ($params['agentType']) {
			case 1:
				$domestic = 2;
				break;
			case 2:
				$domestic = 1;
				break;
			
			default:
				$domestic = 0;
				break;
		}

		$value = isset($params['value'])?$params['value']:'';
		if( !empty($value) ){
			$condition1[]=['ds.generic_name','like','%'.$value.'%'];
			$condition1[]=['ds.generic_name_pinyin','like','%'.strtoupper($value).'%'];

		}else{
			$condition1=[];
		}

		$alias = [ 'drug_system'=>'ds','drug_sales_enterprise'=>'dh'];
		$join= [['drug_system','ds.system_number=dh.drug_number']];

		$condition[]=['dh.enterprise_id','=',$companyId];
		$condition[]=['dh.delete_flag','=',2];
		$condition[]=['dh.state','=',1];

		if(!empty($domestic)){
			$condition[]=['ds.domestic','=',$domestic];
		}

		$condition[]=['ds.supplier_id','=',$sid];
		$condition[]=['ds.source','in',[1,2]];
		$where = $condition;

		//筛选判断
		$res = Db::table('drug_sales_enterprise')->alias($alias)->join($join)->where($where)->where(function($query) use ($condition1){
			$query->whereOr($condition1);
		})->field('ds.*,dh.drug_prices')->group('ds.id')->orderRaw("convert(ds.generic_name using gbk) asc")->limit(($pageIndex-1)*30,30)->select();	

		self::returnMsg(200,'success',$res);
	}

	/*
	 * //获取集团公司未调拨的入库药品
	 */
	public function getGroupDrug()
	{
		$params = input('');
		
		$companyId = $params['companyId'];
		$pageIndex = $params['pageIndex'];	//显示第几页

		//搜索判断
		$value = isset($params['value'])?$params['value']:'';
		if( !empty($value) ){
			$condition1[]=['ds.generic_name','like','%'.$value.'%'];
			$condition1[]=['ds.generic_name_pinyin','like','%'.strtoupper($value).'%'];

		}else{
			$condition1=[];
		}

		$alias = [ 'drug_system'=>'ds','drug_warehouse_into'=>'dwi'];
		$join= [['drug_system','dwi.drug_number=ds.system_number']];

		$condition[]=['dwi.company_id','=',$companyId];
		$condition[]=['dwi.from_type','=',1];
		$condition[]=['dwi.allot_count','=',0];
		$condition[]=['dwi.delete_flag','=',2];
		$condition[]=['dwi.surplus_amount','=',0];

		$res = Db::name('drug_warehouse_into')->alias($alias)->join($join)->where($condition)->where(function($query) use ($condition1){
			$query->whereOr($condition1);
		})->field('ds.*,dwi.into_number,dwi.into_time,dwi.into_count,dwi.picture_type,dwi.creation_time c_time,dwi.id into_id')->order("c_time desc")->limit(($pageIndex-1)*30,30)->select();

		//重组数据
		foreach ($res as $key => $value) {
			$res[$key]['into_time'] = date('Y-m-d',strtotime($value['into_time']));
		}
		self::returnMsg(200,'success',$res);
		
	}

	/*
	 * 检查入库药品是否存在药检报告
	 */
	public function checkDrugReport()
	{
		$params = input('');
		$condition['batch_number'] = $params['batchNumber'];
		$condition['drug_id'] = $params['drugId'];
		$condition['delete_flag'] = 1;
		$res = Db::table('drug_report')->where($condition)->field('id,picture_paths')->find();
		if($res){
			self::returnMsg(200,'success',$res);
		}else{
			self::returnMsg(200,'success',['id'=>'','picture_paths'=>'']);
		}
	}

	/*
	 * 检查入库药品是否存在国产、进口总代证明
	 */
	public function checkAgentExist()
	{
		$params = input('');
		$condition['drug_number'] = $params['drugNumber'];
		switch ($params['domestic']) {
			case '1':
				$condition['domestic'] = 2;
				break;
			
			default:
				$condition['domestic'] = 1;
				break;
		}
		$condition['status'] = 1;
		$condition['delete_flag'] = 1;
		$res = Db::table('drug_agency')->where($condition)->field('id,agency_photo')->find();
		if($res){
			self::returnMsg(200,'success',$res);
		}else{
			self::returnMsg(200,'success',['id'=>'','agency_photo'=>'']);
		}
	}

	/*
	 * 检查入库药品是否存科工贸证明
	 */
	public function checkTradeExist()
	{
		$params = input('');
		$condition['supplier_id'] = $params['sid'];
		$condition['status'] = 1;
		$condition['delete_flag'] = 1;
		$res = Db::table('drug_production_trade')->where($condition)->field('id,productionTrade_photo')->find();
		if($res){
			self::returnMsg(200,'success',$res);
		}else{
			self::returnMsg(201,'请上传科工贸证明');
		}
	}

	/*
	 * 获取本企业集团公司
	 */
	public function getGroupCompany()
	{
		$params = input('');
		$condition['company_id'] = $params['companyId'];
		$condition['status'] = 1;
		$condition['delete_flag'] = 0;
		$res = Db::name('relation_company')->where($condition)->field('id,relation_company_name name')->select();
		
		//组合数据
		if(!empty($res)){
			$arr = [];
			foreach ($res as $key => $value) {
				$arr[] = [
					'value' => $value['id'],
					'text' => $value['name']
				];
			}
			self::returnMsg(200,'success',$arr);
		}else{
			self::returnMsg(201,'请添加集团企业');
		}
		
	}

	
	/*
	 * 上传随货同行单、发票、总代证明、科工贸、集团关系等
	 */
	public function uploadTicket()
	{
		$cid = input('cid');
		$text = input('text');
		$file = (new FileUpload())->file('upload');

		$filedir = 'appupload/'.date('Ymd').'/'.$cid;
		$dir = config('save_root').'uploads/'.$filedir;

		$info = $file->validate(['size'=>1024*1024*8,'ext'=>'jpg,png,jpeg'])->move($dir,$text);

		if($info){
			$filename = $file->getSaveName();
			self::uploadBack(200,['url'=>config('ticket_url').'/'.$filedir.'/'.$filename,'save'=>$filedir.'/'.$filename]);

		}else{
			$error = $file->getError();
			self::uploadBack(201,['err'=>$error]);
		}


	}

	/*
	 * 药品来源于生产企业入库操作
	 */
	public function warehouseIntoFromPro()
	{
		$params = input('');
		//组合数据
		//拼接随货同行单、发票
		$billImage = $this->ticketUrlSplice($params['billImage']);			//随货同行单
		$invoiceImage = $this->ticketUrlSplice($params['invoiceImage']);	//发票

		$billImage1 = $this->ticketUrlSplice($params['billImage1']);			//随货同行单-三票制第二票
		$invoiceImage1 = $this->ticketUrlSplice($params['invoiceImage1']);	//发票-三票制第二票

		$companyId = $params['accountInfo']['userid'];		//经营企业id			
		$userid = $params['accountInfo']['id'];				//用户id
		$sid = $params['currentPro']['sid'];				//生产厂家id
		$sendNumber = $params['sendNumber'];				//发货单号
		$intoTime = $params['intoTime'];					//入库日期
		$pictureType = $params['ticketType'];				//票制类型
		$creationTime = date('Y-m-d H:i:s');				//创建时间、更新时间
		$fromType = 0;										//来源类型：0、生产企业 1、集团公司

		//判断一票状态
		$photoState = 0;									//一票情况
		if(!empty($billImage) && !empty($invoiceImage)){
			$photoState = 1;
		}elseif(empty($billImage) && !empty($invoiceImage)){
			$photoState = 2;
		}elseif(!empty($billImage) && empty($invoiceImage)){
			$photoState = 3;
		}

		//获取药品清单
		$drugs = $params['drugs'];
		$data = [];

		foreach ($drugs as $key => $value) {
			$reportId = $value['reportId'];
			$reportUrl = $value['reportUrl'];
			$batchNumber = $value['batch_number'];
			$info = ['user_id'=>$userid,'role_id'=>$params['accountInfo']['roleid'],'dept_id'=>$companyId,'drug_id'=>$value['id'],'batch_number'=>$batchNumber];
			$reportId = $this->getReportId($reportId,$reportUrl,$info,$companyId);
			$data[] = [
				'company_id' =>	$companyId,
				'user_id' => $userid,
				'sid' => $sid,
				'report_id' => $reportId,
				'send_number' => $sendNumber,
				'drug_number' => $value['system_number'],
				'into_number' => $batchNumber,
				'into_time' => date('Y-m-d H:i:s',strtotime($intoTime)),
				'into_count' => $value['into_count'],
				'surplus_amount' => $value['into_count'],
				'out_photo' => $billImage,
				'invoice_photo' => $invoiceImage,
				'out_photo_2' => $billImage1,
				'invoice_photo_2' => $invoiceImage1,
				'picture_type' => $pictureType,
				'photo_state' => $photoState,
				'from_type' => $fromType,
				'sort_flag' => 2,
				'state' => 0,
				'creation_time' => date('Y-m-d H:i:s'),
				'last_update_time' => date('Y-m-d H:i:s')
			];
		}

		//入库添加
		$result = Db::name('drug_warehouse_into')->insertAll( $data );
		if( $result > 0){
			self::returnMsg(200,'success',$result);
		}else{
			self::returnMsg(201,'入库添加失败');
		}

	}

	/*
	 * 药品来源于可视生产企业入库操作
	 */
	public function warehouseIntoFromAsPro()
	{
		$params = input('');
		//组合数据

		//拼接随货同行单、发票、科工贸证明
		$billImage = $this->ticketUrlSplice($params['billImage']);			//随货同行单
		$invoiceImage = $this->ticketUrlSplice($params['invoiceImage']);	//发票

		$billImage1 = $this->ticketUrlSplice($params['billImage1']);			//随货同行单-三票制第二票
		$invoiceImage1 = $this->ticketUrlSplice($params['invoiceImage1']);	//发票-三票制第二票


		$companyId = $params['accountInfo']['userid'];		//经营企业id			
		$userid = $params['accountInfo']['id'];				//用户id
		$sid = $params['currentPro']['sid'];				//生产厂家id
		$sendNumber = $params['sendNumber'];				//发货单号
		$intoTime = $params['intoTime'];					//入库日期
		$pictureType = $params['ticketType'];				//票制类型
		$creationTime = date('Y-m-d H:i:s');				//创建时间、更新时间
		$fromType = 0;										//来源类型：0、生产企业 1、集团公司

		//判断一票状态
		$photoState = 0;									//一票情况
		if(!empty($billImage) && !empty($invoiceImage)){
			$photoState = 1;
		}elseif(empty($billImage) && !empty($invoiceImage)){
			$photoState = 2;
		}elseif(!empty($billImage) && empty($invoiceImage)){
			$photoState = 3;
		}

		$agentType = $params['agentType'];					//总代类型 1、进口总代 2、国产总代 3、科工贸一体总代
		switch ($agentType) {
			case '1':
				# 进口
				$agentData['domestic'] = 2;
				break;
			case '2':
				# 国产
				$agentData['domestic'] = 1;
				break;

			case '3':

				if( !empty($params['tradeImage']) && !empty($params['tradeInfo']) ){
					$tradeImage = $this->ticketUrlSplice($params['tradeImage']);
					$tradeAgentCompany = $params['tradeInfo']['agentCompany'];		//科工贸代理企业

					//添加科工贸企业
					$tradeData['user_id'] = $userid;
					$tradeData['supplier_id'] = $sid;
					$tradeData['supplier_name'] = $params['currentPro']['name'];
					$tradeData['agent_company'] = $tradeAgentCompany;
					$tradeData['productionTrade_photo'] = $tradeImage;
					$tradeData['status'] = 0;
					$tradeData['created'] = date('Y-m-d H:i:s');
					$tradeData['updated'] = date('Y-m-d H:i:s');
					try{
						$tradeInsert = Db::name('drug_production_trade')->insert( $tradeData );

					}catch(\Exception $e){
						self::returnMsg(201,'已存在该科工贸证明,请等待审核');
						
					}

				}

			break;
		}

		
		//获取药品清单
		$drugs = $params['drugs'];
		$data = [];

		foreach ($drugs as $key => $value) {

			//添加药检报告
			$reportId = $value['reportId'];
			$reportUrl = $value['reportUrl'];
			$batchNumber = $value['batch_number'];
			$info = ['user_id'=>$userid,'role_id'=>$params['accountInfo']['roleid'],'dept_id'=>$companyId,'drug_id'=>$value['id'],'batch_number'=>$batchNumber];
			$reportId = $this->getReportId($reportId,$reportUrl,$info,$companyId);
			
			//添加国产、进口总代证明
			$agentId = isset($value['agentId'])?$value['agentId']:null;
			$agentUrl = isset($value['agentUrl'])?$value['agentUrl']:null;
			if( empty($agentId) && !empty($agentUrl)){
				$agentInfo = $value['agentInfo'];
				$agentData['user_id'] = $userid;
				$agentData['drug_number'] = $value['system_number'];
				$agentData['agent_company'] = $agentInfo['agentCompany'];
				$agentData['agency_photo'] = $agentUrl;
				$agentData['expiry_type'] = $agentInfo['valideType'];
				if($agentInfo['valideType'] == 1){
					$agentData['expiry_date'] = $agentInfo['valideTime'];
				}
				$agentData['status'] = 0;
				$agentData['created'] = date('Y-m-d H:i:s');
				$agentData['updated'] = date('Y-m-d H:i:s');
				$agentInsert = Db::name('drug_agency')->insert( $agentData );
			}

			$data[] = [
				'company_id' =>	$companyId,
				'user_id' => $userid,
				'sid' => $sid,
				'report_id' => $reportId,
				'send_number' => $sendNumber,
				'drug_number' => $value['system_number'],
				'into_number' => $batchNumber,
				'into_time' => date('Y-m-d H:i:s',strtotime($intoTime)),
				'into_count' => $value['into_count'],
				'surplus_amount' => $value['into_count'],
				'out_photo' => $billImage,
				'invoice_photo' => $invoiceImage,
				'out_photo_2' => $billImage1,
				'invoice_photo_2' => $invoiceImage1,
				'picture_type' => $pictureType,
				'photo_state' => $photoState,
				'from_type' => $fromType,
				'state' => 0,
				'sort_flag'	=>2,
				'creation_time' => date('Y-m-d H:i:s'),
				'last_update_time' => date('Y-m-d H:i:s')
			];
		}

		//入库添加
		$result = Db::name('drug_warehouse_into')->insertAll( $data );
		if( $result > 0){
			self::returnMsg(200,'success',$result);
		}else{
			self::returnMsg(201,'入库添加失败');
		}

	}

	/*
	 * 药品来源于集团公司入库操作
	 */
	public function warehouseIntoFromGroup()
	{
		$params = input('');
		//组合数据
		//拼接随货同行单、发票
		$billImage = $this->ticketUrlSplice($params['billImage']);			//随货同行单
		$invoiceImage = $this->ticketUrlSplice($params['invoiceImage']);	//发票

		$billImage1 = $this->ticketUrlSplice($params['billImage1']);			//随货同行单-三票制第二票
		$invoiceImage1 = $this->ticketUrlSplice($params['invoiceImage1']);	//发票-三票制第二票

		$companyId = $params['accountInfo']['userid'];		//经营企业id			
		$userid = $params['accountInfo']['id'];				//用户id
		$sid = $params['currentPro']['sid'];				//生产厂家id
		$sendNumber = $params['sendNumber'];				//发货单号
		$intoTime = $params['intoTime'];					//入库日期
		$pictureType = $params['ticketType'];				//票制类型
		$creationTime = date('Y-m-d H:i:s');				//创建时间、更新时间
		$fromType = 1;										//来源类型：0、生产企业 1、集团公司

		//判断一票状态
		$photoState = 0;									//一票情况
		if(!empty($billImage) && !empty($invoiceImage)){
			$photoState = 1;
		}elseif(empty($billImage) && !empty($invoiceImage)){
			$photoState = 2;
		}elseif(!empty($billImage) && empty($invoiceImage)){
			$photoState = 3;
		}

		//获取药品清单
		$drugs = $params['drugs'];
		$data = [];

		foreach ($drugs as $key => $value) {
			$reportId = $value['reportId'];
			$reportUrl = $value['reportUrl'];
			$batchNumber = $value['batch_number'];
			$info = ['user_id'=>$userid,'role_id'=>$params['accountInfo']['roleid'],'dept_id'=>$companyId,'drug_id'=>$value['id'],'batch_number'=>$batchNumber];
			$reportId = $this->getReportId($reportId,$reportUrl,$info,$companyId);
			$data[] = [
				'company_id' =>	$companyId,
				'user_id' => $userid,
				'sid' => $sid,
				'report_id' => $reportId,
				'send_number' => $sendNumber,
				'drug_number' => $value['system_number'],
				'into_number' => $batchNumber,
				'into_time' => date('Y-m-d H:i:s',strtotime($intoTime)),
				'into_count' => $value['into_count'],
				'surplus_amount' => $value['into_count'],
				'out_photo' => $billImage,
				'invoice_photo' => $invoiceImage,
				'out_photo_2' => $billImage1,
				'invoice_photo_2' => $invoiceImage1,
				'picture_type' => $pictureType,
				'photo_state' => $photoState,
				'from_type' => $fromType,
				'sort_flag' => 2,
				'state' => 0,
				'creation_time' => date('Y-m-d H:i:s'),
				'last_update_time' => date('Y-m-d H:i:s')
			];
		}

		//入库添加
		$result = Db::name('drug_warehouse_into')->insertAll( $data );
		if( $result > 0){
			self::returnMsg(200,'success',$result);
		}else{
			self::returnMsg(201,'入库添加失败');
		}

	}

	/*
	 * 添加集团企业
	 */
	public function addGroupCompany()
	{
		$params = input('');
		
		$data['relation_attest'] = $this->ticketUrlSplice($params['groupImage']);
		$data['company_id'] = $params['accountInfo']['userid'];
		$data['user_id'] = $params['accountInfo']['id'];

		$data['status'] = 0;
		$data['relation_company_name'] = $params['groupCompany'];
		$data['create_time'] = date('Y-m-d H:i:s');

		$data['update_time'] = date('Y-m-d H:i:s');
		$res = Db::name('relation_company')->insert( $data );

		if($res){
			self::returnMsg(200,'success');
		}else{
			self::returnMsg(201,'fail');
		}

	}

	/*
	 * 添加内部调拨入库操作
	 */
	public function warehouseIntoFromAllot()
	{
		$params = input('');
		//组合数据
		//拼接内部调拨票
		$allotImage = $this->ticketUrlSplice($params['allotImage']);

		$companyId = $params['accountInfo']['userid'];		//经营企业id			
		$userid = $params['accountInfo']['id'];				//用户id
		$rid = $params['groupId'];							//集团公司表id
		$rname = $params['groupCompany'];					//集团公司名称
		$allotNumber = $params['sendNumber'];				//发货单号
		$invoiceTime = $params['invoiceTime'];					//开票日期
		$creationTime = date('Y-m-d H:i:s');				//创建时间、更新时间

		//添加内部调拨单
		$data['company_id'] = $companyId;
		$data['user_id'] = $userid;
		$data['relation_company_id'] = $rid;
		$data['rcompany_name'] = $rname;
		$data['allot_number'] = $allotNumber;
		$data['invoice_date'] = $invoiceTime;
		$data['allot_ticket_src'] = $allotImage;
		$data['created'] = $creationTime;
		$data['updated'] = $creationTime;

		$allotId = Db::name('drug_ticket_allot')->insertGetId($data);
		//添加失败返回
		if(!$allotId){
			self::returnMsg(201,'添加失败');
		}

		//获取集团入库药品清单
		$drugs = $params['drugs'];
		$right= 0;
		$fail = 0;
		$data = [];
		foreach ($drugs as $key => $value) {
			$allotCount = $value['allot_count'];
			$condition['id'] = $value['into_id'];
			$data['allot_count'] =	$allotCount;
			$data['ticket_allot_id'] = $allotId;
			$data['surplus_amount'] = $allotCount;
			$data['last_update_time'] = date('Y-m-d H:i:s');
			$res = Db::name('drug_warehouse_into')->where($condition)->update($data);
			if(!$res){
				++$fail;
				continue;
			}
			++$right;
		}

		if( $right > 0){
			self::returnMsg(200,'',['right'=>$right,'fail'=>$fail]);
		}else{
			self::returnMsg(201,'内部调拨失败');
		}

	}

	/*
	 * 获取药检报告id
	 * @params String $reportId
	 * @params String $reportUrl
	 * @params Array $info
	 * @return String $id
	 */
	protected function getReportId($reportId,$reportUrl,$info,$companyId)
    {
	    $id = 0;
	    if(!empty($reportId) && !empty($reportUrl)){
	      $id = $reportId;
	    }

	    if( empty($reportId) && !empty($reportUrl) ){
	      //插入到药检报告表
	      $info['picture_paths'] = $reportUrl;
	      $info['created'] = date('Y-m-d H:i:s');
	      $info['updated'] = date('Y-m-d H:i:s');

	      //获取药检报告剩余数量，如果没有则无法上传药检报告
	      //获取钱包
	      $wallet = Db::name('pay_wallet')->where(['owner_id'=>$companyId])->find();
	      if( $wallet['owner_state'] == 2 ){
	        //非试用期，需扣药检报告上传条数
	        //获取还剩余条数的订单
	        $condition[] =['wallet_id','=',$wallet['id']];
	        $condition[] = ['state_flag','=',2];
	        $condition[] =['report_surplus_amount','>',0];
	        $result = Db::name('pay_order')->where( $condition )->field('id,report_surplus_amount')->order('out_expiration_time ASC')->find();
	        
	        if( !empty($result) ){
	          //存在剩余数量，则扣除一条
	          Db::name('pay_order')->where([ 'id' => $result['id'] ])->setDec('report_surplus_amount');
	          //添加药检报告
	          $reportId = Db::name('drug_report')->insertGetId( $info );
	          $id = $reportId;

	        }else{
	          //没有剩余条数,则不能出库
	          $id = 0;
	        }
	        
	      }else{
	        //试用期客户，无需扣条数
	        //添加药检报告
	        $reportId = Db::name('drug_report')->insertGetId( $info );
	        $id = $reportId;
	      }

	    }

	    return $id;
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

	/*
	 * 删除上传的票据
	 */
	public function deleteTicket()
	{
		$params = input('');
		try{
			$filedir = iconv('utf-8', 'gbk', config('save_root').'/uploads/'.$params['url']);
			
			if(file_exists($filedir)){
				$unlink = unlink( $filedir );
			}

		}catch(Exception $e){
			self::returnMsg(200,'success');
		}
		
		self::returnMsg(200,'success');

		
		
	}









}