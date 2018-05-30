<?php
/**
 * Created by PhpStorm.
 * User: Mikkle
 * QQ:776329498
 * Date: 2018/4/6
 * Time: 11:15
 */

namespace mikkle\tp_wxpay\src;


use mikkle\tp_master\Exception;
use mikkle\tp_wxpay\base\Tools;
use mikkle\tp_wxpay\base\WxpayClientBase;

class NativeLink extends WxpayClientBase
{
    public function _initialize()
    {
        $this->params["time_stamp"] = (string) time() ;//终端ip
    }

    protected function checkParams()
    {
        if($this->params["product_id"] == null)
        {
            throw new Exception("缺少Native支付二维码链接必填参数product_id！"."<br>");
        }
    }


    /**
     * 返回链接
     */
    function getUrl()
    {
        $this->createXml();
        $bizString = Tools::formatBizQueryParaMap($this->params, false);
        $this->url = "weixin://wxpay/bizpayurl?".$bizString;
        return $this->url;
    }


}