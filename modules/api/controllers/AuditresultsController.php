<?php

namespace app\modules\api\controllers;

use app\classes\BaseController;
use app\service\AuditresultsService;
use app\service\AssessService;
use app\models\ProjectDao;
use app\models\ViolationDao;
use app\models\UserDao;
use app\models\PeopleProjectDao;
use app\classes\ErrorDict;
use app\service\UserService;
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
        $arres['people_msg'] = $userDao->queryInfo( $userid );

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
        $peopleid = $this->data['ID'];
        $problemid = $this->getParam('problemid');
        $problemdetailid = $this->getParam('problemdetailid');

        //判断项目是否存在
        $projectDao = new ProjectDao();
        $projectInfo = $projectDao->queryByID($projectid);
        if (!$projectInfo) {
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
        $projectType = json_decode($projectInfo['projtype']);
        foreach( $violations as $p){
            if( $p['name'] == $projectType[0] ){
                foreach( $p['list'] as $pd ){
                    if( $pd['name'] == $projectType[1] ){
                        foreach( $pd['list'] as $pda ){
                            if ($pda['id'] == $problemid) {
                                foreach ($pda['list'] as $pdab) {
                                    if ($pdab['id'] == $problemdetailid) {
                                        $isvalid = true;
                                        break;
                                    }
                                }
                                break;
                            }
                        }
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
        $params['peopleid'] = $peopleid;

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
        $peopleid = $this->data['ID'];
        $problemid = $this->getParam('problemid');
        $problemdetailid = $this->getParam('problemdetailid');

        //判断项目是否存在
        $projectDao = new ProjectDao();
        $projectInfo = $projectDao->queryByID($projectid);
        if (!$projectInfo) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '项目不存在');
            $ret = $this->outputJson('', $error);
            return $ret;    
        }

        //判断用户是否存在
        $userService = new UserService();
        $userInfo = $userService->getPeopleInfo($peopleid);
        if ( !$userInfo ) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '用户不存在');
            $ret = $this->outputJson('', $error);
            return $ret;    
        }
        
        //判断problemid和detailid是否合法
        $assessService = new AssessService();
        $violations = $assessService->Violations();
        $isvalid = false;
        $projectType = json_decode($projectInfo['projtype']);
        foreach( $violations as $p){
            if( $p['name'] == $projectType[0] ){
                foreach( $p['list'] as $pd ){
                    if( $pd['name'] == $projectType[1] ){
                        foreach( $pd['list'] as $pda ){
                            if ($pda['id'] == $problemid) {
                                foreach ($pda['list'] as $pdab) {
                                    if ($pdab['id'] == $problemdetailid) {
                                        $isvalid = true;
                                        break;
                                    }
                                }
                                break;
                            }
                        }
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
        $params['peopleid'] = $userInfo['id'];

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
        $projectid = $this->getParam('projectid','');
        $status = intval($this->getParam('status',-1));
        $start = $this->getParam('start',0);
        $length = $this->getParam('length',10);
        $arService = new AuditresultsService();
        $arList = $arService->getAuditResultsList( $projectid,$status,$start,$length );
        $projectDao = new ProjectDao();
        $userDao = new UserDao();
        foreach( $arList['list'] as $ak=>$av ) {
            $projid = $av['projectid'];    
            $userid = $av['peopleid'];
            $arList['list'][$ak]['project_msg'] = $projectDao->queryByID( $projid );
            $arList['list'][$ak]['people_msg'] = $userDao->queryInfo( $userid );
        }
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($arList, $error);
        return $ret;
    }

    public function actionExcel() {
        $this->defineMethod = 'GET';

        //$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(APP_PATH."/static/shenjichengguo.xlsx");


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

        //$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        //$writer->save('php://output');
        readfile( APP_PATH."/static/shenjichengguo.xlsx");
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
        unset( $sheetData[2]);

        $insertData = [];
        foreach( $sheetData as $sk=>$data) {
            $tmpdata = [];
            if(empty($data['A']) || empty($data['B'])) {
                continue;    
            }

            $userObj = UserDao::find()->where(['pid'=>$data['A']])->one();
            if( is_null($userObj) ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '第'.$sk.'行第A列错误,用户不存在！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $tmpdata['peopleid'] = $userObj->id;

            $projObj = ProjectDao::find()->where(['projectnum'=>$data['B']])->one();
            if( is_null($projObj) ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '第'.$sk.'行第B列错误,项目编号不存在！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $tmpdata['projectid'] = $projObj->id;

            //判断用户和项目是否有关联
            if( PeopleProjectDao::find()->where(['pid'=>$tmpdata['peopleid'],'projid'=>$tmpdata['projectid']])->count() <= 0) {
                //$error = ErrorDict::getError(ErrorDict::G_PARAM, '', '第'.$sk.'行，找不到人员和项目编号的对应关系！');
                //$ret = $this->outputJson('', $error);
                //return $ret;
            }

            $tmpdata['havereport'] = explode(':',$data['E'])[0];
            if( empty($tmpdata['havereport']) ||!is_numeric($tmpdata['havereport']) || !in_array($tmpdata['havereport'],[1,2]) ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '第'.$sk.'行第E列类型格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }

            $problemid = ViolationDao::find()->where(['name'=>trim($data['F'])])->one();
            if( is_null($problemid) ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '第'.$sk.'行第F列类型格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $tmpdata['problemid'] = $problemid->id;
            $problemdetailid = ViolationDao::find()->where(['name'=>trim($data['G'])])->one();
            if( is_null($problemdetailid) ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '第'.$sk.'行第G列类型格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $tmpdata['problemdetailid'] = $problemdetailid->id;

            $tmpdata['amountone'] = intval($data['H']);
            if( !is_numeric($tmpdata['amountone']) ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '第'.$sk.'行第H列类型格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $tmpdata['amounttwo'] = intval($data['I']);
            if( !is_numeric($tmpdata['amounttwo']) ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '第'.$sk.'行第I列类型格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $tmpdata['amountthree'] = intval($data['J']);
            if( !is_numeric($tmpdata['amountthree']) ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '第'.$sk.'行第J列类型格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $tmpdata['amountfour'] = intval($data['K']);
            if( !is_numeric($tmpdata['amountfour']) ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '第'.$sk.'行第K列类型格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $tmpdata['amountfive'] = intval($data['L']);
            if( !is_numeric($tmpdata['amountfive']) ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '第'.$sk.'行第L列类型格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $tmpdata['amountsix'] = intval($data['M']);
            if( !is_numeric($tmpdata['amountsix']) ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '第'.$sk.'行第M列类型格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }

            $tmpdata['desc'] = $data['N'];

            $tmpdata['isfindout'] = explode(':',$data['O'])[0];
            if( empty($tmpdata['isfindout']) ||!is_numeric($tmpdata['isfindout']) || !in_array($tmpdata['isfindout'],[1,2]) ){
                $tmpdata['isfindout'] = 0;
            }
            
            $tmpdata['findoutnum'] = $data['P'];
            if( empty($tmpdata['findoutnum']) ||!is_numeric($tmpdata['findoutnum']) ){
                $tmpdata['findoutnum'] = 0;
            }

            $tmpdata['istransfer'] = explode(':',$data['Q'])[0];
            if( empty($tmpdata['istransfer']) ||!is_numeric($tmpdata['istransfer']) || !in_array($tmpdata['istransfer'],[1,2]) ){
                $tmpdata['istransfer'] = 0;
            }
            $tmpdata['processorgans'] = explode(':',$data['R'])[0];
            if( empty($tmpdata['processorgans']) ||!is_numeric($tmpdata['processorgans']) || !in_array($tmpdata['processorgans'],[1,2,3]) ){
                $tmpdata['processorgans'] = 0;
            }
            $tmpdata['transferamount'] = $data['S'];
            if( empty($tmpdata['transferamount']) ||!is_numeric($tmpdata['transferamount']) ){
                $tmpdata['transferamount'] = 0;
            }
            $tmpdata['transferpeoplenum'] = $data['T'];
            if( empty($tmpdata['transferpeoplenum']) ||!is_numeric($tmpdata['transferpeoplenum']) ){
                $tmpdata['transferpeoplenum'] = 0;
            }
            $tmpdata['transferpeopletype'] = explode(':',$data['U'])[0];
            if( empty($tmpdata['transferpeopletype']) ||!is_numeric($tmpdata['transferpeopletype']) || !in_array($tmpdata['transferpeopletype'],[1,2,3,4]) ){
                $tmpdata['transferpeopletype'] = 0;
            }
            $tmpdata['transferresult'] = $data['V'];
            $tmpdata['bringintoone'] = explode(':',$data['W'])[0];
            if( empty($tmpdata['bringintoone']) ||!is_numeric($tmpdata['bringintoone']) || !in_array($tmpdata['bringintoone'],[1,2]) ){
                $tmpdata['bringintoone'] = 0;
            }
            $tmpdata['bringintotwo'] = explode(':',$data['X'])[0];
            if( empty($tmpdata['bringintotwo']) ||!is_numeric($tmpdata['bringintotwo']) || !in_array($tmpdata['bringintotwo'],[1,2]) ){
                $tmpdata['bringintotwo'] = 0;
            }
            $tmpdata['appraisal'] = explode(':',$data['Y'])[0];
            if( empty($tmpdata['appraisal']) ||!is_numeric($tmpdata['appraisal']) || !in_array($tmpdata['appraisal'],[1,2,3,4,5,6,7,8]) ){
                $tmpdata['appraisal'] = 0;
            }
            
            $insertData[] = $tmpdata;
            break;
        }

        $arservice = new AuditresultsService();
        foreach( $insertData as $idata ) {
            $arservice->SubmitAuditResult( $idata );
        }

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson(true, $error);
        return $ret;
    }

    //查询用户参与的项目列表
    public function actionProjectlist() {
        $this->defineMethod = 'POST';
        $this->defineParams = array ();
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $pid = $this->data['ID'];
        $userService = new UserService();
        $peopleInfo = $userService->getPeopleInfo($pid);
        $projects = (new \yii\db\Query())
            ->from('peopleproject')
            ->innerJoin('project', 'peopleproject.projid = project.id')
            ->select('project.*, peopleproject.roletype')
            ->where(['peopleproject.pid' => $peopleInfo['id']])
            ->groupBy('projid')
            ->all();
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($projects, $error);
        return $ret;
    }
}
