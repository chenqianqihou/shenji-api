<?php

namespace app\service;

use app\models\AssessconfigDao;
use app\models\AuditGroupDao;
use app\models\PeopleProjectDao;
use app\models\ProjectDao;
use app\models\QanswerDao;
use app\models\QuestionsDao;
use app\models\ObjectivescoreDao;
use app\models\ObjectivetypeDao;
use app\models\RoleDao;
use app\models\UserDao;
use app\models\ViolationDao;
use Yii;


class AssessService
{
    public static $ASSESSTYPE = [
        '5' => '审计成员给审计组长的评价',
        '4' => '审计成员给主审的评价',
        '6' => '审计组长给审计成员的评价',
        '7' => '审计组长给第三方审计人员的评价',
        '2' => '审理处给第三方审计人员的评价',
        '3' => '业务处给第三方审计人员的评价',
        '1' => '法规处给第三方审计人员的评价',
    ];

    public static $assessTypeName = [
        '审计成员给审计组长的评价' => '5',
        '审计成员给主审的评价' => '4',
        '审计组长给审计成员的评价' => '6',
        '审计组长给第三方审计人员的评价' => '7',
        '审理处给第三方审计人员的评价' => '2',
        '业务处给第三方审计人员的评价' => '3',
        '法规处给第三方审计人员的评价' => '1',
    ];

    public static $accessStatus = [
        0 => '无需评分',
        1 => '未评分',
        2 => '已评分',
    ];

    public static $accessStatusName = [
        '无需评分' => 0,
        '未评分' => 1,
        '已评分' => 2,
    ];

    public function AssessType() {
        return self::$ASSESSTYPE;
    }

    public function FormContent( $uid,$objuid,$projectid,$typeid) {
        
        $result = ['answer'=>[],'question'=>[]];
        //是否已经回答过
        if( QanswerDao::find()->where(['pid'=>$uid,'objpid'=>$objuid,'projectid'=>$projectid,'configid'=>$typeid])->count() ){
            $answer = QanswerDao::find()->where(['pid'=>$uid,'objpid'=>$objuid,'projectid'=>$projectid,'configid'=>$typeid])->asArray()->one();
            $result['answer'] = json_decode($answer['answers'],true);
        }

        $result['question'] = $this->getConfigQuestion( $typeid );
        return $result;
    }

    protected function getDefaultConfig( $typeid ) {
        $result = [];
        if( isset(self::$ASSESSTYPE[$typeid]) ){
            $result =  AssessconfigDao::find()->where(['id' => $typeid])->asArray()->one();
        }
        return $result;
    }

    protected function getConfigQuestion( $typeid ) {
        $configMsg = $this->getDefaultConfig( $typeid );
        if( empty($configMsg) ){
            return [];    
        }    

        $questionlist = json_decode($configMsg['questionlist'],true);
        $questMsgs = QuestionsDao::find()->where(['id'=>$questionlist])->asArray()->all();
        $result = [];
        foreach( $questMsgs as $q){
            $qtype = $q['qtype'];
            $q['options'] = json_decode( $q['qoptions'],true);
            unset($q['qoptions']);
            if( !isset($result[$qtype])){
                $result[$qtype] = [];    
            }
            $questionInfo = [
                'id' => $q['id'],
                'title' => $q['title'],
                'options' => $q['options'],
            ];
            $result[$qtype][] = $questionInfo;
        }
        return $result;
    }

    public function SubmitFormContent( $uid,$objuid,$projectid,$typeid,$answers) {
        
        $result = ['answer'=>[],'question'=>[]];

        //计算分数
        $totalscore = 0;
        foreach( $answers as $av) {
            if (!is_array($av)) {
                continue;
            }
            foreach($av as $aitem) {
                $sc = isset($aitem['score']) ? $aitem['score'] : 0;
                $isselect = empty( $aitem['selected'] ) ? 0 : 1;
                $totalscore += $sc * $isselect;
            }
        }

        //是否已经回答过
        if( QanswerDao::find()->where(['pid'=>$uid,'objpid'=>$objuid,'projectid'=>$projectid,'configid'=>$typeid])->count() ){
            $qanswer = QanswerDao::find()->where(['pid'=>$uid,'objpid'=>$objuid,'projectid'=>$projectid,'configid'=>$typeid])->one();
            $qanswer->answers = json_encode( $answers );
            $qanswer->score = $totalscore;
            $qanswer->save();
        } else {
            $qanswer = new QanswerDao();
            $qanswer->pid=$uid;
            $qanswer->objpid=$objuid;
            $qanswer->projectid=$projectid;
            $qanswer->configid=$typeid;
            $qanswer->answers=json_encode($answers);
            $qanswer->score = $totalscore;
            $qanswer->save();   
        }
        $result['answer'] = $answers;
        $result['question'] = $this->getConfigQuestion( $typeid );
        return $result;
    }

