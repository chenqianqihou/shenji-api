<?php

namespace app\modules\api\controllers;

use app\classes\BaseController;
use app\classes\ErrorDict;
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
                'uid' => array (
                    'require' => true,
                    'checker' => 'noCheck',
                    ),
                'projectid' => array (
                    'require' => true,
                    'checker' => 'noCheck',
                    ),
                );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }

        $uid = $this->getParam('uid');
        $pid = $this->getParam('projectid');

        $projDao = new ProjectDao();
        $projInfo = $projDao->queryByID($pid);
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
        
        print_r( $projInfo );die;
        
        $result = [];
        $result['stat'] = false;
        //根据项目状态判断当前评论的状态
        if( in_array($projInfo['status'],[3,4,5]) ){
            $result['stat'] = true;    
        }

        //todo 根据人员的角色和项目中审计组人员的列表分别给出评价状态

        //todo end

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson(true, $error);
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
    }
}
