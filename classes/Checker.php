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
}
