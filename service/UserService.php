<?php

namespace app\service;

use app\models\ExpertiseDao;
use app\models\TechtitleDao;
use app\models\UserDao;

class UserService
{
    // 查询用户信息
    public function getUserInfo($pid) {
        $userDao = new UserDao();
        $userInfo = $userDao->queryByID($pid);
        if ($userInfo) {
            $expertiseId = $userInfo['expertise_id'];
            $techtitleId = $userInfo['techtitle_id'];
            $techtitleDao = new TechtitleDao();
            $techtitleInfo = $techtitleDao->queryById($techtitleId);
            if($techtitleInfo) {
                $userInfo['techtitle'] = $techtitleInfo['name'];
            }else {
                return false;
            }
            $expertiseArr = [];
            $expertiseIdArr = explode(',', $expertiseId);
            $expertiseDao = new ExpertiseDao();
            foreach ($expertiseIdArr as $id) {
                $expertiseInfo = $expertiseDao->queryById($id);
                if ($expertiseInfo) {
                    $expertiseArr[$expertiseInfo['id']] =  $expertiseInfo['name'];
                }else {
                    return false;
                }
            }
            $userInfo['expertise'] = $expertiseArr;
        }
        return $userInfo;
    }

    //查询人员属性下拉选的配置信息
    public function getSelectConfig() {
        $expertiseDao = new ExpertiseDao();
        $expertiseList = [];
        $expertiseInfo = $expertiseDao->queryAll();
        foreach ($expertiseInfo as $one) {
            $expertiseList[$one['id']] = $one['name'];
        }
        $techtitleDao = new TechtitleDao();
        $techtitleList = [];
        $techtitleInfo = $techtitleDao->queryAll();
        foreach ($techtitleInfo as $one) {
            $techtitleList[$one['id']] = $one['name'];
        }
        $selectConfig = [
            'education' => UserDao::$education,
            'level' => UserDao::$level,
            'nature' => UserDao::$nature,
            'political' => UserDao::$political,
            'position' => UserDao::$position,
            'expertise' => $expertiseList,
            'techtitle' => $techtitleList,
        ];
        return $selectConfig;
    }
}