    public function ScoreConfig() {
        $result = [];
        $typelist = ObjectivetypeDao::find()->asArray()->all();
        foreach( $typelist as $tk=>$tv) {
            $one = $tv['onetype'];
            $two = $tv['twotype'];
            if( count($result) <= 0 || $result[count($result)-1]['name'] != $one ){
                $result[] = ['name'=>$one,'list'=>[]];    
            }
            $result[count($result)-1]['list'][] = ['id'=>$tv['id'],'list'=>[],'name'=>$two,'kindid'=>$tv['kindid']];    
        }

        $scorelist = ObjectivescoreDao::find()->asArray()->all();
        $scoreArr = [];
        foreach( $scorelist as $sv){
            $typeid = $sv['typeid'];
            $kindid = $sv['kindid'];
            if( $kindid == 2 ){
                $sv['nameone'] = json_decode( $sv['nameone'], true );    
            }

            if( !isset( $scoreArr[$typeid] )){
                $scoreArr[$typeid] = [];
            }
            $scoreArr[$typeid][] = $sv;
        }

        foreach( $result as $rk=>$rv){
            foreach($rv['list'] as $rvk=>$rvv){
                if( isset( $scoreArr[$rvv['id']] ) ){
                    $result[$rk]['list'][$rvk]['list'] = $scoreArr[$rvv['id']];    
                }    
            }    
        }

        return $result;
    }

    public function Violations() {
        return json_decode(Yii::$app->params['violations'],true);

        /* //生成violations json的逻辑
        $result = [];
        $level0 = ViolationDao::find()->where(['parentid'=>0])->asArray()->all();              
        foreach( $level0 as $lv) {
            $result[] = ['name'=>$lv['name'],'id'=>$lv['id'],'type'=>'parent','data'=>[],'list'=>[]];    
        }

        foreach( $result as $rk=>$rv ) {
            $sid = $rv['id'];    
            $level1 = ViolationDao::find()->where(['parentid'=>$sid])->asArray()->all();              
            foreach( $level1 as $lv) {
                $result[$rk]['list'][] = ['name'=>$lv['name'],'id'=>$lv['id'],'type'=>'parent','data'=>[],'list'=>[]];    
            }

            foreach( $result[$rk]['list'] as $rkk=>$rvv) {
                $ssid = $rvv['id']; 
                $level2 = ViolationDao::find()->where(['parentid'=>$ssid])->asArray()->all();              
                foreach( $level2 as $lv) {
                    $result[$rk]['list'][$rkk]['list'][] = ['name'=>$lv['name'],'id'=>$lv['id'],'type'=>'parent','data'=>[],'list'=>[]];    
                }

                foreach( $result[$rk]['list'][$rkk]['list'] as $rkkk=>$rvvv) {
                    $sssid = $rvvv['id']; 
                    $level3 = ViolationDao::find()->where(['parentid'=>$sssid])->asArray()->all();              
                    foreach( $level3 as $lv) {
                        $result[$rk]['list'][$rkk]['list'][$rkkk]['list'][] = ['name'=>$lv['name'],'id'=>$lv['id'],'type'=>'parent','data'=>[],'list'=>[]];    
                    }
                }
            }
        }

        return $result;
        */
    }

    public function addNewConfigs( $newconfig ) {
        foreach( $newconfig as $cv ) {
            if(!isset( $cv['kindid'])){
                continue;    
            }
            unset( $cv['id']);    
            unset( $cv['ctime']);    
            unset( $cv['utime']);    
            $scoreObj = new ObjectivescoreDao();

            if( $cv['kindid'] == 2 ){
                $cv['nameone'] = json_encode( $cv['nameone']);    
            }

            isset( $cv['nameone']) ? $scoreObj->nameone = $cv['nameone']: '';
            isset( $cv['nametwo']) ? $scoreObj->nametwo = $cv['nametwo']: '';
            isset( $cv['score']) ? $scoreObj->score = $cv['score']: '';
            isset( $cv['typeid']) ? $scoreObj->typeid = $cv['typeid']: '';
            isset( $cv['kindid']) ? $scoreObj->kindid = $cv['kindid']: '';
            $scoreObj->save();
        }

        return true;
    }

