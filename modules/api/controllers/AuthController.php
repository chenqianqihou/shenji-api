<?php

namespace app\modules\api\controllers;

use app\classes\BaseController;
use app\classes\ErrorDict;
use app\service\AuditGroupService;
use app\service\AuthService;
use app\service\ProjectService;
use app\service\AssessService;
use app\models\ProjectDao;
use app\models\UserDao;
use Yii;

class AuthController extends BaseController
{
    //权限列表
    public function actionList()
    {
        $this->defineMethod = 'POST';
        $retData = [];
        $authService = new AuthService();
        $authList = $authService->getAuthList();
        $retData['auth'] = $authList;
        $roleList = $authService->getRoleList();
        $retData['role'] = $roleList;
        $roleAuthList = $authService->getRoleAuthList();
        $retData['roleauth'] = $roleAuthList;
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($retData, $error);
        return $ret;
    }

    //变更权限
    public function actionUpdate() {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
                'roleauth' => array (
                    'require' => true,
                    'checker' => 'noCheck',
                    ),
                );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }

        $roleAuth = $this->getParam('roleauth');
        $authService = new AuthService();
        $ret = $authService->updateRoleAuth($roleAuth);
        if ($ret) {
            $error = ErrorDict::getError(ErrorDict::SUCCESS);
            $ret = $this->outputJson($ret, $error);
            return $ret;
        }else {
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson($ret, $error);
            return $ret;
        }
    }

}
