<?php

namespace app\tasks;

use app\models\AuditGroupDao;
use app\models\PeopleProjectDao;
use app\models\ProjectDao;
use app\models\UserDao;
use linslin\yii2\curl\Curl;
use yii\console\Controller;

class UserController extends Controller{

    /**
     * 计算审计人员=》客观分+主观分
     * 1、项目结束时计算
     */
    public function actionAuditpeoplescore()
    {
        $peopleProjects = (new \yii\db\Query())
            ->from('peopleproject')
            ->innerJoin('project', 'peopleproject.projid = project.id')
            ->select('peopleproject.*, project.projlevel')
            ->where(['status' => ProjectDao::$statusToName['项目结束']])
            ->andWhere(['subjectscore' => -1])
            ->all();
        $projectIds = [];
        $projectIdRoleLevel = [];
        foreach ($peopleProjects as $one) {
            if (!in_array($one['projid'], $projectIds)) {
                $projectIds[] = $one['projid'];
            }
            if (!isset($projectIdRoleLevel[$one['projid']])) {
                $projectIdRoleLevel[$one['projid']] = [];
            }
            $projectIdRoleLevel[$one['projid']][$one['pid']] = [];
            $projectIdRoleLevel[$one['projid']][$one['pid']]['projlevel'] = $one['projlevel'];
            $projectIdRoleLevel[$one['projid']][$one['pid']]['roletype'] = $one['roletype'];
        }
        if (count($projectIds) == 0) {
            return;
        }
        $qanswers = (new \yii\db\Query())
            ->from('qanswer')
            ->innerJoin('people', 'people.pid = qanswer.objpid')
            ->select('qanswer.*, people.type, people.id as peopleid')
            ->where(['in', 'projectid', $projectIds])
            ->all();
        $peopleProjectScore = [];
        foreach ($qanswers as $answer) {
            if (!isset($peopleProjectScore[$answer['projectid']])) {
                $peopleProjectScore[$answer['projectid']] = [];
            }
            if (!isset($peopleProjectScore[$answer['projectid']][$answer['peopleid']])) {
                $peopleProjectScore[$answer['projectid']][$answer['peopleid']] = [];
            }
            $peopleProjectScore[$answer['projectid']][$answer['peopleid']]['role'] = $answer['type'];
            if (!isset($peopleProjectScore[$answer['projectid']][$answer['peopleid']]['score'])) {
                $peopleProjectScore[$answer['projectid']][$answer['peopleid']]['score'] = [];
            }
            $peopleProjectScore[$answer['projectid']][$answer['peopleid']]['score'][] = $answer['score'];
        }

        //计算主观分数
        foreach ($peopleProjects as $one) {
            if (isset($peopleProjectScore[$one['projid']])
                && isset($peopleProjectScore[$one['projid']][$one['pid']])) {
                $score = $peopleProjectScore[$one['projid']][$one['pid']]['score'];
                if ($peopleProjectScore[$one['projid']][$one['pid']]['role']
                    == UserDao::$typeToName['审计机关']) {
                    $subjectScore = intval(array_sum($score) / count($score));
                }else {
                    $subjectScore = array_sum($score);
                }
                $peopleProjectDao = PeopleProjectDao::find()->where(
                    ['projid' => $one['projid'], 'pid' => $one['pid']])->one();
                $peopleProjectDao->subjectscore = $subjectScore;
                $peopleProjectDao->save();
            }
        }

        //计算客观分数
        $auditResults = (new \yii\db\Query())
            ->from('auditresults')
            ->select('*')
            ->where(['in', 'projectid', $projectIds])
            ->all();
        $redis_conn = \Yii::$app->redis;
        $cache_value = $redis_conn->get("objectiveScoreRule");
        $obejectiveScoreInfo = json_decode($cache_value, true);
        foreach ($auditResults as $one) {
            $score = 0;
            $projectId = $one['projectid'];
            $peopleId = $one['peopleid'];
            if (!isset($projectIdRoleLevel[$projectId]) ||
                !isset($projectIdRoleLevel[$projectId][$peopleId])) {
                continue;
            }
            //项目层级
            $projLevel = $projectIdRoleLevel[$projectId][$peopleId]['projlevel'];
            if (isset($obejectiveScoreInfo['single01Score'][$projLevel])) {
                $score = $score + $obejectiveScoreInfo['single01Score'][$projLevel];
            }
            //项目角色
            $roleType = $projectIdRoleLevel[$projectId][$peopleId]['roletype'];
            if (isset($obejectiveScoreInfo['single02Score'][$roleType])) {
                $score = $score + $obejectiveScoreInfo['single02Score'][$roleType];
            }
            //是否撰写报告，0不填1是2否
            if (isset($obejectiveScoreInfo['single03Score'][$one['havereport']])) {
                $score = $score + $obejectiveScoreInfo['single03Score'][$one['havereport']];
            }
            //是否单独查出0为不填1是2否
            if (isset($obejectiveScoreInfo['single04Score'][$one['isfindout']])) {
                $score = $score + $obejectiveScoreInfo['single04Score'][$one['isfindout']];
            }
            //是否移送事项0为不填1是2否
            if (isset($obejectiveScoreInfo['single05Score'][$one['istransfer']])) {
                $score = $score + $obejectiveScoreInfo['single05Score'][$one['istransfer']];
            }
            //移送机关，1司法机关2纪检监察机关3有关部门
            if (isset($obejectiveScoreInfo['single06Score'][$one['processorgans']])) {
                $score = $score + $obejectiveScoreInfo['single06Score'][$one['processorgans']];
            }
            //移送人员类型，0不选1地厅级以上、2县处级、3乡科级、4其他
            if (isset($obejectiveScoreInfo['single07Score'][$one['transferpeopletype']])) {
                $score = $score + $obejectiveScoreInfo['single07Score'][$one['transferpeopletype']];
            }
            //是否纳入本级工作报告0为不填1是2否
            if (isset($obejectiveScoreInfo['single08Score'][$one['bringintoone']])) {
                $score = $score + $obejectiveScoreInfo['single08Score'][$one['bringintoone']];
            }
            //是否纳入上级审计工作报告0为不填1是2否
            if (isset($obejectiveScoreInfo['single09Score'][$one['bringintotwo']])) {
                $score = $score + $obejectiveScoreInfo['single09Score'][$one['bringintotwo']];
            }
            //评优，1署优秀、2署表彰、3省优秀、4省表彰、5市优秀、6市表彰、7县优秀、8县表彰
            if (isset($obejectiveScoreInfo['single10Score'][$one['appraisal']])) {
                $score = $score + $obejectiveScoreInfo['single10Score'][$one['appraisal']];
            }
            //问题性质id //问题明细id与违规列表关联
            if (isset($obejectiveScoreInfo['problemScore'][$one['problemdetailid']])) {
                $score = $score + $obejectiveScoreInfo['problemScore'][$one['problemdetailid']];
            }
            //处理金额
            if (isset($obejectiveScoreInfo['range01Score'])) {
                $range01Score = $obejectiveScoreInfo['range01Score'];
                foreach ($range01Score as $rang) {
                    if ($one['amountone'] >= $rang['value'][0] && $one['amountone'] <= $rang['value'][1]) {
                        $score = $score + $rang['score'];
                        break;
                    }
                }
            }
            //审计期间整改金额
            if (isset($obejectiveScoreInfo['range02Score'])) {
                $range02Score = $obejectiveScoreInfo['range02Score'];
                foreach ($range02Score as $rang) {
                    if ($one['amounttwo'] >= $rang['value'][0] && $one['amounttwo'] <= $rang['value'][1]) {
                        $score = $score + $rang['score'];
                        break;
                    }
                }
            }
            //审计促进整改落实有关问题资金
            if (isset($obejectiveScoreInfo['range03Score'])) {
                $range03Score = $obejectiveScoreInfo['range03Score'];
                foreach ($range03Score as $rang) {
                    if ($one['amountthree'] >= $rang['value'][0] && $one['amountthree'] <= $rang['value'][1]) {
                        $score = $score + $rang['score'];
                        break;
                    }
                }
            }
            //审计促进整改有关问题资金金额
            if (isset($obejectiveScoreInfo['range04Score'])) {
                $range04Score = $obejectiveScoreInfo['range04Score'];
                foreach ($range04Score as $rang) {
                    if ($one['amountfour'] >= $rang['value'][0] && $one['amountfour'] <= $rang['value'][1]) {
                        $score = $score + $rang['score'];
                        break;
                    }
                }
            }
            //审计促进拨付资金到位
            if (isset($obejectiveScoreInfo['range05Score'])) {
                $range05Score = $obejectiveScoreInfo['range05Score'];
                foreach ($range05Score as $rang) {
                    if ($one['amountfive'] >= $rang['value'][0] && $one['amountfive'] <= $rang['value'][1]) {
                        $score = $score + $rang['score'];
                        break;
                    }
                }
            }
            //审计后挽回金额
            if (isset($obejectiveScoreInfo['range06Score'])) {
                $range06Score = $obejectiveScoreInfo['range06Score'];
                foreach ($range06Score as $rang) {
                    if ($one['amountsix'] >= $rang['value'][0] && $one['amountsix'] <= $rang['value'][1]) {
                        $score = $score + $rang['score'];
                        break;
                    }
                }
            }
            //查出人数
            if (isset($obejectiveScoreInfo['range07Score'])) {
                $range07Score = $obejectiveScoreInfo['range07Score'];
                foreach ($range07Score as $rang) {
                    if ($one['findoutnum'] >= $rang['value'][0] && $one['findoutnum'] <= $rang['value'][1]) {
                        $score = $score + $rang['score'];
                        break;
                    }
                }
            }
            //移送处理金额
            if (isset($obejectiveScoreInfo['range08Score'])) {
                $range08Score = $obejectiveScoreInfo['range08Score'];
                foreach ($range08Score as $rang) {
                    if ($one['transferamount'] >= $rang['value'][0] && $one['transferamount'] <= $rang['value'][1]) {
                        $score = $score + $rang['score'];
                        break;
                    }
                }
            }
            //移送人数
            if (isset($obejectiveScoreInfo['range09Score'])) {
                $range09Score = $obejectiveScoreInfo['range09Score'];
                foreach ($range09Score as $rang) {
                    if ($one['transferpeoplenum'] >= $rang['value'][0] && $one['transferpeoplenum'] <= $rang['value'][1]) {
                        $score = $score + $rang['score'];
                        break;
                    }
                }
            }
            $peopleProjectDao = PeopleProjectDao::find()->where(
                ['projid' => $one['projectid'], 'pid' => $one['peopleid']])->one();
            $peopleProjectDao->objectscore = $score;
            $peopleProjectDao->save();
        }

    }

