<?php

namespace app\modules\api\controllers;

use app\classes\BaseController;
use app\classes\ErrorDict;
use app\service\OrganizationService;
use Yii;

class OrganizationController extends BaseController
{

    public function actionAdd()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array ();
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $params = $this->getParams();

        $organService = new OrganizationService();
        $checkres = $organService->checkParams( $params );
        if( !$checkres['res']){
            $error = ErrorDict::getError(ErrorDict::G_PARAM);
            $ret = $this->outputJson($checkres, $error);
            return $ret;
        }

        $addres = $organService->insertOrganization( $params );
        if( !$addres['res']){
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson($addres, $error);
            return $ret;
        }

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($addres, $error);
        return $ret;
    }

    public function actionInfo() {
        $this->defineMethod = 'GET';
        $this->defineParams = array (
            'oid' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );

        $oid = $this->getParams('oid');
        $organService = new OrganizationService();
        $organInfo = $organService->getOrganizationInfo( $oid );
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($organInfo, $error);
        return $ret;
    }
}
