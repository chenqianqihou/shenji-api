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

    public function actionMultiadd()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array ();
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $params = $this->getParams();

        $organService = new OrganizationService();
        foreach( $params as $p){
            $checkres = $organService->checkParams( $p );
            if( !$checkres['res']){
                $error = ErrorDict::getError(ErrorDict::G_PARAM);
                $ret = $this->outputJson($checkres, $error);
                return $ret;
            }
        }

        foreach( $params as $p){
            $addres = $organService->insertOrganization( $p );
            if( !$addres['res']){
                $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
                $ret = $this->outputJson($addres, $error);
                return $ret;
            }
        }

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson(true, $error);
        return $ret;
    }

    public function actionInfo() {
        $this->defineMethod = 'GET';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );

        $oid = $this->getParams('id');
        $organService = new OrganizationService();
        $organInfo = $organService->getOrganizationInfo( $oid );
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($organInfo, $error);
        return $ret;
    }

    public function actionUpdate()
    {
        $this->defineMethod = 'POST';
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
        $params = $this->getParams();

        $organService = new OrganizationService();
        $checkres = $organService->checkParams( $params );
        if( !$checkres['res']){
            $error = ErrorDict::getError(ErrorDict::G_PARAM);
            $ret = $this->outputJson($checkres, $error);
            return $ret;
        }

        $updres = $organService->updateOrganization( $params );
        if( !$updres['res']){
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson($updres, $error);
            return $ret;
        }

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($updres, $error);
        return $ret;
    }

    public function actionSearch()
    {
        $this->defineMethod = 'GET';
        $this->defineParams = array ();
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $keyword = $this->getParam('key','');
        $otype = $this->getParam('type',-1);
        $start = $this->getParam('start',0);
        $length = $this->getParam('length',0);
        $organService = new OrganizationService();
        $organList = $organService->getOrganizationList( $keyword,$otype,$start,$length );
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($organList, $error);
        return $ret;
    }


}
