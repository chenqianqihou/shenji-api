<?php

namespace app\modules\api\controllers;

use app\classes\BaseController;
use app\classes\ErrorDict;
use app\service\AuditGroupService;
use app\service\ProjectService;
use app\service\AssessService;
use app\models\ProjectDao;
use app\models\UserDao;
use Yii;

class AssessController extends BaseController
{
    public function actionStatlist()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
                'projectid' => array (
                    'require' => true,
                    'checker' => 'noCheck',
                    ),
                );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $pid = $this->data['ID'];
        $projectId = $this->getParam('projectid');
        //todo 判断用户是否是领导
        $isLeader = true;
        $projDao = new ProjectDao();
        $projInfo = $projDao->queryByID($projectId);
        if( $projInfo == false){
            return $this->outputJson(
                    '',
                    ErrorDict::getError(ErrorDict::G_PARAM, "不存在的项目单位！")
                    );    
        }
        $assessService = new AssessService();
        $result = $assessService->assessList($pid, $projectId);
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($result, $error);
        return $ret;
    }

    public function actionForm()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
                'uid' => array (
                    'require' => true,
                    'checker' => 'noCheck',
                    ),
                'objuid' => array (
                    'require' => true,
                    'checker' => 'noCheck',
                    ),
                'projectid' => array (
                    'require' => true,
                    'checker' => 'noCheck',
                    ),
                'typeid' => array (
                    'require' => true,
                    'checker' => 'noCheck',
                    ),
                );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }

        $uid = $this->getParam('uid');
        $objuid = $this->getParam('objuid');
        $typeid = $this->getParam('typeid');
        $projectid = $this->getParam('projectid');

        $projDao = new ProjectDao();
        $projInfo = $projDao->queryByID($projectid);
        if( $projInfo == false){
            return $this->outputJson(
                    '',
                    ErrorDict::getError(ErrorDict::G_PARAM, "不存在的项目单位！")
                    );    
        }

        $userDao = new UserDao();
        $userInfo = $userDao->queryByID($uid);
        if( $userInfo == false){
            return $this->outputJson(
                    '',
                    ErrorDict::getError(ErrorDict::G_PARAM, "不存在的用户！")
                    );    
        }
        $objuserInfo = $userDao->queryByID($objuid);
        if( $objuserInfo == false){
            return $this->outputJson(
                    '',
                    ErrorDict::getError(ErrorDict::G_PARAM, "不存在的用户！")
                    );    
        }
        
        $result = [];
        $result['userinfo'] = $objuserInfo;

        $assessService = new AssessService();
        $formcontent = $assessService->FormContent( $uid,$objuid,$projectid,$typeid);
        $result['content'] = $formcontent;

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($result, $error);
        return $ret;
    }

    public function actionAssesstypes() {
        $assessService = new AssessService();    
        $types = $assessService->AssessType();
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($types, $error);
        return $ret;
    }

    public function actionSubmit() {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
                'uid' => array (
                    'require' => true,
                    'checker' => 'noCheck',
                    ),
                'objuid' => array (
                    'require' => true,
                    'checker' => 'noCheck',
                    ),
                'projectid' => array (
                    'require' => true,
                    'checker' => 'noCheck',
                    ),
                'typeid' => array (
                    'require' => true,
                    'checker' => 'noCheck',
                    ),
                'answers' => array (
                    'require' => true,
                    'checker' => 'noCheck',
                    ),
                );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }

        $uid = $this->getParam('uid');
        $objuid = $this->getParam('objuid');
        $typeid = $this->getParam('typeid');
        $projectid = $this->getParam('projectid');
        $answers = $this->getParam('answers');

        $projDao = new ProjectDao();
        $projInfo = $projDao->queryByID($projectid);
        if( $projInfo == false){
            return $this->outputJson(
                    '',
                    ErrorDict::getError(ErrorDict::G_PARAM, "不存在的项目单位！")
                    );    
        }

        $userDao = new UserDao();
        $userInfo = $userDao->queryByID($uid);
        if( $userInfo == false){
            return $this->outputJson(
                    '',
                    ErrorDict::getError(ErrorDict::G_PARAM, "不存在的用户！")
                    );    
        }
        $objuserInfo = $userDao->queryByID($objuid);
        if( $objuserInfo == false){
            return $this->outputJson(
                    '',
                    ErrorDict::getError(ErrorDict::G_PARAM, "不存在的用户！")
                    );    
        }

        $assessService = new AssessService();
        $formcontent = $assessService->SubmitFormContent( $uid,$objuid,$projectid,$typeid,$answers);

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($formcontent, $error);
        return $ret;
    }

    public function actionScoreconfig() {
        $assessService = new AssessService();    
        $configs = $assessService->ScoreConfig();
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($configs, $error);
        return $ret;
    }

    public function actionViolations() {
        $assessService = new AssessService();    
        $configs = $assessService->Violations();
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($configs, $error);
        return $ret;
    }

    public function actionSaveconfig() {
        $params = $this->getParams();
        $updres = [];
        $addres = [];
        foreach( $params as $pv ) {
            if( !isset( $pv['list'] ) ) {
                return $this->outputJson(
                        '',
                        ErrorDict::getError(ErrorDict::G_PARAM, "参数格式异常！")
                        );    
            }

            foreach( $pv['list'] as $pvv ) {
                if( !isset($pvv['list']) || !isset($pvv['id']) ||!isset($pvv['kindid'])){
                    return $this->outputJson(
                            '',
                            ErrorDict::getError(ErrorDict::G_PARAM, "参数子层级格式异常，缺少id/list/kindid！")
                            );    
                }    

                $typeid = $pvv['id'];
                $kindid = $pvv['kindid'];
                $list = $pvv['list'];

                foreach( $list as $lv ) {
                    if( !isset( $lv['nameone']) ){
                        continue;    
                    }
                    if( !isset( $lv['id'] ) || $lv['id'] == 0){
                        $lv['typeid'] = $typeid;
                        $lv['kindid'] = $kindid;
                        $addres[] = $lv;
                    } else {
                        $updres[] = $lv;    
                    }
                }
            }
        }

        $assessService = new AssessService();
        $assessService->addNewConfigs( $addres );
        $assessService->updateConfigs( $updres );

        $configs = $assessService->ScoreConfig();
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($configs, $error);
        return $ret;
    }

    public function actionDeleteconfig() {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
                'configid' => array (
                    'require' => true,
                    'checker' => 'noCheck',
                    ),
                );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }

        $configid = $this->getParam('configid');

        $assessService = new AssessService();
        $result = $assessService->deleteConfig( $configid );

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($result, $error);
        return $ret;
    }

}
