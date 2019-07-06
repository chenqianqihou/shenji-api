<?php

namespace app\service;

use app\models\ExpertiseDao;
use app\models\TechtitleDao;
use app\models\UserDao;

class UserService
{
    //查询people表信息
    public function getPeopleInfo($pid) {
        $userDao = new UserDao();
        $userInfo = $userDao->queryByID($pid);
        return $userInfo;
    }
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
                $userInfo['techtitle'] = "";
            }
            $expertiseArr = [];
            $expertiseIdArr = explode(',', $expertiseId);
            $expertiseDao = new ExpertiseDao();
            foreach ($expertiseIdArr as $id) {
                $expertiseInfo = $expertiseDao->queryById($id);
                if ($expertiseInfo) {
                    $expertiseArr[] =  $expertiseInfo['name'];
                }else {
                   continue;
                }
            }
            $userInfo['expertise'] = $expertiseArr;
            $userInfo['type'] = UserDao::$type[$userInfo['type']];
            unset($userInfo['id']);
            unset($userInfo['passwd']);
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
