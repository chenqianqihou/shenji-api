<?php

namespace app\modules\api\controllers;

use app\classes\BaseController;
use app\classes\ErrorDict;
use app\service\UserService;
use Yii;

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
        //$userService = new UserService();
        //$userInfo = $userService->userInfo($account);
//        if (!$userInfo) {
//            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', 'sorry, phone or password error.');
//            $ret = $this->outputJson('', $error);
//            return $ret;
//        }
//        if ($pwd != $userInfo['pwd']) {
//            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', 'sorry, phone or password error.');
//            $ret = $this->outputJson('', $error);
//            return $ret;
//        }
        $builder = new Builder();
        $signer  = new Sha256();
        $secret = Yii::$app->params['secret'];
        var_dump(55555);
        die;
        //设置header和payload，以下的字段都可以自定义
        $builder->setIssuer("shenji") //发布者
            ->setAudience("shenji") //接收者
            ->setId("abc", true) //对当前token设置的标识
            ->setIssuedAt(time()) //token创建时间
            ->setExpiration(time() + 3600) //过期时间
            ->setNotBefore(time() + 5) //当前时间在这个时间前，token不能使用
            ->set('name', 'test'); //自定义数据
        //设置签名
        $builder->sign($signer, $secret);
        //获取加密后的token，转为字符串
        $token = (string)$builder->getToken();
        header("HTTP_AUTHORIZATION: $token");
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson('', $error);
        return $ret;
    }
}
