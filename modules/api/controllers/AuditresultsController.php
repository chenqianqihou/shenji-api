<?php

namespace app\modules\api\controllers;

use app\classes\BaseController;
use app\service\AuditresultsService;
use app\service\AssessService;
use app\models\ProjectDao;
use app\models\UserDao;
use app\classes\ErrorDict;
use Yii;

class AuditresultsController extends BaseController
{

    public function actionDetails() {
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

        $resultid = $this->getParam('id');
        $arservice = new AuditresultsService();

        if( $arservice->AuditResultCount($resultid) <= 0 ) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '审计成果不存在');
            $ret = $this->outputJson('', $error);
            return $ret;    
        }

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($arservice->GetAuditResultById($resultid), $error);
        return $ret;
    }

    public function actionSaveresult()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'projectid' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'peopleid' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'problemid' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'problemdetailid' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }

        $projectid = $this->getParam('projectid');
        $peopleid = $this->getParam('peopleid');
        $problemid = $this->getParam('problemid');
        $problemdetailid = $this->getParam('problemdetailid');

        //判断项目是否存在
        if ( ProjectDao::find()->where(['id'=>$projectid])->count() <= 0 ) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '项目不存在');
            $ret = $this->outputJson('', $error);
            return $ret;    
        }

        //判断用户是否存在
        if ( UserDao::find()->where(['id'=>$peopleid])->count() <= 0 ) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '用户不存在');
            $ret = $this->outputJson('', $error);
            return $ret;    
        }
        
        //判断problemid和detailid是否合法
        $assessService = new AssessService();
        $violations = $assessService->Violations();
        $isvalid = false;
        foreach( $violations as $p){
            if( $p['id'] == $problemid ){
                foreach( $p['list'] as $pd ){
                    if( $pd['id'] == $problemdetailid ){
                        $isvalid = true;
                        break;    
                    }  
                }    
                break;
            }    
        }
        if( $isvalid == false ){
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '问题性质或问题明细不合法');
            $ret = $this->outputJson('', $error);
            return $ret;    
        }

        $params = $this->getParams();

        $arservice = new AuditresultsService();
        
        $saveres = $arservice->SaveAuditResult( $params );

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($saveres, $error);
        return $ret;
    }

    public function actionSubmitresult()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'projectid' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'peopleid' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'problemid' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'problemdetailid' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }

        $projectid = $this->getParam('projectid');
        $peopleid = $this->getParam('peopleid');
        $problemid = $this->getParam('problemid');
        $problemdetailid = $this->getParam('problemdetailid');

        //判断项目是否存在
        if ( ProjectDao::find()->where(['id'=>$projectid])->count() <= 0 ) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '项目不存在');
            $ret = $this->outputJson('', $error);
            return $ret;    
        }

        //判断用户是否存在
        if ( UserDao::find()->where(['id'=>$peopleid])->count() <= 0 ) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '用户不存在');
            $ret = $this->outputJson('', $error);
            return $ret;    
        }
        
        //判断problemid和detailid是否合法
        $assessService = new AssessService();
        $violations = $assessService->Violations();
        $isvalid = false;
        foreach( $violations as $p){
            if( $p['id'] == $problemid ){
                foreach( $p['list'] as $pd ){
                    if( $pd['id'] == $problemdetailid ){
                        $isvalid = true;
                        break;    
                    }  
                }    
                break;
            }    
        }
        if( $isvalid == false ){
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '问题性质或问题明细不合法');
            $ret = $this->outputJson('', $error);
            return $ret;    
        }

        $params = $this->getParams();

        $arservice = new AuditresultsService();
        
        $saveres = $arservice->SubmitAuditResult( $params );

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($saveres, $error);
        return $ret;
    }

    public function actionDelresult()
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

        $ids = $this->getParam('id');

        $arservice = new AuditresultsService();
        
        $delres = $arservice->DeleteAuditResult( $ids );

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($delres, $error);
        return $ret;
    }

}
