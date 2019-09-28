<?php

namespace app\service;

use app\models\OrganizationDao;
use app\models\UserDao;
use app\classes\Util;
use Yii;

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

    // 查询某机构下的部门列表
    public function getOrganSonInfo($oid) {
        return OrganizationDao::find()->where(['parentid' => $oid])->asArray()->all();
    }

    // 根据type查询机构信息
    public function getOrganizationListByType($otype,$isparent = true) {
        if( $isparent ){
            return OrganizationDao::find()->where(['otype' => $otype])->asArray()->all();
        }
        return OrganizationDao::find()->where(['otype' => $otype])->andWhere('parentid=0')->asArray()->all();
    }


    //只返回机构的名字和行政号码
    public function getShortOrgans( $type,$isparent = true ) {
        $organList = $this->getOrganizationListByType( $type,$isparent);    
        $res = [];
        foreach( $organList as $ov ){
            if( $type == 3){
                $res[] = ['id' => $ov['id'], 'name' => $ov['name'],'regnum' => Util::getFullRegnum( $ov['regnum'])];
            } else {
                $res[] = [ 'id' => $ov['id'],'name' => $ov['name'],'regnum' => $ov['regnum']];
            }
        }
        return $res;
    }

    // 查询机构列表
    public function getOrganizationList($keyword,$otype,$start,$length) {
        $res = OrganizationDao::find()->where(1);
        if( $otype > 0 ){
            $res = $res->andWhere(['otype'=>$otype]);    
        }else{
            $res = $res->andWhere('otype != 3');    
        }

        if( trim($keyword) != '' ){
            $res = $res->andWhere(['like', 'name', $keyword]);    
        }
        $total = $res->count();
        $list = $res->orderBy('id desc')->offset( $start )->limit($length)->asArray()->all();
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
        return UserDao::find()->where(['organid' => $oid])->count() + UserDao::find()->where(['department' => $oid])->count();    
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
        $organDao->regaddress = isset($params['regaddress']) ? $params['regaddress'] : '-';
        $organDao->category = $params['category'];
        $organDao->level = $params['level'];
        $organDao->capital = $params['capital'];
        $organDao->workbegin = $params['workbegin'];
        $organDao->costeng = $params['costeng'];
        $organDao->coster = $params['coster'];
        $organDao->accountant = $params['accountant'];
        $organDao->highlevel = $params['highlevel'];
        $organDao->midlevel = $params['midlevel'];
        $organDao->retiree = isset($params['retiree']) ? $params['retiree'] : '-';
        $organDao->parttimers = isset($params['parttimers']) ? $params['parttimers'] : 0;
        $organDao->contactor = $params['contactor'];
        $organDao->contactphone = $params['contactphone'];
        $organDao->contactnumber = $params['contactnumber'];
        $organDao->officenum = $params['officenum'];
        $organDao->officeaddress = isset($params['officeaddress']) ? $params['officeaddress'] : '-';
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

    //获取某种类型的组织结构信息
    public function getDepartsByType($type){
        $organs = OrganizationDao::find()->where('parentid <= 0 and otype = :otype', [':otype' => $type])->asArray()->all();
        $departs = OrganizationDao::find()->where('parentid > 0 and otype = :otype', [':otype' => $type])->asArray()->all();
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
        $oid = $params['oid'];
        $organModel = new OrganizationDao();
        $attrs = $organModel->attributes();
        $organDao = OrganizationDao::find()->where(['id' => $oid])->one();
        unset( $params['oid']);
        foreach( $params as $pk=>$pv){
            if( $pk =='otype'){
                continue;    
            }
            if( !in_array($pk,$attrs) ){
                continue;    
            }
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

        //如果是内审，只允许贵州的机构
        $regArr = explode(',',$params['regnum']);
        if( $params['otype'] == 2){
            $r0 = intval($regArr[count($regArr)-1] / 10000) * 10000;

            //判断是否为520000区域
            if( $r0 != '520000'){
                $result = [
                    'res' => false,
                    'key' => 'regnum',
                    'message' => 'regnum must guizhou!'
                ];

                return $result;
            }
        }


        if( empty($params['regaddress']) ){
            $params['regaddress'] = '-';
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

        if( !isset($params['capital']) || $params['capital'] < 0 ){
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

        if( !isset($params['costeng']) || !is_numeric($params['costeng']) || $params['costeng'] < 0 ){
            $result = [
                'res' => false,
                'key' => 'costeng',
                'message' => 'costeng error'
            ];

            return $result;
        }

        if( !isset($params['coster']) || !is_numeric($params['coster']) || $params['coster'] < 0 ){
            $result = [
                'res' => false,
                'key' => 'coster',
                'message' => 'coster error'
            ];

            return $result;
        }

        if( !isset($params['accountant']) || !is_numeric($params['accountant']) || $params['accountant'] < 0 ){
            $result = [
                'res' => false,
                'key' => 'accountant',
                'message' => 'accountant error'
            ];

            return $result;
        }

        if( !isset($params['highlevel']) || !is_numeric($params['highlevel']) || $params['highlevel'] < 0 ){
            $result = [
                'res' => false,
                'key' => 'highlevel',
                'message' => 'highlevel error'
            ];

            return $result;
        }

        if( !isset($params['midlevel']) || !is_numeric($params['midlevel']) || $params['midlevel'] < 0 ){
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

/*
        if( empty($params['contactphone']) || !is_numeric($params['contactphone']) ){
            $result = [
                'res' => false,
                'key' => 'contactphone',
                'message' => 'contactphone error'
            ];

            return $result;
        }

        if( !isset($params['contactnumber']) || !is_numeric($params['contactnumber']) ){
            $result = [
                'res' => false,
                'key' => 'contactnumber',
                'message' => 'contactnumber error'
            ];

            return $result;
        }
*/

        if( empty($params['officenum']) ){
            $result = [
                'res' => false,
                'key' => 'officenum',
                'message' => 'officenum error'
            ];

            return $result;
        }

        if( empty($params['officeaddress']) ){
            $params['officeaddress'] = '-';
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

    function getDistrictRervMap( $provinceid = 0) {
        $dists = Yii::$app->params['districts'];

        $distArr = json_decode( $dists,true);
        krsort( $distArr );
        //$res = ['100000' => ['name'=>'中国','id'=>'100000','type'=>'parent','data'=> [],'list'=>[] ]];
        $res = [];
        foreach( $distArr['100000'] as $k=>$v ){
            if( !isset($res[$k]) ){
                $res[$k] =  ['name'=>$v,'id'=>$k,'type'=>'parent','data'=> [],'list'=>[] ];
            }
            if( !isset($distArr[$k]) ){
                continue;
            }
            foreach( $distArr[$k] as $vk=>$vv){
                $res[$k]['list'][$vk] =  ['name'=>$vv,'id'=>$vk,'type'=>'parent','data'=> [],'list'=>[] ];
            }
        }

        return $res[$provinceid];
    }


    public function getSubordinateIds($organid){

        $organCount = $this->getOrganizationCount( $organid);
        if( $organCount <=0){
            return false;
        }
        $organInfo = $this->getOrganizationInfo( $organid );
        $objnum = $organInfo['regnum'];

        //机关机构
        $distinct = $this->getDistrictRervMap( 520000 );
        $threelist = $this->getOrganizationListByType(3);
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


        if($objnum == '520000'){
            $result = $distinct;
        } else {
            if( isset($distinct['list'][$objnum]) && isset($distinct['list'][$objnum]['list'][$organid]) ){
                $result = $distinct['list'][$objnum]['list'][$organid];
            } else {
                $result = ['id'=>$organInfo['id'],'name'=>$organInfo['name'],'type'=>'child','data'=>$organInfo,'list'=>[]];
            }
        }

        //todo 弄懂这部分逻辑关系之后，在遍历树结构吧
        $ret = array_keys($result['list']);
        $ret[] = $organid;

        return $ret;

    }

    //查询行政区下的一级审计机构列表
    public function getOrganIdByRegNum($regNum){
        $organs = OrganizationDao::find()->where('parentid = 0 and regnum = :regnum', [':regnum' => $regNum])->asArray()->all();
        $organIdArr = [];
        foreach ($organs as $one) {
            $organIdArr[] = $one['id'];
        }
        return $organIdArr;
    }

    //根据审计机构类型和行政区域编码，返回机构list
    public function getOrganIdByRegnumAndType( $otype, $regnum) {
        $res = [];    
        if( $otype == 3) {
            if( $regnum == '520000' ){
                $organs = OrganizationDao::find()->where('parentid = 0 and otype = 3 and regnum >= :regnum', [':regnum' => $regnum])->asArray()->all();
            } else {
                $organs = OrganizationDao::find()->where('parentid = 0 and otype = 3 and regnum = :regnum', [':regnum' => $regnum])->asArray()->all();
            }
            foreach ($organs as $one) {
                $res[] = $one;
            }
        }

        if( $otype == 2 || $otype == 1) {
            $organs = OrganizationDao::find()->where('parentid = 0 and otype = :otype and regnum like "%' . $regnum . '%"', [':otype'=>$otype])->asArray()->all();
            foreach ($organs as $one) {
                $res[] = $one;
            }
        }

        return $res;
    }

}
