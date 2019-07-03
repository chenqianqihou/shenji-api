<?php

namespace app\service;

use app\models\UserDao;

class UserService
{

    // 查询用户信息
    public function getUserInfo($pid) {
        $userDao = new UserDao();
        $ret = $userDao->queryByID($pid);
        return $ret;
    }
}
