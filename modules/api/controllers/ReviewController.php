<?php
/**
 * 审核模块
 *
 * User: ryugou
 * Date: 2019/8/11
 * Time: 11:29 AM
 */

namespace app\modules\api\controllers;

use app\classes\Log;
use \app\models\ProjectDao;
use \app\models\UserDao;
use \app\models\ReviewDao;
use \app\models\PeopleReviewDao;
use \app\classes\ErrorDict;
use \app\models\PeopleProjectDao;
use \app\models\OrganizationDao;
use app\classes\BaseController;
use \app\models\AuditresultsDao;
use app\models\ViolationDao;

class ReviewController extends BaseController{

    /**
     * 人员调配审核列表
     *
     * @return mixed
     */
    public function actionList(){
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
            'query' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'page_size' => array (
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
        $query = $this->getParam('query', '');
        $page_size = intval($this->getParam('page_size', 0));
        $page = intval($this->getParam('page', 0));

        $con = (new \yii\db\Query())
            ->from('review')
            ->select('review.id, project.projectnum, project.name, project.projyear, project.projlevel, project.plantime, project.projtype, organization.name as projorgan, project.id as projid, review.status, review.ptype')
            ->innerJoin('project', 'project.id = review.projid')
            ->innerJoin('organization', 'organization.id = project.projorgan');

        if ($projyear) {
            if ( !is_numeric( $projyear) ) {
                return $this->outputJson('',
                    ErrorDict::getError(ErrorDict::G_PARAM, "projyear 输入格式不对！应为年份格式!")
                );
            }
            $con->where(['project.projyear' => $projyear]);
        }

        if ($projlevel) {
            if (!in_array($projlevel, [1, 2, 3, 4])) {
                return $this->outputJson('',
                    ErrorDict::getError(ErrorDict::G_PARAM, "projlevel 输入格式不对！")
                );
            }
            $con->where(['project.projlevel' => $projlevel]);
        }


        if ($query) {
            $con->where(['or', ['like', 'project.projectnum', $query], ['like', 'project.name', $query]]);
        }
        if (!$page_size) {
            $page_size = 20;
        }
        if (!$page) {
            $page = 1;
        }
        $countCon = clone $con;
        $list = $con->limit($page_size)->offset(($page - 1) * $page_size)->all();
        $list = array_map(function($e){
            $tmp = [
                "id" => $e['id'],
                "projectnum" => $e['projectnum'],
                "name" => $e['name'],
                "projorgan" => $e['projorgan'],
                "projyear" => $e['projyear'],
                "projlevel" => $e['projlevel'],
                "plantime" => $e['plantime'],
                "ptype" => ReviewDao::$ptypeMsg[$e['ptype']] ?? ''
            ];
            if($e['projtype'] == UserDao::$typeToName['审计机关']){
                $tmp['status'] = PeopleReviewDao::REVIEW_NO_NEED_TYPE;
            }else {

                if(is_null($e['status'])){
                    $tmp['status'] = PeopleReviewDao::REVIEW_NOT_SURE_TYPE;
                }else{
                    switch ($e['status']){
                        case ReviewDao::STATUS_DEFAULT:
                            $tmp['status'] = PeopleReviewDao::REVIEW_WAIT_TYPE;
                            break;
                        case ReviewDao::STATUS_SUCCESS:
                            $tmp['status'] = PeopleReviewDao::REVIEW_SUCCESS_TYPE;
                            break;
                        case ReviewDao::STATUS_FAILED:
                            $tmp['status'] = PeopleReviewDao::REVIEW_FAILED_TYPE;
                            break;
                        default:
                            $tmp['status'] = PeopleReviewDao::REVIEW_NO_NEED_TYPE;
                    }
                }
            }
            return $tmp;


        }, $list);


        $total = $countCon->count();

        return $this->outputJson([
            'list' => $list,
            'total' => $total,
        ], ErrorDict::SUCCESS);
    }

    /**
     * 人员调配审核列表搜索项信息
     *
     * @return mixed]
     */
    public function actionListsetting(){
        $this->defineMethod = 'GET';


        $projyears = (new \yii\db\Query())
            ->from('review')
            ->innerJoin('project', 'project.id = review.projid')
            ->groupBy('project.projyear')
            ->select('project.projyear')
            ->all();


        $projlevels = (new \yii\db\Query())
            ->from('review')
            ->innerJoin('project', 'project.id = review.projid')
            ->groupBy('project.projlevel')
            ->select('project.projlevel')
            ->all();

        return $this->outputJson([
            'projyears' => $projyears,
            'projlevels' => $projlevels,
        ], ErrorDict::SUCCESS);
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
        $rew = ReviewDao::find()
            ->where(['id' => $id])
            ->andWhere(['status' => ReviewDao::STATUS_DEFAULT])
            ->one();
        if (!$rew){
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, 'id错误!'));
        }
        if ($rew->type != ReviewDao::PEOPLE_TYPE) {
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '审核类型不对！'));
        }

        $proid = $rew->projid;


        $projectDao = new ProjectDao();
        $data = $projectDao->queryByID($proid);
        if(!$data){
            $error = ErrorDict::getError(ErrorDict::G_PARAM, '未找到此项目!');
            $ret = $this->outputJson("", $error);
            return $ret;
        }
        $ret['project'] = [
            'projectnum' => $data['projectnum'],
            'projname' => $data['name'],
            'projyear' => $data['projyear'],
            'projtype' => $data['projtype'],
            'projlevel' => $projectDao->getProjectLevelMsg($data['projlevel']),
            'leadernum' => $data['leadernum'],
            'auditornum' => $data['auditornum'],
            'masternum' => $data['masternum'],
            'plantime' => $data['plantime'],
            'projdesc' => $data['projdesc'],
            'projstart' => $data['projstart'],
            'projauditcontent' => $data['projauditcontent'],

        ];
        $orgDao = new OrganizationDao();
        $org = $orgDao::find()
            ->where(['id' => $data['leadorgan']])->one();
        $ret['project']['leadorgan'] = $org['name'] ?? "";
        $org = $orgDao::find()
            ->where(['id' => $data['projorgan']])->one();
        $ret['project']['projorgan'] = $org['name'] ?? "";

        $peoples = (new \yii\db\Query())
            ->from('peoplereview')
            ->innerJoin("review", 'review.id = peoplereview.rid')
            ->where(['peoplereview.rid' => $id])
            ->andWhere(['review.status' => ReviewDao::STATUS_DEFAULT])
            ->select('pid')
            ->all();
        $pids = array_map(function($e){
            return $e['pid'];
        }, $peoples);

        $ret['people'] = [
            'num' => count($peoples),
            'type' => $rew->ptype,
        ];

        if(count($peoples) <= 0){
            $error = ErrorDict::getError(ErrorDict::SUCCESS);
            $ret = $this->outputJson($ret, $error);
            return $ret;
        }

        $peoples = (new \yii\db\Query())
            ->from('peopleproject')
            ->innerJoin('people', 'peopleproject.pid = people.id')
            ->select('people.pid, people.name, people.type, people.sex, people.address as location, peopleproject.roletype as projrole, people.location as organization')
            ->where(['peopleproject.projid' => $proid])
            ->andWhere(['in', 'people.id', $pids])
            ->all();
        $peoples = array_map(function($e){
            $e['sex'] = UserDao::$sex[$e['sex']] ?? '';
            $e['type'] = UserDao::$type[$e['type']] ?? '';
            $e['projrole'] = PeopleProjectDao::$ROLES[$e['projrole']] ?? '';
            return $e;
        }, $peoples);


        $ret['people']['list'] = $peoples;

        return $this->outputJson($ret, ErrorDict::getError(ErrorDict::SUCCESS));
    }



    /**
     * 人员调配审核
     *
     * @return mixed]
     */
    public function actionOperate(){
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => false,
                'checker' => 'isNumber',
            ),
            'status' => array (
                'require' => false,
                'checker' => 'isNumber',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }

        $id = $this->getParam('id', '');
        $status = intval($this->getParam('status', 0));

        if(!in_array($status, [1, 2])){
            return $this->outputJson('', ErrorDict::G_PARAM);
        }

        $rew = ReviewDao::findOne($id);
        if(!$rew){
            return $this->outputJson('', ErrorDict::G_PARAM);
        }

        $transaction = ReviewDao::getDb()->beginTransaction();
        try {
            $rew->status = $status;
            $rew->save();

            if($status == ReviewDao::STATUS_FAILED){
                $pids = PeopleReviewDao::find()
                    ->where(['rid' => $id])
                    ->groupBy('pid')
                    ->select('pid')
                    ->asArray()
                    ->all();
                $pids = array_map(function($e){
                    return $e["pid"];
                }, $pids);

                if(count($pids) > 0){
                    $pros = PeopleProjectDao::find()
                        ->where(['projid' => $rew->projid])
                        ->andWhere(['in', 'pid', $pids])
                        ->all();
                    foreach ($pros as $pro){
                        $pro->delete();
                    }
                }
            }


            $transaction->commit();
        } catch(\Exception $e) {
            $transaction->rollBack();
            Log::fatal($e->getTrace());
            return $this->outputJson('', ErrorDict::ERR_INTERNAL);
        } catch(\Throwable $e) {
            $transaction->rollBack();
            Log::fatal($e->getTrace());
            return $this->outputJson('', ErrorDict::ERR_INTERNAL);
        }


        return $this->outputJson('', ErrorDict::SUCCESS);
    }

    /**
     * 人员调配中介审核
     *
     * @return mixed]
     */
    public function actionZhongjieoperate(){
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => false,
                'checker' => 'isNumber',
            ),
            'status' => array (
                'require' => false,
                'checker' => 'isNumber',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }

        $id = $this->getParam('id', '');
        $status = intval($this->getParam('status', 0));

        if(!in_array($status, [1, 2])){
            return $this->outputJson('', ErrorDict::G_PARAM);
        }

        $rew = ReviewDao::findOne($id);
        if(!$rew){
            return $this->outputJson('', ErrorDict::G_PARAM);
        }

        $transaction = ReviewDao::getDb()->beginTransaction();
        try {
            $rew->status = $status;
            $rew->save();

            if($status == ReviewDao::STATUS_FAILED){
                $pids = PeopleReviewDao::find()
                    ->where(['rid' => $id])
                    ->groupBy('pid')
                    ->select('pid')
                    ->asArray()
                    ->all();
                $pids = array_map(function($e){
                    return $e["pid"];
                }, $pids);

                if(count($pids) > 0){
                    $pros = PeopleProjectDao::find()
                        ->where(['projid' => $rew->projid])
                        ->andWhere(['in', 'pid', $pids])
                        ->all();
                    foreach ($pros as $pro){
                        $pro->delete();
                    }
                }
            }


            $transaction->commit();
        } catch(\Exception $e) {
            $transaction->rollBack();
            Log::fatal($e->getTrace());
            return $this->outputJson('', ErrorDict::ERR_INTERNAL);
        } catch(\Throwable $e) {
            $transaction->rollBack();
            Log::fatal($e->getTrace());
            return $this->outputJson('', ErrorDict::ERR_INTERNAL);
        }


        return $this->outputJson('', ErrorDict::SUCCESS);
    }


    /**
     * 人员调配内审审核
     *
     * @return mixed]
     */
    public function actionNeishenoperate(){
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => false,
                'checker' => 'isNumber',
            ),
            'status' => array (
                'require' => false,
                'checker' => 'isNumber',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }

        $id = $this->getParam('id', '');
        $status = intval($this->getParam('status', 0));

        if(!in_array($status, [1, 2])){
            return $this->outputJson('', ErrorDict::G_PARAM);
        }

        $rew = ReviewDao::findOne($id);
        if(!$rew){
            return $this->outputJson('', ErrorDict::G_PARAM);
        }

        $transaction = ReviewDao::getDb()->beginTransaction();
        try {
            $rew->status = $status;
            $rew->save();

            if($status == ReviewDao::STATUS_FAILED){
                $pids = PeopleReviewDao::find()
                    ->where(['rid' => $id])
                    ->groupBy('pid')
                    ->select('pid')
                    ->asArray()
                    ->all();
                $pids = array_map(function($e){
                    return $e["pid"];
                }, $pids);

                if(count($pids) > 0){
                    $pros = PeopleProjectDao::find()
                        ->where(['projid' => $rew->projid])
                        ->andWhere(['in', 'pid', $pids])
                        ->all();
                    foreach ($pros as $pro){
                        $pro->delete();
                    }
                }
            }


            $transaction->commit();
        } catch(\Exception $e) {
            $transaction->rollBack();
            Log::fatal($e->getTrace());
            return $this->outputJson('', ErrorDict::ERR_INTERNAL);
        } catch(\Throwable $e) {
            $transaction->rollBack();
            Log::fatal($e->getTrace());
            return $this->outputJson('', ErrorDict::ERR_INTERNAL);
        }


        return $this->outputJson('', ErrorDict::SUCCESS);
    }




    /**
     * 审计成果列表
     *
     * @return array
     */
    public function actionResultlist() {
        $this->defineMethod = 'GET';
        $this->defineParams = array (
            'query' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'page_size' => array (
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

        $useCode = $this->data['ID'];
        $query = $this->getParam('query', '');
        $page_size = intval($this->getParam('page_size', 0));
        $page = intval($this->getParam('page', 0));

        $user = UserDao::find()
            ->where(['pid' => $useCode])
            ->one();
        if(!$user){
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '登录用户未知！'));
        }

        $con = (new \yii\db\Query())
            ->from('auditresults')
            ->innerJoin('project', 'project.id = auditresults.projectid')
            ->innerJoin('people', 'people.id = auditresults.peopleid')
            ->innerJoin('peopleproject', 'peopleproject.pid = people.id')
            ->select('auditresults.id, project.projectnum, project.name, project.projyear, project.projlevel, people.id as pid, people.name as pname, peopleproject.roletype as projrole, auditresults.status')
            ->where(['auditresults.operatorid' => $user['id']]);

        if($query){
            $con = $con->where(['or', ['like', 'project.projectnum', "%{$query}%"], ['like', 'project.name', "%{$query}%"]]);
        }
        $countCon = clone $con;
        $total = $countCon->count();
        $list = $con->limit($page_size)->offset(($page - 1) * $page_size)->all();


        return $this->outputJson([
            'list' => $list,
            'total' => $total,
        ], ErrorDict::getError(ErrorDict::SUCCESS));
    }

    /**
     * 审计成果详情
     *
     * @return array
     */
    public function actionResultdetails() {
        $this->defineMethod = 'GET';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $id = $this->getParam('id', 0);

        $info = (new \yii\db\Query())
            ->from('auditresults')
            ->innerJoin('project', 'project.id = auditresults.projectid')
            ->innerJoin('people', 'people.id = auditresults.peopleid')
            ->select('auditresults.*, project.projectnum, project.name as projname, project.projyear, project.projlevel, project.projtype, people.id as pid, people.name as pname, people.pid as pnum')
            ->where(['auditresults.id' => $id])
            ->one();

        if(!$info){
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '参数不合法！未找到合适的审计成果!'));
        }
        $ret['basic_info'] = [
            "pnum" => $info['pnum'], #人员cod
            "pname" => $info['pname'], #姓名
            "projname" => $info['projname'], #项目名称
            "projnum" => $info['projectnum'], #项目编号
            "projyear" => $info['projyear'], #项目年度
            "projtype" =>  $info['projtype'], #项目类型
            "projlevel" => $info['projlevel'], # 1省厅统一组织 2市州本级 3市州统一组织 4县级
            "havereport" =>  $info['havereport'], #0不填1是2否
        ];

        $peoRro = PeopleProjectDao::find()
            ->where(['pid' => $info['peopleid']])
            ->andWhere(['projid' => $info['projectid']])
            ->one();
        $ret['basic_info']['projrole'] = $peoRro['roletype'] ?? 0;

        $ret['audit_result'] = [
            "amountone" =>  $info['amountone'], #处理金额
            "amounttwo" =>  $info['amounttwo'], #审计期间整改金额
            "amountthree" =>  $info['amountthree'], #审计促进整改落实有关问题资金
            "amountfour" =>  $info['amountfour'], #审计促进整改有关问题资金金额
            "amountfive" =>  $info['amountfive'], #审计促进拨付资金到位
            "amountsix" => $info['amountsix'], #审计后挽回金额
            "desc" =>  $info['desc'],#问题描述
            "isfindout" =>  $info['isfindout'], #是否单独查出0为不填1是2否
            "findoutnum" => $info['findoutnum'], #查出人数
            "havecoordinate" => $info['havecoordinate'],
            "haveanalyse" => $info['haveanalyse'],
            "havereport" => $info['havereport']
        ];

        $violation = ViolationDao::findOne($info['problemid']);
        $ret['audit_result']['problemtype'] = $violation['name'] ?? '';

