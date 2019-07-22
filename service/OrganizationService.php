<?php

namespace app\service;

use app\models\OrganizationDao;
use app\models\UserDao;

class OrganizationService
{

    // 查询机构数量
    public function getOrganizationCount($oid) {
        return OrganizationDao::find()->where(['id' => $oid])->count();
    }

    // 查询机构信息
    public function getOrganizationInfo($oid) {
        return OrganizationDao::find()->where(['id' => $oid])->asArray()->one();
    }

    // 根据type查询机构信息
    public function getOrganizationListByType($otype) {
        return OrganizationDao::find()->where(['otype' => $otype])->andWhere('parentid=0')->asArray()->all();
    }

    // 查询机构列表
    public function getOrganizationList($keyword,$otype,$start,$length) {
        $res = OrganizationDao::find()->where(1);
        if( $otype > 0 ){
            $res = $res->andWhere(['otype'=>$otype]);    
        }
        if( trim($keyword) != '' ){
            $res = $res->andWhere(['like', 'name', $keyword]);    
        }
        $total = $res->count();
        $list = $res->offset( $start )->limit($length)->asArray()->all();
        return ['total'=>$total,'list'=>$list];
    }

    // 查询机构的用户列表
    public function getOrganizationPeopleList($oid,$start,$length) {
        $res = UserDao::find()->where(['organid'=>$oid]);
        $total = $res->count();
        $list = $res->offset( $start )->limit($length)->asArray()->all();
        return ['total'=>$total,'list'=>$list];
    }

    //删除机构
    public function deleteOrganizations( $oids = [] ) {
        if( count($oids) <= 0 ){
            return 0;    
        }    

        foreach( $oids as $oid ){
            if( $this->numberPeopleBelong($oid) > 0 ){
                continue;    
            }
            $om = OrganizationDao::findOne($oid);
            if( is_null($om)){
                continue;    
            }
            $om->delete();
        }
        return true;
    }

    //检查机构下是否有人员
    public function numberPeopleBelong( $oid ){
        return UserDao::find()->where(['organid' => $oid])->count();    
    }

    //添加机构
    public function insertOrganization( $params = []) {
        $checkres = $this->checkParams($params);
        if( !$checkres['res'] ){
            return $checkres;    
        }

        $organDao = new OrganizationDao;       
        $organDao->name = $params['name'];
        $organDao->otype = $params['otype'];
        $organDao->deputy = $params['deputy'];
        $organDao->regtime = $params['regtime'];
        $organDao->regnum = $params['regnum'];
        $organDao->regaddress = $params['regaddress'];
        $organDao->category = $params['category'];
        $organDao->level = $params['level'];
        $organDao->capital = $params['capital'];
        $organDao->workbegin = $params['workbegin'];
        $organDao->costeng = $params['costeng'];
        $organDao->coster = $params['coster'];
        $organDao->accountant = $params['accountant'];
        $organDao->highlevel = $params['highlevel'];
        $organDao->midlevel = $params['midlevel'];
        $organDao->retiree = $params['retiree'];
        $organDao->parttimers = $params['parttimers'];
        $organDao->contactor = $params['contactor'];
        $organDao->contactphone = $params['contactphone'];
        $organDao->contactnumber = $params['contactnumber'];
        $organDao->officenum = $params['officenum'];
        $organDao->officeaddress = $params['officeaddress'];
        if( isset($params['parentid'] )){
            $organDao->parentid = $params['parentid'];
        }
        $res = $organDao->save();
        
        return [
            'res' => $res,
            'key' => \Yii::$app->db->lastInsertID,
            'message' => $organDao
        ];
    }

    public function getAllOrganization(){
        return OrganizationDao::find()->asArray()->all();
    }

    //获取departs
    public function getDeparts(){
        $organs = OrganizationDao::find()->where('parentid <= 0')->asArray()->all();  
        $departs = OrganizationDao::find()->where('parentid > 0')->asArray()->all();
//        var_dump($organs);
//        var_dump($departs);

        $organDict = [];
        $organList = [];
        foreach( $organs as $ov){
            $organDict[$ov['id']]['info'] = $ov;
            $organDict[$ov['id']]['departments'] = [];   
        }
        foreach( $departs as $dv){
            $pid = $dv['parentid'];
            if( isset($organDict[$pid]) ){
                $organDict[$pid]['departments'][] = $dv;
            }
        }
        foreach ($organDict as $id => $dict) {
            $organ['id'] = $id;
            $organ['name'] = $dict['info']['name'];
            $organ['partment'] = [];
            foreach ($dict['departments'] as $one) {
                $tmp = [];
                $tmp[$one['id']] = $one['name'];
                $organ['partment'][] = $tmp;
            }
            $organList[] = $organ;
        }
        return $organList;
    }

    //修改机构
    public function updateOrganization( $params = []) {
        $oid = $params['id'];
        $organDao = OrganizationDao::find()->where(['id' => $oid])->one();
        unset( $params['id']);
        foreach( $params as $pk=>$pv){
            $organDao->$pk = $pv;    
        }
        /*
        $organDao->name = $params['name'];
        $organDao->otype = $params['otype'];
        $organDao->deputy = $params['deputy'];
        $organDao->regtime = $params['regtime'];
        $organDao->regnum = $params['regnum'];
        $organDao->regaddress = $params['regaddress'];
        $organDao->category = $params['category'];
        $organDao->level = $params['level'];
        $organDao->capital = $params['capital'];
        $organDao->workbegin = $params['workbegin'];
        $organDao->costeng = $params['costeng'];
        $organDao->coster = $params['coster'];
        $organDao->accountant = $params['accountant'];
        $organDao->highlevel = $params['highlevel'];
        $organDao->midlevel = $params['midlevel'];
        $organDao->retiree = $params['retiree'];
        $organDao->parttimers = $params['parttimers'];
        $organDao->contactor = $params['contactor'];
        $organDao->contactphone = $params['contactphone'];
        $organDao->contactnumber = $params['contactnumber'];
        $organDao->officenum = $params['officenum'];
        $organDao->officeaddress = $params['officeaddress'];
        */
        if( isset($params['parentid'] )){
            $organDao->parentid = $params['parentid'];
        }
        $res = $organDao->save();
        
        return [
            'res' => $res,
            'key' => \Yii::$app->db->lastInsertID,
            'message' => $organDao
        ];
    }

