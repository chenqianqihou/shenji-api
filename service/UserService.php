<?php

namespace app\service;

use app\models\UserDao;
use Yii;
use linslin\yii2\curl\Curl;

class UserService
{

    // 查询用户所属组
    public function getUserGroup($userId) {
        $userDao = new UserDao();
        $ret = $userDao->queryByName($userId);
        return $ret;
    }
}
