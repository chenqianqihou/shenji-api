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
use app\models\PeopleProjectDao;
use app\models\ProjectDao;


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
        $id = intval($this->getParam('id', 0));
        $pid = intval($this->getParam('pid', 0));

        $proDao = new PeopleProjectDao();
        try{
            $peoPros = $proDao::find()->where(['groupid' => $id])->groupBy('projid')->all();
        }catch (\Exception $e){
            Log::fatal($e->getMessage());
            return $this->outputJson('', ErrorDict::getError(ErrorDict::ERR_INTERNAL));
        }

        $transaction = $proDao::getDb()->beginTransaction();
        try{
            foreach ($peoPros as $e) {
                $newPeop = new PeopleProjectDao();
                $newPeop->pid = $pid;
                $newPeop->projid = $e->projid;
                $newPeop->groupid = $id;
                $newPeop->roletype = $newPeop::ROLE_TYPE_GROUPER;
                $newPeop->islock = 1;
                $newPeop->save();
            }
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

}