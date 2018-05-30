<?php
/**
 * Created by PhpStorm.
 * Power By Mikkle
 * Email：776329498@qq.com
 * Date: 2017/9/11
 * Time: 14:02
 */

namespace mikkle\tp_wechat\src;


use mikkle\tp_wechat\base\WechatBase;
use mikkle\tp_master\Cache;
use mikkle\tp_wechat\support\Curl;
use mikkle\tp_wechat\support\StaticFunction;

class Card extends WechatBase
{
    /** 卡券相关地址 */
    const CARD_CREATE = '/card/create?';
    // 删除卡卷
    const CARD_DELETE = '/card/delete?';
    // 更新卡卷信息
    const CARD_UPDATE = '/card/update?';
    // 获取卡卷详细信息
    const CARD_GET = '/card/get?';
    // 读取粉丝拥有的卡卷列表
    const CARD_USER_GET_LIST = '/card/user/getcardlist?';
    // 卡卷核查接口
    const CARD_CHECKCODE = '/card/code/checkcode?';
    // 卡卷图文群发获取HTML
    const CARD_SET_SELFCONSUMECELL = '/card/selfconsumecell/set?';
    const CARD_SEND_HTML = '/card/mpnews/gethtml?';
    const CARD_BATCHGET = '/card/batchget?';
    const CARD_MODIFY_STOCK = '/card/modifystock?';
    const CARD_GETCOLORS = '/card/getcolors?';
    const CARD_QRCODE_CREATE = '/card/qrcode/create?';
    const CARD_CODE_CONSUME = '/card/code/consume?';
    const CARD_CODE_DECRYPT = '/card/code/decrypt?';
    const CARD_CODE_GET = '/card/code/get?';
    const CARD_CODE_UPDATE = '/card/code/update?';
    const CARD_CODE_UNAVAILABLE = '/card/code/unavailable?';
    const CARD_TESTWHILELIST_SET = '/card/testwhitelist/set?';
    const CARD_MEETINGCARD_UPDATEUSER = '/card/meetingticket/updateuser?'; //更新会议门票
    const CARD_MEMBERCARD_ACTIVATE = '/card/membercard/activate?';   //激活会员卡
    const CARD_MEMBERCARD_UPDATEUSER = '/card/membercard/updateuser?'; //更新会员卡
    const CARD_MOVIETICKET_UPDATEUSER = '/card/movieticket/updateuser?';   //更新电影票(未加方法)
    const CARD_BOARDINGPASS_CHECKIN = '/card/boardingpass/checkin?';  //飞机票-在线选座(未加方法)
    /** 更新红包金额 */
    const CARD_LUCKYMONEY_UPDATE = '/card/luckymoney/updateuserbalance?';
    /*买单接口*/
    const CARD_PAYCELL_SET = '/card/paycell/set?';
    /*设置开卡字段接口*/
    const CARD_MEMBERCARD_ACTIVATEUSERFORM_SET = '/card/membercard/activateuserform/set?';

    public function  __construct(array $option)
    {
        parent::__construct($option);
        $this->getToken();
    }

