<?php
/**
 * Created by PhpStorm.
 * User: Mikkle
 * QQ:776329498
 * Date: 2017/06/13
 * Time: 15:14
 */

namespace mikkle\tp_controller;

use think\Controller;

class ControllerBase extends Controller
{
    protected $error;
    public function addError($error){
        $this->error = is_string($error) ? $error : json_encode($error);
    }
    public function getError(){
        return $this->error;
    }

    public function getTimeString($time=""){
        switch (true) {
            case (empty($time)):
                $timeInt=time();
                break;
            // 1513699200 "2017-12-20 00:00:00"  1514736000 "2018-1-1"
            case (is_numeric($time) && (int)$time > 1513699200 ):
                $timeInt = $time;
                break;
            case (is_string($time)):
                $timeInt = strtotime($time);
                if ($timeInt == false) {
                    $timeInt=time();
                }
                break;
            default :
                $timeInt=time();
        }
        return date("Y-m-d H:i:s",(int)$timeInt) ;
    }

    protected function getTimeInt($time=""){
        switch (true) {
            case (empty($time)):
                $timeInt=time();
                break;
            // 1513699200 "2017-12-20 00:00:00"  1514736000 "2018-1-1"
            case (is_numeric($time) && (int)$time > 1514736000 ):
                $timeInt = (int)$time;
                break;
            case (is_string($time)):
                $timeInt = strtotime($time);
                if ($timeInt == false) {
                    $timeInt=time();
                }
                break;
            default :
                $timeInt=time();
        }
        return $timeInt ;
    }



    /*
     * 检查是注重某些值是非为空
     */
    protected function checkArrayValueEmpty($array,$value,$error=true){
        switch (true){
            case (empty($array)||!is_array($array)):
                if ($error==true){
                    $this->addError("要检测的数据不存在或者非数组");
                }
                return false;
                break;
            case (is_array($value)):
                foreach ($value as $item){
                    if (!isset($array[$item]) || (empty($array[$item]) && $array[$item]!==0)){
                        if ($error==true) {
                            $this->addError("要检测的数组数据有不存在键值{$item}");
                        }
                        return false;
                    }
                }
                break;
            case (is_string($value)):
                if (!isset($array[$value]) || empty($array[$value] && $array[$value]!==0)){
                    if ($error==true) {
                        $this->addError("要检测的数组数据有不存在键值{$value}");
                    }
                    return false;
                }
                break;
            default:
        }
        return true;
    }

    protected function showMoney($fen){
        return sprintf("%.2f", ($fen / 100))."元";
    }


}