    public function actionObjectivescore() {
        $objectiveScores = (new \yii\db\Query())
            ->from('objectivescore')
            ->innerJoin('objectivetype', 'objectivescore.typeid = objectivetype.id')
            ->select('objectivescore.*, objectivetype.onetype, objectivetype.twotype')
            ->all();
        //选择问题类型
        $problemScore = [];
        //单值类型
        $single01Score = [];//项目层级
        $single02Score = [];//项目角色
        $single03Score = [];//报告撰写
        $single04Score = [];//是否单独查出set
        $single05Score = [];//是否有移送事项
        $single06Score = [];//移送处理机关
        $single07Score = [];//送处理人员情况
        $single08Score = [];//是否纳入本级工作报告
        $single09Score = [];//是否纳入上级审计工作报告
        $single10Score = [];//评优
        //区间类型
        $range01Score = [];//审计成果:处理金额
        $range02Score = [];//审计成果:审计期间整改金额
        $range03Score = [];//审计成果:审计促进整改落实有关问题资金
        $range04Score = [];//审计成果:审计促进整改有关问题资金金额
        $range05Score = [];//审计成果:审计促进拨付资金到位
        $range06Score = [];//审计成果:审计后挽回（避免损失）金额
        $range07Score = [];//审计成果:查出人数
        $range08Score = [];//移送处理情况:移送处理金额
        $range09Score = [];//移送处理情况:移送人数
        foreach ($objectiveScores as $one) {
            if ($one['kindid'] == 2) {
                $tmp = json_decode($one['nameone']);
                if (count($tmp) == 4) {
                    $problemScore[$tmp[3]] = $one['score'];
                }

            }elseif ($one['kindid'] == 1) {
                switch ($one['nameone']) {
                    case '省厅统一组织':
                        $single01Score[ProjectDao::$projLevelName['省厅统一组织']] = $one['score'];
                        break;
                    case '市州本级':
                        $single01Score[ProjectDao::$projLevelName['市州本级']] = $one['score'];
                        break;
                    case '市州统一组织':
                        $single01Score[ProjectDao::$projLevelName['市州统一组织']] = $one['score'];
                        break;
                    case '县级':
                        $single01Score[ProjectDao::$projLevelName['县级']] = $one['score'];
                        break;
                    case '审计组长':
                        $single02Score[AuditGroupDao::$roleTypeName['审计组长']] = $one['score'];
                        break;
                    case '主审':
                        $single02Score[AuditGroupDao::$roleTypeName['主审']] = $one['score'];
                        break;
                    case '审计成员':
                        $single02Score[AuditGroupDao::$roleTypeName['审计组员']] = $one['score'];
                        break;
                    default:
                        break;
                }
                if ($one['twotype'] == '报告撰写' && $one['nameone'] == '是') {
                    $single03Score[1] = $one['score'];
                }
                if ($one['twotype'] == '报告撰写' && $one['nameone'] == '否') {
                    $single03Score[2] = $one['score'];
                }
                if ($one['twotype'] == '是否单独查出' && $one['nameone'] == '是') {
                    $single04Score[1] = $one['score'];
                }
                if ($one['twotype'] == '是否单独查出' && $one['nameone'] == '否') {
                    $single04Score[2] = $one['score'];
                }
                if ($one['twotype'] == '是否有移送事项' && $one['nameone'] == '是') {
                    $single05Score[1] = $one['score'];
                }
                if ($one['twotype'] == '是否有移送事项' && $one['nameone'] == '否') {
                    $single05Score[2] = $one['score'];
                }
                if ($one['twotype'] == '移送处理机关' && $one['nameone'] == '司法机关') {
                    $single06Score[1] = $one['score'];
                }
                if ($one['twotype'] == '移送处理机关' && $one['nameone'] == '纪检监察机关') {
                    $single06Score[2] = $one['score'];
                }
                if ($one['twotype'] == '移送处理机关' && $one['nameone'] == '有关部门') {
                    $single06Score[3] = $one['score'];
                }
                if ($one['twotype'] == '送处理人员情况' && $one['nameone'] == '地厅级以上') {
                    $single07Score[1] = $one['score'];
                }
                if ($one['twotype'] == '送处理人员情况' && $one['nameone'] == '县处级') {
                    $single07Score[2] = $one['score'];
                }
                if ($one['twotype'] == '送处理人员情况' && $one['nameone'] == '乡科级') {
                    $single07Score[3] = $one['score'];
                }
                if ($one['twotype'] == '送处理人员情况' && $one['nameone'] == '其他') {
                    $single07Score[4] = $one['score'];
                }
                if ($one['twotype'] == '是否纳入本级工作报告' && $one['nameone'] == '是') {
                    $single08Score[1] = $one['score'];
                }
                if ($one['twotype'] == '是否纳入本级工作报告' && $one['nameone'] == '否') {
                    $single08Score[2] = $one['score'];
                }
                if ($one['twotype'] == '是否纳入上级审计工作报告' && $one['nameone'] == '是') {
                    $single09Score[1] = $one['score'];
                }
                if ($one['twotype'] == '是否纳入上级审计工作报告' && $one['nameone'] == '否') {
                    $single09Score[2] = $one['score'];
                }
                if ($one['twotype'] == '评优' && $one['nameone'] == '署优秀') {
                    $single10Score[1] = $one['score'];
                }
                if ($one['twotype'] == '评优' && $one['nameone'] == '署表彰') {
                    $single10Score[2] = $one['score'];
                }
                if ($one['twotype'] == '评优' && $one['nameone'] == '省表彰') {
                    $single10Score[3] = $one['score'];
                }
                if ($one['twotype'] == '评优' && $one['nameone'] == '市表彰') {
                    $single10Score[4] = $one['score'];
                }
                if ($one['twotype'] == '评优' && $one['nameone'] == '县优秀') {
                    $single10Score[5] = $one['score'];
                }
                if ($one['twotype'] == '评优' && $one['nameone'] == '省优秀') {
                    $single10Score[6] = $one['score'];
                }
                if ($one['twotype'] == '评优' && $one['nameone'] == '市优秀') {
                    $single10Score[7] = $one['score'];
                }
                if ($one['twotype'] == '评优' && $one['nameone'] == '县表彰') {
                    $single10Score[8] = $one['score'];
                }


            }elseif($one['kindid'] == 3) {
                if ($one['onetype'] == '审计成果' && $one['twotype'] == '处理金额') {
                    if ($one['nameone'] != '' && $one['nametwo'] != '') {
                        $tmp = [];
                        $tmp['score'] = $one['score'];
                        $tmp['value'] = [];
                        $tmp['value'][] = $one['nameone'];
                        $tmp['value'][] = $one['nametwo'];
                        $range01Score[] = $tmp;
                    }
                }
                if ($one['onetype'] == '审计成果' && $one['twotype'] == '审计期间整改金额') {
                    if ($one['nameone'] != '' && $one['nametwo'] != '') {
                        $tmp = [];
                        $tmp['score'] = $one['score'];
                        $tmp['value'] = [];
                        $tmp['value'][] = $one['nameone'];
                        $tmp['value'][] = $one['nametwo'];
                        $range02Score[] = $tmp;
                    }
                }
                if ($one['onetype'] == '审计成果' && $one['twotype'] == '审计促进整改落实有关问题资金') {
                    if ($one['nameone'] != '' && $one['nametwo'] != '') {
                        $tmp = [];
                        $tmp['score'] = $one['score'];
                        $tmp['value'] = [];
                        $tmp['value'][] = $one['nameone'];
                        $tmp['value'][] = $one['nametwo'];
                        $range03Score[] = $tmp;
                    }
                }
                if ($one['onetype'] == '审计成果' && $one['twotype'] == '审计促进整改有关问题资金金额') {
                    if ($one['nameone'] != '' && $one['nametwo'] != '') {
                        $tmp = [];
                        $tmp['score'] = $one['score'];
                        $tmp['value'] = [];
                        $tmp['value'][] = $one['nameone'];
                        $tmp['value'][] = $one['nametwo'];
                        $range04Score[] = $tmp;
                    }
                }
                if ($one['onetype'] == '审计成果' && $one['twotype'] == '审计促进拨付资金到位') {
                    if ($one['nameone'] != '' && $one['nametwo'] != '') {
                        $tmp = [];
                        $tmp['score'] = $one['score'];
                        $tmp['value'] = [];
                        $tmp['value'][] = $one['nameone'];
                        $tmp['value'][] = $one['nametwo'];
                        $range05Score[] = $tmp;
                    }
                }
                if ($one['onetype'] == '审计成果' && $one['twotype'] == '审计后挽回（避免损失）金额') {
                    if ($one['nameone'] != '' && $one['nametwo'] != '') {
                        $tmp = [];
                        $tmp['score'] = $one['score'];
                        $tmp['value'] = [];
                        $tmp['value'][] = $one['nameone'];
                        $tmp['value'][] = $one['nametwo'];
                        $range06Score[] = $tmp;
                    }
                }
                if ($one['onetype'] == '审计成果' && $one['twotype'] == '查出人数') {
                    if ($one['nameone'] != '' && $one['nametwo'] != '') {
                        $tmp = [];
                        $tmp['score'] = $one['score'];
                        $tmp['value'] = [];
                        $tmp['value'][] = $one['nameone'];
                        $tmp['value'][] = $one['nametwo'];
                        $range07Score[] = $tmp;
                    }
                }
                if ($one['onetype'] == '移送处理情况' && $one['twotype'] == '移送处理金额') {
                    if ($one['nameone'] != '' && $one['nametwo'] != '') {
                        $tmp = [];
                        $tmp['score'] = $one['score'];
                        $tmp['value'] = [];
                        $tmp['value'][] = $one['nameone'];
                        $tmp['value'][] = $one['nametwo'];
                        $range08Score[] = $tmp;
                    }
                }
                if ($one['onetype'] == '移送处理情况' && $one['twotype'] == '移送人数') {
                    if ($one['nameone'] != '' && $one['nametwo'] != '') {
                        $tmp = [];
                        $tmp['score'] = $one['score'];
                        $tmp['value'] = [];
                        $tmp['value'][] = $one['nameone'];
                        $tmp['value'][] = $one['nametwo'];
                        $range09Score[] = $tmp;
                    }
                }
            }
        }
        $objectiveScoreRule = [
            //选择问题类型
            "problemScore" => $problemScore,
            //单值类型
            "single01Score" => $single01Score,//项目层级
            "single02Score" => $single02Score,//项目角色
            "single03Score" => $single03Score,//报告撰写
            "single04Score" => $single04Score,//是否单独查出
            "single05Score" => $single05Score,//是否有移送事项
            "single06Score" => $single06Score,//移送处理机关
            "single07Score" => $single07Score,//送处理人员情况
            "single08Score" => $single08Score,//是否纳入本级工作报告
            "single09Score" => $single09Score,//是否纳入上级审计工作报告
            "single10Score" =>  $single10Score,//评优
            //区间类型
            "range01Score" => $range01Score,//审计成果:处理金额
            "range02Score" => $range02Score,//审计成果:审计期间整改金额
            "range03Score" => $range03Score,//审计成果:审计促进整改落实有关问题资金
            "range04Score" => $range04Score,//审计成果:审计促进整改有关问题资金金额
            "range05Score" => $range05Score,//审计成果:审计促进拨付资金到位
            "range06Score" => $range06Score,//审计成果:审计后挽回（避免损失）金额
            "range07Score" => $range07Score,//审计成果:查出人数
            "range08Score" => $range08Score,//移送处理情况:移送处理金额
            "range09Score" => $range09Score,//移送处理情况:移送人数
        ];
        $objectiveScoreRuleJson = json_encode($objectiveScoreRule);
        $key = 'objectiveScoreRule';
        $redis_conn = \Yii::$app->redis;
        $redis_conn->set($key, $objectiveScoreRuleJson);
        $redis_conn->EXPIRE($key, 3600 * 24 * 365 * 10); //设置过期时间为10年
    }
}