    /**
     * 获取微信卡券 api_ticket
     * @param string $appid
     * @param string $jsapi_ticket
     * @return bool|string
     */
    public function getJsCardTicket($appid = '', $jsapi_ticket = '')
    {
        if (!$this->access_token ) {
            return false;
        }
        $appid = empty($appid) ? $this->appId : $appid;
        if ($jsapi_ticket) {
            return $jsapi_ticket;
        }
        $authname = 'mikkle_wechat_jsapi_ticket_wxcard_' . $appid;
        if (($jsapi_ticket = Cache::get($authname))) {
            return $jsapi_ticket;
        }

        $curl_url = self::API_URL_PREFIX . self::GET_TICKET_URL ."access_token={$this->access_token}". '&type=wx_card';
        $result = Curl::curlGet($curl_url);
        if ($result) {
             $json = StaticFunction::parseJSON($result);
            if (!$json || !empty($json['errcode'])) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return $this->checkRetry(__FUNCTION__, func_get_args());
            }
            $expire = $json['expires_in'] ? intval($json['expires_in']) - 100 : 3600;
            Cache::set($authname, $json['ticket'], $expire);
            return $json['ticket'];
        }
        return false;
    }

    /**
     * 生成选择卡卷JS签名包
     * @param string $cardid 卡券Id
     * @param string $cardtype 卡券类型
     * @param string $shopid 门店Id
     * @return array
     */
    public function createChooseCardJsPackage($cardid = null, $cardtype = null, $shopid = null)
    {
        $data = array();
        $data['api_ticket'] = $this->getJsCardTicket();
        $data['app_id'] = $this->appId;
        $data['timestamp'] = time();
        $data['nonceStr'] = StaticFunction::createRandStr();
        if(!empty($cardid)){
            $data['cardId'] = $cardid;
        }
        if(!empty($cardtype)){
            $data['cardType'] = $cardtype;
        }
        if(!empty($shopid)){
            $data['shopId'] = $shopid;
        }
        $data['cardSign'] = $this->getTicketSignature($data);
        $data['signType'] = 'SHA1';
        unset($data['api_ticket'], $data['app_id']);
        return $data;
    }

    /**
     * 生成添加卡卷JS签名包
     * @param string|null $cardid 卡卷ID
     * @param array $data 其它限定参数
     * @return array
     */
    public function createAddCardJsPackage($cardid = null, $data = [])
    {
        $cardList = [];
        if (is_array($cardid)) {
            foreach ($cardid as $id) {
                $cardList[] = ['cardId' => $id, 'cardExt' => json_encode($this->_sign($id, $data, $this))];
            }
        } else {
            $cardList[] = ['cardId' => $cardid, 'cardExt' => json_encode($this->_sign($cardid, $data, $this))];
        }
        return ['cardList' => $cardList];
    }

    protected function _sign($cardid = null, $attr = array())
    {
        unset($attr['outer_id']);
        $attr['cardId'] = $cardid;
        $attr['timestamp'] = time();
        $attr['api_ticket'] = $this->getJsCardTicket();
        $attr['nonce_str'] = StaticFunction::createRandStr();
        $attr['signature'] = $this->getTicketSignature($attr);
        unset($attr['api_ticket']);
        return $attr;
    }

    /**
     * 获取微信卡券签名
     * @param array $arrdata 签名数组
     * @param string $method 签名方法
     * @return bool|string 签名值
     */
    public function getTicketSignature($arrdata, $method = "sha1")
    {
        if (!function_exists($method)) {
            return false;
        }
        $newArray = array();
        foreach ($arrdata as $value) {
            array_push($newArray, (string)$value);
        }
        sort($newArray, SORT_STRING);
        return $method(implode($newArray));
    }

    /**
     * 创建卡券
     * @param array $data 卡券数据
     * @return bool|array 返回数组中card_id为卡券ID
     */
    public function createCard($data)
    {
        if (!$this->access_token || empty($data)) {
            return false;
        }

        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_CREATE ."access_token={$this->access_token}";
        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());

    }

    /**
     * 更改卡券信息
     * 调用该接口更新信息后会重新送审，卡券状态变更为待审核。已被用户领取的卡券会实时更新票面信息。
     * @param string $data
     * @return bool
     */
    public function updateCard($data)
    {
        if (!$this->access_token || empty($data)) {
            return false;
        }

        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_UPDATE ."access_token={$this->access_token}";
        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());

    }

    /**
     * 删除卡券
     * 允许商户删除任意一类卡券。删除卡券后，该卡券对应已生成的领取用二维码、添加到卡包 JS API 均会失效。
     * 注意：删除卡券不能删除已被用户领取，保存在微信客户端中的卡券，已领取的卡券依旧有效。
     * @param string $card_id 卡券ID
     * @return bool
     */
    public function delCard($card_id)
    {
        if (!$this->access_token || empty($card_id)) {
            return false;
        }
        $data = ['card_id' => $card_id];


        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_DELETE ."access_token={$this->access_token}";
        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());

    }

    /**
     * 获取粉丝下所有卡卷列表
     * @param $openid 粉丝openid
     * @param string $card_id 卡卷ID（可不给）
     * @return bool|array
     */
    public function getCardList($openid, $card_id = '')
    {
        if (!$this->access_token || empty($openid)) {
            return false;
        }
        $data = ['openid' => $openid];
        if(!empty($card_id)){
            $data['card_id'] = $card_id;
        }


        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_USER_GET_LIST ."access_token={$this->access_token}";
        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());
    }

    /**
     * 获取图文消息群发卡券HTML
     * @param string $card_id 卡卷ID
     * @return bool|array
     */
    public function getCardMpHtml($card_id)
    {
        if (!$this->access_token || empty($card_id)) {
            return false;
        }
        $data = ['card_id' => $card_id];



        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_SEND_HTML ."access_token={$this->access_token}";
        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());
    }

    /**
     * 卡卷code核查
     * @param string $card_id 卡卷ID
     * @param array $code_list 卡卷code列表（一维数组）
     * @return bool|array
     */
    public function checkCardCodeList($card_id, $code_list)
    {
        if (!$this->access_token || empty($card_id) || empty($code_list) ) {
            return false;
        }
        $data = ['card_id' => $card_id, 'code' => $code_list];

        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_CHECKCODE ."access_token={$this->access_token}";
        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());
    }

    /**
     * 查询卡券详情
     * @param string $card_id 卡卷ID
     * @return bool|array
     */
    public function getCardInfo($card_id)
    {
        if (!$this->access_token || empty($card_id) ) {
            return false;
        }
        $data = ['card_id' => $card_id];

        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_GET ."access_token={$this->access_token}";
        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());
    }

    /**
     * 获取颜色列表
     * 获得卡券的最新颜色列表，用于创建卡券
     * @return bool|array
     */
    public function getCardColors()
    {
        if (!$this->access_token ) {
            return false;
        }

        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_GETCOLORS ."access_token={$this->access_token}";
        return $this->returnGetResult($curl_url,__FUNCTION__, func_get_args());
    }

    /**
     * 生成卡券二维码
     * 成功则直接返回ticket值，可以用 getQRUrl($ticket) 换取二维码url
     * @param string $card_id 卡券ID 必须
     * @param string $code 指定卡券 code 码，只能被领一次。use_custom_code 字段为 true 的卡券必须填写，非自定义 code 不必填写。
     * @param string $openid 指定领取者的 openid，只有该用户能领取。bind_openid 字段为 true 的卡券必须填写，非自定义 openid 不必填写。
     * @param int $expire_seconds 指定二维码的有效时间，范围是 60 ~ 1800 秒。不填默认为永久有效。
     * @param bool $is_unique_code 指定下发二维码，生成的二维码随机分配一个 code，领取后不可再次扫描。填写 true 或 false。默认 false。
     * @param string $balance 红包余额，以分为单位。红包类型必填（LUCKY_MONEY），其他卡券类型不填。
     * @return bool|string
     */
    public function createCardQrcode($card_id, $code = '', $openid = '', $expire_seconds = 0, $is_unique_code = false, $balance = '')
    {
        if (!$this->access_token  || empty($card_id)) {
            return false;
        }

        $card = ['card_id' => $card_id];
        if (!empty($code)){
            $card['code'] = $code;
        }
        if (!empty($openid)){
            $card['openid'] = $openid;
        }
        if (!empty($is_unique_code)){
            $card['is_unique_code'] = $is_unique_code;
        }
        if (!empty($balance)){
            $card['balance'] = $balance;
        }

        $data = ['action_name' => "QR_CARD"];
        if (!empty($expire_seconds)){
            $data['expire_seconds'] = $expire_seconds;
        }
        $data['action_info'] = ['card' => $card];

        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_QRCODE_CREATE ."access_token={$this->access_token}";
        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());
    }

    /**
     * 消耗 code
     * 自定义 code（use_custom_code 为 true）的优惠券，在 code 被核销时，必须调用此接口。
     * @param string $code 要消耗的序列号
     * @param string $card_id 要消耗序列号所述的 card_id，创建卡券时use_custom_code 填写 true 时必填。
     * @return bool|array
     * {
     *  "errcode":0,
     *  "errmsg":"ok",
     *  "card":{"card_id":"pFS7Fjg8kV1IdDz01r4SQwMkuCKc"},
     *  "openid":"oFS7Fjl0WsZ9AMZqrI80nbIq8xrA"
     * }
     */
    public function consumeCardCode($code, $card_id = '')
    {
        if (!$this->access_token  || empty($code)) {
            return false;
        }
        $data = ['code' => $code];
        if(!empty($card_id)){
            $data['card_id'] = $card_id;
        }

        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_CODE_CONSUME ."access_token={$this->access_token}";
        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());
    }

    /**
     * code 解码
     * @param string $encrypt_code 通过 choose_card_info 获取的加密字符串
     * @return bool|array
     * {
     *  "errcode":0,
     *  "errmsg":"ok",
     *  "code":"751234212312"
     *  }
     */
    public function decryptCardCode($encrypt_code)
    {
        if (!$this->access_token || empty($encrypt_code)) {
            return false;
        }
        $data = ['encrypt_code' => $encrypt_code,];

        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_CODE_DECRYPT ."access_token={$this->access_token}";
        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());
    }

    /**
     * 查询 code 的有效性（非自定义 code）
     * @param string $code
     * @return bool|array
     * {
     *  "errcode":0,
     *  "errmsg":"ok",
     *  "openid":"oFS7Fjl0WsZ9AMZqrI80nbIq8xrA",    //用户 openid
     *  "card":{
     *      "card_id":"pFS7Fjg8kV1IdDz01r4SQwMkuCKc",
     *      "begin_time": 1404205036,               //起始使用时间
     *      "end_time": 1404205036,                 //结束时间
     *  }
     * }
     */
    public function checkCardCode($code)
    {
        if (!$this->access_token  || empty($code)) {
            return false;
        }
        $data = ['code' => $code];

        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_CODE_GET ."access_token={$this->access_token}";
        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());
    }

    /**
     * 批量查询卡列表
     * @param int $offset 开始拉取的偏移，默认为0从头开始
     * @param int $count 需要查询的卡片的数量（数量最大50,默认50）
     * @return bool|array
     * {
     *  "errcode":0,
     *  "errmsg":"ok",
     *  "card_id_list":["ph_gmt7cUVrlRk8swPwx7aDyF-pg"],    //卡 id 列表
     *  "total_num":1                                       //该商户名下 card_id 总数
     * }
     */
    public function getCardIdList($offset = 0, $count = 50)
    {
        if (!$this->access_token ) {
            return false;
        }
        if ( $count > 50){
            $count = 50;
        }
        $data = ['offset' => $offset, 'count' => $count];


        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_BATCHGET ."access_token={$this->access_token}";
        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());
    }

    /**
     * 更改 code
     * 为确保转赠后的安全性，微信允许自定义code的商户对已下发的code进行更改。
     * 注：为避免用户疑惑，建议仅在发生转赠行为后（发生转赠后，微信会通过事件推送的方式告知商户被转赠的卡券code）对用户的code进行更改。
     * @param string $code 卡券的 code 编码
     * @param string $card_id 卡券 ID
     * @param string $new_code 新的卡券 code 编码
     * @return bool
     */
    public function updateCardCode($code, $card_id, $new_code)
    {
        if (!$this->access_token || empty($code)|| empty($card_id)|| empty($new_code)) {
            return false;
        }
        $data = ['code' => $code, 'card_id' => $card_id, 'new_code' => $new_code];

        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_CODE_UPDATE ."access_token={$this->access_token}";
        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());
    }

    /**
     * 设置卡券失效
     * 设置卡券失效的操作不可逆
     * @param string $code 需要设置为失效的 code
     * @param string $card_id 自定义 code 的卡券必填。非自定义 code 的卡券不填。
     * @return bool
     */
    public function unavailableCardCode($code, $card_id = '')
    {
        if (!$this->access_token  || empty($code)) {
            return false;
        }
        $data = ['code' => $code];
        if(!empty($card_id)){
            $data['card_id'] = $card_id;
        }

        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_CODE_UNAVAILABLE ."access_token={$this->access_token}";
        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());
    }

    /**
     * 库存修改
     * @param string $data
     * @return bool
     */
    public function modifyCardStock($data)
    {
        if (!$this->access_token  || empty($data) ) {
            return false;
        }

        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_MODIFY_STOCK ."access_token={$this->access_token}";
        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());
    }

    /**
     * 更新门票
     * @param string $data
     * @return bool
     */
    public function updateMeetingCard($data)
    {
        if (!$this->access_token  || empty($data)) {
            return false;
        }

        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_MEETINGCARD_UPDATEUSER ."access_token={$this->access_token}";
        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());
    }

    /**
     * 激活/绑定会员卡
     * @param string $data 具体结构请参看卡券开发文档(6.1.1 激活/绑定会员卡)章节
     * @return bool
     */
    public function activateMemberCard($data)
    {
        if (!$this->access_token || empty($data)) {
            return false;
        }

        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_MEMBERCARD_ACTIVATE ."access_token={$this->access_token}";
        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());
    }

    /**
     * 会员卡交易
     * 会员卡交易后每次积分及余额变更需通过接口通知微信，便于后续消息通知及其他扩展功能。
     * @param string $data 具体结构请参看卡券开发文档(6.1.2 会员卡交易)章节
     * @return bool|array
     */
    public function updateMemberCard($data)
    {
        if (!$this->access_token || empty($data)) {
            return false;
        }

        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_MEMBERCARD_UPDATEUSER ."access_token={$this->access_token}";
        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());
    }

    /**
     * 设置卡券测试白名单
     * @param array $openid 测试的 openid 列表
     * @param array $user 测试的微信号列表
     * @return bool
     */
    public function setCardTestWhiteList($openid = [], $user = [])
    {
        if (!$this->access_token  || (empty($openid) && empty($user))) {
            return false;
        }
        $data = [];
        if ( count($openid) > 0 ){
            $data['openid'] = $openid;
        }
        if ( count($user) > 0 ){
            $data['username'] = $user;
        }

        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_TESTWHILELIST_SET ."access_token={$this->access_token}";

        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());
    }

    /**
     * 更新红包金额
     * @param string $code 红包的序列号
     * @param int $balance 红包余额
     * @param string $card_id 自定义 code 的卡券必填。非自定义 code 可不填。
     * @return bool|array
     */
    public function updateLuckyMoney($code, $balance, $card_id = '')
    {
        if (!$this->access_token  || empty($code)  || empty($balance)) {
            return false;
        }
        $data = ['code' => $code, 'balance' => $balance];
        if ( !empty($card_id) ){
            $data['card_id'] = $card_id;
        }
        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_LUCKYMONEY_UPDATE ."access_token={$this->access_token}";
        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());
    }

    /**
     * 设置自助核销接口
     * @param string $card_id 卡券ID
     * @param bool $is_openid 是否开启自助核销功能，填true/false，默认为false
     * @param bool $need_verify_cod 用户核销时是否需要输入验证码，填true/false，默认为false
     * @param bool $need_remark_amount 用户核销时是否需要备注核销金额，填true/false，默认为false
     * @return bool|array
     */
    public function setSelfconsumecell($card_id, $is_openid = false, $need_verify_cod = false, $need_remark_amount = false)
    {
        if (!$this->access_token  || empty($card_id)) {
            return false;
        }
        $data = [
            'card_id'            => $card_id,
            'is_open'            => $is_openid,
            'need_verify_cod'    => $need_verify_cod,
            'need_remark_amount' => $need_remark_amount,
        ];

        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_SET_SELFCONSUMECELL ."access_token={$this->access_token}";
        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());
    }

    /**
     * 设置买单接口
     * @param string $card_id
     * @param bool $is_openid
     * @return bool|mixed
     */
    public function setPaycell($card_id, $is_openid = true)
    {
        if (!$this->access_token  || empty($card_id)) {
            return false;
        }
        $data = ['card_id' => $card_id, 'is_open' => $is_openid,];


        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_PAYCELL_SET ."access_token={$this->access_token}";
        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());
    }

    /**
     * 设置开卡字段信息接口
     * @param array $data
     * @return bool|array
     */
    public function setMembercardActivateuserform($data)
    {
        if (!$this->access_token  || empty($data)) {
            return false;
        }

        $curl_url = self::API_BASE_URL_PREFIX . self::CARD_MEMBERCARD_ACTIVATEUSERFORM_SET ."access_token={$this->access_token}";
        return $this->returnPostResult($curl_url,$data,__FUNCTION__, func_get_args());
    }


}