<?php

namespace app\modules\api\controllers;

use app\classes\BaseController;
use app\classes\Checker;
use app\classes\ErrorDict;
use app\classes\Log;
use app\classes\Pinyin;
use app\models\AuditGroupDao;
use app\models\ExpertiseDao;
use app\models\OrganizationDao;
use app\models\PeopleProjectDao;
use app\models\PeopleReviewDao;
use app\models\ProjectDao;
use app\models\QualificationDao;
use app\models\ReviewDao;
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
use app\service\ReviewService;

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
        $organ = OrganizationDao::find()->where(['id' => $projorgan])->one();
        if(!$organ){
            return $this->outputJson(
                '',
                ErrorDict::getError(ErrorDict::G_PARAM, "不存在的项目单位！")
            );
        }
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
        $leadorgan = OrganizationDao::find()->where(['id' => $leadorganId])->one();
        if(!$leadorgan){
            return $this->outputJson(
                '',
                ErrorDict::getError(ErrorDict::G_PARAM, "不存在的牵头业务部门！")
            );
        }

        $transaction = ProjectDao::getDb()->beginTransaction();

        try{
            $service = new ProjectService();
            $projectId = $service->createProject(
                1,
                strtotime('now'),
                $name,
                $projyear,
                $plantime,
                $projdesc,
                $projorgan,
                json_encode($projtype, JSON_UNESCAPED_UNICODE),
                $projlevel,
                $leadorganId,
                $leadernum,
                $auditornum,
                $masternum
            );

            //预分配人员

            $orgIds = (new OrganizationService)->getSubordinateIds($projorgan);

            $allMatchPeoples = UserDao::find()
                ->where(['isjob' => 2])
                ->andWhere(['in', 'organid', $orgIds])
                ->limit($leadernum + $auditornum + $masternum)
                ->all();
            if (count($allMatchPeoples) == 0) {
                $group = new AuditGroupDao();
                $group->addAuditGroup($projectId);
                $transaction->commit();
                return $this->outputJson('', ErrorDict::getError(ErrorDict::SUCCESS));
            }

            $groups = [];
            if (count($allMatchPeoples) < $leadernum) {
                $leadernum = count($allMatchPeoples);
            }
            for($i = 0; $i < $leadernum; $i++) {
                $group = new AuditGroupDao();
                $groups[] = [
                    'id' => $group->addAuditGroup($projectId),
                    'leader' => $allMatchPeoples[$i]->id
                ];
                unset($allMatchPeoples[$i]);
            }

            $allMatchPeoples = array_values($allMatchPeoples);
            if ($masternum > count($allMatchPeoples)){
                $masternum = count($allMatchPeoples);
            }
            $preMasterNum = intval($masternum/$leadernum);
            for($i = $leadernum - 1; $i >= 0; $i--) {
                for($j = 0; $j < $preMasterNum; $j++){
                    $groups[$i]['master_ids'][] = $allMatchPeoples[$i]->id;
                    unset($allMatchPeoples[$i]);
                }
            }
            $allMatchPeoples = array_values($allMatchPeoples);
            $surplusMasterNum = $masternum - $preMasterNum * $leadernum;
            for($i = 0; $i < $surplusMasterNum; $i++) {
                $groups[$i]['master_ids'][] = $allMatchPeoples[$i]->id;
                unset($allMatchPeoples[$i]);
            }

            $allMatchPeoples = array_values($allMatchPeoples);
            if ($auditornum > count($allMatchPeoples)){
                $auditornum = count($allMatchPeoples);
            }
            $preAuditorNum = intval($auditornum/$leadernum);
            for($i = 0; $i < $leadernum; $i++) {
                for($j = 0; $j < $preAuditorNum; $j++){
                    $groups[$i]['auditor_ids'][] = $allMatchPeoples[$i]->id;
                    unset($allMatchPeoples[$i]);
                }
            }
            $allMatchPeoples = array_values($allMatchPeoples);
            $surplusAuditorNum = $auditornum - $preAuditorNum * $leadernum;
            for($i = 0; $i < $surplusAuditorNum; $i++) {
                $groups[$i]['auditor_ids'][] = $allMatchPeoples[$i]->id;
                unset($allMatchPeoples[$i]);
            }

            foreach ($groups as $e ){
                //leader
                $pepProject = new PeopleProjectDao();
                $pepProject->pid = $e['leader'];
                $pepProject->groupid = $e['id'];
                $pepProject->roletype = $pepProject::ROLE_TYPE_GROUP_LEADER;
                $pepProject->islock = $pepProject::NOT_LOCK;
                $pepProject->projid = $projectId;
                $pepProject->save();
                $person = UserDao::findOne($e['leader']);
                $person->isaudit = UserDao::IS_AUDIT;
                $person->isjob = UserDao::IS_JOB;
                $person->save();

                //master
                if(isset($e['master_ids']) && !empty($e['master_ids'])){
                    foreach ($e['master_ids'] as $m){
                        $pepProject = new PeopleProjectDao();
                        $pepProject->pid = $m;
                        $pepProject->groupid = $e['id'];
                        $pepProject->roletype = $pepProject::ROLE_TYPE_MASTER;
                        $pepProject->islock = $pepProject::NOT_LOCK;
                        $pepProject->projid = $projectId;
                        $pepProject->save();

                        $person = UserDao::findOne($e['leader']);
                        $person->isaudit = UserDao::IS_AUDIT;
                        $person->isjob = UserDao::IS_JOB;
                        $person->save();
                    }
                }

                //group
                if(isset($e['auditor_ids']) && !empty($e['auditor_ids'])){
                    foreach ($e['auditor_ids'] as $a){
                        $pepProject = new PeopleProjectDao();
                        $pepProject->pid = $a;
                        $pepProject->groupid = $e['id'];
                        $pepProject->roletype = $pepProject::ROLE_TYPE_GROUPER;
                        $pepProject->islock = $pepProject::NOT_LOCK;
                        $pepProject->projid = $projectId;
                        $pepProject->save();

                        $person = UserDao::findOne($e['leader']);
                        $person->isaudit = UserDao::IS_AUDIT;
                        $person->isjob = UserDao::IS_JOB;
                        $person->save();
                    }
                }

            }

            $transaction->commit();
        } catch(\Exception $e){
            Log::fatal(printf("create project error %s, %s", $e->getMessage(), $e->getTraceAsString()));
            $transaction->rollBack();
            return $this->outputJson('',
                ErrorDict::getError(ErrorDict::ERR_INTERNAL, "创建项目内部错误！")
            );
        }


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
        $idArr = $this->getParam('id', '');
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

    /**
     * 编辑项目接口
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    public function actionUpdate()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
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
            )

        );
        if (!$this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $id = intval($this->getParam('id', 0));
        $name = $this->getParam('name', '');
        $plantime = intval($this->getParam('plantime', 0));
        $projyear = intval($this->getParam('projyear', 0));
        $projorgan = intval($this->getParam('projorgan', 0));
        $projdesc = $this->getParam('projdesc', '');
        $projtype = $this->getParam('projtype', '');
        $projlevel = intval($this->getParam('projlevel', 0));
        $leadorganId = intval($this->getParam('leadorgan', 0));

        $pro = new ProjectDao();
        $instance = $pro::findOne($id);
        if(!$instance) {
            return $this->outputJson(
                '',
                ErrorDict::getError(ErrorDict::G_PARAM)
            );
        }
        $instance->name = $name;
        $instance->plantime = $plantime;
        $instance->projyear = $projyear;
        $instance->projorgan = $projorgan;
        $instance->projdesc = $projdesc;
        $instance->projtype = json_encode($projtype, JSON_UNESCAPED_UNICODE);
        $instance->projlevel = $projlevel;
        $instance->leadorgan = $leadorganId;
        $instance->save();


        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson('', $error);
        return $ret;
    }

    /**
     * 项目列表接口
     *
     * @return array
     */
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
            //if (date('Y', strtotime($projyear)) !== $projyear) {
            if ( !is_numeric( $projyear) ) {
                return $this->outputJson('',
                    ErrorDict::getError(ErrorDict::G_PARAM, "projyear 输入格式不对！应为年份格式!")
                );
            }
            $con = $con->andwhere(['projyear' => $projyear]);
        }

        if ($projlevel) {
            if (!in_array($projlevel, [1, 2, 3, 4])) {
                return $this->outputJson('',
                    ErrorDict::getError(ErrorDict::G_PARAM, "projlevel 输入格式不对！")
                );
            }
            $con = $con->andwhere(['projlevel' => $projlevel]);
        }

        if ($medium) {
            if (!in_array($medium, [1, 2, 3, 4, 5])) {
                return $this->outputJson('',
                    ErrorDict::getError(ErrorDict::G_PARAM, "medium 输入格式不对！")
                );
            }
            if($medium == 1){
                $projs = (new \yii\db\Query())
                    ->from('peopleproject')
                    ->innerJoin('people', 'peopleproject.pid = people.id')
                    ->andWhere(['people.type' => UserDao::$typeToName['中介机构']])
                    ->select('peopleproject.projid')
                    ->groupBy('peopleproject.projid')
                    ->all();

                $projs = array_map(function($e){
                    return $e['projid'];
                }, $projs);

                if(count($projs) !== 0){
                    $con = $con->andWhere(['not in', 'id', $projs]);
                }
            }else if($medium == 2){
                $projs = (new \yii\db\Query())
                    ->from('peopleproject')
                    ->innerJoin('people', 'peopleproject.pid = people.id')
                    ->andWhere(['people.type' => UserDao::$typeToName['中介机构']])
                    ->select('peopleproject.projid')
                    ->groupBy('peopleproject.projid')
                    ->all();
                $projs = array_map(function($e){
                    return $e['projid'];
                }, $projs);

                $rews = ReviewDao::find()
                    ->andWhere(['ptype' => 1])
                    ->groupBy('projid')
                    ->select('projid')
                    ->all();
                $rews = array_map(function($e){
                    return $e['projid'];
                }, $rews);

                $projs = array_diff($projs, $rews);

                $con = $con->andWhere(['in', 'id', $projs]);

            }else if($medium == 3){
                $projs = (new \yii\db\Query())
                    ->from('peopleproject')
                    ->innerJoin('people', 'peopleproject.pid = people.id')
                    ->andWhere(['people.type' => UserDao::$typeToName['中介机构']])
                    ->select('peopleproject.projid')
                    ->groupBy('peopleproject.projid')
                    ->all();
                $projs = array_map(function($e){
                    return $e['projid'];
                }, $projs);

                $rews = ReviewDao::find()
                    ->andWhere(['ptype' => 1])
                    ->andWhere(['in', 'projid', $projs])
                    ->andWhere(['status' => 0])
                    ->groupBy('projid')
                    ->select('projid')
                    ->all();
                $rews = array_map(function($e){
                    return $e['projid'];
                }, $rews);

                $con = $con->andWhere(['in', 'id', $rews]);


            }else if($medium == 4){
                $projs = (new \yii\db\Query())
                    ->from('peopleproject')
                    ->innerJoin('people', 'peopleproject.pid = people.id')
                    ->andWhere(['people.type' => UserDao::$typeToName['中介机构']])
                    ->select('peopleproject.projid')
                    ->groupBy('peopleproject.projid')
                    ->all();
                $projs = array_map(function($e){
                    return $e['projid'];
                }, $projs);
                $rews = ReviewDao::find()
                    ->andWhere(['ptype' => 1])
                    ->andWhere(['in', 'projid', $projs])
                    ->andWhere(['status' => 1])
                    ->groupBy('projid')
                    ->select('projid')
                    ->all();
                $rews = array_map(function($e){
                    return $e['projid'];
                }, $rews);

                $con = $con->andWhere(['in', 'id', $rews]);

            }else if($medium == 5){
                $projs = (new \yii\db\Query())
                    ->from('peopleproject')
                    ->innerJoin('people', 'peopleproject.pid = people.id')
                    ->andWhere(['people.type' => UserDao::$typeToName['中介机构']])
                    ->select('peopleproject.projid')
                    ->groupBy('peopleproject.projid')
                    ->all();
                $projs = array_map(function($e){
                    return $e['projid'];
                }, $projs);
                $rews = ReviewDao::find()
                    ->andWhere(['ptype' => 1])
                    ->andWhere(['in', 'projid', $projs])
                    ->andWhere(['status' => 2])
                    ->groupBy('projid')
                    ->select('projid')
                    ->all();
                $rews = array_map(function($e){
                    return $e['projid'];
                }, $rews);

                $con = $con->andWhere(['in', 'id', $rews]);
            }


        }

        if ($internal) {
            if (!in_array($internal, [1, 2, 3, 4, 5])) {
                return $this->outputJson('',
                    ErrorDict::getError(ErrorDict::G_PARAM, "medium 输入格式不对！")
                );
            }
            if($internal == 1){
                $projs = (new \yii\db\Query())
                    ->from('peopleproject')
                    ->innerJoin('people', 'peopleproject.pid = people.id')
                    ->andWhere(['people.type' => UserDao::$typeToName['内审机构']])
                    ->select('peopleproject.projid')
                    ->groupBy('peopleproject.projid')
                    ->all();
                $projs = array_map(function($e){
                    return $e['projid'];
                }, $projs);

                if(count($projs) !== 0){
                    $con = $con->andWhere(['not in', 'id', $projs]);
                }
            }else if($internal == 2){
                $projs = (new \yii\db\Query())
                    ->from('peopleproject')
                    ->innerJoin('people', 'peopleproject.pid = people.id')
                    ->andWhere(['people.type' => UserDao::$typeToName['内审机构']])
                    ->select('peopleproject.projid')
                    ->groupBy('peopleproject.projid')
                    ->all();
                $projs = array_map(function($e){
                    return $e['projid'];
                }, $projs);
                $rews = ReviewDao::find()
                    ->andWhere(['ptype' => 1])
                    ->groupBy('projid')
                    ->select('projid')
                    ->all();
                $rews = array_map(function($e){
                    return $e['projid'];
                }, $rews);

                $projs = array_diff($projs, $rews);

                $con = $con->andWhere(['in', 'id', $projs]);
            }else if($internal == 3){
                $projs = (new \yii\db\Query())
                    ->from('peopleproject')
                    ->innerJoin('people', 'peopleproject.pid = people.id')
                    ->andWhere(['people.type' => UserDao::$typeToName['内审机构']])
                    ->select('peopleproject.projid')
                    ->groupBy('peopleproject.projid')
                    ->all();
                $projs = array_map(function($e){
                    return $e['projid'];
                }, $projs);
                $rews = ReviewDao::find()
                    ->andWhere(['ptype' => 1])
                    ->andWhere(['in', 'projid', $projs])
                    ->andWhere(['status' => 0])
                    ->groupBy('projid')
                    ->select('projid')
                    ->all();
                $rews = array_map(function($e){
                    return $e['projid'];
                }, $rews);

                $con = $con->andWhere(['in', 'id', $rews]);
            }else if($internal == 4){
                $projs = (new \yii\db\Query())
                    ->from('peopleproject')
                    ->innerJoin('people', 'peopleproject.pid = people.id')
                    ->andWhere(['people.type' => UserDao::$typeToName['内审机构']])
                    ->select('peopleproject.projid')
                    ->groupBy('peopleproject.projid')
                    ->all();
                $projs = array_map(function($e){
                    return $e['projid'];
                }, $projs);
                $rews = ReviewDao::find()
                    ->andWhere(['ptype' => 1])
                    ->andWhere(['in', 'projid', $projs])
                    ->andWhere(['status' => 1])
                    ->groupBy('projid')
                    ->select('projid')
                    ->all();
                $rews = array_map(function($e){
                    return $e['projid'];
                }, $rews);

                $con = $con->andWhere(['in', 'id', $rews]);
            }else if($internal == 5){
                $projs = (new \yii\db\Query())
                    ->from('peopleproject')
                    ->innerJoin('people', 'peopleproject.pid = people.id')
                    ->andWhere(['people.type' => UserDao::$typeToName['内审机构']])
                    ->select('peopleproject.projid')
                    ->groupBy('peopleproject.projid')
                    ->all();
                $projs = array_map(function($e){
                    return $e['projid'];
                }, $projs);

                $rews = ReviewDao::find()
                    ->andWhere(['ptype' => 1])
                    ->andWhere(['in', 'projid', $projs])
                    ->andWhere(['status' => 2])
                    ->groupBy('projid')
                    ->select('projid')
                    ->all();
                $rews = array_map(function($e){
                    return $e['projid'];
                }, $rews);
                $con = $con->andWhere(['in', 'id', $rews]);
            }

        }

        if ($projstage) {
            if (!in_array($projstage, [1, 2, 3, 4, 5])) {
                return $this->outputJson('',
                    ErrorDict::getError(ErrorDict::G_PARAM, "projstage 输入格式不对！")
                );
            }
            $con->andwhere(['projstage' => $projstage]);
        }


        if ($query) {
            $con = $con->andwhere(['or', ['like', 'projectnum', $query], ['like', 'name', $query]]);
        }
        if (!$length) {
            $length = 20;
        }
        if (!$page) {
            $page = 1;
        }
        $countCon = clone $con;
        $list = $con->limit($length)->offset(($page - 1) * $length)->asArray()->all();
        $rewService = new ReviewService();
        $list = array_map(function($e )use ($rewService){
            $e['medium'] = $rewService->getMediumStatus($e['id']);
            $e['internal'] = $rewService->getInternalStatus($e['id']);
            return $e;
        }, $list);
        $total = $countCon->count();

        return $this->outputJson([
            'list' => $list,
            'total' => $total,
        ], ErrorDict::SUCCESS);
    }

    /**
     * 新建页&编辑页配置接口
     *
     * @return array
     */
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
     * 项目详情接口一列表部分
     *
     * @return array
     */
    public function actionInfolist() {
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

        $rew = new ReviewService();

        $ret['auditgroup'] = [
            'medium' => $rew->getMediumStatus($id),
            'internal' => $rew->getInternalStatus($id), //需要审核模块
        ];

        $auditGroups = AuditGroupDao::find()
            ->where(['pid' => $id])
            ->asArray()
            ->all();
        foreach ($auditGroups as $e){
            $tmp = [
                'id' => $e['id'],
                'status' => $e['status'],
            ];

            if(in_array($e['status'], [AuditGroupDao::$statusToName['无'], AuditGroupDao::$statusToName['应进点'], AuditGroupDao::$statusToName['该进点而未进点']])){
                $tmp['operate'] = 1;
            }else if(in_array($e['status'], [AuditGroupDao::$statusToName['已进点'], AuditGroupDao::$statusToName['该结束未结束']])){
                $tmp['operate'] = 2;
            }else if(in_array($e['status'], [AuditGroupDao::$statusToName['审理中'], AuditGroupDao::$statusToName['审理结束']])){
                $tmp['operate'] = 3;
            }else if(in_array($e['status'], [AuditGroupDao::$statusToName['待报告'], AuditGroupDao::$statusToName['报告中']])){
                $tmp['operate'] = 4;
            }


            $peoples = (new \yii\db\Query())
                ->from('peopleproject')
                ->innerJoin('people', 'peopleproject.pid = people.id')
                ->select('people.id, people.pid, people.name, people.sex, peopleproject.roletype, people.address as location, peopleproject.roletype as role, people.level, peopleproject.islock')
                ->where(['peopleproject.groupid' => $e['id']])
                ->andWhere(['peopleproject.projid' => $id])
                ->all();

            foreach ($peoples as $p){
                $tmp['group'][] = $p;
            }
            $ret['auditgroup']['list'][] = $tmp;
        }

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($ret, $error);
        return $ret;
    }

    /**
     * 项目详情接口一无列表部分
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
            'projectname' => $data['name'],
            'projectstatus' => $data['status'],
        ];

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
                'checker' => 'isNumber',
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
        $projstart = intval($this->getParam('projstart', ''));
        $projstart = date('Y-m-d H:i:s', $projstart);
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


    /**
     * 项目状态变更接口
     *
     */
    public function actionUpdatestatus() {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'operate' => array (
                'require' => true,
                'checker' => 'noCheck',
            )
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $id = intval($this->getParam('id', 0));
        $operate = intval($this->getParam('operate', 0));
        if(!in_array($operate, array_keys(ProjectDao::$operatorStatus))) {
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '状态不正确！'));
        }

        //todo 注意！可能后续会加上权限相关的东西

        $transaction = ProjectDao::getDb()->beginTransaction();

        try{
            $pro = ProjectDao::findOne($id);
            switch ($operate) {
                case ProjectDao::OPERATOR_STATUS_SURE:

                    $pepProGroups = PeopleProjectDao::find()
                        ->where(["projid" => $id])
                        ->groupBy('groupid')
                        ->all();
                    $groupIds = array_map(
                        function($e)
                        {
                            return $e->groupid;
                        },
                        $pepProGroups
                    );

                    $groups = AuditGroupDao::findAll($groupIds);
                    foreach ($groups as $e){
                        $e->status = AuditGroupDao::$statusToName['应进点'];
                        $e->save();
                    }
                    $transaction->commit();
                    break;
                case ProjectDao::OPERATOR_STATUS_START:
                    $pro->status = ProjectDao::$statusToName['实施阶段'];
                    $pro->save();

//                    $pepProGroups = PeopleProjectDao::find()
//                        ->where(["projid" => $id])
//                        ->groupBy('groupid')
//                        ->all();
//                    $groupIds = array_map(
//                        function($e)
//                        {
//                            return $e->groupid;
//                        },
//                        $pepProGroups
//                    );
//
//                    $groups = AuditGroupDao::findAll($groupIds);
//                    foreach ($groups as $e){
//                        $e->status = AuditGroupDao::$statusToName['应进点'];
//                        $e->save();
//                    }
                    $transaction->commit();
                    break;
                case ProjectDao::OPERATOR_STATUS_AUDIT:
                    $pro->status = ProjectDao::$statusToName['审理阶段'];
                    $pro->save();

                    $pepProGroups = PeopleProjectDao::find()
                        ->where(["projid" => $id])
                        ->groupBy('groupid')
                        ->all();
                    $groupIds = array_map(
                        function($e)
                        {
                            return $e->groupid;
                        },
                        $pepProGroups
                    );

                    $groups = AuditGroupDao::findAll($groupIds);
                    foreach ($groups as $e){
                        $e->status = AuditGroupDao::$statusToName['审理中'];
                        $e->save();
                    }
                    $transaction->commit();
                    break;
                case ProjectDao::OPERATOR_STATUS_END:
                    $pro->status = ProjectDao::$statusToName['项目结束'];
                    $pro->save();

                    $pepProGroups = PeopleProjectDao::find()
                        ->where(["projid" => $id])
                        ->groupBy('groupid')
                        ->all();
                    $groupIds = array_map(
                        function($e)
                        {
                            return $e->groupid;
                        },
                        $pepProGroups
                    );

                    $groups = AuditGroupDao::findAll($groupIds);
                    foreach ($groups as $e){
                        $e->status = AuditGroupDao::$statusToName['报告中'];
                        $e->save();
                    }
                    $transaction->commit();
                    break;
            }
        }catch(\Exception $e){
            $transaction->rollBack();
            Log::addLogNode('状态变更错误！', serialize($e->errorInfo));
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson('', $error);
            return $ret;
        }


        return $this->outputJson('', ErrorDict::getError(ErrorDict::SUCCESS));

    }


}
