<?php

namespace app\service;

use app\models\ExpertiseDao;
use app\models\OrganizationDao;
use app\models\QualificationDao;
use app\models\RoleDao;
use app\models\TechtitleDao;
use app\models\TrainDao;
use app\models\UserDao;
use yii\web\User;

class UserService
{
    public function AddPeopleInfo($pid, $name, $sex, $type, $organId, $department, $level, $phone, $email,
                                  $passwd, $cardid, $address, $education, $school, $major, $political, $nature,
                                  $specialties, $achievements, $position, $location, $workbegin, $auditbegin, $comment) {
        $userDao = new UserDao();
        $ret = $userDao->addPeople($pid, $name, $sex, $type, $organId, $department, $level, $phone, $email,
            $passwd, $cardid, $address, $education, $school, $major, $political, $nature,
            $specialties, $achievements, $position, $location, $workbegin, $auditbegin, $comment);
        return $ret;
    }

    public function updatePeopleInfo($pid, $name, $sex, $type, $organId, $department, $level, $phone, $email,
                                  $cardid, $address, $education, $school, $major, $political, $nature,
                                  $specialties, $achievements, $position, $location, $workbegin, $auditbegin, $comment) {
        $userDao = new UserDao();
        $ret = $userDao->updatePeople($pid, $name, $sex, $type, $organId, $department, $level, $phone, $email,
            $cardid, $address, $education, $school, $major, $political, $nature,
            $specialties, $achievements, $position, $location, $workbegin, $auditbegin, $comment);
        return $ret;
    }

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
            if ($userInfo['type'] == UserDao::$typeToName['审计机关']) {
                $techtitleArr = [];
                $techtitleDao = new TechtitleDao();
                $techtitleAllInfo = $techtitleDao->queryByPid($pid);
                foreach ($techtitleAllInfo as $one) {
                    $techtitleArr[] = $one['name'];
                }
                $expertiseArr = [];
                $expertiseDao = new ExpertiseDao();
                $expertiseAllInfo = $expertiseDao->queryByPid($pid);
                foreach ($expertiseAllInfo as $one) {
                    $expertiseArr[] =  $one['name'];
                }
                $trainArr = [];
                $trainDao = new TrainDao();
                $trainAllInfo = $trainDao->queryByPid($pid);
                foreach ($trainAllInfo as $one) {
                    $trainArr[] =  $one['train'];
                }
                $userInfo['techtitle'] = $techtitleArr;
                $userInfo['expertise'] = $expertiseArr;
                $userInfo['train'] = $trainArr;
                $userInfo['workbegin'] = strtotime($userInfo['workbegin']);
                $userInfo['auditbegin'] = strtotime($userInfo['auditbegin']);
                $userInfo['nature'] = UserDao::$nature[$userInfo['nature']];
                unset($userInfo['specialties']);
                unset($userInfo['achievements']);

            }else {
                $qualificationArr = [];
                $qualificationDao = new QualificationDao();
                $qualificationAllInfo = $qualificationDao->queryByPid($pid);
                foreach ($qualificationAllInfo as $one) {
                    $q = [];
                    $q['info'] = $one['info'];
                    $q['time'] = strtotime($one['time']);
                    $qualificationArr[] = $q;
                }
                $userInfo['qualification'] = $qualificationArr;
                unset($userInfo['department']);
                unset($userInfo['techtitle']);
                unset($userInfo['expertise']);
                unset($userInfo['train']);
                unset($userInfo['workbegin']);
                unset($userInfo['auditbegin']);
                unset($userInfo['nature']);
            }
            $roleArr = [];
            $roleDao = new RoleDao();
            $roleAllInfo = $roleDao->queryByPid($pid);
            foreach ($roleAllInfo as $one) {
                $roleArr[] =  $one['name'];
            }
            $organizationService = new OrganizationService();
            $organizationInfo = $organizationService->getOrganizationInfo($userInfo['organid']);
            if ($organizationInfo) {
                $userInfo['organization'] = $organizationInfo['name'];
            }
            $userInfo['role'] = $roleArr;
            $userInfo['type'] = UserDao::$type[$userInfo['type']];
            $userInfo['sex'] = UserDao::$sex[$userInfo['sex']];
            $userInfo['level'] = UserDao::$level[$userInfo['level']];
            $userInfo['education'] = UserDao::$education[$userInfo['education']];
            $userInfo['political'] = UserDao::$political[$userInfo['political']];
            unset($userInfo['id']);
            unset($userInfo['passwd']);
            unset($userInfo['organid']);
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
            'type' => UserDao::$type,
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
