<?php

namespace app\modules\api\controllers;

use app\classes\BaseController;
use app\classes\Checker;
use app\classes\ErrorDict;
use app\classes\Log;
use app\classes\Pinyin;
use app\models\ExpertiseDao;
use app\models\OrganizationDao;
use app\models\ProjectDao;
use app\models\QualificationDao;
use app\models\RoleDao;
use app\models\TechtitleDao;
use app\models\TrainDao;
use app\models\UserDao;
use app\service\OrganizationService;
use app\service\UserService;
use app\service\ProjectService;
use Yii;
use \Lcobucci\JWT\Builder;
use \Lcobucci\JWT\Signer\Hmac\Sha256;
use yii\db\Exception;

class ProjectController extends BaseController
{

    public function actionCreate()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'name' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'plantime' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'projyear' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'projdesc' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'projorgan' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'projtype' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'projlevel' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'leadorgan' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'leadernum' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'masternum' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'auditornum' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),

        );
        if (!$this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $name = $this->getParam('name', '');
        $plantime = intval($this->getParam('plantime', 0));
        $projyear = intval($this->getParam('projyear', 0));
        $projorgan = intval($this->getParam('projorgan', 0));
        $projdesc = $this->getParam('projdesc', '');
        $projtype = $this->getParam('projtype', '');
        $projlevel = intval($this->getParam('projlevel', 0));
        $leadorganId = intval($this->getParam('leadorgan', 0));
        $leadernum = intval($this->getParam('leadernum', 0));
        $masternum = intval($this->getParam('masternum', 0));
        $auditornum = intval($this->getParam('auditornum', 0));

        //如果单位组织不存在，则返回异常
        $organ = ProjectDao::find()->where(['id' => $projorgan])->one();
        if(!$organ){
            return $this->outputJson(
                '',
                ErrorDict::getError(ErrorDict::G_PARAM, "不存在的项目单位！")
            );
        }
        $projtype = json_decode($projtype, true);
        if(empty($projtype)){
            return $this->outputJson(
                '',
                ErrorDict::getError(ErrorDict::G_PARAM, "项目类型错误!")
            );
        }
        if(!in_array($projlevel, [1, 2, 3, 4])){
            return $this->outputJson(
                '',
                ErrorDict::getError(ErrorDict::G_PARAM, "项目层级输入有误！")
            );
        }
        $leadorgan = ProjectDao::find()->where(['id' => $leadorganId])->one();
        if(!$leadorgan){
            return $this->outputJson(
                '',
                ErrorDict::getError(ErrorDict::G_PARAM, "不存在的牵头业务部门！")
            );
        }
        $service = new ProjectService();
        ;

        $service->createProject(
            1,
            strtotime('now'),
            $name,
            $projyear,
            $plantime,
            $projdesc,
            $projorgan,
            $projtype,
            $projlevel,
            $leadorganId,
            $leadernum,
            $auditornum,
            $masternum
            );

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson('', $error);
        return $ret;
    }

    /**
     * 删除项目接口（批量）
     *
     * @return array
     * @throws Exception
     * @throws \Throwable
     */
    public function actionDelete()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $idArr = json_decode($this->getParam('id', ''), true);
        if (!$idArr) {
            return $this->outputJson('',
                ErrorDict::getError(ErrorDict::G_PARAM, '参数格式不对！')
            );
        }

        $service = new ProjectService();
        if(!$service->deletePro($idArr)){
            return $this->outputJson('',
                ErrorDict::getError(ErrorDict::ERR_INTERNAL)
            );
        }

        return $this->outputJson('',
            ErrorDict::getError(ErrorDict::SUCCESS)
        );
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
                $department = $this->getParam('department', '');
                $nature = intval($this->getParam('nature', 0));
                $techtitle = $this->getParam('techtitle', '');
                $expertise = $this->getParam('expertise', '');
                $train = $this->getParam('train', '');
                $workbegin = $this->getParam('workbegin', '');
                $auditbegin = $this->getParam('auditbegin', '');
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
                    $techtitleDao->addPeopletitle($pid, $tid);
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
            Log::addLogNode('addException', serialize($e->errorInfo));
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson('', $error);
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
        $this->defineMethod = 'GET';
        $this->defineParams = array (
            'projyear' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'projlevel' => array (
                'require' => false,
                'checker' => 'isNumber',
            ),
            'medium' => array (
                'require' => false,
                'checker' => 'isNumber',
            ),
            'internal' => array (
                'require' => false,
                'checker' => 'isNumber',
            ),
            'projstage' => array (
                'require' => false,
                'checker' => 'isNumber',
            ),
            'query' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'length' => array (
                'require' => false,
                'checker' => 'isNumber',
            ),
            'page' => array (
                'require' => false,
                'checker' => 'isNumber',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $projyear = $this->getParam('projyear', '');
        $projlevel = intval($this->getParam('projlevel', 0));
        $medium = intval($this->getParam('medium', 0));
        $internal = intval($this->getParam('internal', 0));
        $projstage = intval($this->getParam('projstage', 0));
        $query = $this->getParam('query', '');
        $length = intval($this->getParam('length', 0));
        $page = intval($this->getParam('page', 0));

        $prj = new ProjectDao();
        $con = $prj::find();

        if ($projyear) {
            if (date('Y', strtotime($projyear)) !== $projyear) {
                return $this->outputJson('',
                    ErrorDict::getError(ErrorDict::G_PARAM, "projyear 输入格式不对！应为年份格式!")
                );
            }
            $con->where(['projyear' => $projyear]);
        }

        if ($projlevel) {
            if (!in_array($projlevel, [1, 2, 3, 4])) {
                return $this->outputJson('',
                    ErrorDict::getError(ErrorDict::G_PARAM, "projlevel 输入格式不对！")
                );
            }
            $con->where(['projlevel' => $projlevel]);
        }

        if ($medium) {
            if (!in_array($medium, [1, 2, 3, 4, 5])) {
                return $this->outputJson('',
                    ErrorDict::getError(ErrorDict::G_PARAM, "medium 输入格式不对！")
                );
            }
            $con->where(['medium' => $medium]);
        }

        /**  注释部分为审核相关内容 */

//        if ($internal) {
//            if (!in_array($internal, [1, 2, 3, 4, 5])) {
//                return $this->outputJson('',
//                    ErrorDict::getError(ErrorDict::G_PARAM, "internal 输入格式不对！")
//                );
//            }
//            $con->where(['medium' => $internal]);
//        }
//
//        if ($projstage) {
//            if (!in_array($projstage, [1, 2, 3, 4, 5])) {
//                return $this->outputJson('',
//                    ErrorDict::getError(ErrorDict::G_PARAM, "projstage 输入格式不对！")
//                );
//            }
//            $con->where(['projstage' => $projstage]);
//        }


        if ($query) {
            $con->where(['or', ['like', 'projectnum', $query], ['like', 'name', $query]]);
        }
        if (!$length) {
            $length = 20;
        }
        if (!$page) {
            $page = 1;
        }
        $countCon = clone $con;
        $list = $con->limit($length)->offset(($page - 1) * $length)->all();
        $total = $countCon->count();

        return $this->outputJson([
            'list' => $list,
            'total' => $total,
        ], ErrorDict::SUCCESS);
    }

    public function actionSelectconfig() {
        $data = [];
        $organizationService = new OrganizationService();
        $organList = $organizationService->getDeparts();
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $data['organlist'] = $organList;
        $data['type'] = Yii::$app->params['project_type'];
        $ret = $this->outputJson($data, $error);
        return $ret;
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

    /**
     * 项目编辑页详情接口
     *
     * @return array
     */
    public function actionEditinfo() {
        $this->defineMethod = 'GET';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $id = intval($this->getParam('id', 0));
        $project = new ProjectDao();
        $data = $project->queryByID($id);
        if(!$data){
            $error = ErrorDict::getError(ErrorDict::G_PARAM);
            $ret = $this->outputJson("", $error);
            return $ret;
        }

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($data, $error);
        return $ret;
    }


    /**
     * 项目详情接口
     *
     * @return array
     */
    public function actionInfo() {
        $this->defineMethod = 'GET';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $id = intval($this->getParam('id', 0));
        $projectDao = new ProjectDao();
        $data = $projectDao->queryByID($id);
        if(!$data){
            $error = ErrorDict::getError(ErrorDict::G_PARAM);
            $ret = $this->outputJson("", $error);
            return $ret;
        }
        $ret['head'] = [
            'projectnum' => $data['projectnum'],
            'projyear' => $data['projyear'],
            'projtype' => $data['projtype'],
            'projlevel' => $projectDao->getProjectLevelMsg($data['projlevel']),
            'leadernum' => $data['leadernum'],
            'auditornum' => $data['auditornum'],
            'masternum' => $data['masternum'],
            'plantime' => $data['plantime'],
        ];
        $orgDao = new OrganizationDao();
        $org = $orgDao::find()
            ->where(['id' => $data['leadorgan']])->one();
        $ret['head']['leadorgan'] = $org['name'] ?? "";


        $org = $orgDao::find()
            ->where(['id' => $data['projorgan']])->one();
        $ret['head']['projorgan'] = $org['name'] ?? "";

        $ret['basic'] = [
            'projdesc' => $data['projdesc'],
            'projstart' => $data['projstart'],
            'projauditcontent' => $data['projauditcontent'],
        ];

        //todo
        // 1、牵扯到状态的
        // 2、审计组



        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($ret, $error);
        return $ret;
    }

    /**
     * 变更审计信息接口
     *
     * @return array
     */
    public function actionAuditinfo() {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'projstart' => array (
                'require' => true,
                'checker' => 'isDateValid',
            ),
            'projauditcontent' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $id = intval($this->getParam('id', 0));
        $projstart = $this->getParam('projstart', '');
        $projauditcontent = $this->getParam('projauditcontent', '');


        $projectDao = new ProjectDao();
        $data = $projectDao->queryByID($id);
        if(!$data){
            $error = ErrorDict::getError(ErrorDict::G_PARAM);
            $ret = $this->outputJson("", $error);
            return $ret;
        }
        $projectDao->updateAuditContent($id, $projstart, $projauditcontent);

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson('', $error);
        return $ret;
    }



    /**
     * 项目列表查询下拉选接口
     *
     * @return array
     */
    public function actionListselect() {
        $data = [
            'projyear' => ["2018","2019","2020"], //项目年度
            'projlevel' => [
                [ '1' => '省厅统一组织'],
                [ '2' => '市州本级'],
                [ '3' => '市州统一组织' ],
                [ '4' => '县级']
            ],
            'medium' => [
                [ '1' => '无需审核'],
                [ '2' => '待提审'],
                [ '3' => '待审核'],
                [ '4' => '审核通过'],
                [ '5' => '审核未通过'],
            ],
            'internal' => [
                [ '1' => "无需审核"],
                [ '2' => '待提审'],
                [ '3' => '审核通过'],
                [ '4' => '审核通过'],
                [ '5' => '审核未通过'],
            ],
            'projstage' => [
                [ '1' => '计划阶段'],
                [ '2' => '实施阶段'],
                [ '3' => '审理阶段'],
                [ '4' => '报告阶段'],
                [ '5' => '项目结束']
            ]
        ];

        return $this->outputJson($data, ErrorDict::getError(ErrorDict::SUCCESS));

    }




}
