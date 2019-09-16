<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 用户登录
Route::rule('/user/login','api/user.User/login');
// 短信验证码
Route::rule('/user/smsCode','api/user.User/sendSms');
//注册客服端clientid
Route::rule('/user/saveClientId','api/user.User/saveClientId');



// 获取挂网药品
Route::rule('/drug/getNetDrug','api/drug.NetDrug/index');
//获取本院药品
Route::rule('/drug/getHosDrug','api/drug.HosDrug/index');
//获取本院票据
Route::rule('/drug/getHosTicket','api/drug.HosTicket/index');
Route::rule('/drug/getHosCompany','api/drug.HosTicket/getHosCompany');
Route::rule('/drug/getDetailTicket','api/drug.HosTicket/getDetailTicket');
Route::rule('/drug/checkTicket','api/drug.HosTicket/checkTicket');

//获取本企药品
Route::rule('/drug/getComDrug','api/drug.ComDrug/index');
//本企入库药品
Route::rule('/drug/getComTicket','api/drug.ComTicket/index');
//查看单个入库药品票据
Route::rule('/drug/getIntoTicket','api/drug.ComTicket/intoTicket');
Route::rule('/drug/delDrugInto','api/drug.WarehouseInto/delDrugInto');
Route::rule('/drug/editDrugInto','api/drug.WarehouseInto/editDrugInto');
// 上传票据
Route::rule('/drug/uploadTicket','api/drug.WarehouseInto/uploadTicket');

// 查询生产企业
Route::rule('/drug/getProducerList','api/drug.WarehouseInto/getProducerList');
Route::rule('/drug/getDrugList','api/drug.WarehouseInto/getDrugList');
Route::rule('/drug/checkDrugReport','api/drug.WarehouseInto/checkDrugReport');
Route::rule('/drug/deleteTicket','api/drug.WarehouseInto/deleteTicket');
Route::rule('/drug/warehouseIntoFromPro','api/drug.WarehouseInto/warehouseIntoFromPro');
Route::rule('/drug/checkTradeExist','api/drug.WarehouseInto/checkTradeExist');
Route::rule('/drug/checkAgentExist','api/drug.WarehouseInto/checkAgentExist');
Route::rule('/drug/warehouseIntoFromAsPro','api/drug.WarehouseInto/warehouseIntoFromAsPro');
Route::rule('/drug/warehouseIntoFromGroup','api/drug.WarehouseInto/warehouseIntoFromGroup');
Route::rule('/drug/getGroupCompany','api/drug.WarehouseInto/getGroupCompany');
Route::rule('/drug/addGroupCompany','api/drug.WarehouseInto/addGroupCompany');
Route::rule('/drug/getGroupDrug','api/drug.WarehouseInto/getGroupDrug');
Route::rule('/drug/warehouseIntoFromAllot','api/drug.WarehouseInto/warehouseIntoFromAllot');

// 出库
Route::rule('/drug/getComOut','api/drug.WarehouseOut/getComOut');
Route::rule('/drug/getHospital','api/drug.WarehouseOut/getHospital');
Route::rule('/drug/delDrugOut','api/drug.WarehouseOut/delDrugOut');
Route::rule('/drug/editDrugOut','api/drug.WarehouseOut/editDrugOut');
Route::rule('/drug/getRejectOut','api/drug.WarehouseOut/getRejectOut');
Route::rule('/drug/rejectToHos','api/drug.WarehouseOut/rejectToHos');
Route::rule('/drug/getOutDrug','api/drug.WarehouseOut/getOutDrug');
Route::rule('/drug/warehouseOut','api/drug.WarehouseOut/warehouseOut');

// 药检报告
Route::rule('/drug/reportList','api/report.Index/reportList');
Route::rule('/drug/getReportDrug','api/report.Index/getReportDrug');
Route::rule('/drug/addReport','api/report.Index/addReport');
Route::rule('/drug/delReport','api/report.Index/delReport');

// 药品采购
Route::rule('/drug/getShortage','api/shortage.Index/getShortage');
Route::rule('/drug/proNetDrug','api/shortage.Index/proNetDrug');
Route::rule('/drug/reSubmit','api/shortage.Index/reSubmit');
Route::rule('/drug/checkProvide','api/shortage.Index/checkProvide');
Route::rule('/drug/isProvide','api/shortage.Index/isProvide');








// 客服聊天
Route::rule('/chat/chatImgUpload','chat/Index/chatImgUpload');
Route::rule('/chat/saveMessage','chat/Index/saveMessage');
Route::rule('/chat/changeIsRead','chat/Index/changeIsRead');
Route::rule('/chat/checkNoneUser','chat/Index/checkNoneUser');
Route::rule('/chat/checkAdminId','chat/Index/checkAdminId');
Route::rule('/chat/loadMessageList','chat/Index/loadMessageList');
Route::rule('/chat/getChatList','chat/Index/getChatList');
Route::rule('/chat/deleteRecored','chat/Index/deleteRecored');
Route::rule('/chat/searchUser','chat/Index/searchUser');
Route::rule('/chat/checkRelationType','chat/Index/checkRelationType');
Route::rule('/chat/userAddRequst','chat/Index/userAddRequst');
Route::rule('/chat/getUserList','chat/Index/getUserList');
Route::rule('/chat/getNewFriend','chat/Index/getNewFriend');
Route::rule('/chat/checkUser','chat/Index/checkUser');
Route::rule('/chat/newRecordDel','chat/Index/newRecordDel');
Route::rule('/chat/friendDel','chat/Index/friendDel');

Route::rule('/push/pushTest','chat/Index/pushTest');





//所有路由匹配不到情况下触发该路由
Route::miss('\app\api\controller\Exception::miss');
