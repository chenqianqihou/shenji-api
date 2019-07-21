<?php

namespace app\modules\api\controllers;

use app\classes\BaseController;
use app\classes\ErrorDict;
use app\classes\Log;
use app\classes\Pinyin;
use app\models\ExpertiseDao;
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
        $role = intval($this->getParam('role', 0));
        $position = intval($this->getParam('position', 0));
        $organization = intval($this->getParam('organization', 0));
        $organService = new OrganizationService();
        $organInfo = $organService->getOrganizationInfo($organization);
        if (!$organInfo) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '所属机构填写错误');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        //校验基本信息
        //todo 校验手机号、邮箱、身份证号是否已存在
        if (!isset(UserDao::$type[$type])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '人员类型填写错误');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if (!isset(UserDao::$sex[$sex])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '性别填写错误');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if (!isset(UserDao::$education[$education])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '学历填写错误');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if (!isset(UserDao::$political[$political])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '政治面貌填写错误');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        $namePinyin = Pinyin::utf8_to($name);
        if ($namePinyin == "") {
            $namePinyin = rand(10000, 20000);
        }
        $pid = 'sj' . $namePinyin;
        //判断用户名是否存在
        $userService = new UserService();
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
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '用户名重复01-09已分配完成，不在分配，请联系管理员处理');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        $passwd = md5('12345678');
        $tr = Yii::$app->get('db')->beginTransaction();
        try {
            //不同审计人员类别，填写不同的数据
            if ($type == UserDao::$typeToName['审计机关']) {
                $department = intval($this->getParam('department', 0));
                $nature = intval($this->getParam('nature', 0));
                $techtitle = $this->getParam('techtitle', '');
                $expertise = $this->getParam('expertise', '');
                $train = $this->getParam('train', '');
                $workbegin = $this->getParam('workbegin', '');
                $auditbegin = $this->getParam('auditbegin', '');
                //校验所属部门信息
                $organInfo = $organService->getOrganizationInfo($department);
                if (!$organInfo) {
                    $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '所属部门填写错误');
                    $ret = $this->outputJson('', $error);
                    return $ret;
                }
                if ($organInfo['parentid'] != $organization) {
                    $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '所属部门不在所属机构下');
                    $ret = $this->outputJson('', $error);
                    return $ret;
                }
                //校验审计机构的其他信息
                if (!isset(UserDao::$position[$position])) {
                    $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '现任职务填写错误');
                    $ret = $this->outputJson('', $error);
                    return $ret;
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
                        $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '专业技术资质信息填写错误');
                        $ret = $this->outputJson('', $error);
                        return $ret;
                    }
                }
                $userService->AddPeopleInfo($pid, $name, $sex, $type, $organization, '', $level, $phone, $email,
                    $passwd, $cardid, $address, $education, $school, $major, $political, 0,
                    $specialties, $achievements, $position, $location, $curTime, $curTime, $comment);
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
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '',
                '部分人员删除成功');
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
        $role = intval($this->getParam('role', 0));
        $position = $this->getParam('position', '');
        $organization = intval($this->getParam('organization', 0));
        $organService = new OrganizationService();
        $organInfo = $organService->getOrganizationInfo($organization);
        if (!$organInfo) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '所属机构填写错误');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        //校验基本信息
        //todo 校验手机号、邮箱、身份证号是否已存在
        if (!isset(UserDao::$type[$type])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '人员类型填写错误');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if (!isset(UserDao::$sex[$sex])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '性别填写错误');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if (!isset(UserDao::$education[$education])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '学历填写错误');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if (!isset(UserDao::$political[$political])) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '政治面貌填写错误');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        $userService = new UserService();
        $tr = Yii::$app->get('db')->beginTransaction();
        try {
            //不同审计人员类别，填写不同的数据
            if ($type == UserDao::$typeToName['审计机关']) {
                $department = $this->getParam('department', 0);
                $nature = intval($this->getParam('nature', 0));
                $techtitle = $this->getParam('techtitle', '');
                $expertise = $this->getParam('expertise', '');
                $train = $this->getParam('train', '');
                $workbegin = $this->getParam('workbegin', '');
                $auditbegin = $this->getParam('auditbegin', '');
                //校验所属部门信息
                $organInfo = $organService->getOrganizationInfo($department);
                if (!$organInfo) {
                    $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '所属部门填写错误');
                    $ret = $this->outputJson('', $error);
                    return $ret;
                }
                if ($organInfo['parentid'] != $organization) {
                    $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '所属部门不在所属机构下');
                    $ret = $this->outputJson('', $error);
                    return $ret;
                }
                //校验审计机构的其他信息
                if (!isset(UserDao::$position[$position])) {
                    $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '现任职务填写错误');
                    $ret = $this->outputJson('', $error);
                    return $ret;
                }
                if (!isset(UserDao::$nature[$nature])) {
                    $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '岗位性质填写错误');
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
                        $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '业务培训情况填写错误');
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
                    '', '', $position, $location, $workbegin, $auditbegin, $comment);

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
                        $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '专业技术资质信息填写错误');
                        $ret = $this->outputJson('', $error);
                        return $ret;
                    }
                    foreach ($qualificationArr as $one) {
                        $one['time'] = date('Y-m-d', $one['time']);
                        $qualificationDao->addQualification($pid, $one['info'], $one['time']);
                    }
                }
                $userService->updatePeopleInfo($pid, $name, $sex, $type, $organization, '', $level, $phone, $email,
                    $cardid, $address, $education, $school, $major, $political, 0,
                    $specialties, $achievements, $position, $location, $curTime, $curTime, $comment);
            }
            //todo role id 是否准确
            //先删除，后添加
            $roleDao = new RoleDao();
            $roleDao->deletePeopleRole($pid);
            $roleIdArr = explode(',', $role);
            foreach ($roleIdArr as $rid) {
                $roleDao->addPeopleRole($pid, $rid);
            }
            $tr->commit();
        }catch (Exception $e) {
            $tr->rollBack();
            var_dump($e);
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
            'organization' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'type' => array (
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
        //查询类型 1 所有 2 人员类型 3 具体机构
        $organizationArr = [1, 2, 3];
        $organization = $this->getParam('organization');
        $type = $this->getParam('type', '');
        $organid = $this->getParam('organid', '');
        $query = $this->getParam('query', '');
        $length = $this->getParam('length');
        $page = $this->getParam('page');
        if (!in_array($organization, $organizationArr)) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', 'organization is error');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        $userService = new UserService();
        $data = $userService->getUserList($organization, $type, $organid, $query, $length, $page);
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
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '用户不存在');
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        if ($old != $userInfo['passwd']) {
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', 'old password is error');
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
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '用户不存在');
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
            //先删除，后添加
            $roleDao = new RoleDao();
            $roleDao->deletePeopleRole($pid);
            $roleIdArr = explode(',', $role);
            foreach ($roleIdArr as $rid) {
                $roleDao->addPeopleRole($pid, $rid);
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
        $organList = $organService->getOrganizationListByType(3);
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
            $validation->setPrompt('审计特长输入格式和可选内容为："'.$techtitlestr.'"');

            $validation = $spreadsheet->getActiveSheet()->getCell('X'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_WHOLE);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setPromptTitle('角色配置 录入');
            $validation->setPrompt('角色配置 输入格式和可选内容为："'.$techtitlestr.'"');

    
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

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        Yii::$app->end();
    }
}
