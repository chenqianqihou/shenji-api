<?php

namespace app\service;

use app\classes\Log;
use app\models\AuditGroupDao;
use app\models\ExpertiseDao;
use app\models\OrganizationDao;
use app\models\QualificationDao;
use app\models\RoleDao;
use app\models\TechtitleDao;
use app\models\TrainDao;
use app\models\UserDao;
use yii\web\User;
use Yii;
use yii\db\Exception;
use app\classes\Pinyin;

class UserService
{
    //工作状态
    public static $jobStatus = [
        1 => "在点",
        2 => "不在点",
        3 => "-",
    ];

    //工作状态
    public static $jobStatusToName = [
        "在点" => 1,
        "不在点" => 2,
        "-" => 3,
    ];

    public function AddPeopleInfo($pid, $name, $sex, $type, $organId, $department, $level, $phone, $email,
                                  $passwd, $cardid, $address, $education, $school, $major, $political, $nature,
                                  $specialties, $achievements, $position, $location, $workbegin, $auditbegin, $comment, $isAudit, $isJob) {
        $userDao = new UserDao();
        $ret = $userDao->addPeople($pid, $name, $sex, $type, $organId, $department, $level, $phone, $email,
            $passwd, $cardid, $address, $education, $school, $major, $political, $nature,
            $specialties, $achievements, $position, $location, $workbegin, $auditbegin, $comment, $isAudit, $isJob);
        return $ret;
    }

    public function updatePeopleInfo($pid, $name, $sex, $type, $organId, $department, $level, $phone, $email,
                                  $cardid, $address, $education, $school, $major, $political, $nature,
                                  $specialties, $achievements, $position, $location, $workbegin, $auditbegin, $comment, $isAudit) {
        $userDao = new UserDao();
        $ret = $userDao->updatePeople($pid, $name, $sex, $type, $organId, $department, $level, $phone, $email,
            $cardid, $address, $education, $school, $major, $political, $nature,
            $specialties, $achievements, $position, $location, $workbegin, $auditbegin, $comment, $isAudit);
        return $ret;
    }

    //查询people表信息
    public function getPeopleInfo($pid) {
        $userDao = new UserDao();
        $userInfo = $userDao->queryByID($pid);
        return $userInfo;
    }

