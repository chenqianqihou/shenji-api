<?php
/**
 * 分析模块
 * User: ryugou
 * Date: 2019/9/4
 * Time: 11:43 PM
 */
namespace app\modules\api\controllers;

use app\classes\BaseController;
use app\classes\ErrorDict;
use app\models\ExpertiseDao;
use app\models\OrganizationDao;
use app\models\PeopleExpertiseDao;
use app\models\ProjectDao;
use app\models\UserDao;
use app\service\OrganizationService;

class DashbordController extends BaseController {

    /**
     * 饼图 审计特长
     *
     */
    public function actionExpertise() {
        $useCode = $this->data['ID'];
        $user = UserDao::find()
            ->where(['pid' => $useCode])
            ->one();
        if(!$user){
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '登录用户未知！'));
        }
        $originService = new OrganizationService();
        $origins = $originService->getSubIds($user['organid']);
        $origins[]= $user['organid'];

        $ret = [];

        if(count($origins) == 0){
            $ret['total'] = 0;
            $ret['list'] = [
                "caiwu" => 0,
                "touzi" => 0,
                "kuaiji" => 0,
                "qiye" => 0,
                "jinrong" => 0,
                "jisuanji" => 0,
                "qita" => 0,
                "weitianxie" => 0
            ];
            return $this->outputJson($ret, ErrorDict::getError(ErrorDict::SUCCESS));
        }

        $ret['total'] = UserDao::find()
            ->where(["in", "organid", $origins])
            ->count();

        $expertises = ExpertiseDao::find()
            ->asArray()
            ->all();


        $caiwuPids = $this->getExpertisePids("财务", $expertises);
        if(count($caiwuPids) == 0){
            $ret['list']['caiwu'] = 0;
        }else {
            $ret['list']['caiwu'] = UserDao::find()
                ->where(["in", 'pid', $caiwuPids])
                ->andwhere(["in", "organid", $origins])
                ->count();
        }

        $touziPids = $this->getExpertisePids("投资", $expertises);
        if(count($touziPids) == 0){
            $ret['list']['touzi'] = 0;
        }else {
            $ret['list']['touzi'] = UserDao::find()
                ->where(["in", 'pid', $touziPids])
                ->andwhere(["in", "organid", $origins])
                ->count();
        }

        $kuaijiPids = $this->getExpertisePids("会计", $expertises);
        if(count($kuaijiPids) == 0){
            $ret['list']['kuaiji'] = 0;
        }else {
            $ret['list']['kuaiji'] = UserDao::find()
                ->where(["in", 'pid', $kuaijiPids])
                ->andwhere(["in", "organid", $origins])
                ->count();
        }

        $jinrongPids = $this->getExpertisePids("金融", $expertises);
        if(count($jinrongPids) == 0){
            $ret['list']['jinrong'] = 0;
        }else {
            $ret['list']['jinrong'] = UserDao::find()
                ->where(["in", 'pid', $jinrongPids])
                ->andwhere(["in", "organid", $origins])
                ->count();
        }

        $jisuanjiPids = $this->getExpertisePids("计算机", $expertises);
        if(count($jisuanjiPids) == 0){
            $ret['list']['jisuanji'] = 0;
        }else {
            $ret['list']['jisuanji'] = UserDao::find()
                ->where(["in", 'pid', $jisuanjiPids])
                ->andwhere(["in", "organid", $origins])
                ->count();
        }

        $qitaPids = $this->getExpertisePids("其他", $expertises);
        if(count($qitaPids) == 0){
            $ret['list']['qita'] = 0;
        }else {
            $ret['list']['qita'] = UserDao::find()
                ->where(["in", 'pid', $qitaPids])
                ->andwhere(["in", "organid", $origins])
                ->count();
        }

        $otherPids = array_merge($caiwuPids, $touziPids, $kuaijiPids, $jinrongPids, $jisuanjiPids, $qitaPids);
        if(count($caiwuPids) == 0){
            $ret['list']['weitianxie'] = 0;
        }else {
            $ret['list']['weitianxie'] = UserDao::find()
                ->where(["not in", 'pid', $otherPids])
                ->andwhere(["in", "organid", $origins])
                ->count();
        }

        return $this->outputJson($ret, ErrorDict::getError(ErrorDict::SUCCESS));
    }

    private function getExpertisePids($msg, $expertises) {
        $eid = 0;
        foreach ($expertises as $e){
            if ($e['name'] === $msg){
                $eid =  $e['id'];
            }
        }
        if ($eid == 0) {
            return [];
        }

        $people = PeopleExpertiseDao::find()
            ->where(['eid' => $eid])
            ->asArray()
            ->all();
        $ret = [];
        foreach ($people as $p) {
            $ret[] = $p['pid'];
        }

        return $ret;

    }

    /**
     * 饼图 年龄性别
     *
     */
    public function actionSex() {
        $useCode = $this->data['ID'];
        $user = UserDao::find()
            ->where(['pid' => $useCode])
            ->one();
        if(!$user){
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '登录用户未知！'));
        }
        $originService = new OrganizationService();
        $origins = $originService->getSubIds($user['organid']);
        $origins[]= $user['organid'];

        $uses = UserDao::find()
            ->where(["in", "organid", $origins])
            ->asArray()
            ->all();
        $uses = array_map(function($e){
            $e['age'] = substr($e['cardid'], 7, 4);
            return $e;
        }, $uses);

        $users18_24 = array_filter($uses, function($e){
            return $e['age'] >= 18 && $e['age'] <= 24;
        });
        $users25_34 = array_filter($uses, function($e){
            return $e['age'] >= 25 && $e['age'] <= 34;
        });
        $users35_44 = array_filter($uses, function($e){
            return $e['age'] >= 35 && $e['age'] <= 44;
        });
        $users45_54 = array_filter($uses, function($e){
            return $e['age'] >= 45 && $e['age'] <= 54;
        });
        $users55_64 = array_filter($uses, function($e){
            return $e['age'] >= 55 && $e['age'] <= 64;
        });
        $users64 = array_filter($uses, function($e){
            return $e['age'] >= 64;
        });
        $userMan = array_filter($uses, function($e){
            return $e['sex'] == UserDao::MAN;
        });
        $useFemale = array_filter($uses, function($e){
            return $e['sex'] == UserDao::FEMALE;
        });

        $ret = [
            'total' => count($uses),
            "gender" => [
                "man" => count($userMan),
                "female" => count($useFemale),
            ],
            "age" => [
                "18-24" => count($users18_24),
                "25-34" => count($users25_34),
                "35-44" => count($users35_44),
                "45-54" => count($users45_54),
                "55-64" => count($users55_64),
                "64-" => count($users64)
            ]
        ];

        return $this->outputJson($ret, ErrorDict::getError(ErrorDict::SUCCESS));
    }

    /**
     * 饼图 项目类型
     *
     */
    public function actionProj() {
        $useCode = $this->data['ID'];
        $user = UserDao::find()
            ->where(['pid' => $useCode])
            ->one();
        if(!$user){
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '登录用户未知！'));
        }
        $originService = new OrganizationService();
        $origins = $originService->getSubIds($user['organid']);
        $origins[]= $user['organid'];

        $originDatas = ProjectDao::find()
            ->where(["in", "projorgan", $origins])
            ->asArray()
            ->all();

        $province = array_filter($originDatas, function($e){
            return $e['projlevel'] == OrganizationDao::PROJ_LEVEL_PROVINCE;
        });
        $cityLocal = array_filter($originDatas, function($e){
            return $e['projlevel'] == OrganizationDao::PROJ_LEVEL_CITY_LOCAL;
        });
        $cityUnified = array_filter($originDatas, function($e){
            return $e['projlevel'] == OrganizationDao::PROJ_LEVEL_CITY_UNIFIED;
        });
        $country = array_filter($originDatas, function($e){
            return $e['projlevel'] == OrganizationDao::PROJ_LEVEL_COUNTRY;
        });


        $ret = [
            'total' => count($originDatas),
            "list" => [
                "province" => count($province),
                "city_local" => count($cityLocal), #市州本级
                "city_unified" => count($cityUnified), #市州统一组织
                "country" => count($country)
            ],

        ];

        return $this->outputJson($ret, ErrorDict::getError(ErrorDict::SUCCESS));
    }


    /**
     * 整体数据情况
     *
     */
    public function actionWhole() {
        $this->defineMethod = 'GET';
        $this->defineParams = array (
            'city' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $city = $this->getParam('city', 0);


        $useCode = $this->data['ID'];
        $user = UserDao::find()
            ->where(['pid' => $useCode])
            ->one();
        if(!$user){
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '登录用户未知！'));
        }

        if ($user['organid'] !== 1012) {
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '没有权限看其他市的！'));
        }

        if($city == 0){
            $organizes = OrganizationDao::find()
                ->andWhere(['otype' => 3])
                ->andWhere(['parentid' => 0])
                ->asArray()
                ->all();
        }else{
            $organizes = OrganizationDao::find()
                ->where(['regnum' => $city])
                ->andWhere(['otype' => 3])
                ->andWhere(['parentid' => 0])
                ->asArray()
                ->all();
        }

        $origins = array_map(function($e){
            return $e['id'];
        }, $organizes);

        $uses = UserDao::find()
            ->where(["in", "organid", $origins])
            ->asArray()
            ->all();

        $isJob = array_filter($uses,function($e){
            return $e['isjob'] == UserDao::IS_JOB;
        });
        $notJob = array_filter($uses,function($e){
            return $e['isjob'] == UserDao::IS_NOT_JOB;
        });
        $ret = [
            "people" => [
                "total" => count($uses),
                "isjob" => count($isJob),
                "isnotjob" => count($notJob)
            ]
        ];


        $projects = ProjectDao::find()
            ->where(["in", "projorgan", $origins])
            ->asArray()
            ->all();
        $planProj = array_filter($projects, function($e){
            return in_array($e['status'], [0, 1]);
        });
        $doingProj = array_filter($projects, function($e){
            return $e['status'] == ProjectDao::$statusToName['实施阶段'];
        });
        $heardProj = array_filter($projects, function($e){
            return $e['status'] == ProjectDao::$statusToName['审理阶段'];
        });
        $reportProj = array_filter($projects, function($e){
            return $e['status'] == ProjectDao::$statusToName['报告阶段'];
        });
        $completeProj = array_filter($projects, function($e){
            return $e['status'] == ProjectDao::$statusToName['项目结束'];
        });


        $ret["project"] = [
            "total" => count($projects),
            "plan" => count($planProj),
            "doing" => count($doingProj),
            "heard" => count($heardProj),
            "report" => count($reportProj),
            "complete" => count($completeProj)
        ];


        return $this->outputJson($ret, ErrorDict::getError(ErrorDict::SUCCESS));
    }


    /**
     * 获取当前用户所属机构的层级
     *
     */
    public function actionUserlocation() {
        $useCode = $this->data['ID'];
        $user = UserDao::find()
            ->where(['pid' => $useCode])
            ->one();
        if(!$user){
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '登录用户未知！'));
        }
        $organ = OrganizationDao::findOne($user['organid']);
        if(!$organ){
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '用户机构不存在！'));
        }
        return $this->outputJson([
            "regnum" => $organ['regnum']
        ], ErrorDict::getError(ErrorDict::SUCCESS));

    }



}