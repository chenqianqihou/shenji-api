<?php

namespace app\modules\api\controllers;

use app\classes\BaseController;
use app\classes\ErrorDict;
use app\service\UserService;
use Yii;
use yii\web\User;
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
        $userInfo = $userService->getUserInfo($account);
        if (!$userInfo) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', 'sorry, account or password error.');
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

    public function actionInfo() {
        $ID = $this->data['ID'];
        $userService = new UserService();
        $userInfo = $userService->getUserInfo($ID);
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($userInfo, $error);
        return $ret;
    }
}
