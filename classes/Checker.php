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
            self::$_err = ErrorDict::getError(ErrorDict::G_PARAM, 'invalid param '.$paramName, $paramName.'格式错误');
            self::$_ret = false;
        }

        //校验日期合法性
        if(date($format, $unixTime) == $paramValue) {
            self::$_ret = true;
        }

        return false;
    }
}
