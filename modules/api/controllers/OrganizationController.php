<?php

namespace app\modules\api\controllers;

use app\classes\BaseController;
use app\classes\ErrorDict;
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
            'id' => array (
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
            $regnum = $tr['regnum'];    
            $r1 = intval($regnum / 100) * 100;

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
            'list' => $distinct
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
            $regnum = $tr['regnum'];    
            $r1 = intval($regnum / 100) * 100;
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
            'list' => $distinct
        ];

        //中介列表
        $onelist = $organService->getOrganizationListByType(1);
        $oneres = [];
        foreach( $onelist as $one){
            $oneres[ $one['id'] ] = ['id'=>$one['id'],'name'=>$one['name'],'type'=>'parent','data'=>$one,'list'=>[]];
        }
        $result[] = [
            'type' => 1,
            'list' => ['name'=>'中介','id'=>1,'type'=>'parent','data'=>[],'list'=>$oneres]
        ];


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

}