//        $violation = ViolationDao::findOne($info['problemdetailid']);
//        $ret['audit_result']['problemdetail'] = $violation['name'] ?? '';

        $ret['move_result_info'] = [
            "istransfer" =>  $info['istransfer'], #是否移送事项0为不填1是2否
            "processorgans" =>  $info['processorgans'] , #移送机关，1司法机关2纪检监察机关3有关部门
            "transferamount" =>  $info['transferamount'], #移送处理金额
            "transferpeople" => $info['transferpeople'],
//            "transferpeoplenum" => $info['transferpeoplenum'], #移送人数
//            "transferpeopletype" => $info['transferpeopletype'], #移送人员类型，0不选1地厅级以上、2县处级、3乡科级、4其他
            "transferresult" => $info['transferresult'], #移送处理结果
        ];
        $ret['total_result_info'] = [
            "bringintoone" =>  $info['bringintoone'], #是否纳入本级工作报告0为不填1是2否
            "bringintotwo" =>  $info['bringintotwo'], #是否纳入上级审计工作报告0为不填1是2否
            "appraisal" => $info['appraisal'], #评优，1署优秀、2署表彰、3省优秀、4省表彰、5市优秀、6市表彰、7县优秀、8县表彰
        ];


        return $this->outputJson($ret, ErrorDict::getError(ErrorDict::SUCCESS));
    }

    /**
     * 审核
     *
     * @return array
     */
    public function actionResultoperate() {
        $this->defineMethod = 'GET';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'status' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $id = $this->getParam('id', 0);
        $status = $this->getParam('status', 0);
        if(!in_array($status, [1, 2])){
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '状态不对！'));
        }

        $result = AuditresultsDao::findOne($id);
        if(!$result){
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '审核列表id不对！'));
        }
        $result->status = $status;
        $result->save();

        return $this->outputJson('', ErrorDict::getError(ErrorDict::SUCCESS));
    }
}