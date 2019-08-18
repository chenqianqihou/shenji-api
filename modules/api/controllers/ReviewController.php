<?php
/**
 * 审核模块
 *
 * User: ryugou
 * Date: 2019/8/11
 * Time: 11:29 AM
 */

use \app\models\ProjectDao;
use \app\models\UserDao;
use \app\models\ReviewDao;
use \app\models\PeopleReviewDao;
use \app\classes\ErrorDict;
use \app\models\PeopleProjectDao;
use \app\models\OrganizationDao;
use app\classes\BaseController;
use \app\models\AuditresultsDao;

class ReviewController extends BaseController{

    /**
     * 人员调配审核列表
     *
     * @return mixed
     */
    public function actionReviewlist(){
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
            ->select('review.id, project.projectnum, project.name, project.projyear, project.projorgan, project.projlevel, project.plantime')
            ->innerJoin('project', 'project.id = review.projid');

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
            $length = 20;
        }
        if (!$page) {
            $page = 1;
        }
        $countCon = clone $con;
        $list = $con->limit($length)->offset(($page - 1) * $length)->all();
        $list = array_map(function($e){
            $tmp = [
                "id" => $e->id,
                "projectnum" => $e->projectnum,
                "name" => $e->name,
                "projyear" => $e->projyear,
                "projlevel" => $e->projlevel,
            ];
            if($e['projtype'] == UserDao::$type['审计机关']){
                $tmp['status'] = PeopleReviewDao::REVIEW_NO_NEED_TYPE;
            }else {
                $rew = ReviewDao::find()
                    ->where(['projid' => $e->id])
                    ->one();
                if(!$rew){
                    $tmp['status'] = PeopleReviewDao::REVIEW_NOT_SURE_TYPE;
                }else{
                    $tmp['status'] = $rew->status;
                }
            }


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
     * 人员调配审核列表搜索项信息
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
                    ->where(['rid' => $rew->projid])
                    ->groupBy('pid')
                    ->select('pid')
                    ->all();

                if(count($pids) > 0){
                    $pros = PeopleProjectDao::find()
                        ->where(['projid' => $rew->projid])
                        ->andWhere('in', 'pid', $pids)
                        ->all();
                    foreach ($pros as $pro){
                        $pro->delete();
                    }
                }
            }


            $transaction->commit();
        } catch(\Exception $e) {
            $transaction->rollBack();
            return $this->outputJson('', ErrorDict::ERR_INTERNAL);
        } catch(\Throwable $e) {
            $transaction->rollBack();
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
        $useid = intval($this->data['ID']);
        $query = $this->getParam('query', '');
        $page_size = intval($this->getParam('page_size', 0));
        $page = intval($this->getParam('page', 0));

        $con = (new \yii\db\Query())
            ->from('auditresults')
            ->innerJoin('project', 'project.id = auditresults.id')
            ->innerJoin('people', 'people.pid = auditresults.pid')
            ->select('auditresults.id, project.projectnum, project.name, project.projyear, project.projlevel, people.id as pid, people.name as pname, people.projrole, auditresults.status')
            ->where(['auditresults.operatorid' => $useid]);

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
            ->innerJoin('project', 'project.id = auditresults.id')
            ->innerJoin('people', 'people.pid = auditresults.pid')
            ->select('auditresults.*, project.projectnum, project.name as projname, project.projyear, project.projlevel, project.projtype, people.id as pid, people.name as pname, people.pid as pnum')
            ->where(['auditresults.id' => $id])
            ->one();

        if(!$info){
            return $this->outputJson('', ErrorDict::G_PARAM);
        }
        $ret['basic_info'] = [
            "pnum" => $info['pnum'], #人员cod
            "pname" => $info['pname'], #姓名
            "projname" => $info['projname'], #项目名称
            "projnum" => $info['projnum'], #项目编号
            "projyear" => $info['projyear'], #项目年度
            "projtype" =>  $info['projtype'], #项目类型
            "projlevel" => $info['projlevel'], # 1省厅统一组织 2市州本级 3市州统一组织 4县级
            "havereport" =>  $info['havereport'], #0不填1是2否
            "projrole" => $info['projrole'], #1审计组长,2主审,3审计组员
        ];
        $ret['audit_result'] = [
            "problemtype" => $info['problemtype'], #查出问题性质
            "problemdetail" =>  $info['problemdetail'] , #查出问题明细
            "amountone" =>  $info['amountone'], #处理金额
            "amounttwo" =>  $info['amounttwo'], #审计期间整改金额
            "amountthree" =>  $info['amountthree'], #审计促进整改落实有关问题资金
            "amountfour" =>  $info['amountfour'], #审计促进整改有关问题资金金额
            "amountfive" =>  $info['amountfive'], #审计促进拨付资金到位
            "amountsix" => $info['amountsix'], #审计后挽回金额
            "desc" =>  $info['desc'],#问题描述
            "isfindout" =>  $info['isfindout'], #是否单独查出0为不填1是2否
            "findoutnum" => $info['findoutnum'], #查出人数
        ];
        $ret['move_result_info'] = [
            "istransfer" =>  $info['istransfer'], #是否移送事项0为不填1是2否
            "processorgans" =>  $info['processorgans'] , #移送机关，1司法机关2纪检监察机关3有关部门
            "transferamount" =>  $info['transferamount'], #移送处理金额
            "transferpeoplenum" => $info['transferpeoplenum'], #移送人数
            "transferpeopletype" => $info['transferpeopletype'], #移送人员类型，0不选1地厅级以上、2县处级、3乡科级、4其他
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
     * 审计成果详情
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