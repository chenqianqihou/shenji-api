<?php

namespace app\modules\api\controllers;

use app\classes\BaseController;
use app\classes\ErrorDict;
use app\classes\Util;
use app\classes\Log;
use app\classes\Pinyin;
use app\models\ExpertiseDao;
use app\models\OrganizationDao;
use app\models\QualificationDao;
use app\models\RoleDao;
use app\models\TechtitleDao;
use app\models\TrainDao;
use app\models\UserDao;
use app\service\OrganizationService;
use app\service\UserService;
use Yii;
use \Lcobucci\JWT\Builder;
use \Lcobucci\JWT\Signer\Hmac\Sha256;
use yii\db\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use yii\debug\models\search\User;

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
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '用户不存在');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if ($pwd != $userInfo['passwd']) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '密码错误');
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
            ->setExpiration(time() + 3600*24) //过期时间
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
                'require' => true,
                'checker' => 'noCheck',
            ),
            'specialties' => array (
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
        $type = intval($this->getParam('type', 0));
        $name = $this->getParam('name', '');
        $cardid = $this->getParam('cardid', '');
        $sex = intval($this->getParam('sex', 0));
        $phone = $this->getParam('phone', '');
        $email = $this->getParam('email', '');
        $address = $this->getParam('address', '');
        $education = intval($this->getParam('education', 0));
        $school = $this->getParam('school', '');
        $major = $this->getParam('major', '');
        $political = intval($this->getParam('political', 0));
        $location = $this->getParam('location', '');
        $level = intval($this->getParam('level', 0));
        $comment = $this->getParam('comment', '');
        $role = $this->getParam('role', '');
        $position = intval($this->getParam('position', 0));
        $organization = intval($this->getParam('organization', 0));
        $workbegin = $this->getParam('workbegin', '');
        $organService = new OrganizationService();
        $organInfo = $organService->getOrganizationInfo($organization);
        if (!$organInfo) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '所属机构填写错误');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        $userService = new UserService();
        //校验基本信息
        if ($cardid) {
            $existIdCard = $userService->getPeopleByIdCard($cardid);
            if ($existIdCard) {
                $error = ErrorDict::getError(ErrorDict::G_PARAM, '身份证号已经注册过用户！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
        }
        if ($phone) {
            $existIdPhone = UserDao::find()->where(['phone' => $phone])->one();
            if ($existIdPhone) {
                $error = ErrorDict::getError(ErrorDict::G_PARAM, '电话已经注册过用户！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
        }

        if (!isset(UserDao::$type[$type])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '人员类型填写错误');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if (!isset(UserDao::$sex[$sex])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '性别填写错误');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if (!isset(UserDao::$education[$education])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '学历填写错误');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if (!isset(UserDao::$political[$political])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '政治面貌填写错误');
            $ret = $this->outputJson('', $error);
            return $ret;
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
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '用户名重复01-09已分配完成，不在分配，请联系管理员处理');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        $passwd = md5('12345678');
        $tr = Yii::$app->get('db')->beginTransaction();
        try {
            //todo role id 是否准确
            $isAudit = UserDao::$isAuditToName['不是'];
            $roleDao = new RoleDao();
            $roleIdArr = explode(',', $role);
            foreach ($roleIdArr as $rid) {
                //判断是否角色为“审计组员”
                if ($rid == 8) {
                    $isAudit = UserDao::$isAuditToName['是'];
                }
                $roleDao->addPeopleRole($pid, $rid);
            }
            if ($isAudit == UserDao::$isAuditToName['是']) {
                $isJob = UserDao::$isJobToName['不在点'];
            }else {
                $isJob = UserDao::$isJobToName['-'];
            }
            //不同审计人员类别，填写不同的数据
            if ($type == UserDao::$typeToName['审计机关']) {
                $department = intval($this->getParam('department', 0));
                $nature = intval($this->getParam('nature', 0));
                $techtitle = $this->getParam('techtitle', '');
                $expertise = $this->getParam('expertise', '');
                $train = $this->getParam('train', '');
                $auditbegin = $this->getParam('auditbegin', '');
                //校验所属部门信息
                $organInfo = $organService->getOrganizationInfo($department);
                if (!$organInfo) {
                    $error = ErrorDict::getError(ErrorDict::G_PARAM, '所属部门填写错误');
                    $ret = $this->outputJson('', $error);
                    return $ret;
                }
                if ($organInfo['parentid'] != $organization) {
                    $error = ErrorDict::getError(ErrorDict::G_PARAM, '所属部门不在所属机构下');
                    $ret = $this->outputJson('', $error);
                    return $ret;
                }
                //校验审计机构的其他信息
                if (!isset(UserDao::$position[$position])) {
                    $error = ErrorDict::getError(ErrorDict::G_PARAM, '现任职务填写错误');
                    $ret = $this->outputJson('', $error);
                    return $ret;
                }
                if (!isset(UserDao::$nature[$nature])) {
                    $error = ErrorDict::getError(ErrorDict::G_PARAM, '岗位性质填写错误');
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
                        $error = ErrorDict::getError(ErrorDict::G_PARAM, '业务培训情况填写错误');
                        $ret = $this->outputJson('', $error);
                        return $ret;
                    }
                }
                //录入数据
                $userService->AddPeopleInfo($pid, $name, $sex, $type, $organization, $department, $level, $phone, $email,
                    $passwd, $cardid, $address, $education, $school, $major, $political, $nature,
                    '', '', $position, $location, $workbegin, $auditbegin, $comment, $isAudit, $isJob);

            }else {
                $specialties = $this->getParam('specialties', '');
                $qualification = $this->getParam('qualification', '');
                $achievements = $this->getParam('achievements', '');
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
                        $error = ErrorDict::getError(ErrorDict::G_PARAM, '专业技术资质信息填写错误');
                        $ret = $this->outputJson('', $error);
                        return $ret;
                    }
                }
                $workbegin = date('Y-m-d H:i:s', intval($workbegin));
                $userService->AddPeopleInfo($pid, $name, $sex, $type, $organization, 0, $level, $phone, $email,
                    $passwd, $cardid, $address, $education, $school, $major, $political, 0,
                    $specialties, $achievements, $position, $location, $workbegin, $curTime, $comment, $isAudit, $isJob);
            }
            $tr->commit();
        }catch (Exception $e) {
            $tr->rollBack();
            Log::fatal($e->getMessage());
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson('', $error);
        return $ret;
    }

    //删除人员
    public function actionDelete()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'pid' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $pidArr = $this->getParam('pid', '');
        $successPid = [];
        $failPid = [];
        $userService = new UserService();
        foreach ($pidArr as $pid) {
            //todo 当人员还有项目时，不可删除
            $peopleInfo = $userService->getPeopleInfo($pid);
            if ($peopleInfo) {
                $deleteRet = $userService->deleteUserInfo($pid, $peopleInfo['type']);
                if ($deleteRet) {
                    $successPid[] = $pid;
                }else {
                    $failPid[] = $pid;
                }
            }else {
                $successPid[] = $pid;
            }
        }
        if (count($failPid) == 0) {
            $error = ErrorDict::getError(ErrorDict::SUCCESS);
            $ret = $this->outputJson('', $error);
            return $ret;
        }else {
            Log::addLogNode('delete user fail pid:', json_encode($failPid));
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '部分人员删除成功');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
    }

    //修改人员信息
    public function actionUpdate()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'pid' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
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
                'require' => true,
                'checker' => 'noCheck',
            ),
            'specialties' => array (
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
        $pid = $this->getParam('pid', '');
        $type = intval($this->getParam('type', 0));
        $name = $this->getParam('name', '');
        $cardid = $this->getParam('cardid', '');
        $sex = intval($this->getParam('sex', 0));
        $phone = $this->getParam('phone', '');
        $email = $this->getParam('email', '');
        $address = $this->getParam('address', '');
        $education = intval($this->getParam('education', 0));
        $school = $this->getParam('school', '');
        $major = $this->getParam('major', '');
        $political = intval($this->getParam('political', 0));
        $location = $this->getParam('location', '');
        $level = intval($this->getParam('level', 0));
        $comment = $this->getParam('comment', '');
        $role = $this->getParam('role', "");
        $position = $this->getParam('position', '');
        $organization = intval($this->getParam('organization', 0));
        $workbegin = $this->getParam('workbegin', '');
        $organService = new OrganizationService();
        $organInfo = $organService->getOrganizationInfo($organization);
        if (!$organInfo) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '所属机构填写错误');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        //校验基本信息
        $userService = new UserService();
        $oldPeopleInfo = $userService->getPeopleInfo($pid);
        if (!$oldPeopleInfo) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM,  '不存在此用户');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if ($oldPeopleInfo['cardid'] != $cardid) {
            $existIdCard = $userService->getPeopleByIdCard($cardid);
            if ($existIdCard) {
                $error = ErrorDict::getError(ErrorDict::G_PARAM, '身份证号已经注册过用户！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
        }
        if (!isset(UserDao::$type[$type])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '人员类型填写错误');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if (!isset(UserDao::$sex[$sex])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '性别填写错误');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if (!isset(UserDao::$education[$education])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '学历填写错误');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if (!isset(UserDao::$political[$political])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '政治面貌填写错误');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        $tr = Yii::$app->get('db')->beginTransaction();
        try {
            //todo role id 是否准确
            //先删除，后添加
            $isAudit = UserDao::$isAuditToName['不是'];
            $roleDao = new RoleDao();
            $roleDao->deletePeopleRole($pid);
            $roleIdArr = explode(',', $role);
            foreach ($roleIdArr as $rid) {
                //判断是否角色为“审计组员”
                if ($rid == 8) {
                    $isAudit = UserDao::$isAuditToName['是'];
                }
                $roleDao->addPeopleRole($pid, $rid);
            }

            //不同审计人员类别，填写不同的数据
            if ($type == UserDao::$typeToName['审计机关']) {
                $department = $this->getParam('department', 0);
                $nature = intval($this->getParam('nature', 0));
                $techtitle = $this->getParam('techtitle', '');
                $expertise = $this->getParam('expertise', '');
                $train = $this->getParam('train', '');
                $auditbegin = $this->getParam('auditbegin', '');
                //校验所属部门信息
                $organInfo = $organService->getOrganizationInfo($department);
                if (!$organInfo) {
                    $error = ErrorDict::getError(ErrorDict::G_PARAM, '所属部门填写错误');
                    $ret = $this->outputJson('', $error);
                    return $ret;
                }
                if ($organInfo['parentid'] != $organization) {
                    $error = ErrorDict::getError(ErrorDict::G_PARAM, '所属部门不在所属机构下');
                    $ret = $this->outputJson('', $error);
                    return $ret;
                }
                //校验审计机构的其他信息
                if (!isset(UserDao::$position[$position])) {
                    $error = ErrorDict::getError(ErrorDict::G_PARAM, '现任职务填写错误');
                    $ret = $this->outputJson('', $error);
                    return $ret;
                }
                if (!isset(UserDao::$nature[$nature])) {
                    $error = ErrorDict::getError(ErrorDict::G_PARAM, '岗位性质填写错误');
                    $ret = $this->outputJson('', $error);
                    return $ret;
                }
                $workbegin = date('Y-m-d H:i:s', $workbegin);
                $auditbegin = date('Y-m-d H:i:s', $auditbegin);
                //todo 判断techtitle ID是否存在
                //先删除，再重新插入
                $techtitleDao = new TechtitleDao();
                $techtitleDao->deletePeopletitle($pid);
                $techtitleIdArr = explode(',', $techtitle);
                foreach ($techtitleIdArr as $tid) {
                    $tid = intval($tid);
                    if ($tid) {
                        $techtitleDao->addPeopletitle($pid, $tid);
                    }
                }
                //todo 判断expertise ID是否存在
                //先删除，再重新插入
                $expertiseDao = new ExpertiseDao();
                $expertiseDao->deletePeopleExpertise($pid);
                $expertiseIdArr = explode(',', $expertise);
                foreach ($expertiseIdArr as $eid) {
                    $eid = intval($eid);
                    if ($eid) {
                        $expertiseDao->addPeopleExpertise($pid, $eid);
                    }
                }
                //先删除，再重新插入
                $trainDao = new TrainDao();
                $trainDao->deleteTrain($pid);
                if ($train) {
                    if (!is_array($train)) {
                        $error = ErrorDict::getError(ErrorDict::G_PARAM, '业务培训情况填写错误');
                        $ret = $this->outputJson('', $error);
                        return $ret;
                    }
                    foreach ($train as $oneTrain) {
                        $trainDao->addTrain($pid, $oneTrain);
                    }
                }
                //更新数据
                $userService->updatePeopleInfo($pid, $name, $sex, $type, $organization, $department, $level, $phone, $email,
                    $cardid, $address, $education, $school, $major, $political, $nature,
                    '', '', $position, $location, $workbegin, $auditbegin, $comment, $isAudit);

            }else {
                $specialties = $this->getParam('specialties', '');
                $qualification = $this->getParam('qualification', '');
                $achievements = $this->getParam('achievements', '');
                //先删除，后添加
                $qualificationDao = new QualificationDao();
                $qualificationDao->deleteQualification($pid);
                $qualificationArr = $qualification;
                $curTime = date('Y-m-d H:i:s');
                if ($qualificationArr) {
                    if (!is_array($qualificationArr)) {
                        $error = ErrorDict::getError(ErrorDict::G_PARAM, '专业技术资质信息填写错误');
                        $ret = $this->outputJson('', $error);
                        return $ret;
                    }
                    foreach ($qualificationArr as $one) {
                        $one['time'] = date('Y-m-d', $one['time']);
                        $qualificationDao->addQualification($pid, $one['info'], $one['time']);
                    }
                }
                $workbegin = date('Y-m-d H:i:s', $workbegin);
                $userService->updatePeopleInfo($pid, $name, $sex, $type, $organization, 0, $level, $phone, $email,
                    $cardid, $address, $education, $school, $major, $political, 0,
                    $specialties, $achievements, $position, $location, $workbegin, $curTime, $comment, $isAudit);
            }
            $tr->commit();
        }catch (Exception $e) {
            $tr->rollBack();
            Log::addLogNode('addException', serialize($e->errorInfo));
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson('', $error);
        return $ret;
    }

    //用户信息
    public function actionInfo() {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'account' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $pid = $this->getParam('account');
        $userService = new UserService();
        $userInfo = $userService->getUserInfo($pid);
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($userInfo, $error);
        return $ret;
    }

    //个人信息
    public function actionMy() {
        $pid = $this->data['ID'];
        $userService = new UserService();
        $userInfo = $userService->getUserInfo($pid);
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($userInfo, $error);
        return $ret;
    }

    //查询列表
    public function actionList() {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'type' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'regnum' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'organid' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'query' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'status' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'sex' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'education' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'position' => array (
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
            'auditbeginleft' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'auditbeginright' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'length' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'page' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $type = $this->getParam('type', ''); //人员类型 1 中介机构 2 内审机构 3 审计机关 4 第三方机构
        $regNum = $this->getParam('regnum', '');
        $organid = $this->getParam('organid', '');
        $query = $this->getParam('query', '');
        $status = intval($this->getParam('status', 0));
        $sex = $this->getParam('sex', '');
        $education = intval($this->getParam('education', 0));
        $position = $this->getParam('position', '');
        $techtitle = $this->getParam('techtitle', '');
        $expertise = intval($this->getParam('expertise', 0));
        $auditBeginLeft = $this->getParam('auditbeginleft', '');
        $auditBeginRight = $this->getParam('auditbeginright', '');
        $length = $this->getParam('length');
        $page = $this->getParam('page');
        if (intval($auditBeginLeft) && intval($auditBeginRight)) {
            $auditBeginLeft = date('Y-m-d', intval($auditBeginLeft));
            $auditBeginRight = date('Y-m-d', intval($auditBeginRight));
        }
        $userService = new UserService();
        $data = $userService->getUserList($type, $regNum, $organid, $query, $status, $sex, $education,
            $position, $techtitle, $expertise, $auditBeginLeft, $auditBeginRight, $length, $page);
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($data, $error);
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

    //修改用户密码
    public function actionPwdupdate()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'old' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'new' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $old = $this->getParam('old', '');
        $new = $this->getParam('new', '');
        $pid = $this->data['ID'];
        $salt = '';
        $old = md5($old . $salt);
        $new = md5($new . $salt);
        $userService = new UserService();
        $userInfo = $userService->getPeopleInfo($pid);
        if (!$userInfo) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '用户不存在');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if ($old != $userInfo['passwd']) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, 'old password is error');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if ($old == $new) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '新旧密码一致，无需修改！');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        $userDao = new UserDao();
        $ret = $userDao->updatePassword($pid, $new);
        if ($ret) {
            $error = ErrorDict::getError(ErrorDict::SUCCESS);
            $ret = $this->outputJson('', $error);
            return $ret;
        }else {
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson('', $error);
            return $ret;
        }
    }

    //重置密码
    public function actionPwdreset()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'pid' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $pid = $this->getParam('pid', '');
        $salt = '';
        $passwd = md5("12345678" . $salt);
        $userDao = new UserDao();
        $userInfo = $userDao->queryByID($pid);
        if (!$userInfo) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '用户不存在');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if ($userInfo['passwd'] == $passwd) {
            $error = ErrorDict::getError(ErrorDict::SUCCESS);
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        $ret = $userDao->updatePassword($pid, $passwd);
        if ($ret) {
            $error = ErrorDict::getError(ErrorDict::SUCCESS);
            $ret = $this->outputJson('', $error);
            return $ret;
        }else {
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson('', $error);
            return $ret;
        }
    }

    //修改角色
    public function actionUpdaterole()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'pid' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'role' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $pid = $this->getParam('pid', '');
        $role = $this->getParam('role', '');
        $tr = Yii::$app->get('db')->beginTransaction();
        try {
            //todo role id 是否准确
            //先删除，后添加
            $isAudit = UserDao::$isAuditToName['不是'];
            $roleDao = new RoleDao();
            $roleDao->deletePeopleRole($pid);
            $roleIdArr = explode(',', $role);
            foreach ($roleIdArr as $rid) {
                //判断是否角色为“审计组员”
                if ($rid == 8) {
                    $isAudit = UserDao::$isAuditToName['是'];
                }
                $roleDao->addPeopleRole($pid, $rid);
            }
            $userDao = new UserDao();
            $userInfo = $userDao->queryByID($pid);
            if ($userInfo['isaudit'] != $isAudit) {
                $userDao->updateIsAudit($pid, $isAudit);
                if ($isAudit == UserDao::$isAuditToName['是'] && $userInfo['isjob'] == UserDao::$isJobToName['-']) {
                    $userDao->updateIsJob($pid, UserDao::$isJobToName['不在点']);
                }elseif ($isAudit == UserDao::$isAuditToName['不是'] && $userInfo['isjob'] == UserDao::$isJobToName['不在点']) {
                    $userDao->updateIsJob($pid, UserDao::$isJobToName['-']);
                }
            }
            $tr->commit();
        }catch (Exception $e) {
            $tr->rollBack();
            Log::addLogNode('update role exception', serialize($e->errorInfo));
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson('', $error);
        return $ret;
    }

    public function actionOrgansexcel() {
        $this->defineMethod = 'GET';

        $userService = new UserService();
        $selectConfig = $userService->getSelectConfig();

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(APP_PATH."/static/jiguanrenyuanluru.xlsx");

        //教育学历
        $ddedu = [];
        foreach( $selectConfig['education'] as $ek=>$ev){
            $ddedu[] = $ek.':'.$ev;    
        }
        $ddedustr = join(',',$ddedu);
        $ddedustr = ' ,'.$ddedustr;

        //政治面貌
        $politicalarr = [];
        foreach( $selectConfig['political'] as $pk=>$pv){
            $politicalarr[] = $pk.':'.$pv;    
        }
        $politicalstr = join(',',$politicalarr);
        $politicalstr = ' ,'.$politicalstr;

        //现任职务
        $positionarr = [];
        foreach( $selectConfig['position'] as $pk=>$pv){
            $positionarr[] = $pk.':'.$pv;    
        }
        $positionstr = join(',',$positionarr);
        $positionstr = ' ,'.$positionstr;

        //人员类型
        $typearr = [];
        foreach( $selectConfig['type'] as $tk=>$tv) {
            $typearr[] = $tk.':'.$tv;    
        }
        $typestr = join(',',$typearr);
        $typestr = ' ,'.$typestr;

        //岗位性质
        $naturearr = [];
        foreach( $selectConfig['nature'] as $tk=>$tv) {
            $naturearr[] = $tk.':'.$tv;    
        }
        $naturestr = join(',',$naturearr);
        $naturestr = ' ,'.$naturestr;

        //专业技术职称
        $techtitlestr = join(' / ', $selectConfig['techtitle']);

        //审计特长
        $expertisestr = join(' / ', $selectConfig['expertise']);

        //角色配置
        $rolestr = join(' / ',$selectConfig['role']);


        $organService = new OrganizationService();
        $organList = $organService->getOrganizationListByType(3,false);
        foreach( $organList as $ok=>$ov){
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('ZZ'.($ok+1), $ov['id'].':'.$ov['name']);
        }

        $ss = 2;
        $se = 1100;
        
        for($ss = 2; $ss < 1000;$ss++){

            $validation = $spreadsheet->getActiveSheet()->getCell('R'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_WHOLE);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setPromptTitle('专业技术职称 录入');
            $validation->setPrompt('专业技术职称 输入格式和可选内容为："'.$techtitlestr.'"');

            $validation = $spreadsheet->getActiveSheet()->getCell('S'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_WHOLE);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setPromptTitle('审计特长 录入');
            $validation->setPrompt('审计特长输入格式和可选内容为："'.$expertisestr.'"');

            $validation = $spreadsheet->getActiveSheet()->getCell('X'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_WHOLE);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setPromptTitle('角色配置 录入');
            $validation->setPrompt('角色配置 输入格式和可选内容为："'.$rolestr.'"');

    
            $validation = $spreadsheet->getActiveSheet()->getCell('F'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('Value is not in list.');
            $validation->setPromptTitle('Pick from list');
            $validation->setPrompt('Please pick a value from the drop-down list.');
            $validation->setFormula1('"'.$ddedustr.'"'); 

            $validation = $spreadsheet->getActiveSheet()->getCell('I'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('Value is not in list.');
            $validation->setPromptTitle('Pick from list');
            $validation->setPrompt('Please pick a value from the drop-down list.');
            $validation->setFormula1('"'.$politicalstr.'"'); 

            $validation = $spreadsheet->getActiveSheet()->getCell('P'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('Value is not in list.');
            $validation->setPromptTitle('Pick from list');
            $validation->setPrompt('Please pick a value from the drop-down list.');
            $validation->setFormula1('"'.$positionstr.'"'); 

            $validation = $spreadsheet->getActiveSheet()->getCell('M'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('Value is not in list.');
            $validation->setPromptTitle('Pick from list');
            $validation->setPrompt('Please pick a value from the drop-down list.');
            $validation->setFormula1('"'.$typestr.'"'); 


            $validation = $spreadsheet->getActiveSheet()->getCell('N'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('Value is not in list.');
            $validation->setPromptTitle('Pick from list');
            $validation->setPrompt('Please pick a value from the drop-down list.');
            $validation->setFormula1('=$ZZ$1:$ZZ$'.count($organList)); 

            $validation = $spreadsheet->getActiveSheet()->getCell('Q'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('Value is not in list.');
            $validation->setPromptTitle('Pick from list');
            $validation->setPrompt('Please pick a value from the drop-down list.');
            $validation->setFormula1('"'.$naturestr.'"'); 


            $spreadsheet->getActiveSheet()->getStyle('U'.$ss) 
                ->getNumberFormat() 
                ->setFormatCode( 
                        \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_YYYYMMDD2
                        ); 
            $validation = $spreadsheet->getActiveSheet()->getCell('U'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_DATE);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('请输入正确的日期格式 ‘2019-06-12’');
            $validation->setPromptTitle('Allowed input');
            $validation->setPrompt('请输入正确的日期格式 ‘2019-06-12’');

            $spreadsheet->getActiveSheet()->getStyle('V'.$ss) 
                ->getNumberFormat() 
                ->setFormatCode( 
                        \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_YYYYMMDD2
                        ); 
            $validation = $spreadsheet->getActiveSheet()->getCell('V'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_DATE);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('请输入正确的日期格式 ‘2019-06-12’');
            $validation->setPromptTitle('Allowed input');
            $validation->setPrompt('请输入正确的日期格式 ‘2019-06-12’');

        }

        // Redirect output to a client’s web browser (Xlsx)
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="审计机关人员导入.xlsx"');
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');
        // If you're serving to IE over SSL, then the following may be needed
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0
        header('Access-Control-Expose-Headers: Content-Disposition');
        header('Access-Control-Allow-Origin: *');


        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        Yii::$app->end();
    }

    public function actionOrgansexcelupload() {
        if( empty($_FILES["file"]) ){
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson('', $error);
            return $ret;
        }


        $userService = new UserService();
        $selectConfig = $userService->getSelectConfig();

        $districts = $this->getDistricts();

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['file']['tmp_name']);
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        unset( $sheetData[1]);
        
        $insertData = [];
        foreach( $sheetData as $data) {
            $tmpdata = [];
            $tmpdata['type'] = 1; //永远是审计机关;
            $tmpdata['name'] = $data['A'];
            if( empty($tmpdata['name']) ){
                continue;    
            }
            $tmpdata['cardid'] = $data['B'];
            if( !Util::checkIdCard( $data['B'] )) {
                $error = ErrorDict::getError(ErrorDict::G_PARAM, $data['B'].' 身份证号格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $tmpdata['sex'] = Util::getSexByIdcard($data['B']);
            $tmpdata['phone'] = $data['C'];
            $tmpdata['email'] = $data['D'];
            $tmpdata['address'] = $data['E'];
            $tmpdata['education'] = explode(':',$data['F'])[0];
            if( empty($tmpdata['education']) || !isset( $selectConfig['education'][$tmpdata['education']] ) ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, $data['F'].' 学历格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $tmpdata['school'] = $data['G'];
            $tmpdata['major'] = $data['H'];
            $tmpdata['political'] = explode(':',$data['I'])[0];
            if( empty($tmpdata['political']) || !isset( $selectConfig['political'][$tmpdata['political']] ) ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, $data['I'].' 政治面貌格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $shen = explode('_',$data['J']);
            if( count($shen) != 3 || !isset( $districts[$shen[1]] )){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, $data['J'].' 地区格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $shi = explode('_',$data['K']);
            if( count($shi) != 3 || !isset( $districts[$shi[1]] )){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, $data['K'].' 地区格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $qu = explode('_',$data['L']);
            if( count($qu) != 3 ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, $data['L'].' 地区格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $shenstr = $shen[1];
            $shistr = $shi[1];
            $qustr = $qu[1];
            $tmpdata['location'] = "$shenstr,$shistr,$qustr";
            $tmpdata['organization'] = explode(':',$data['N'])[0];
            //$tmpdata[''] = $data['O'];
            $tmpdata['position'] = explode(':',$data['P'])[0];
            if( empty($tmpdata['position']) || !isset( $selectConfig['position'][$tmpdata['position']] ) ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, $data['P'].' 职务格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $tmpdata['nature'] = explode(':',$data['Q'])[0];
            if( empty($tmpdata['nature']) || !isset( $selectConfig['nature'][$tmpdata['nature']] ) ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, $data['Q'].' 岗位性质格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $tmpdata['techtitle'] = [];
            $techtitleArr = explode('/',$data['R']);
            $techDict = array_flip( $selectConfig['techtitle'] );
            foreach( $techtitleArr as $tv ){
                $tv = trim( $tv );
                if( isset($techDict[$tv]) ){
                    $tmpdata['techtitle'][] = $techDict[$tv];    
                }
            }

            $tmpdata['expertise'] = [];
            $expertiseArr = explode('/',$data['S']);
            $expertiseDict = array_flip( $selectConfig['expertise'] );
            foreach( $expertiseArr as $tv ){
                $tv = trim( $tv );
                if( isset($expertiseDict[$tv]) ){
                    $tmpdata['expertise'][] = $expertiseDict[$tv];    
                }
            }
            $tmpdata['train'] = json_encode([$data['T']]);
            $tmpdata['workbegin'] = strtotime($data['U']);
            $tmpdata['auditbegin'] = strtotime($data['V']);
            $tmpdata['comment'] = $data['W'];
            $tmpdata['role'] = [];
            $roleArr = explode('/',$data['X']);
            $roleDict = array_flip( $selectConfig['role'] );
            foreach( $roleArr as $tv ){
                $tv = trim( $tv );
                if( isset($roleDict[$tv]) ){
                    $tmpdata['role'][] = $roleDict[$tv];    
                }
            }
            $insertData[] = $tmpdata;
        }


        $user = new UserService();
        foreach($insertData as $attributes) {
            $type = $attributes['type'];
            unset($attributes['type']);
            $user->addNewUser($attributes, $type);
        }


        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson('', $error);
        return $ret;
    }

    public function actionThirdpartexcel() {
        $this->defineMethod = 'GET';

        $userService = new UserService();
        $selectConfig = $userService->getSelectConfig();

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(APP_PATH."/static/thirdrenyuanluru.xlsx");

        //教育学历
        $ddedu = [];
        foreach( $selectConfig['education'] as $ek=>$ev){
            $ddedu[] = $ek.':'.$ev;    
        }
        $ddedustr = join(',',$ddedu);
        $ddedustr = ' ,'.$ddedustr;

        //政治面貌
        $politicalarr = [];
        foreach( $selectConfig['political'] as $pk=>$pv){
            $politicalarr[] = $pk.':'.$pv;    
        }
        $politicalstr = join(',',$politicalarr);
        $politicalstr = ' ,'.$politicalstr;

        //现任职务
        $positionarr = [];
        foreach( $selectConfig['position'] as $pk=>$pv){
            $positionarr[] = $pk.':'.$pv;    
        }
        $positionstr = join(',',$positionarr);
        $positionstr = ' ,'.$positionstr;

        //人员类型
        $typearr = [];
        foreach( $selectConfig['type'] as $tk=>$tv) {
            $typearr[] = $tk.':'.$tv;    
        }
        unset( $typearr[2] );
        $typestr = join(',',$typearr);
        $typestr = ' ,'.$typestr;

        //岗位性质
        $naturearr = [];
        foreach( $selectConfig['nature'] as $tk=>$tv) {
            $naturearr[] = $tk.':'.$tv;    
        }
        $naturestr = join(',',$naturearr);
        $naturestr = ' ,'.$naturestr;

        //专业技术职称
        $techtitlestr = join(' / ', $selectConfig['techtitle']);

        //审计特长
        $expertisestr = join(' / ', $selectConfig['expertise']);

        //角色配置
        $rolestr = join(' / ',$selectConfig['role']);


        $organService = new OrganizationService();
        $organList1 = $organService->getOrganizationListByType(1,false);
        $organList2 = $organService->getOrganizationListByType(2,false);
        $organList = array_merge($organList1,$organList2);
        foreach( $organList as $ok=>$ov){
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('ZZ'.($ok+1), $ov['id'].':'.$ov['name']);
        }

        $ss = 2;
        $se = 1100;
        
        for($ss = 2; $ss < 1000;$ss++){
            $validation = $spreadsheet->getActiveSheet()->getCell('V'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_WHOLE);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setPromptTitle('角色配置 录入');
            $validation->setPrompt('角色配置 输入格式和可选内容为："'.$rolestr.'"');

    
            $validation = $spreadsheet->getActiveSheet()->getCell('F'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('Value is not in list.');
            $validation->setPromptTitle('Pick from list');
            $validation->setPrompt('Please pick a value from the drop-down list.');
            $validation->setFormula1('"'.$ddedustr.'"'); 

            $validation = $spreadsheet->getActiveSheet()->getCell('I'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('Value is not in list.');
            $validation->setPromptTitle('Pick from list');
            $validation->setPrompt('Please pick a value from the drop-down list.');
            $validation->setFormula1('"'.$politicalstr.'"'); 

            $validation = $spreadsheet->getActiveSheet()->getCell('M'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('Value is not in list.');
            $validation->setPromptTitle('Pick from list');
            $validation->setPrompt('Please pick a value from the drop-down list.');
            $validation->setFormula1('"'.$typestr.'"'); 


            $validation = $spreadsheet->getActiveSheet()->getCell('N'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('Value is not in list.');
            $validation->setPromptTitle('Pick from list');
            $validation->setPrompt('Please pick a value from the drop-down list.');
            $validation->setFormula1('=$ZZ$1:$ZZ$'.count($organList)); 

            $spreadsheet->getActiveSheet()->getStyle('P'.$ss) 
                ->getNumberFormat() 
                ->setFormatCode( 
                        \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_YYYYMMDD2
                        ); 
            $validation = $spreadsheet->getActiveSheet()->getCell('P'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_DATE);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('请输入正确的日期格式 ‘2019-06-12’');
            $validation->setPromptTitle('Allowed input');
            $validation->setPrompt('请输入正确的日期格式 ‘2019-06-12’');

            $spreadsheet->getActiveSheet()->getStyle('S'.$ss) 
                ->getNumberFormat() 
                ->setFormatCode( 
                        \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_YYYYMMDD2
                        ); 
            $validation = $spreadsheet->getActiveSheet()->getCell('S'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_DATE);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('请输入正确的日期格式 ‘2019-06-12’');
            $validation->setPromptTitle('Allowed input');
            $validation->setPrompt('请输入正确的日期格式 ‘2019-06-12’');

        }

        // Redirect output to a client’s web browser (Xlsx)
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="内审中介人员导入.xlsx"');
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');
        // If you're serving to IE over SSL, then the following may be needed
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Expose-Headers: Content-Disposition');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        Yii::$app->end();
    }

    public function actionThirdpartexcelupload() {
        if( empty($_FILES['file']) ){
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson('', $error);
            return $ret;
        }  


        $userService = new UserService();
        $selectConfig = $userService->getSelectConfig();

        $districts = $this->getDistricts();

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['file']['tmp_name']);
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        unset( $sheetData[1]);
        
        $insertData = [];
        foreach( $sheetData as $data) {
            $tmpdata = [];
            $tmpdata['name'] = $data['A'];
            if( empty($tmpdata['name']) ){
                continue;    
            }
            $tmpdata['cardid'] = $data['B'];
            if( !Util::checkIdCard( $data['B'] )) {
                $error = ErrorDict::getError(ErrorDict::G_PARAM, $data['B'].' 身份证号格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $tmpdata['sex'] = Util::getSexByIdcard($data['B']);
            $tmpdata['phone'] = $data['C'];
            $tmpdata['email'] = $data['D'];
            $tmpdata['address'] = $data['E'];
            $tmpdata['education'] = explode(':',$data['F'])[0];
            if( empty($tmpdata['education']) || !isset( $selectConfig['education'][$tmpdata['education']] ) ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, $data['F'].' 学历格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $tmpdata['school'] = $data['G'];
            $tmpdata['major'] = $data['H'];
            $tmpdata['political'] = explode(':',$data['I'])[0];
            if( empty($tmpdata['political']) || !isset( $selectConfig['political'][$tmpdata['political']] ) ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, $data['I'].' 政治面貌格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $shen = explode('_',$data['J']);
            if( count($shen) != 3 || !isset( $districts[$shen[1]] )){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, $data['J'].' 地区格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $shi = explode('_',$data['K']);
            if( count($shi) != 3 || !isset( $districts[$shi[1]] )){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, $data['K'].' 地区格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $qu = explode('_',$data['L']);
            if( count($qu) != 3 ){
                $error = ErrorDict::getError(ErrorDict::G_PARAM, $data['L'].' 地区格式错误！');
                $ret = $this->outputJson('', $error);
                return $ret;
            }
            $shenstr = $shen[1];
            $shistr = $shi[1];
            $qustr = $qu[1];
            $tmpdata['location'] = "$shenstr,$shistr,$qustr";
            $tmpdata['type'] = explode(':',$data['M'])[0];
            $tmpdata['organization'] = explode(':',$data['N'])[0];
            $tmpdata['position'] = $data['O'];
            $tmpdata['workbegin'] = strtotime($data['P']);
            $tmpdata['specialties'] = $data['Q'];
            $tmpdata['qualification'] = [ ['info'=>$data['R'],'time'=>$data['S']] ];
            $tmpdata['achievements'] = $data['T'];

            $tmpdata['comment'] = $data['U'];
            $tmpdata['role'] = [];
            $roleArr = explode('/',$data['V']);
            $roleDict = array_flip( $selectConfig['role'] );
            foreach( $roleArr as $tv ){
                $tv = trim( $tv );
                if( isset($roleDict[$tv]) ){
                    $tmpdata['role'][] = $roleDict[$tv];    
                }
            }
            $insertData[] = $tmpdata;
        }

        foreach($insertData as $attributes) {
            $type = $attributes['type'];
            unset($attributes['type']);
            $user = new UserService();
            $user->addNewUser($attributes, $type);
        }

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson('', $error);
        return $ret;
    }

}
