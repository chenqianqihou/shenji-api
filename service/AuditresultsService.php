<?php

namespace app\service;

use app\models\AuditresultsDao;
use Yii;


class AuditresultsService
{
    const STATUSTYPE = [
        '1' => '待提审',
        '2' => '待审核',
        '3' => '审核通过',
        '4' => '审核未通过',
    ];

    public function StatusType() {
        return self::ASSESSTYPE;
    }

    public function GetAuditResultById( $id ) {
            return AuditresultsDao::find()->where(['id' => $params['id']])->one();    
    }

    public function SaveAuditResult( $params = [] ) {
        if( isset($params['id']) && is_numeric($params['id']) && AuditresultsDao::find()->where(['id' => $params['id']])->count() > 0 ){
            $auditdao = AuditresultsDao::find()->where(['id' => $params['id']])->one();    
        }else{
            $auditdao = new AuditresultsDao();
        }

        $attrs = $auditdao->attributes();

        foreach( $params as $pk=>$pv) {
            if( in_array($pk,$attrs) ){
                $auditdao->$pk=>$pv;    
            }    
        }
        $auditdao->status = 1;

        return $auditdao->save();
    }

    public function SubmitAuditResult( $params = [] ) {
        if( isset($params['id']) && is_numeric($params['id']) && AuditresultsDao::find()->where(['id' => $params['id']])->count() > 0 ){
            $auditdao = AuditresultsDao::find()->where(['id' => $params['id']])->one();    
        }else{
            $auditdao = new AuditresultsDao();
        }

        $attrs = $auditdao->attributes();

        foreach( $params as $pk=>$pv) {
            if( in_array($pk,$attrs) ){
                $auditdao->$pk=>$pv;    
            }    
        }
        $auditdao->status = 2;

        return $auditdao->save();    
    }

    public function AuditSuccess( $id) {
        if( !is_numeric($id) || AuditresultsDao::find()->where(['id' => $id])->count() <= 0 ){
            return false;
        }

        $auditdao = AuditresultsDao::find()->where(['id' => $id])->one();    
        $auditdao->status = 3;

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
}
