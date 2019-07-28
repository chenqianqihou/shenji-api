<?php

namespace app\modules\api\controllers;

use app\classes\BaseController;
use app\classes\ErrorDict;
use app\models\UserDao;
use app\service\OrganizationService;
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

}
