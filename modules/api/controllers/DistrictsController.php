<?php

namespace app\modules\api\controllers;

use app\classes\BaseController;
use app\classes\ErrorDict;
use Yii;

class DistrictsController extends BaseController
{
    public function actionList()
    {
        $this->defineMethod = 'GET';
        $this->defineParams = array (
        );
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($this->getDistricts(), $error);
        return $ret;
    }
}
