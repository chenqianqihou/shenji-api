<?php

namespace app\service;

use app\models\AssessconfigDao;
use app\models\QanswerDao;
use app\models\QuestionsDao;

class AssessService
{
    const ASSESSTYPE = [
        '5' => '审计成员给审计组长的评价',
        '4' => '审计成员给主审的评价',
        '6' => '审计组长给审计成员的评价',
        '7' => '审计组长给第三方审计人员的评价',
        '2' => '审理处给第三方审计人员的评价',
        '3' => '业务处给第三方审计人员的评价',
        '1' => '法规处给第三方审计人员的评价',
    ];

    public function AssessType() {
        return self::ASSESSTYPE;
    }

    public function FormContent( $uid,$objuid,$projectid,$typeid) {
        
        $result = ['answer'=>[],'question'=>[]];
        //是否已经回答过
        if( QanswerDao::find()->where(['pid'=>$uid,'objpid'=>$objuid,'projectid'=>$projectid,'configid'=>$typeid])->count() ){
            $answer = QanswerDao::find()->where(['pid'=>$uid,'objpid'=>$objuid,'projectid'=>$projectid,'configid'=>$typeid])->asArray()->one();
            $result['answer'] = json_decode($answer['answers'],true);
        }

        $result['question'] = $this->getConfigQuestion( $typeid );
        return $result;
    }

    protected function getDefaultConfig( $typeid ) {
        $result = [];
        if( isset(self::ASSESSTYPE[$typeid]) ){
            $result =  AssessconfigDao::find()->where(['id' => $typeid])->asArray()->one();
        }
        return $result;
    }

    protected function getConfigQuestion( $typeid ) {
        $configMsg = $this->getDefaultConfig( $typeid );
        if( empty($configMsg) ){
            return [];    
        }    

        $questionlist = json_decode($configMsg['questionlist'],true);
        $questMsgs = QuestionsDao::find()->where(['id'=>$questionlist])->asArray()->all();
        $result = [];
        foreach( $questMsgs as $q){
            $qtype = $q['qtype'];
            $q['options'] = json_decode( $q['qoptions'],true);
            unset($q['qoptions']);
            if( !isset($result[$qtype])){
                $result[$qtype] = [];    
            }    
            $result[$qtype][] = $q;
        }
        return $result;
    }

    public function SubmitFormContent( $uid,$objuid,$projectid,$typeid,$answers) {
        
        $result = ['answer'=>[],'question'=>[]];
        //是否已经回答过
        if( QanswerDao::find()->where(['pid'=>$uid,'objpid'=>$objuid,'projectid'=>$projectid,'configid'=>$typeid])->count() ){
            $qanswer = QanswerDao::find()->where(['pid'=>$uid,'objpid'=>$objuid,'projectid'=>$projectid,'configid'=>$typeid])->one();
            $qanswer->answers = json_encode( $answers );
            $qanswer->save();
        } else {
            $qanswer = new QanswerDao();
            $qanswer->pid=$uid;
            $qanswer->objpid=$objuid;
            $qanswer->projectid=$projectid;
            $qanswer->configid=$typeid;
            $qanswer->answers=json_encode($answers);
            $qanswer->save();   
        }
        $result['answer'] = $answers;
        $result['question'] = $this->getConfigQuestion( $typeid );
        return $result;
    }

}
