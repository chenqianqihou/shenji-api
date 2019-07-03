<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;

class SiteController extends Controller {
    public function actionError() {
        if ($error = Yii::$app->errorHandler->exception) {
            Yii::error('FinalError:' . json_encode($error));
            return $this->render('500');
        }
    }
}