    public function updateConfigs( $configs ) {
        foreach( $configs as $cv ) {
            if(!isset( $cv['id'])){
                continue;    
            }
            unset( $cv['ctime']);    
            unset( $cv['utime']);    
            $scoreObj = ObjectivescoreDao::find()->where(['id'=>$cv['id']])->one();

            if( $cv['kindid'] == 2 ){
                $cv['nameone'] = json_encode( $cv['nameone']);    
            }

            isset( $cv['nameone']) ? $scoreObj->nameone = $cv['nameone']: '';
            isset( $cv['nametwo']) ? $scoreObj->nametwo = $cv['nametwo']: '';
            isset( $cv['score']) ? $scoreObj->score = $cv['score']: '';
            isset( $cv['typeid']) ? $scoreObj->typeid = $cv['typeid']: '';
            isset( $cv['kindid']) ? $scoreObj->kindid = $cv['kindid']: '';
            $scoreObj->save();
        }

        return true;
    }

    public function deleteConfig( $configid ) {
        if( ObjectivescoreDao::find()->where(['id'=>$configid])->count() <= 0 ){
            return false;    
        }
        return ObjectivescoreDao::find()->where(['id'=>$configid])->one()->delete();
    }

    //查询用户在某个项目下已经答题的列表
    public function queryProjectAnswersList($pid, $projectId) {
        $answers = QanswerDao::find()->where(['pid'=>$pid, 'projectid'=>$projectId])->asArray()->all();
        return $answers;
    }

