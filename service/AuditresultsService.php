<?php

namespace app\service;

use app\models\AuditresultsDao;
use app\models\PeopleProjectDao;
use app\models\UserDao;
use Yii;


class AuditresultsService
{
    public static $statusType = [
        '1' => '待提审',
        '2' => '待审核',
        '3' => '审核通过',
        '4' => '审核未通过',
    ];

    public static $statusTypeName = [
        '待提审' => '1',
        '待审核' => '2',
        '审核通过' => '3',
        '审核未通过' => '4',
    ];

    public function StatusType() {
        return self::$statusType;
    }

    public function GetAuditResultById( $id ) {
            return AuditresultsDao::find()->where(['id' => $id])->asArray()->one();    
    }

    public function AuditResultCount( $id ) {
            return AuditresultsDao::find()->where(['id' => $id])->count();    
    }

    public function DeleteAuditResult( $ids ) {
            return AuditresultsDao::deleteAll(['id'=>$ids]);    
    }

    public function getAuditResultsList($peopleid, $projectid, $status, $start, $length) {
        $res = (new \yii\db\Query())
            ->from('auditresults')
            ->innerJoin('project', 'auditresults.projectid = project.id')
            ->select('project.name, project.projectnum, auditresults.*')
            ->where(1);
        $res = $res->andWhere(['auditresults.peopleid'=>$peopleid]);
        if( $status > 0 ){
            $res = $res->andWhere(['auditresults.status'=>$status]);
        }
        if ($projectid) {
            $res = $res->andwhere(['or', ['like', 'projectnum', $projectid], ['like', 'name', $projectid]]);
        }
        $total = $res->count();
        $list = $res->orderBy('id desc')->offset( $start )->limit($length)->all();
        return ['total'=>$total,'list'=>$list];
    }

    public function getAuditResultsByOrgan($projorgan, $projectid, $status, $start, $length) {
        $res = (new \yii\db\Query())
            ->from('auditresults')
            ->innerJoin('project', 'auditresults.projectid = project.id')
            ->select('project.name, project.projectnum, auditresults.*')
            ->where(1);
        $res = $res->andWhere(['project.projorgan'=>$projorgan]);
        if( $status > 0 ){
            $res = $res->andWhere(['auditresults.status'=>$status]);
        }
        if ($projectid) {
            $res = $res->andwhere(['or', ['like', 'projectnum', $projectid], ['like', 'name', $projectid]]);
        }
        $total = $res->count();
        $list = $res->orderBy('id desc')->offset( $start )->limit($length)->all();
        return ['total'=>$total,'list'=>$list];
    }

    public function getAuditResultsByOrganIds($projorganIds, $projectid, $status, $start, $length) {
        $res = (new \yii\db\Query())
            ->from('auditresults')
            ->innerJoin('project', 'auditresults.projectid = project.id')
            ->select('project.name, project.projectnum, auditresults.*')
            ->where(1);
        $res = $res->andWhere(['in','project.projorgan',$projorganIds]);
        if( $status > 0 ){
            $res = $res->andWhere(['auditresults.status'=>$status]);
        }
        if ($projectid) {
            $res = $res->andwhere(['or', ['like', 'projectnum', $projectid], ['like', 'name', $projectid]]);
        }
        $total = $res->count();
        $list = $res->orderBy('id desc')->offset( $start )->limit($length)->all();
        return ['total'=>$total,'list'=>$list];
    }

    public function getAuditResultsAllList($projectid, $status, $start, $length) {
        $res = (new \yii\db\Query())
            ->from('auditresults')
            ->innerJoin('project', 'auditresults.projectid = project.id')
            ->select('project.name, project.projectnum, auditresults.*')
            ->where(1);
        if( $status > 0 ){
            $res = $res->andWhere(['auditresults.status'=>$status]);
        }
        if ($projectid) {
            $res = $res->andwhere(['or', ['like', 'projectnum', $projectid], ['like', 'name', $projectid]]);
        }
        $total = $res->count();
        $list = $res->orderBy('id desc')->offset( $start )->limit($length)->all();
        return ['total'=>$total,'list'=>$list];
    }

