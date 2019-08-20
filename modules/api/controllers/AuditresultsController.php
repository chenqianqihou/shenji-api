<?php

namespace app\modules\api\controllers;

use app\classes\BaseController;
use app\service\AuditresultsService;
use app\service\AssessService;
use app\models\ProjectDao;
use app\models\UserDao;
use app\classes\ErrorDict;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
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

        $arres = $arservice->GetAuditResultById( $resultid );
        $projectDao = new ProjectDao();
        $userDao = new UserDao();
        $projid = $arres['projectid'];    
        $userid = $arres['peopleid'];
        $arres['project_msg'] = $projectDao->queryByID( $projid );
        $arres['people_msg'] = $userDao->queryByID( $userid );

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($arres, $error);
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
        if ( UserDao::find()->where(['pid'=>$peopleid])->count() <= 0 ) {
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
        if ( UserDao::find()->where(['pid'=>$peopleid])->count() <= 0 ) {
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

    public function actionSearch() {
        $this->defineMethod = 'POST';
        $this->defineParams = array ();
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $projectids = $this->getParam('projectid',[]);
        $status = intval($this->getParam('status',-1));
        $start = $this->getParam('start',0);
        $length = $this->getParam('length',10);
        $arService = new AuditresultsService();
        $arList = $arService->getAuditResultsList( $projectids,$status,$start,$length );
        $projectDao = new ProjectDao();
        $userDao = new UserDao();
        foreach( $arList['list'] as $ak=>$av ) {
            $projid = $av['projectid'];    
            $userid = $av['peopleid'];
            $arList['list'][$ak]['project_msg'] = $projectDao->queryByID( $projid );
            $arList['list'][$ak]['people_msg'] = $userDao->queryByID( $userid );
        }
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($arList, $error);
        return $ret;
    }

    public function actionExcel() {
        $this->defineMethod = 'GET';

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(APP_PATH."/static/shenjichengguo.xlsx");


        // Redirect output to a client’s web browser (Xlsx)
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="审计成果录入.xlsx"');
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

    }
}