    //查询身份证信息
    public function getPeopleByIdCard($idCard) {
        $userDao = new UserDao();
        $userInfo = $userDao->queryByIDCard($idCard);
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
                    $techtitleArr[] = $one['tid'];
                }
                $expertiseArr = [];
                $expertiseDao = new ExpertiseDao();
                $expertiseAllInfo = $expertiseDao->queryByPid($pid);
                foreach ($expertiseAllInfo as $one) {
                    $expertiseArr[] =  $one['eid'];
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
                $userInfo['workbegin'] = strtotime($userInfo['workbegin']);
                unset($userInfo['department']);
                unset($userInfo['techtitle']);
                unset($userInfo['expertise']);
                unset($userInfo['train']);
                unset($userInfo['auditbegin']);
                unset($userInfo['nature']);
            }
            $roleArr = [];
            $roleDao = new RoleDao();
            $roleAllInfo = $roleDao->queryByPid($pid);
            foreach ($roleAllInfo as $one) {
                $roleArr[] =  $one['rid'];
            }
            $userInfo['organization'] = $userInfo['organid'];
            $userInfo['role'] = $roleArr;
            unset($userInfo['id']);
            unset($userInfo['passwd']);
            unset($userInfo['organid']);
        }
        return $userInfo;
    }

    // 用户列表
    public function getUserList($type, $regNum, $organid, $query, $status, $sex, $education,
                                $position, $techtitle, $expertise, $auditBeginLeft, $auditBeginRight, $length, $page) {
        $data = [
            'list' => [],
            'total' => 0,
        ];
        $list = [];
        $type = intval($type);
        if (empty($type)) {
            return $data;
        }
        if (!isset(UserDao::$type[$type])) {
            return $data;
        }
        if ($type != UserDao::$typeToName['审计机关']) {
            $position = ''; $techtitle = ''; $expertise = ''; $auditBeginLeft = ''; $auditBeginRight = '';
        }
        //判断按行政区查询还是审计机构查询
        if ($regNum) {
            //查询行政区编码下面的机构ID
            $organizationService = new OrganizationService();
            $organids = $organizationService->getOrganIdByRegNum($regNum);
            if (count($organids) == 0) {
                return $data;
            }
            $departid = 0;
        }else {
            $organid = intval($organid);
            $departid = 0;
            if (empty($organid)) {
                return $data;
            }
            $organizationService = new OrganizationService();
            $organInfo = $organizationService->getOrganizationInfo($organid);
            if ($organInfo['parentid'] != 0) {
                $departid = $organid;
                $organids = [];
            }else {
                $organids = [$organid];
            }
        }
        $userDao = new UserDao();
        $page = intval($page);
        if ($page < 1) {
            $page = 1;
        }
        $start = $length * ($page - 1);
        $organids = implode(',', $organids);
        $userList = $userDao->queryPeopleListNew($type, $organids, $departid, $query, $status, $sex, $education,
            $position, $techtitle, $expertise, $auditBeginLeft, $auditBeginRight, $start, $length);
        $organizationService = new OrganizationService();
        $organizationInfo = [];
        $allOrganization = $organizationService->getAllOrganization();
        foreach ($allOrganization as $one) {
            $organizationInfo[$one['id']] = $one['name'];
        }
        $auditGroupDao = new AuditGroupDao();
        $groupCount = [];
        $groupCountInfo = $auditGroupDao->queryUnEndGroupCount();
        foreach ($groupCountInfo as $one) {
            $groupCount[$one['pid']] = $one['c'];
        }
        $roleDao = new RoleDao();
        foreach ($userList as $user) {
            $one = [];
            $one['name'] = $user['name'];
            $one['pid'] = $user['pid'];
            $one['sex'] = $user['sex'];
            $one['organization'] = isset($organizationInfo[$user['organid']]) ? $organizationInfo[$user['organid']] : '';
            $one['department'] = isset($organizationInfo[$user['department']]) ? $organizationInfo[$user['department']] : '';
            if (isset($groupCount[$user['id']])) {
                $projectnum = $groupCount[$user['id']];
            }else {
                $projectnum = 0;
            }
            $one['status'] = $user['isjob'];
            $one['projectnum'] = $projectnum;
            $roleList = [];
            $roleInfo = $roleDao->queryByPid($user['pid']);
            if ($roleInfo) {
                foreach ($roleInfo as $role) {
                    $roleList[] = $role['rid'];
                }
            }
            $one['role'] = $roleList;
            $one['type'] = intval($user['type']);
            $list[] = $one;
        }
        $userDao = new UserDao();
        $count = $userDao->countPeopleListNew($type, $organids, $departid, $query, $status, $sex, $education,
            $position, $techtitle, $expertise, $auditBeginLeft, $auditBeginRight);
        $data['list'] = $list;
        $data['total'] = $count;
        return $data;
    }

    // 删除用户信息
    public function deleteUserInfo($pid, $type) {
        $tr = Yii::$app->get('db')->beginTransaction();
        try {
            if ($type == UserDao::$typeToName['审计机关']) {
                $techtitleDao = new TechtitleDao();
                $techtitleDao->deletePeopletitle($pid);
                $expertiseDao = new ExpertiseDao();
                $expertiseDao->deletePeopleExpertise($pid);
                $trainDao = new TrainDao();
                $trainDao->deleteTrain($pid);
            } else {
                $qualificationDao = new QualificationDao();
                $qualificationDao->deleteQualification($pid);
            }
            $roleDao = new RoleDao();
            $roleDao->deletePeopleRole($pid);
            $userDao = new UserDao();
            $userDao->deletePeople($pid);
            $tr->commit();
        }catch (Exception $e) {
            $tr->rollBack();
            Log::addLogNode('deleteUserException', serialize($e->errorInfo));
            return false;
        }
        return true;
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
        $roleDao = new RoleDao();
        $roleList = [];
        $roleAllInfo = $roleDao->queryAll();
        foreach ($roleAllInfo as $one) {
            $roleList[$one['id']] = $one['name'];
        }
        $selectConfig = [
            'sex' => UserDao::$sex,
            'type' => UserDao::$type,
            'education' => UserDao::$education,
            'level' => UserDao::$level,
            'nature' => UserDao::$nature,
            'political' => UserDao::$political,
            'position' => UserDao::$position,
            'expertise' => $expertiseList,
            'techtitle' => $techtitleList,
            'role' => $roleList,
            'status' => UserService::$jobStatus
        ];
        return $selectConfig;
    }

    /**
     * 添加人员信息(审计机关/内审、中介)
     *
     * @param $params
     * @return mixed
     * @throws \yii\base\InvalidConfigException
     */
    public function addNewUser($params, $type) {

        // 参数校验
        if(!array_key_exists($type, UserDao::$type)){
            return false;
        }
        $needed = [
            "name", "cardid", "sex", "phone", "email", "address",
            "education", "school", "major", "political", "location", "level",
            "comment", "role", "position", "organization", "workbegin",
        ];
        $sjneeded = [
            "department", "nature", "techtitle", "expertise", "train", "auditbegin"
        ];
        $thirdNeeded = [
            "specialties", "qualification", "achievements"
        ];
        if($type == UserDao::$typeToName['审计机关']){
            $needed = array_merge($needed, $sjneeded);
        }else{
            $needed = array_merge($needed, $thirdNeeded);
        }
        foreach ($needed as $e) {
            if (!in_array($e, $params)) {
                return false;
            }
        }


        $type = intval($type);
        $name = $params['name'];
        $cardid = $params['cardid'];
        $sex = intval($params['sex']);
        $phone = $params['phone'];
        $email = $params['email'];
        $address = $params['address'];
        $education = intval($params['education']);
        $school = $params['school'];
        $major = $params['major'];
        $political = intval($params['political']);
        $location = $params['location'];
        $level = intval($params['level']);
        $comment = $params['comment'];
        $role = $params['role'];
        $position = intval($params['position']);
        $organization = intval($params['organization']);
        $workbegin = $params['workbegin'];
        $organService = new OrganizationService();
        $organInfo = $organService->getOrganizationInfo($organization);
        if (!$organInfo) {
            return false;
        }
        //校验基本信息
        $userService = new UserService();
        $existIdCard = $userService->getPeopleByIdCard($cardid);
        if ($existIdCard) {
            return false;
        }
        if (!isset(UserDao::$type[$type])) {
            return false;
        }
        if (!isset(UserDao::$sex[$sex])) {
            return false;
        }
        if (!isset(UserDao::$education[$education])) {
            return false;
        }
        if (!isset(UserDao::$political[$political])) {
            return false;
        }
        $namePinyin = Pinyin::utf8_to($name);
        if ($namePinyin == "") {
            $namePinyin = rand(10000, 20000);
        }
        $pid = 'sj' . $namePinyin;
        //判断用户名是否存在
        $i = 0;
        $unique = false;
        while ($i < 10) {
            $peopleInfo = $userService->getPeopleInfo($pid);
            if ($peopleInfo) {
                $i++;
                $pid = 'sj' . $namePinyin . '0' . $i;
            }else {
                $unique = true;
                break;
            }
        }
        if (!$unique) {
            return false;
        }
        $passwd = md5('12345678');
        $tr = Yii::$app->get('db')->beginTransaction();
        try {
            //不同审计人员类别，填写不同的数据
            if ($type == UserDao::$typeToName['审计机关']) {
                $department = intval($params['department']);
                $nature = intval($params['nature']);
                $techtitle = $params['techtitle'];
                $expertise = $params['expertise'];
                $train = $params['train'];
                $auditbegin = $params['auditbegin'];
                //校验所属部门信息
                $organInfo = $organService->getOrganizationInfo($department);
                if (!$organInfo) {
                   return false;
                }
                if ($organInfo['parentid'] != $organization) {
                   return false;
                }
                //校验审计机构的其他信息
                if (!isset(UserDao::$position[$position])) {
                   return false;
                }
                if (!isset(UserDao::$nature[$nature])) {
                    $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '岗位性质填写错误');
                    $ret = $this->outputJson('', $error);
                    return $ret;
                }
                $workbegin = date('Y-m-d H:i:s', intval($workbegin));
                $auditbegin = date('Y-m-d H:i:s', intval($auditbegin));
                //todo 判断techtitle ID是否存在
                $techtitleDao = new TechtitleDao();
                $techtitleIdArr = explode(',', $techtitle);
                foreach ($techtitleIdArr as $tid) {
                    $tid = intval($tid);
                    if ($tid) {
                        $techtitleDao->addPeopletitle($pid, $tid);
                    }
                }
                //todo 判断expertise ID是否存在
                $expertiseDao = new ExpertiseDao();
                $expertiseIdArr = explode(',', $expertise);
                foreach ($expertiseIdArr as $eid) {
                    $eid = intval($eid);
                    if ($eid) {
                        $expertiseDao->addPeopleExpertise($pid, $eid);
                    }
                }
                $trainDao = new TrainDao();
                $trainArr = $train;
                if ($train) {
                    if (is_array($trainArr)) {
                        foreach ($trainArr as $train) {
                            $trainDao->addTrain($pid, $train);
                        }
                    }else {
                        $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '业务培训情况填写错误');
                        $ret = $this->outputJson('', $error);
                        return $ret;
                    }
                }
                //录入数据
                $userService->AddPeopleInfo($pid, $name, $sex, $type, $organization, $department, $level, $phone, $email,
                    $passwd, $cardid, $address, $education, $school, $major, $political, $nature,
                    '', '', $position, $location, $workbegin, $auditbegin, $comment);

            }else {
                $specialties = $params['specialties'];
                $qualification = $params['qualification'];
                $achievements = $params['achievements'];
                $qualificationDao = new QualificationDao();
                $qualificationArr = $qualification;
                $curTime = date('Y-m-d H:i:s');
                if ($qualificationArr) {
                    if (is_array($qualificationArr)) {
                        foreach ($qualificationArr as $one) {
                            $one['time'] = date('Y-m-d', $one['time']);
                            $qualificationDao->addQualification($pid, $one['info'], $one['time']);
                        }
                    }else {
                        return false;
                    }
                }
                $workbegin = date('Y-m-d H:i:s', intval($workbegin));
                $userService->AddPeopleInfo($pid, $name, $sex, $type, $organization, 0, $level, $phone, $email,
                    $passwd, $cardid, $address, $education, $school, $major, $political, 0,
                    $specialties, $achievements, $position, $location, $workbegin, $curTime, $comment);
            }
            //todo role id 是否准确
            $roleDao = new RoleDao();
            $roleIdArr = explode(',', $role);
            foreach ($roleIdArr as $rid) {
                $roleDao->addPeopleRole($pid, $rid);
            }
            $tr->commit();
        }catch (Exception $e) {
            $tr->rollBack();
            Log::addLogNode('addException', serialize($e->errorInfo));
            return false;
        }
        return true;
    }

    //查询人员是否在点
    public function peopleJobStatus($pid) {
        $auditGroupDao = new AuditGroupDao();
        $groupInfo = $auditGroupDao->queryJoinGroup($pid);
        if (count($groupInfo) > 0) {
            return UserService::$jobStatusToName['在点'];
        }else {
            return UserService::$jobStatusToName['不在点'];
        }
    }

    //查询机构下人员列表
    public function getOrganPeopleList($organId) {
        $peopleList = UserDao::find()
            ->where(['organid' => $organId])
            ->asArray()
            ->all();
        return $peopleList;
    }

    //查询部门下人员列表
    public function getDepartmentPeopleList($departmentId) {
        $peopleList = UserDao::find()
            ->where(['department' => $departmentId])
            ->asArray()
            ->all();
        return $peopleList;
    }
}
