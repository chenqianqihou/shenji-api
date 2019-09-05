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
use app\models\PeopleExpertiseDao;
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
        $origins = $originService->getSubordinateIds($user['organid']);
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
}