<?php

namespace app\modules\api\controllers;

use app\classes\BaseController;
use app\classes\ErrorDict;
use app\models\UserDao;
use app\service\UserService;
use Yii;
use \Lcobucci\JWT\Builder;
use \Lcobucci\JWT\Signer\Hmac\Sha256;

class UserController extends BaseController
{

    //用户登陆
    public function actionLogin()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'account' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'pwd' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $account = $this->getParam('account', '');
        $pwd = $this->getParam('pwd', '');
        $salt = '';
        $pwd = md5($pwd . $salt);
        $userService = new UserService();
        $userInfo = $userService->getPeopleInfo($account);
        if (!$userInfo) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '用户不存在');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if ($pwd != $userInfo['passwd']) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', 'sorry, account or password error.');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        $builder = new Builder();
        $signer  = new Sha256();
        $secret = Yii::$app->params['secret'];
        //设置header和payload，以下的字段都可以自定义
        $builder->setIssuer("shenji") //发布者
            ->setAudience("shenji") //接收者
            ->setId("abc", true) //对当前token设置的标识
            ->setIssuedAt(time()) //token创建时间
            ->setExpiration(time() + 3600) //过期时间
            ->setNotBefore(time() + 5) //当前时间在这个时间前，token不能使用
            ->set('ID', $userInfo['pid'])
            ->set('name', $userInfo['name']); //自定义数据
        //设置签名
        $builder->sign($signer, $secret);
        //获取加密后的token，转为字符串
        $token = (string)$builder->getToken();
        $returnInfo = [
            'token' => $token,
        ];
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($returnInfo, $error);
        return $ret;
    }

    //新建人员
    public function actionAdd()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'type' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'name' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'cardid' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'sex' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'phone' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'email' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'address' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'education' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'school' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'major' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'political' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'location' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'level' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'department' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'position' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'nature' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'techtitle' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'expertise' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'train' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'workbegin' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'auditbegin' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'organization' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'qualification' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'achievements' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'comment' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'role' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $type = $this->getParam('type', '');
        $name = $this->getParam('name', '');
        $cardid = $this->getParam('cardid', '');
        $sex = $this->getParam('sex', '');
        $phone = $this->getParam('phone', '');
        $email = $this->getParam('email', '');
        $address = $this->getParam('address', '');
        $education = $this->getParam('education', '');
        $school = $this->getParam('school', '');
        $major = $this->getParam('major', '');
        $political = $this->getParam('political', '');
        $location = $this->getParam('location', '');
        $level = $this->getParam('level', '');
        $comment = $this->getParam('comment', '');
        $role = $this->getParam('role', '');
        //校验基本信息
        if (!isset(UserDao::$type[$type])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', 'type is error');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if (!isset(UserDao::$sex[$sex])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', 'sex is error');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if (!isset(UserDao::$education[$education])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', 'education is error');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if (!isset(UserDao::$political[$political])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', 'political is error');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if (!isset(UserDao::$political[$political])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', 'political is error');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if (!isset(UserDao::$political[$political])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', 'political is error');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        //不同审计人员类别，填写不同的数据
        if ($type == UserDao::$typeToName['审计机关']) {
            $department = $this->getParam('department', '');
            $position = $this->getParam('position', '');
            $nature = $this->getParam('nature', '');
            $techtitle = $this->getParam('techtitle', '');
            $expertise = $this->getParam('expertise', '');
            $train = $this->getParam('train', '');
            $workbegin = $this->getParam('workbegin', '');
            $auditbegin = $this->getParam('auditbegin', '');
        }else {
            $department = $this->getParam('department', '');
            $position = $this->getParam('position', '');
            $nature = $this->getParam('nature', '');
            $techtitle = $this->getParam('techtitle', '');
            $expertise = $this->getParam('expertise', '');
            $train = $this->getParam('train', '');
            $workbegin = $this->getParam('workbegin', '');
            $auditbegin = $this->getParam('auditbegin', '');
        }


        $salt = '';
        $pwd = md5($pwd . $salt);
        $userService = new UserService();
        $userInfo = $userService->getPeopleInfo($account);
        if (!$userInfo) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '用户不存在');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if ($pwd != $userInfo['passwd']) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', 'sorry, account or password error.');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        $builder = new Builder();
        $signer  = new Sha256();
        $secret = Yii::$app->params['secret'];
        //设置header和payload，以下的字段都可以自定义
        $builder->setIssuer("shenji") //发布者
        ->setAudience("shenji") //接收者
        ->setId("abc", true) //对当前token设置的标识
        ->setIssuedAt(time()) //token创建时间
        ->setExpiration(time() + 3600) //过期时间
        ->setNotBefore(time() + 5) //当前时间在这个时间前，token不能使用
        ->set('ID', $userInfo['pid'])
            ->set('name', $userInfo['name']); //自定义数据
        //设置签名
        $builder->sign($signer, $secret);
        //获取加密后的token，转为字符串
        $token = (string)$builder->getToken();
        $returnInfo = [
            'token' => $token,
        ];
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($returnInfo, $error);
        return $ret;
    }

    //用户信息
    public function actionInfo() {
        $ID = $this->data['ID'];
        $userService = new UserService();
        $userInfo = $userService->getUserInfo($ID);
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($userInfo, $error);
        return $ret;
    }

    //用户属性下拉选配置
    public function actionSelectconfig() {
        $userService = new UserService();
        $selectConfig = $userService->getSelectConfig();
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($selectConfig, $error);
        return $ret;
    }
}