    public function checkParams( $params ){
        $result = [
            'res' => true,
            'key' => '',
            'message' => ''
        ];

        if( empty($params['name']) || trim($params['name']) == ''){
            $result = [
                'res' => false,
                'key' => 'name',
                'message' => 'name can not be null!'
            ];

            return $result;
        }    

        if( empty($params['otype']) || !in_array($params['otype'],[1,2,3] ) ){
            $result = [
                'res' => false,
                'key' => 'otype',
                'message' => 'otype error!'
            ];

            return $result;
        }

        if( intval($params['otype']) == 3 ){
            return $result;    
        }

        if( empty($params['deputy']) ){
            $result = [
                'res' => false,
                'key' => 'deputy',
                'message' => 'deputy can not be null!'
            ];

            return $result;
        }

        if( empty($params['regtime']) || $params['regtime']  > time() ){
            $result = [
                'res' => false,
                'key' => 'regtime',
                'message' => 'regtime error!'
            ];

            return $result;
        }

        if( empty($params['regnum']) ){
            $result = [
                'res' => false,
                'key' => 'regnum',
                'message' => 'regnum error!'
            ];

            return $result;
        }

        if( empty($params['regaddress']) ){
            $result = [
                'res' => false,
                'key' => 'regaddress',
                'message' => 'regaddress can not be null!'
            ];

            return $result;
        }

        if( empty($params['category']) ){
            $result = [
                'res' => false,
                'key' => 'category',
                'message' => 'category can not be null!'
            ];

            return $result;
        }

        if( empty($params['level']) ){
            $result = [
                'res' => false,
                'key' => 'level',
                'message' => 'level can not be null!'
            ];

            return $result;
        }

        if( empty($params['capital']) || $params['capital'] < 0 ){
            $result = [
                'res' => false,
                'key' => 'capital',
                'message' => 'capital error!'
            ];

            return $result;
        }

        if( empty($params['workbegin']) || $params['workbegin'] > time() ){
            $result = [
                'res' => false,
                'key' => 'workbegin',
                'message' => 'workbegin error'
            ];

            return $result;
        }

        if( empty($params['costeng']) || !is_numeric($params['costeng']) || $params['costeng'] < 0 ){
            $result = [
                'res' => false,
                'key' => 'costeng',
                'message' => 'costeng error'
            ];

            return $result;
        }

        if( empty($params['coster']) || !is_numeric($params['coster']) || $params['coster'] < 0 ){
            $result = [
                'res' => false,
                'key' => 'coster',
                'message' => 'coster error'
            ];

            return $result;
        }

        if( empty($params['accountant']) || !is_numeric($params['accountant']) || $params['accountant'] < 0 ){
            $result = [
                'res' => false,
                'key' => 'accountant',
                'message' => 'accountant error'
            ];

            return $result;
        }

        if( empty($params['highlevel']) || !is_numeric($params['highlevel']) || $params['highlevel'] < 0 ){
            $result = [
                'res' => false,
                'key' => 'highlevel',
                'message' => 'highlevel error'
            ];

            return $result;
        }

        if( empty($params['midlevel']) || !is_numeric($params['midlevel']) || $params['midlevel'] < 0 ){
            $result = [
                'res' => false,
                'key' => 'midlevel',
                'message' => 'midlevel error'
            ];

            return $result;
        }
        
        /*
        if( empty($params['retiree']) || !is_numeric($params['retiree']) || $params['retiree'] < 0 ){
            $result = [
                'res' => false,
                'key' => 'retiree',
                'message' => 'retiree error'
            ];

            return $result;
        }

        if( empty($params['parttimers']) || !is_numeric($params['parttimers']) || $params['parttimers'] < 0 ){
            $result = [
                'res' => false,
                'key' => 'parttimers',
                'message' => 'parttimers error'
            ];

            return $result;
        }
        */

        if( empty($params['contactor']) ){
            $result = [
                'res' => false,
                'key' => 'contactor',
                'message' => 'contactor can not be null'
            ];

            return $result;
        }

        if( empty($params['contactphone']) || !is_numeric($params['contactphone']) ){
            $result = [
                'res' => false,
                'key' => 'contactphone',
                'message' => 'contactphone error'
            ];

            return $result;
        }

        if( empty($params['contactnumber']) || !is_numeric($params['contactnumber']) ){
            $result = [
                'res' => false,
                'key' => 'contactnumber',
                'message' => 'contactnumber error'
            ];

            return $result;
        }

        if( empty($params['officenum']) ){
            $result = [
                'res' => false,
                'key' => 'officenum',
                'message' => 'officenum error'
            ];

            return $result;
        }

        if( empty($params['officeaddress']) ){
            $result = [
                'res' => false,
                'key' => 'officeaddress',
                'message' => 'officeaddress can not be null'
            ];

            return $result;
        }

/*
        if( empty($params['qualiaudit']) || !in_array($params['qualiaudit'],[1,2] ) ){
            $result = [
                'res' => false,
                'key' => 'qualiaudit',
                'message' => 'qualiaudit error!'
            ];

            return $result;
        }
        */

        return $result;
    }
}