    //基于用户查询某项目下所有审计人员是否需要被评分
    public function assessList($pid, $projectId) {
        $returnData = [
            'isleader' => 0,
            'list' => [],
        ];
        //判断用户是否是领导
        $roleDao = new RoleDao();
        $roleInfo = $roleDao->queryByPid($pid);
        $userRoleList = [];
        foreach ($roleInfo as $oneRole) {
            if ($oneRole['name'] == '厅领导' || $oneRole['name'] == '局领导') {
                $returnData['isleader'] = 1;
            }
            $userRoleList[] = $oneRole['name'];
        }
        $projectDao = new ProjectDao();
        $projectInfo = $projectDao->queryByID($projectId);
        if (!$projectInfo) {
            return $returnData;
        }
        //查询项目所属单位下的人员列表
        $userService = new UserService();
        $organPeopleListDict = [];
        $organPeopleList = $userService->getOrganPeopleList($projectInfo['projorgan']);
        foreach ($organPeopleList as $one) {
            $organPeopleListDict[$one['pid']] = $one;
        }
        //查询项目牵头业务部门下的人员列表
        $leadPeopleListDict = [];
        $leadPeopleList = $userService->getDepartmentPeopleList($projectInfo['leadorgan']);
        foreach ($leadPeopleList as $one) {
            $leadPeopleListDict[$one['pid']] = $one;
        }
        //审计组列表
        $auditGroupService = new AuditGroupService();
        $groupList = $auditGroupService->listByProjectId($projectId);
        //已经评价列表
        $answeredDict = [];
        $answers = $this->queryProjectAnswersList($pid, $projectId);
        foreach ($answers as $oneAnswer) {
            $answeredDict[$oneAnswer['objpid']] = $oneAnswer;
        }
        $list = [];
        foreach ($groupList as $oneGroup) {
            if (isset($oneGroup['memList']) && $oneGroup['memList']) {
                //判断用户是否在此组中，以及确定用户的角色
                $inGroup = false;
                $inRole = 0;
                foreach ($oneGroup['memList'] as $oneUser) {
                    if ($oneUser['pid'] == $pid) {
                        $inGroup = true;
                        $inRole = $oneUser['roletype'];
                    }
                }
                $groupInfo = [
                    'id' => $oneGroup['id'],
                    'memList' => [],
                ];
                foreach ($oneGroup['memList'] as $onePeople) {
                    $tempPeople = [
                        'typeid' => 0,
                        'pnum' => $onePeople['pid'],
                        'pname' => $onePeople['name'],
                        'sex' => $onePeople['sex'],
                        'ptype' => $onePeople['role'],
                        'location' => $onePeople['location'],
                        'projrole' => $onePeople['roletype'],
                        'status' => 0,
                    ];
                    //判断是否已评分
                    if (isset($answeredDict[$onePeople['pid']])) {
                        $tempPeople['typeid'] = $answeredDict[$onePeople['pid']]['configid'];
                        $tempPeople['status'] = self::$accessStatusName['已评分'];
                    }else {
                        //审计成员给审计组长的评价
                        if ($inGroup && $inRole == AuditGroupDao::$roleTypeName['审计组员']
                            && $onePeople['roletype'] == AuditGroupDao::$roleTypeName['审计组长']) {
                            $tempPeople['typeid'] = self::$assessTypeName['审计成员给审计组长的评价'];
                            $tempPeople['status'] = self::$accessStatusName['未评分'];
                        }
                        //审计成员给主审的评价
                        if ($inGroup && $inRole == AuditGroupDao::$roleTypeName['审计组员']
                            && $onePeople['roletype'] == AuditGroupDao::$roleTypeName['主审']) {
                            $tempPeople['typeid'] = self::$assessTypeName['审计成员给主审的评价'];
                            $tempPeople['status'] = self::$accessStatusName['未评分'];
                        }
                        //审计组长给审计成员的评价
                        if ($inGroup && $inRole == AuditGroupDao::$roleTypeName['审计组长']
                            && $onePeople['roletype'] == AuditGroupDao::$roleTypeName['审计组员']) {
                            $tempPeople['typeid'] = self::$assessTypeName['审计组长给审计成员的评价'];
                            $tempPeople['status'] = self::$accessStatusName['未评分'];
                        }
                        //审计组长给第三方审计人员的评价
                        if ($inGroup && $inRole == AuditGroupDao::$roleTypeName['审计组长']
                            && $onePeople['role'] != UserDao::$typeToName['审计机关']) {
                            $tempPeople['typeid'] = self::$assessTypeName['审计组长给第三方审计人员的评价'];
                            $tempPeople['status'] = self::$accessStatusName['未评分'];
                        }
                        //审理处给第三方审计人员的评价
                        if (isset($organPeopleListDict[$pid]) && in_array('审理处', $userRoleList)
                            && $onePeople['role'] != UserDao::$typeToName['审计机关']) {
                            $tempPeople['typeid'] = self::$assessTypeName['审理处给第三方审计人员的评价'];
                            $tempPeople['status'] = self::$accessStatusName['未评分'];
                        }
                        //业务处给第三方审计人员的评价（牵头业务部门）
                        if (isset($leadPeopleListDict[$pid])
                            && $onePeople['role'] != UserDao::$typeToName['审计机关']) {
                            $tempPeople['typeid'] = self::$assessTypeName['业务处给第三方审计人员的评价'];
                            $tempPeople['status'] = self::$accessStatusName['未评分'];
                        }
                        //审理处给第三方审计人员的评价
                        if (isset($organPeopleListDict[$pid]) && in_array('法规处', $userRoleList)
                            && $onePeople['role'] != UserDao::$typeToName['审计机关']) {
                            $tempPeople['typeid'] = self::$assessTypeName['法规处给第三方审计人员的评价'];
                            $tempPeople['status'] = self::$accessStatusName['未评分'];
                        }
                    }
                    //todo 计算分数
                    if ($returnData['isleader']) {
                        if ($onePeople['objectscore'] == -1) {
                            $tempPeople['objectivescore'] = 0;
                        }else {
                            $tempPeople['objectivescore'] = $onePeople['objectscore'];
                        }
                        if ($onePeople['subjectscore'] == -1) {
                            $tempPeople['subjectivescore'] = 0;
                        }else {
                            $tempPeople['subjectivescore'] = $onePeople['subjectscore'];
                        }
                        $tempPeople['totalscore'] = $tempPeople['objectivescore'] + $tempPeople['subjectivescore'];
                    }
                    $groupInfo['memList'][] = $tempPeople;
                }
                $list[] = $groupInfo;
            }
        }
        $returnData['list'] = $list;
        return $returnData;
    }
}
