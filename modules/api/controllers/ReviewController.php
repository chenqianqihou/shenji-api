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
use \app\models\AuditGroupDao;
use app\classes\BaseController;

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
        $rew = ReviewDao::findOne($id);
        if (!$rew){
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, 'id错误!'));
        }
        $proid = $rew->projid;


        $projectDao = new ProjectDao();
        $data = $projectDao->queryByID($proid);
        if(!$data){
            $error = ErrorDict::getError(ErrorDict::G_PARAM);
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

        $peoples = PeopleReviewDao::find()
            ->where(['rid' => $id])
            ->select('pid')
            ->all();
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
            ->select('people.pid, people.name, people.sex, people.address as location, peopleproject.roletype as projrole')
            ->where('in', 'peopleproject.groupid', $peoples)
            ->andWhere(['peopleproject.projid' => $proid])
            ->all();
        $ret['people']['list'] = $peoples;

        return $this->outputJson($ret, ErrorDict::getError(ErrorDict::SUCCESS));
    }
}