<?php

namespace app\modules\api\controllers;

use app\classes\BaseController;
use app\classes\ErrorDict;
use app\models\UserDao;
use app\service\OrganizationService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use Yii;

class OrganizationController extends BaseController
{

    public function actionAdd()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array ();
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $params = $this->getParams();

        $organService = new OrganizationService();
        $checkres = $organService->checkParams( $params );
        if( !$checkres['res']){
            $error = ErrorDict::getError(ErrorDict::G_PARAM);
            $ret = $this->outputJson($checkres, $error);
            return $ret;
        }

        $addres = $organService->insertOrganization( $params );
        if( !$addres['res']){
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson($addres, $error);
            return $ret;
        }

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($addres, $error);
        return $ret;
    }

    public function actionAddbyname()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'pid' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'name' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $pid = $this->getParam('pid');
        $name = $this->getParam('name');

        $organService = new OrganizationService();
        //检查pid是否存在
        $porgancount = $organService->getOrganizationCount( $pid );
        if( $porgancount <= 0 ){
            $error = ErrorDict::getError(ErrorDict::G_PARAM);
            $error['returnMessage'] = '母机构不存在';
            $ret = $this->outputJson([], $error);
            return $ret;
        }
        $porgan = $organService->getOrganizationInfo( $pid );
        unset( $porgan['id']);
        unset( $porgan['ctime']);
        unset( $porgan['utime']);
        $porgan['name'] = $name;
        $porgan['parentid'] = $pid;

        $addres = $organService->insertOrganization( $porgan );
        if( !$addres['res']){
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson($addres, $error);
            return $ret;
        }

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($addres, $error);
        return $ret;
    }

    public function actionMultiadd()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array ();
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $params = $this->getParams();

        $organService = new OrganizationService();
        foreach( $params as $p){
            $checkres = $organService->checkParams( $p );
            if( !$checkres['res']){
                $error = ErrorDict::getError(ErrorDict::G_PARAM);
                $ret = $this->outputJson($checkres, $error);
                return $ret;
            }
        }

        foreach( $params as $p){
            $addres = $organService->insertOrganization( $p );
            if( !$addres['res']){
                $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
                $ret = $this->outputJson($addres, $error);
                return $ret;
            }
        }

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson(true, $error);
        return $ret;
    }

    public function actionInfo() {
        $this->defineMethod = 'GET';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );

        $oid = $this->getParams('id');
        $organService = new OrganizationService();
        $organInfo = $organService->getOrganizationInfo( $oid );
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($organInfo, $error);
        return $ret;
    }

    public function actionUpdate()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'oid' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $params = $this->getParams();

        $organService = new OrganizationService();
        /*
        $checkres = $organService->checkParams( $params );
        if( !$checkres['res']){
            $error = ErrorDict::getError(ErrorDict::G_PARAM);
            $ret = $this->outputJson($checkres, $error);
            return $ret;
        }
        */

        $updres = $organService->updateOrganization( $params );
        if( !$updres['res']){
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson($updres, $error);
            return $ret;
        }

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($updres, $error);
        return $ret;
    }

    public function actionSearch()
    {
        $this->defineMethod = 'GET';
        $this->defineParams = array ();
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $keyword = $this->getParam('key','');
        $otype = intval($this->getParam('type',-1));
        $start = $this->getParam('start',0);
        $length = $this->getParam('length',10);
        $organService = new OrganizationService();
        $organList = $organService->getOrganizationList( $keyword,$otype,$start,$length );
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($organList, $error);
        return $ret;
    }

    public function actionDelete()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'oid' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $organService = new OrganizationService();
        foreach( $this->getParam('oid') as $oid ){
            if( ! is_numeric($oid) ){
                continue;    
            }    
            if( $organService->numberPeopleBelong($oid) > 0 ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM);
                $error['returnMessage'] = '该机构下有人员，无法删除';
                $ret = $this->outputJson("$oid has people , can not be deleted.", $error);
                return $ret;
            }
        }

        $res = $organService->deleteOrganizations( $this->getParams());
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($res, $error);
        return $ret;
    }

    public function actionList() {
        $this->defineMethod = 'GET';
        $this->defineParams = array ();
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $organService = new OrganizationService();
           
        $result = [];
        $distinct = $this->getDistrictRervMap( 520000 );

        //机关机构
        $threelist = $organService->getOrganizationListByType(3);
        $useParArr = [];
        foreach( $threelist as $tk=>$tr ){
            $parid = $tr['parentid'];
            if($parid > 0){
                if( !isset($useParArr[$parid])){
                    $useParArr[$parid] = [];    
                }
                $useParArr[$parid][$tr['id']] = ['id'=>$tr['id'],'name'=>$tr['name'],'type'=>'child','data'=>$tr,'list'=>[]];
                unset( $threelist[$tk] );
            }
        }
        foreach( $threelist as $tr ){
            $regnum = trim($tr['regnum'],',');    
            $regArr = explode(',', $regnum);
            $r1 = intval($regArr[count($regArr)-1] / 100) * 100;

            $usePList = isset( $useParArr[$tr['id']]) ? $useParArr[$tr['id']] : [];
            if( $regnum == $distinct['id']){
                $distinct['list'][ $tr['id'] ] = ['id'=>$tr['id'], 'name' => $tr['name'], 'type'=>'parent','data'=>$tr,'list'=>$usePList];
            } else {
                if(isset($distinct['list'][$regnum])){
                    $distinct['list'][ $regnum ]['list'][$tr['id']] = ['id'=>$tr['id'], 'name' => $tr['name'], 'type'=>'parent','data'=>$tr,'list'=>$usePList];
                } else {
                    $distinct['list'][$r1]['list'][ $regnum ]['list'][$tr['id']] = ['id'=>$tr['id'], 'name' => $tr['name'], 'type'=>'parent','data'=>$tr,'list'=>$usePList];
                }
            }
        }
        ksort( $distinct['list'], SORT_NUMERIC );

        $result[] = [
            'type' => 3,
            'list' => ['520000'=>$distinct]
        ];

        //内审机构
        $distinct = $this->getDistrictRervMap( 520000 );
        $twolist = $organService->getOrganizationListByType(2);
        $useParArr = [];
        foreach( $twolist as $tk=>$tr ){
            $parid = $tr['parentid'];
            if($parid > 0){
                if( !isset($useParArr[$parid])){
                    $useParArr[$parid] = [];    
                }
                $useParArr[$parid][$tr['id']] = ['id'=>$tr['id'],'name'=>$tr['name'],'type'=>'child','data'=>$tr,'list'=>[]];
                unset( $twolist[$tk] );
            }
        }

        foreach( $twolist as $tr ){
            $regnum = trim($tr['regnum'],',');    
            $regArr = explode(',', $regnum);
            $r1 = intval($regArr[count($regArr)-1] / 100) * 100;
            $usePList = isset( $useParArr[$tr['id']]) ? $useParArr[$tr['id']] : [];
            if( $regnum == $distinct['id']){
                $distinct['list'][ $tr['id'] ] = ['id'=>$tr['id'], 'name' => $tr['name'], 'type'=>'parent','data'=>$tr,'list'=>$usePList];
            } else {
                if(isset($distinct['list'][$regnum])){
                    $distinct['list'][ $regnum ]['list'][$tr['id']] = ['id'=>$tr['id'], 'name' => $tr['name'], 'type'=>'parent','data'=>$tr,'list'=>$usePList];
                } else {
                    $distinct['list'][$r1]['list'][ $regnum ]['list'][$tr['id']] = ['id'=>$tr['id'], 'name' => $tr['name'], 'type'=>'parent','data'=>$tr,'list'=>$usePList];
                }
            }
        }

        $result[] = [
            'type' => 2,
            'list' => ['520000'=>$distinct]
        ];

        //中介列表
        $onelist = $organService->getOrganizationListByType(1);
        $oneres = [];
        foreach( $onelist as $one){
            $oneres[ $one['id'] ] = ['id'=>$one['id'],'name'=>$one['name'],'type'=>'parent','data'=>$one,'list'=>[]];
        }
        $result[] = [
            'type' => 1,
            'list' => ['zj'=>['name'=>'中介','id'=>1,'type'=>'parent','data'=>[],'list'=>$oneres]]
        ];

        //处理格式
        //一层嵌套处理
        foreach( $result as $rk=>$rv){
            if( isset($rv['list']) ){
                $result[$rk]['list'] = array_values( $rv['list'] );    
            }
        }

        //二层嵌套处理
        foreach( $result as $rk=>$rv){
            if( isset($rv['list']) ){
                foreach( $rv['list'] as $rvk=>$rvv ){
                    if( isset($rvv['list']) ){
                        $result[$rk]['list'][$rvk]['list'] = array_values( $rvv['list'] );    
                    }    
                }
            }
        }

        //三层嵌套处理
        foreach( $result as $rk=>$rv){
            if( isset($rv['list']) ){
                foreach( $rv['list'] as $rvk=>$rvv ){
                    if( isset($rvv['list']) ){
                        foreach( $rvv['list'] as $rvvk=>$rvvv){
                            if(isset($rvvv['list'])){
                                $result[$rk]['list'][$rvk]['list'][$rvvk]['list'] = array_values( $rvvv['list'] );    
                            }    
                        }
                    }    
                }
            }
        }

        //四层嵌套处理
        foreach( $result as $rk=>$rv){
            if( isset($rv['list']) ){
                foreach( $rv['list'] as $rvk=>$rvv ){
                    if( isset($rvv['list']) ){
                        foreach( $rvv['list'] as $rvvk=>$rvvv){
                            if(isset($rvvv['list'])){
                                foreach( $rvvv['list'] as $rvvvk=>$rvvvv){
                                    if(isset($rvvvv['list'])){
                                        $result[$rk]['list'][$rvk]['list'][$rvvk]['list'][$rvvvk]['list'] = array_values( $rvvvv['list'] );    
                                    }    
                                }
                            }    
                        }
                    }    
                }
            }
        }

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($result, $error);
        return $ret;
    }

    public function actionUsers()
    {
        $this->defineMethod = 'GET';
        $this->defineParams = array (
            'organid' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $organid = intval($this->getParam('organid',-1));
        $start = $this->getParam('start',0);
        $length = $this->getParam('length',10);
        $organService = new OrganizationService();
        $organList = $organService->getOrganizationPeopleList( $organid,$start,$length );
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($organList, $error);
        return $ret;
    }

    public function actionDeparts(){
        $this->defineMethod = 'GET';
        $organService = new OrganizationService();
        $organList = $organService->getDeparts();
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($organList, $error);
        return $ret;
    }

    public function actionListbytype() {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'type' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $otype = intval($this->getParam('type', 0));
        if (!isset(UserDao::$type[$otype])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        $organizationService = new OrganizationService();
        $organList = $organizationService->getDepartsByType($otype);
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($organList, $error);

        return $ret;
    }

    //获取机构的下属机构和部门
    public function actionSubordinate()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'organid' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }

        $organid = intval($this->getParam('organid'));

        $organService = new OrganizationService();
        $organCount = $organService->getOrganizationCount( $organid);
        if( $organCount <=0){
            $error = ErrorDict::getError(ErrorDict::G_PARAM,"$organid 此机构不存在");
            $ret = $this->outputJson("$organid is not exists!", $error);
            return $ret;
        }
        $organInfo = $organService->getOrganizationInfo( $organid );
        $objnum = $organInfo['regnum'];

        //机关机构
        $distinct = $this->getDistrictRervMap( 520000 );
        $threelist = $organService->getOrganizationListByType(3);
        $useParArr = [];
        foreach( $threelist as $tk=>$tr ){
            $parid = $tr['parentid'];
            if($parid > 0){
                if( !isset($useParArr[$parid])){
                    $useParArr[$parid] = [];    
                }
                $useParArr[$parid][$tr['id']] = ['id'=>$tr['id'],'name'=>$tr['name'],'type'=>'child','data'=>$tr,'list'=>[]];
                unset( $threelist[$tk] );
            }
        }
        foreach( $threelist as $tr ){
            $regnum = trim($tr['regnum'],',');    
            $regArr = explode(',', $regnum);
            $r1 = intval($regArr[count($regArr)-1] / 100) * 100;

            $usePList = isset( $useParArr[$tr['id']]) ? $useParArr[$tr['id']] : [];
            if( $regnum == $distinct['id']){
                $distinct['list'][ $tr['id'] ] = ['id'=>$tr['id'], 'name' => $tr['name'], 'type'=>'parent','data'=>$tr,'list'=>$usePList];
            } else {
                if(isset($distinct['list'][$regnum])){
                    $distinct['list'][ $regnum ]['list'][$tr['id']] = ['id'=>$tr['id'], 'name' => $tr['name'], 'type'=>'parent','data'=>$tr,'list'=>$usePList];
                } else {
                    $distinct['list'][$r1]['list'][ $regnum ]['list'][$tr['id']] = ['id'=>$tr['id'], 'name' => $tr['name'], 'type'=>'parent','data'=>$tr,'list'=>$usePList];
                }
            }
        }

        $result = [];
        if($objnum == '520000'){
            $result = $distinct;
        } else {
            if( isset($distinct['list'][$objnum]) && isset($distinct['list'][$objnum]['list'][$organid]) ){
                $result = $distinct['list'][$objnum]['list'][$organid];
            } else {
                $result = ['id'=>$organInfo['id'],'name'=>$organInfo['name'],'type'=>'child','data'=>$organInfo,'list'=>[]];    
            }   
        }

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($result, $error);
        return $ret;
    }

    public function actionExcel() {
        $this->defineMethod = 'GET';

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(APP_PATH."/static/jigouluru.xlsx");

        $ss = 2;
        $se = 1100;
        
        for($ss = 2; $ss < 1000;$ss++){

            $spreadsheet->getActiveSheet()->getStyle('H'.$ss) 
                ->getNumberFormat() 
                ->setFormatCode( 
                        \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_YYYYMMDD2
                        ); 
            $validation = $spreadsheet->getActiveSheet()->getCell('H'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_DATE);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('请输入正确的日期格式 ‘2019-06-12’');
            $validation->setPromptTitle('Allowed input');
            $validation->setPrompt('请输入正确的日期格式 ‘2019-06-12’');


            $spreadsheet->getActiveSheet()->getStyle('L'.$ss) 
                ->getNumberFormat() 
                ->setFormatCode( 
                        \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_YYYYMMDD2
                        ); 
            $validation = $spreadsheet->getActiveSheet()->getCell('L'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_DATE);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('请输入正确的日期格式 ‘2019-06-12’');
            $validation->setPromptTitle('Allowed input');
            $validation->setPrompt('请输入正确的日期格式 ‘2019-06-12’');

            $validation = $spreadsheet->getActiveSheet()->getCell('AA'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('Value is not in list.');
            $validation->setPromptTitle('Pick from list');
            $validation->setPrompt('Please pick a value from the drop-down list.');
            $validation->setFormula1('"1:已审核,2:未审核"'); 

        }

        // Redirect output to a client’s web browser (Xlsx)
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="机构批量导入.xlsx"');
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');
        // If you're serving to IE over SSL, then the following may be needed
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        Yii::$app->end();
    }

    public function actionExcelupload() {
        if( empty($_FILES['file']) ){
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson('', $error);
            return $ret;
        }  

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['file']['tmp_name']);
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        unset( $sheetData[1]);
        
        $insertData = [];
        foreach( $sheetData as $data) {
            $tmpdata = [];
            $tmpdata['name'] =$data['B'];
            if( empty($tmpdata['name'])){
                continue;
            }

            $tmpdata['otype'] = explode(':',$data['A'])[0];
            if( empty($tmpdata['otype']) ||!is_numeric($tmpdata['otype']) || !in_array($tmpdata['otype'],[1,2]) ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, '', $data['A'].' 类型格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }

            $tmpdata['deputy'] = trim($data['C']);
            $tmpdata['regtime'] = strtotime($data['H']);
            $shen = explode('_',$data['D']);
            if( count($shen) != 3 || !isset( $districts[$shen[1]] )){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, '', $data['D'].' 地区格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $shi = explode('_',$data['E']);
            if( count($shi) != 3 || !isset( $districts[$shi[1]] )){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, '', $data['E'].' 地区格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $qu = explode('_',$data['F']);
            if( count($qu) != 3 ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, '', $data['F'].' 地区格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $shenstr = $shen[1];
            $shistr = $shi[1];
            $qustr = $qu[1];
            $tmpdata['regnum'] = "$shenstr,$shistr,$qustr";
            $tmpdata['regaddress'] = $data['G'];
            $tmpdata['category'] = $data['I'];
            $tmpdata['level'] = $data['J'];
            $tmpdata['capital'] = intval($data['K']);
            $tmpdata['workbegin'] = strtotime($data['L']);
            $tmpdata['costeng'] = intval($data['M']);
            $tmpdata['coster'] = intval($data['N']);
            $tmpdata['accountant'] = intval($data['O']);
            $tmpdata['highlevel'] = intval($data['P']);
            $tmpdata['midlevel'] = intval($data['Q']);
            $tmpdata['retiree'] = $data['R'];
            $tmpdata['parttimers'] = $data['S'];
            $tmpdata['contactor'] = $data['T'];
            $tmpdata['contactphone'] = $data['U'];
            $tmpdata['contactnumber'] = $data['V'];
            $shen = explode('_',$data['W']);
            if( count($shen) != 3 || !isset( $districts[$shen[1]] )){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, '', $data['W'].' 地区格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $shi = explode('_',$data['X']);
            if( count($shi) != 3 || !isset( $districts[$shi[1]] )){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, '', $data['X'].' 地区格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $qu = explode('_',$data['Y']);
            if( count($qu) != 3 ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, '', $data['Y'].' 地区格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $shenstr = $shen[1];
            $shistr = $shi[1];
            $qustr = $qu[1];
            $tmpdata['officenum'] = "$shenstr,$shistr,$qustr";
            $tmpdata['officeaddress'] = $data['Z'];
            $tmpdata['qualiaudit'] = $data['AA'];
            $tmpdata['parentid'] = '0';

            $insertData[] = $tmpdata;	
        }

        $organService = new OrganizationService();
        foreach($insertData as $params) {
            $checkres = $organService->checkParams( $params );
            if( !$checkres['res']){
                $error = ErrorDict::getError(ErrorDict::G_PARAM);
                $ret = $this->outputJson($checkres, $error);
                return $ret;
            }
        }

        foreach($insertData as $params) {
            $organService->insertOrganization( $params );
        }

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson('', $error);
        return $ret;
    }
}