    public function SaveAuditResult( $params = [] ) {
        if( isset($params['id']) && is_numeric($params['id']) && AuditresultsDao::find()->where(['id' => $params['id']])->count() > 0 ){
            $auditdao = AuditresultsDao::find()->where(['id' => $params['id']])->one();    
        }else{
            $auditdao = new AuditresultsDao();
        }

        $attrs = $auditdao->attributes();
        if (isset($params['transferpeople'])) {
            $params['transferpeople'] = json_encode($params['transferpeople'], JSON_UNESCAPED_UNICODE);
        }

        foreach( $params as $pk=>$pv) {
            if( in_array($pk,$attrs) ){
                $auditdao->$pk=$pv;    
            }    
        }
        $auditdao->status = self::$statusTypeName['待提审'];

        return $auditdao->save();
    }

    public function SubmitAuditResult( $params = [] ) {
        if( isset($params['id']) && is_numeric($params['id']) && AuditresultsDao::find()->where(['id' => $params['id']])->count() > 0 ){
            $auditdao = AuditresultsDao::find()->where(['id' => $params['id']])->one();    
        }else{
            $auditdao = new AuditresultsDao();
        }

        $attrs = $auditdao->attributes();
        if (isset($params['transferpeople'])) {
            $params['transferpeople'] = json_encode($params['transferpeople'], JSON_UNESCAPED_UNICODE);
        }

        foreach( $params as $pk=>$pv) {
            if( in_array($pk,$attrs) ){
                $auditdao->$pk=$pv;    
            }    
        }
        $auditdao->status = self::$statusTypeName['待审核'];

        $auditdao->operatorid = $this->getOperator($params['peopleid'], $params['projectid']);

        return $auditdao->save();    
    }

    public function calResultScore($params = []) {

    }

    public function AuditSuccess( $id) {
        if( !is_numeric($id) || AuditresultsDao::find()->where(['id' => $id])->count() <= 0 ){
            return false;
        }

        $auditdao = AuditresultsDao::find()->where(['id' => $id])->one();    
        $auditdao->status = self::$statusTypeName['审核通过'];

        return $auditdao->save();
    }

    public function AuditFailed( $id) {
        if( !is_numeric($id) || AuditresultsDao::find()->where(['id' => $id])->count() <= 0 ){
            return false;
        }

        $auditdao = AuditresultsDao::find()->where(['id' => $id])->one();    
        $auditdao->status = 4;

        return $auditdao->save();
    }

    /**
     * 获取审核操作员
     * 审计成员/主审的成果：提交至该成果对应项目，及此人员所在项目组的审计组长来审核
     *
     * 审计组长的成果：提交至该成果对应项目，及此人员所在项目组的主审来审核；如有多个主审随机抽调安排一位主审审核；
     * 如该组内无主审则由审计成员来审核，如有多个审计成员，随机抽取一位成员审核；
     *
     * 每位成员仅能看到需要自己审核的部分
     *
     * @param $pnum $projId
     */
    public function getOperator($pnum, $projId) {
        $people = UserDao::find()
            ->where(['pid' => $pnum])
            ->one();

        $peopleProjects = PeopleProjectDao::find()
            ->where(['projid' => $projId])
            ->all();

        $roleType = PeopleProjectDao::ROLE_TYPE_GROUPER;
        foreach ($peopleProjects as $proj){
            if($people['id'] == $proj['pid']){
                $roleType = $proj['roletype'];
                break;
            }
        }
        switch ($roleType){
            case PeopleProjectDao::ROLE_TYPE_GROUPER:
                foreach ($peopleProjects as $e){
                    if($e['roletype'] == PeopleProjectDao::ROLE_TYPE_GROUP_LEADER){
                        return $e['pid'];
                    }
                }
                break;
            case PeopleProjectDao::ROLE_TYPE_MASTER:
                foreach ($peopleProjects as $e){
                    if($e['roletype'] == PeopleProjectDao::ROLE_TYPE_GROUP_LEADER){
                        return $e['pid'];
                    }
                }
                break;
            case PeopleProjectDao::ROLE_TYPE_GROUP_LEADER:
                shuffle($peopleProjects);
                foreach ($peopleProjects as $e){
                   return $e['id'];
                }
                break;
        }

        return 0;

    }
}
