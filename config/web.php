<?php
//相关常量和路径定义
define('APP_PATH', realpath(dirname(__FILE__) . '/../'));
define('APP_PROJECT_NAME', 'shenji-api');
define('APP_VERSION', '1.0.0');
define('APP_CONFIG_PATH', APP_PATH . '/config');

//获取当前环境
$environment = "dev";
$params = require(__DIR__ . '/' . $environment . '_params.php');
$db = require(__DIR__ . '/' . $environment . '_db.php');
$params = array_merge($params, $db);

$logDir = "/data0/www/applogs/shenji-api";
$cacheDir = "/data0/www/cache/shenji-api";

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'viewPath' => dirname(__DIR__) . "/views",
    'runtimePath' => $cacheDir,
    'bootstrap' => ['log'],
    'modules' => [
        'act' => [
            'class' => 'app\modules\act\Module',
        ],
        'page' => [
            'class' => 'app\modules\page\Module',
        ],
        'api' => [
            'class' => 'app\modules\api\Module'
        ]
    ],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => '',
            'enableCsrfValidation' => false,
        ],
//        'cache' => [
//            'class' => 'yii\redis\Cache',
//            'keyPrefix' => '',
//        ],
        //'redis' => $params['redis'],
        'errorHandler' => [
            //'errorAction' => 'site/error',
        ],
        //'db' => $params['mysql'],
        'view' => [
            'renderers' => [
                'tpl' => [
                    'class' => 'yii\smarty\ViewRenderer',
                    'cachePath' => '@runtime/Smarty/cache',
                ],
            ],
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'enableStrictParsing' => true,
            'showScriptName' => false,
            'rules' => require(__DIR__ . '/urlRules.php'),
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error'],
                    'logFile' => $logDir . '/shenji-api.error.log.' . date('Ymd')
                ],
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['warning'],
                    'logFile' => $logDir . '/shenji-api.warning.log.' . date('Ymd')
                ],
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['info'],
                    'logFile' => $logDir . '/shenji-api.info.log.' . date('Ymd')
                ],
            ],
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
}
return $config;
