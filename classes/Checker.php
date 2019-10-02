<?php

namespace app\classes;

class Checker
{

    protected static $_ret = true;

    public static function getRet()
    {
        return self::$_ret;
    }

    protected static $_err = array();

    public static function getError()
    {
        return self::$_err;
    }

    /**
     * 判断时间是否合法
     * @param $date
     * @param array $formats
     * @return bool
     */
    public static function isDateValid ($paramName, $paramValue) {
        $format = 'Y-m-d H:i:s';
        $unixTime = strtotime($paramValue);
        if(!$unixTime) { //无法用strtotime转换，说明日期格式非法
            self::$_err = ErrorDict::getError(ErrorDict::G_PARAM, '日期参数有误！ ', '参数格式错误');
            self::$_ret = false;
            return;
        }

        //校验日期合法性
        if(date($format, $unixTime) == $paramValue) {
            self::$_ret = true;
            return;
        }

        self::$_ret = false;
    }

    /**
     * 判断是否是整数
     *
     * @param $paramName
     * @param $paramValue
     */
    public static function isNumber($paramName, $paramValue) {
        if (is_numeric($paramValue)) {
            self::$_ret = true;
            return;
        }

        self::$_err = ErrorDict::getError(ErrorDict::G_PARAM, '数字参数有误！', '参数应为整数!');
        self::$_ret = false;
    }

    /**
     * 判断是否是某一年
     *
     * @param $paramName
     * @param $paramValue
     */
    public static function isYear($paramName, $paramValue) {
        $format = 'Y';
        $unixTime = strtotime($paramValue);
        if(!$unixTime) { //无法用strtotime转换，说明日期格式非法
            self::$_err = ErrorDict::getError(ErrorDict::G_PARAM, '年份日期有误！', '年份格式错误!');
            self::$_ret = false;
            return;
        }

        //校验日期合法性
        if(date($format, $unixTime) == $paramValue) {
            self::$_ret = true;
            return;
        }

        self::$_ret = false;
    }
}
