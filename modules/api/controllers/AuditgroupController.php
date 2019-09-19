<?php
/**
 * Created by PhpStorm.
 * User: ryugou
 * Date: 2019/7/27
 * Time: 11:42 PM
 */
namespace app\modules\api\controllers;

use app\classes\BaseController;
use app\classes\ErrorDict;
use app\classes\Log;
use app\models\AuditGroupDao;
use app\models\JugeProjectDao;
use app\models\OrganizationDao;
use app\models\PeopleProjectDao;
use app\models\PeopleReviewDao;
use app\models\ProjectDao;
use app\models\ReviewDao;
use app\models\UserDao;


class AuditgroupController extends BaseController {

    /**
     * 审计组操作：增加人员
     *
     */
    public function actionAdd(){
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'pid' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }

        $id = intval($this->getParam('id', 0));
        $pid = intval($this->getParam('pid', 0));


        $group = AuditGroupDao::findOne($id);

        $transaction = AuditGroupDao::getDb()->beginTransaction();
        try{
            $peopleProjCount = PeopleProjectDao::find()
                ->where(["groupid" => $id])
                ->andWhere(["pid" => $pid])
                ->count();
            if($peopleProjCount){
                $transaction->rollBack();
                return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '此人员已经加入工作组！'));
            }

            $newPeop = new PeopleProjectDao();
            $newPeop->pid = $pid;
            $newPeop->projid = $group['pid'];
            $newPeop->groupid = $id;
            $newPeop->roletype = PeopleProjectDao::ROLE_TYPE_GROUPER;
            $newPeop->islock = 1;
            $newPeop->save();

            $user = UserDao::findOne($pid);
            if(!$user){
                $transaction->rollBack();
                return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '未找到此人员！'));
            }

            $user->isjob = UserDao::$isJobToName['在点'];
            $user->save();

            $transaction->commit();

        }catch (\Exception $e){
            $transaction->rollBack();
            Log::fatal($e->getMessage());
            return $this->outputJson('', ErrorDict::getError(ErrorDict::ERR_INTERNAL));
        }

        return $this->outputJson('',
            ErrorDict::getError(ErrorDict::SUCCESS)
        );
    }

    /**
     * 审计组操作：更改角色
     *
     */
    public function actionUpdaterole(){
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'pid' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'role' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $id = intval($this->getParam('id', 0));
        $pid = intval($this->getParam('pid', 0));
        $role = intval($this->getParam('role', 0));

        if(!in_array($role, [PeopleProjectDao::ROLE_TYPE_GROUPER,
            PeopleProjectDao::ROLE_TYPE_GROUP_LEADER,
            PeopleProjectDao::ROLE_TYPE_MASTER])) {
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM));
        }


        $peoPro = PeopleProjectDao::find()
            ->where(['groupid' => $id])
            ->andwhere(['pid' => $pid])
            ->one();
        if(!$peoPro){
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '未找不到合适的人员!'));
        }
        $transaction = PeopleProjectDao::getDb()->beginTransaction();

        try {

            if($role == PeopleProjectDao::ROLE_TYPE_GROUP_LEADER){
                $oldLeader = PeopleProjectDao::find()
                    ->where(['groupid' => $id])
                    ->andWhere(['roletype' => PeopleProjectDao::ROLE_TYPE_GROUP_LEADER])
                    ->one();
                if($oldLeader){
                    $oldLeader->roletype = PeopleProjectDao::ROLE_TYPE_GROUPER;
                    $oldLeader->save();
                }

            }


            $peoPro->roletype = $role;
            $peoPro->save();

            $transaction->commit();
        } catch (\Exception $e){
            Log::fatal(printf("更改员工角色错误!%s", $e->getMessage()));
            $transaction->rollBack();
            return $this->outputJson('',
                ErrorDict::getError(ErrorDict::ERR_INTERNAL, "内部错误！")
            );
        }

        return $this->outputJson('',
            ErrorDict::getError(ErrorDict::SUCCESS)
        );
    }


    /**
     * 审计组操作：删除人员
     *
     */
    public function actionDelete(){
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'pid' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $id = intval($this->getParam('id', 0));
        $pid = intval($this->getParam('pid', 0));

        $proDao = new PeopleProjectDao();
        $trans = $proDao::getDb()->beginTransaction();
        $peoPro = $proDao::find()
            ->where(['groupid' => $id])
            ->andwhere(['pid' => $pid])
            ->one();
        if(!$peoPro){
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '未找不到合适的人员!'));
        }
        try{
            $peoPro->delete();

            $use = UserDao::findOne($pid);
            $use->isjob = UserDao::$isJobToName['不在点'];
            $use->save();

            $trans->commit();
        }catch (\Exception $e){
            $trans->rollBack();
            Log::fatal(printf("delete tables error! %s", $e->getMessage()));
            return $this->outputJson('', ErrorDict::getError(ErrorDict::ERR_INTERNAL));
        }

        return $this->outputJson('',
            ErrorDict::getError(ErrorDict::SUCCESS)
        );
    }


    /**
     * 审计组操作：解锁人员
     *
     */
    public function actionUnlock(){
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'pid' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $id = intval($this->getParam('id', 0));
        $pid = intval($this->getParam('pid', 0));

        $proDao = new PeopleProjectDao();
        $peoPro = $proDao::find()
            ->where(['groupid' => $id])
            ->andwhere(['pid' => $pid])
            ->one();
        if(!$peoPro){
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '未找不到合适的人员!'));
        }

        $peoPro->islock = 2;
        $peoPro->save();


        return $this->outputJson('',
            ErrorDict::getError(ErrorDict::SUCCESS)
        );
    }


    /**
     * 审计组操作：审计组长变更状态
     *
     */
    public function actionUpdatestatus(){
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'operate' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'num' => array (
                'require' => false,
                'checker' => 'isNumber',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $id = intval($this->getParam('id', 0));
        $operate = intval($this->getParam('operate', 0));
        $num = intval($this->getParam('num', 0));

        if(!in_array($operate, [1, 2, 3, 4, 5, 6])) {
            return $this->outputJson('',
                ErrorDict::getError(ErrorDict::G_PARAM,
                    '点击操作格式不合法!'
                )
            );
        }
        if($operate == 3 && $num == 0){
            return $this->outputJson('',
                ErrorDict::getError(ErrorDict::G_PARAM, '预审理人数不对！')
            );
        }

        $transaction = AuditGroupDao::getDb()->beginTransaction();

        try {
            switch ($operate) {
                //进点
                case 1:
                    $audit = AuditGroupDao::findOne($id);
                    if (!in_array($audit->status, [AuditGroupDao::$statusToName['应进点'], AuditGroupDao::$statusToName['无']])){
                        return $this->outputJson('',
                            ErrorDict::getError(ErrorDict::G_PARAM, '审计组状态不为应进点!')
                        );
                    }
                    $audit->status = AuditGroupDao::$statusToName['已进点'];
                    $audit->save();

                    $pro = ProjectDao::findOne($audit->pid);
                    $pro->status = ProjectDao::$statusToName['实施阶段'];
                    $pro->save();

                    $transaction->commit();

                    return $this->outputJson('', ErrorDict::getError(ErrorDict::SUCCESS));
                //实施结束
                case 2:
                    $audit = AuditGroupDao::findOne($id);
                    $audit->status = AuditGroupDao::$statusToName['实施结束'];
                    $audit->save();

//                    $pro = ProjectDao::findOne($audit->pid);
//                    $pro->status = ProjectDao::$statusToName['审理阶段'];
//                    $pro->save();
                    $transaction->commit();

                    return $this->outputJson('', ErrorDict::getError(ErrorDict::SUCCESS));
                //开始审理
                case 3:
                    $audit = AuditGroupDao::findOne($id);
//                    if ($audit->status = AuditGroupDao::$statusToName['审理中']){
//                        return $this->outputJson('',
//                            ErrorDict::getError(ErrorDict::G_PARAM, '审计组状态不正确！')
//                        );
//                    }
                    $audit->status = AuditGroupDao::$statusToName['审理中'];
                    $audit->save();

                    $pro = ProjectDao::findOne($audit['pid']);
                    $pro->jugenum = $num;
                    $pro->status = ProjectDao::$statusToName['审理阶段'];
                    $pro->save();

                    $transaction->commit();

                    return $this->outputJson('', ErrorDict::getError(ErrorDict::SUCCESS));
                //审理结束
                case 4:
                    $audit = AuditGroupDao::findOne($id);
//                    if ($audit->status = AuditGroupDao::$statusToName['审理中']){
//                        return $this->outputJson('',
//                            ErrorDict::getError(ErrorDict::G_PARAM, '审计组状态不正确！')
//                        );
//                    }
                    $audit->status = AuditGroupDao::$statusToName['审理结束'];
                    $audit->save();

                    $pro = ProjectDao::findOne($audit['pid']);
                    $pro->status = ProjectDao::$statusToName['报告阶段'];
                    $pro->save();


                    $transaction->commit();

                    return $this->outputJson('', ErrorDict::getError(ErrorDict::SUCCESS));
                //开始报告
                case 5:
                    $audit = AuditGroupDao::findOne($id);
//                    if ($audit->status = AuditGroupDao::$statusToName['审理结束']){
//                        return $this->outputJson('',
//                            ErrorDict::getError(ErrorDict::G_PARAM, '审计组状态不正确！')
//                        );
//                    }
                    $audit->status = AuditGroupDao::$statusToName['报告中'];
                    $audit->save();

                    $pro = ProjectDao::findOne($audit['pid']);
                    $pro->status = ProjectDao::$statusToName['报告阶段'];
                    $pro->save();

                    $transaction->commit();

                    return $this->outputJson('', ErrorDict::getError(ErrorDict::SUCCESS));
                //报告结束
                case 6:
                    $audit = AuditGroupDao::findOne($id);
//                    if ($audit->status = AuditGroupDao::$statusToName['报告中']){
//                        return $this->outputJson('',
//                            ErrorDict::getError(ErrorDict::G_PARAM, '审计组状态不正确！')
//                        );
//                    }
                    $audit->status = AuditGroupDao::$statusToName['报告结束'];
                    $audit->save();

                    $pro = ProjectDao::findOne($audit['pid']);
                    $pro->status = ProjectDao::$statusToName['报告阶段'];
                    $pro->save();

                    $peoplePros = PeopleProjectDao::find()
                        ->where(['projid' => $audit['pid']])
                        ->andWhere(['groupid' => $id])
                        ->groupBy(['pid'])
                        ->all();
                    $pids = array_map(function($e){
                        return $e['pid'];
                    }, $peoplePros);

                    $uses = UserDao::find()
                        ->where(['in', 'id', $pids])
                        ->andWhere(['isaudit' => UserDao::$isAuditToName['是']])
                        ->all();
                    foreach ($uses as $e){
                        $e->isjob = UserDao::$isJobToName['不在点'];
                        $e->save();
                    }


                    $transaction->commit();

                    return $this->outputJson('', ErrorDict::getError(ErrorDict::SUCCESS));
            }
        }catch (\Exception $e){
            $transaction->rollBack();
            Log::fatal("审计组长变更状态出现错误！{$e->getTraceAsString()}");
            return $this->outputJson('', ErrorDict::getError(ErrorDict::ERR_INTERNAL));
        }


        return $this->outputJson('',
            ErrorDict::getError(ErrorDict::SUCCESS)
        );
    }

    /**
     * 增加人员列表接口
     *
     * @return array
     */
    public function actionUserlist() {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'ismedium' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'isinternal' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'jobstatus' => array (
                'require' => false,
                'checker' => 'isNumber',
            ),
            'length'  => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'page' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $ismedium = intval($this->getParam('ismedium', 0));
        $isinternal = intval($this->getParam('isinternal', 0));
        $jobstatus = intval($this->getParam('jobstatus', 0));
        $length = intval($this->getParam('length', 0));
        $page = intval($this->getParam('page', 0));
        $query = $this->getParam('query', '');

        if(!in_array($ismedium, [1, 2])){
            return $this->outputJson('',
                ErrorDict::getError(ErrorDict::G_PARAM, 'ismedium输入不合法！')
            );
        }
        if(!in_array($isinternal, [1, 2])){
            return $this->outputJson('',
                ErrorDict::getError(ErrorDict::G_PARAM, 'isinternal输入不合法！')
            );
        }


        if($jobstatus != 0 && !in_array($jobstatus, [UserDao::IS_JOB, UserDao::IS_NOT_JOB])){
            return $this->outputJson('',
                ErrorDict::getError(ErrorDict::G_PARAM, 'jobstatus输入不合法！')
            );
        }

        $con = (new \yii\db\Query())
            ->from('people')
            ->join('INNER JOIN', 'organization', 'organization.id = people.organid')
            ->select('people.id,people.pid, people.name, people.sex, people.isjob, people.type, organization.name AS oname, people.location');

        $types = [
            UserDao::$typeToName['审计机关']
        ];
        if($ismedium == 1){
            $types[] = UserDao::$typeToName['中介机构'];
        }

        if($isinternal == 1){
            $types[] = UserDao::$typeToName['内审机构'];
        }


        if($jobstatus){
            $con = $con->Where(['people.isjob' => $jobstatus]);
        }

        if($query){
            $con = $con->orWhere(['like', 'people.pid', $query])
                ->orWhere(['like', 'people.name', $query])
                ->orWhere(['like', 'organization.name', $query]);
        }

        $countCon = clone $con;

        $peoples = $con->andWhere(['in', 'people.type', $types])->limit($length)->offset(($page - 1) * $length)->all();

        $ret = array_map(function($e){
            $lockNum = PeopleProjectDao::find()
                ->where(['pid' => $e['pid']])
                ->andWhere(['islock' => PeopleProjectDao::IS_LOCK])
                ->count();
            $tmp = [
                'id' => $e['id'],
                'pid' => $e['pid'],
                'name' => $e['name'],
                'sex' => $e['sex'] == 1 ? "男" : "女",
                'isjob' => $e['isjob'] == 1 ? "在点" : "不在点",
                'type' => OrganizationDao::getOTypeMsg($e['type']),
                'location' => $e['location'],
                'oname' => $e['oname']
            ];
            if($lockNum > 0){
                $tmp['islock'] = "锁定";
            }else{
                $tmp['islock'] = "未锁定";
            }

            $tmp['projectnum'] = PeopleProjectDao::find()
                ->where(['pid' => $e['pid']])
                ->groupBy('pid')
                ->count();
            return $tmp;
        }, $peoples);


        return $this->outputJson([
            'list' => $ret,
            'total' => $countCon->count()
        ],
            ErrorDict::getError(ErrorDict::SUCCESS)
        );
    }

    /**
     * 提交中介/内审人员至人员分配审核
     *
     */
    public function actionReviewadd() {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'pids' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'type' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $id = intval($this->getParam('id', 0));
        $pids = $this->getParam('pids', []);
        $type = $this->getParam('type', 0);

        if(count($pids) == 0) {
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '用户id组错误!'));
        }
        if(!in_array($type,[ReviewDao::ZHONGJIE_TYPE, ReviewDao::NEISHEN_TYPE])){
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, 'type类型不对！'));
        }

        $group = AuditGroupDao::findOne($id);
        if(!$group){
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '项目id不存在！'));
        }

        $transaction = ReviewDao::getDb()->beginTransaction();
        try {
            $rew = new ReviewDao();
            $rew->type = ReviewDao::PEOPLE_TYPE;
            $rew->projid = $group->pid;
            $rew->ptype = $type;
            $rew->save();

            foreach ($pids as $e){
                $prew = new PeopleReviewDao();
                $peoplePro = new PeopleProjectDao();
                $isExsit = $prew::find()->where(['pid' => $e])->andWhere(['rid' => $rew->id])->count();
                if($isExsit){
                    $transaction->rollBack();
                    return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '人员已经存在！'));
                }
                $isPeoRrojExsit = PeopleProjectDao::find()
                    ->where(["pid" => $e])
                    ->andWhere(["groupid" => $id])
                    ->count();
                if($isPeoRrojExsit){
                    $transaction->rollBack();
                    return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '人员已经存在！'));
                }

                $prew->pid = $e;
                $prew->rid = $rew->id;

                $prew->save();

                $peoplePro->pid = $e;
                $peoplePro->groupid = $id;
                $peoplePro->projid = $group->pid;
                $peoplePro->roletype = PeopleProjectDao::ROLE_TYPE_GROUPER;
                $peoplePro->islock = PeopleProjectDao::IS_LOCK;

                $peoplePro->save();

            }
            $transaction->commit();
        } catch(\Exception $e) {
            $transaction->rollBack();
            Log::fatal(printf("提交人员分配审核出现问题：%s", $e->getMessage()));
            return $this->outputJson('', ErrorDict::getError(ErrorDict::ERR_INTERNAL));
        } catch(\Throwable $e) {
            $transaction->rollBack();
            Log::fatal(printf("提交人员分配审核出现问题：%s", $e->getMessage()));
            return $this->outputJson('', ErrorDict::getError(ErrorDict::ERR_INTERNAL));
        }

        return $this->outputJson('',
            ErrorDict::getError(ErrorDict::SUCCESS)
        );
    }

    /**
     * 增加审理人员列表接口
     *
     */
    public function actionJugebind() {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'pid' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'projid' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $pid = intval($this->getParam('pid', 0));
        $projid = $this->getParam('projid', 0);


        $jp = new JugeProjectDao();
        $jp->projid = $projid;
        $jp->pid = $pid;
        $jp->save();

        return $this->outputJson('', ErrorDict::getError(ErrorDict::SUCCESS));

    }

    /**
     * 解除审理人员
     *
     */
    public function actionJugeunbind() {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'pid' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'projid' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $pid = intval($this->getParam('pid', 0));
        $projid = $this->getParam('projid', 0);


        $juge = JugeProjectDao::find()
            ->where(['projid' => $projid])
            ->where(['pid' => $pid])
            ->one();
        if(!$juge){
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM));
        }

        $juge->delete();

        return $this->outputJson('', ErrorDict::getError(ErrorDict::SUCCESS));

    }


    /**
     * 审理人员列表接口
     *
     */
    public function actionJugelist() {
        $this->defineMethod = 'GET';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'page_size' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'page' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $pid = intval($this->getParam('id', 0));
        $page_size = intval($this->getParam('page_size', 0));
        $page = intval($this->getParam('page', 0));

        $people = JugeProjectDao::find()
            ->where(['projid' => $pid])
            ->select('group_concat(groupid), pid')
            ->groupBy('pid');
        $countPeople = clone $people;
        $countPeople = $countPeople->count();

        $people = $people
            ->limit($page_size)
            ->offset(($page - 1) * $page_size)
            ->asArray()
            ->all();

        $result = [];
        foreach ($people as $e){
            $tmp = [
                'id' => $e['pid'],
                'group' => $e['group_concat(groupid)']
            ];
            $user = UserDao::findOne($e['pid']);
            if(!$user){
                continue;
            }

            $tmp['pid'] = $user['pid'];
            $tmp['name'] = $user['name'];
            $tmp['sex'] = UserDao::$sex[$user['sex']];
            $tmp['location'] = $user['location'];

            $depart = OrganizationDao::findOne($user['department']);
            $tmp['department'] = $depart['name'] ?? '';
            $result[] = $tmp;
        }

        return $this->outputJson([
            'list' => $result,
            'total' => $countPeople
        ], ErrorDict::getError(ErrorDict::SUCCESS));

    }

    /**
     * 更改审计组
     *
     */
    public function actionJugechange() {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'pid' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'projid' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'groupids' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $pid = intval($this->getParam('pid', 0));
        $projid = intval($this->getParam('projid', 0));
        $groupids = $this->getParam('groupids');

        if(count($groupids) == 0){
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '审计组id为空!'));
        }

        $peopjos = PeopleProjectDao::find()
            ->where(['pid' => $pid])
            ->andWhere('in', 'groupid', $groupids)
            ->andWhere(['projid' => $projid])
            ->count();
        if($peopjos > 0){
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '审计组中有该成员！'));
        }

        $transaction = JugeProjectDao::getDb()->beginTransaction();

        try {
            $juges = JugeProjectDao::find()
                ->where(['projid' => $projid])
                ->where(['pid' => $pid])
                ->all();
            foreach ($juges as $e){
                $e->delete();
            }

            foreach ($groupids as $e){
                $jg = new JugeProjectDao();
                $jg->pid = $pid;
                $jg->groupid = $e;
                $jg->projid = $projid;
                $jg->save();
            }

            $transaction->commit();
        } catch(\Exception $e) {
            $transaction->rollBack();
            return $this->outputJson('', ErrorDict::getError(ErrorDict::ERR_INTERNAL));
        } catch(\Throwable $e) {
            $transaction->rollBack();
            return $this->outputJson('', ErrorDict::getError(ErrorDict::ERR_INTERNAL));
        }




        return $this->outputJson('', ErrorDict::getError(ErrorDict::SUCCESS));

    }
}