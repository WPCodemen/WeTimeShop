<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 * 2015-11-21
 */
namespace app\api\controller;

use app\common\logic\CartLogic;
use app\common\logic\MessageLogic;
use app\common\logic\UsersLogic;
use app\common\logic\OrderLogic;
use app\common\model\UserAddress;
use app\common\util\TpshopException;
use think\Page;
use think\Verify;
use think\Loader;
use think\Db;

class User extends MobileBase
{

    public $user_id = 0;
    public $user = array();

    /*
    * 初始化操作
    */
    public function _initialize()
    {
        parent::_initialize();
        /*if (session('?user')) {
            $session_user = session('user');
            $select_user = M('users')->where("user_id", $session_user['user_id'])->find();
            $oauth_users = M('OauthUsers')->where(['user_id'=>$session_user['user_id']])->find();
            empty($oauth_users) && $oauth_users = [];
            $user =  array_merge($select_user,$oauth_users);
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;
            $this->user_id = $user['user_id'];
            $this->assign('user', $user); //存储用户信息
        }*/
        $nologin = array(
            'login', 'pop_login', 'do_login', 'logout', 'verify', 'set_pwd', 'finished',
            'verifyHandle', 'reg', 'send_sms_reg_code', 'find_pwd', 'check_validate_code',
            'forget_pwd', 'check_captcha', 'check_username', 'send_validate_code', 'express' , 'bind_guide', 'bind_account','bind_reg'
        );
        /*$is_bind_account = tpCache('basic.is_bind_account');
        if (!$this->user_id && !in_array(ACTION_NAME, $nologin)) {
            if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger') && $is_bind_account){
                header("location:" . U('Mobile/User/bind_guide'));//微信浏览器, 调到绑定账号引导页面
            }else{
                header("location:" . U('Mobile/User/login'));
            }
            exit;
        }

        $order_status_coment = array(
            'WAITPAY' => '待付款 ', //订单查询状态 待支付
            'WAITSEND' => '待发货', //订单查询状态 待发货
            'WAITRECEIVE' => '待收货', //订单查询状态 待收货
            'WAITCCOMMENT' => '待评价', //订单查询状态 待评价
        );
        $this->assign('order_status_coment', $order_status_coment);*/
    }

    /*app的接口*/
    /*
    * 密码修改
    */
    public function password()
    {
        $mobile = input('post.mobile');
        $userLogic = new UsersLogic();
        $data_rt = $userLogic->password($this->user_id,$mobile,I('post.old_password'), I('post.new_password'), I('post.confirm_password'));
        /*//修改成功清除短信验证码
        if($data_rt['status'] == 1){
            $redis->rm($mobile);
        }*/
        return json_encode($data_rt);
    }

    /*
     * 用户地址列表
     */
    public function appaddress_list()
    {
        $address_lists = get_user_address_list($this->user_id);
        if(!empty($address_lists)){
            foreach ($address_lists as $k => $v) {
                $address_lists[$k]['provincea'] = Db::name('region')->where('id',$v['province'])->value('name');
                $address_lists[$k]['citya'] = Db::name('region')->where('id',$v['city'])->value('name');
                $address_lists[$k]['districta'] = Db::name('region')->where('id',$v['district'])->value('name');
            }
            return json_encode(array('status'=>1,'msg'=>"获取成功！",'result'=>$address_lists));
        }

        return json_encode(array('status'=>0,'msg'=>"你没有地址！",'result'=>null));
    }

    /*
    *获取省市区三级联动
     */
    public function threeLevel(){
        $first_dir = dirname(dirname(dirname(dirname(__FILE__))));
        $file_name = $first_dir."/runtime/threelevel/threelevel.txt";
        //如果省市区三级联动缓存存在，则直接读取缓存
        if (file_exists($file_name)) {
            $cache_data = file_get_contents($file_name);
            if (!empty($cache_data)){
                    $cache_data = json_decode($cache_data, true);
                if ($cache_data) {
                    //判断是否过期
                    $time = time();
                    if($cache_data['time'] + $cache_data['expire'] > $time){
                        return json_encode(array('status'=>1,"msg"=>"获取成功！",'result'=>$cache_data['data']));
                    }
                }
            }
        }
        //获取省
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $three_level = $two_level = array();
        $i = 0;
        //获取市
        foreach ($p as $k => $v) {
            $three_level[$i] = $v;
            $two_level = M('region')->where(array('parent_id' => $v['id'], 'level' => 2))->select();
            $j = 0;
            $three_level[$i]['subcat'] = $two_level;
            //获取区
            foreach ($three_level[$i]['subcat'] as $kk => $vv) {

                $three_level[$i]['subcat'][$kk]['subcat'] = M('region')->where(array('parent_id' => $vv['id'], 'level' => 3))->select();

            }


            $j++;

            $i++;
        }

        if(!empty($three_level)){

            $cache_data = array('data' => $three_level, 'time' => time(), 'expire' => 804600);
            $cache_data = json_encode($cache_data);
            if (!file_exists($file_name)) {
                mkdir($first_dir."/runtime/threelevel", 0777, true);
            }
            $put_result = file_put_contents($file_name, $cache_data);
            return json_encode(array('status'=>1,"msg"=>"获取成功！",'result'=>$three_level));
        }
        return json_encode(array('status'=>0,"msg"=>"获取失败！",'result'=>''));
    }

    /*
    *在APP上添加用户地址
     */
    public function addAddress(){
        $post_data = input('post.');
        $logic = new UsersLogic();
        $data_rt = $logic->addAddress($this->user_id, 0, $post_data);
        return json_encode($data_rt);
    }

    /*
    *在APP上编辑用户地址
     */
    public function editAddress()
    {
        $address_id = I('address_id/d');
        $address = M('user_address')->where(array('address_id' => $address_id, 'user_id' => $this->user_id))->find();
        $post_data = input('post.');
        $logic = new UsersLogic();
        $data_rt = $logic->addAddress($this->user_id, $address_id, $post_data);
        return json_encode($data_rt);
    }

    /*
     * 设置默认收货地址app版
     */
    public function app_set_default()
    {
        $address_id = I('get.address_id/d');
        //$source = I('get.source');
        M('user_address')->where(array('user_id' => $this->user_id))->save(array('is_default' => 0));
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $address_id))->save(array('is_default' => 1));
        /*if ($source == 'cart2') {
            header("Location:" . U('Mobile/Cart/cart2'));
            exit;
        } else {
            header("Location:" . U('Mobile/User/address_list'));
        }*/
        if($row){
            return json_encode(array('status'=>1,'msg'=>"设置成功！",'result'=>null));
        }
        return json_encode(array('status'=>0,'msg'=>"设置失败！",'result'=>null));
    }

    /*
    * 地址删除app
    */
    public function delAddress()
    {
        $address_id = I('get.address_id/d');

        $address = M('user_address')->where("address_id", $address_id)->find();
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $address_id))->delete();
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if ($address['is_default'] == 1) {
            $address2 = M('user_address')->where("user_id", $this->user_id)->find();
            $address2 && M('user_address')->where("address_id", $address2['address_id'])->save(array('is_default' => 1));
        }
        if (!$row)
            return json_encode(array('status'=>0,'msg'=>"删除失败！",'result'=>null));
        else
            return json_encode(array('status'=>1,'msg'=>"删除成功！",'result'=>null));
    }


    //获取首页
    public function getFirst(){
        //获取轮播图
        $turn_field = "ad_id,ad_link,ad_code";
        $turn_pic = M("ad")->where(array('is_del'=>1,'enabled'=>1))->cache('turn_pic',86400)->field($turn_field)->order('ad_id desc')->select();

        //首页推荐分类
        $category_field = "id as cat_id,mobile_name,desc,image";
        $cat_con = [
            'is_hot' => 1,
            'is_show' => 1,
            'is_del' => 1
        ];
        $first_category = Db::name('goods_category')->where($cat_con)->cache('first_category',86400)->field($category_field)->order('id desc')->select();

        //首页特惠商品
        //获取商品列表
        $field = "goods_id,cat_id,goods_name,shop_price,market_price,original_img";
        $goods_con = [
            'is_recommend' => 1,
            'is_on_sale'   => 1,
            'is_del'       => 1
        ];
        $goods_list = Db::name('goods')->where($goods_con)->cache('first_goods_list',86400)->limit(15)->field($field)->order('goods_id desc')->select();

        $result = array(
            'turn_pic' => $turn_pic,
            'first_category' => $first_category,
            'goods_list' => $goods_list
        );
        if(!empty($result)){
            return json_encode(array('status'=>1,'msg'=>"获取成功！",'result'=>$result));
        }

        return json_encode(array('status'=>0,'msg'=>"暂时没有数据!",'result'=>null));
    }

    //获取用户的信息
    public function myCenter(){
        $field = "head_pic,nickname,mobile,alipay_acc,wx_acc,pay_points";
        $user_info = Db::name('users')->where(['user_id'=>$this->user_id])->field($field)->find();
        if(!empty($user_info)){
            $where = [
                'name' => "exchange_points",
                'inc_type' => "basic"
            ];
            $where_quota = [
                'name' => "quota",
                'inc_type' => "basic"
            ];
            $exchange_money = Db::name('config')->where($where)->field('name,value')->find();
            $quota = Db::name('config')->where($where_quota)->field('name,value')->find();
            $user_info['exchange_money'] = $exchange_money['value'];
            $user_info['quota'] = $quota['value'];
            return json_encode(array('status'=>1,'msg'=>"获取成功！",'result'=>$user_info));
        }
        return json_encode(array('status'=>0,'msg'=>"获取头像失败！",'result'=>null));
    }

    /**
     * 用户收藏列表
     */
    public function collectList()
    {
        $page = input('post.page');
        $page = empty($page) ? 1 : $page;
        $start = ($page-1) * 15;
        $end = $page * 15;
        //获取我的收藏列表
        $result = M('goods_collect')->alias('c')
            ->field('c.collect_id,c.add_time,g.goods_id,g.goods_name,g.shop_price,g.is_on_sale,g.store_count,g.cat_id,g.original_img')
            ->join('goods g','g.goods_id = c.goods_id','INNER')
            ->where("c.user_id = $this->user_id")
            ->limit($start,$end)
            ->select();
        if(!empty($result)){
            return json_encode(array('status'=>1,'msg'=>"获取成功！",'result'=>$result));
        }
        return json_encode(array('status'=>0,'msg'=>"你没有收藏的商品！"));
    }

    /*
     *取消收藏
     */
    public function cancelCollect()
    {
        $goods_id = I('goods_id/d');
        $user_id = $this->user_id;
        if (M('goods_collect')->where(['goods_id' => $goods_id, 'user_id' => $user_id])->delete()) {
            return json_encode(array('status'=>1,'msg'=>"取消收藏成功！"));
        } else {
            return json_encode(array('status'=>0,'msg'=>"取消收藏失败！"));
            //$this->error("取消收藏失败", U('User/collect_list'));
        }
    }

    //用户反馈留言
    public function leaveWord(){
        $user_id = $this->user_id;
        $content = I('post.content');
        $user = M('users')->where('user_id',$user_id)->find();

        $save_data = array('user_id'=>$user_id,'user_name'=>$user['nickname'],'content'=>$content,'create_time'=>time());
        $ret = M('leave_word')->add($save_data);
        if($ret){
            return json_encode(array('status'=>1,'msg'=>"提交成功",'result'=>null));
        }
        return json_encode(array('status'=>0,'msg'=>"提交失败！",'result'=>null));
    }

    /*
     *用户申请积分体现
     */
    public function withdrawPoints(){
        $user = Db::name('users')->where(["user_id"=>$this->user_id])->find();
        $points = input('post.points');
        $quota = Db::name('config')->where(["name"=>'quota',"inc_type"=>"basic"])->value('value');
        if($points < $quota){
            return json_encode(array('status'=>0,'msg'=>"积分必须满".$quota."才可体现！"));
        }
        if($points > $user['pay_points']){
            return json_encode(array('status'=>0,'msg'=>"你的积分不足！"));
        }
        $type = input('post.type');
        if($type == 'alipay'){
            $acc = $user['alipay_acc'];
            if(empty($acc)){
                return json_encode(array('status'=>0,'msg'=>"请填写支付宝账号"));
            }
        }else{
            $acc = $user['wx_acc'];
            if(empty($acc)){
                return json_encode(array('status'=>0,'msg'=>"请填写微信账号"));
            }
        }

        $exchange_points = Db::name('config')->where(["name"=>'exchange_points',"inc_type"=>"basic"])->value('value');

        $money = $points * 0.01 * $exchange_points;
        $ins_arr = [
            'user_id' => $this->user_id,
            'user_name' => $user['nickname'],
            'type' => $type,
            'account' => $acc,
            'points' => $points,
            'rmb_money' => $money,
            'status' => 0,
            'create_time' => time()
        ];
        $ret = Db::name('withdraw_points')->insert($ins_arr);
        if($ret){
            return json_encode(array('status'=>1,'msg'=>"申请体现成功！"));
        }
        return json_encode(array('status'=>0,'msg'=>"申请体现失败！"));

    }

    //积分体现审核中
    public function getWdPoints(){
        $wp_id = input('post.wp_id');
        $verify_list = Db::name('withdraw_points')->where(['wp_id'=>$wp_id])->find();
        if(!empty($verify_list)){
            return json_encode(array('status'=>1,'msg'=>"获取成功！",'result'=>$verify_list));
        }
        return json_encode(array('status'=>0,'msg'=>"没有正在审核的体现！",'result'=>null));
    }
    //获取积分体现的纪录
    public function getPointsRecord(){
        $record_list = Db::name('withdraw_points')->where(['user_id'=>$this->user_id])
            ->order('wp_id DESC')
            ->select();
        if(!empty($record_list)){
            return json_encode(array('status'=>1,'msg'=>"获取成功！",'result'=>$record_list));
        }
        return json_encode(array('status'=>0,'msg'=>"你没有已完成体现记录！",'result'=>null));
    }

    //修改用户个人信息
    public function editUserCenter(){
        $user_id = $this->user_id;
        $type = input('post.type');
        if($type == 'head_pic'){
            $head_pic = I('post.head_pic');
            $head_pic = uploadImage($head_pic,'users');
            $ret = M('users')->where(array('user_id'=>$user_id))->save(array('head_pic'=>$head_pic));
        } elseif($type == 'nickname'){
            $nickname = input('post.nickname');
            $count = Db::name('users')->where(['nickname'=>$nickname])->count();
            if($count >= 1){
                return json_encode(array('status'=>0,'msg'=>"该昵称已经存在！",'result'=>null));
            }
            $ret = M('users')->where(array('user_id'=>$user_id))->save(array('nickname'=>$nickname));
        } elseif($type == 'mobile'){
            $mobile = input('post.mobile');
            $ckm = check_mobile($mobile);
            if(!$ckm){
                return json_encode(array('status'=>0,'msg'=>"手机号格式不正确！",'result'=>null));
            }
            $count = Db::name('users')->where(['mobile'=>$mobile])->count();
            if($count >= 1){
                return json_encode(array('status'=>0,'msg'=>"该手机号已经存在！",'result'=>null));
            }
            $ret = M('users')->where(array('user_id'=>$user_id))->save(array('mobile'=>$mobile));
        } elseif($type == 'alipay_acc'){
            $alipay_acc = input('post.alipay_acc');
            $count = Db::name('users')->where(['alipay_acc'=>$alipay_acc])->count();
            if($count >= 1){
                return json_encode(array('status'=>0,'msg'=>"该支付宝账号已经存在！",'result'=>null));
            }
            $ret = M('users')->where(array('user_id'=>$user_id))->save(array('alipay_acc'=>$alipay_acc));
        } elseif($type == 'wx_acc'){
            $wx_acc = input('post.wx_acc');
            $count = Db::name('users')->where(['wx_acc'=>$wx_acc])->count();
            if($count >= 1){
                return json_encode(array('status'=>0,'msg'=>"该微信账号已经存在！",'result'=>null));
            }
            $ret = M('users')->where(array('user_id'=>$user_id))->save(array('wx_acc'=>$wx_acc));
        }else{
            return json_encode(array('status'=>0,'msg'=>"请上传修改内容！",'result'=>null));
        }

        if($ret){
            return json_encode(array('status'=>1,'msg'=>"操作成功！",'result'=>null));
        }
        return json_encode(array('status'=>0,'msg'=>"内容未做修改！",'result'=>null));
    }

    //修改用户的个人头像
    public function editProtrait(){
        $user_id = $this->user_id;
        $head_pic = I('post.head_pic');
        $head_pic = uploadImage($head_pic,'users');
        $ret = M('users')->where(array('user_id'=>$user_id))->save(array('head_pic'=>$head_pic));
        if($ret){
            return json_encode(array('status'=>1,'msg'=>"操作成功！",'result'=>null));
        }
        return json_encode(array('status'=>0,'msg'=>"内容未做修改！",'result'=>null));
    }

    //修改个人中心
    public function editCenter(){

        $where = [
            'user_id' => $this->user_id,
        ];
        $nickname =  input('post.nickname');
        $mobile = input('post.mobile');
        $alipay_acc = input('post.alipay_acc');
        $wx_acc = input('post.wx_acc');
        $up_data = array();
        if($nickname){
            $count = Db::name('users')->where(['nickname'=>$nickname])->count();
            if($count >= 1){
                return json_encode(array('status'=>0,'msg'=>"该昵称已经存在！",'result'=>null));
            }
            $up_data['nickname'] = $nickname;
        }
        if($mobile){
            $ckm = check_mobile($mobile);
            if(!$ckm){
                return json_encode(array('status'=>0,'msg'=>"手机号格式不正确！",'result'=>null));
            }
            $count = Db::name('users')->where(['mobile'=>$mobile])->count();
            if($count >= 1){
                return json_encode(array('status'=>0,'msg'=>"该手机号已经存在！",'result'=>null));
            }
            $up_data['mobile'] = $mobile;
        }
        if($alipay_acc){
            $count = Db::name('users')->where(['alipay_acc'=>$alipay_acc])->count();
            if($count >= 1){
                return json_encode(array('status'=>0,'msg'=>"该支付宝账号已经存在！",'result'=>null));
            }
            $up_data['alipay_acc'] = $alipay_acc;
        }
        if($wx_acc){
            $count = Db::name('users')->where(['wx_acc'=>$wx_acc])->count();
            if($count >= 1){
                return json_encode(array('status'=>0,'msg'=>"该微信账号已经存在！",'result'=>null));
            }
            $up_data['wx_acc'] = $wx_acc;
        }

        if(empty($up_data)){
            return json_encode(array('status'=>0,'msg'=>"内容未做修改！",'result'=>null));
        }

        $ret = M('users')->where($where)->save($up_data);
        if($ret){
            return json_encode(array('status'=>1,'msg'=>"操作成功！",'result'=>null));
        }
        return json_encode(array('status'=>0,'msg'=>"内容未做修改！",'result'=>null));
    }


    public function getAppQr(){
        $qrcode = "/public/upload/qr_code/app.png";
        return json_encode(array('status'=>1,'msg'=>"操作成功！",'result'=>$qrcode));
    }
















    /*app的接口*/




    /*
     * 用户中心首页
     */
    public function index()
    {
        $user_id =$this->user_id;
        $logic = new UsersLogic();
        $user = $logic->get_info($user_id); //当前登录用户信息
        $comment_count = M('comment')->where("user_id", $user_id)->count();   // 我的评论数
        $level_name = M('user_level')->where("level_id", $this->user['level'])->getField('level_name'); // 等级名称
        //获取用户信息的数量
        $messageLogic = new MessageLogic();
        $user_message_count = $messageLogic->getUserMessageCount();
        $this->assign('user_message_count', $user_message_count);
        $this->assign('level_name', $level_name);
        $this->assign('comment_count', $comment_count);
        $this->assign('user',$user['result']);
        return $this->fetch();
    }


    public function logout()
    {
        session_unset();
        session_destroy();
        setcookie('uname','',time()-3600,'/');
        setcookie('cn','',time()-3600,'/');
        setcookie('user_id','',time()-3600,'/');
        setcookie('PHPSESSID','',time()-3600,'/');
        //$this->success("退出成功",U('Mobile/Index/index'));
        header("Location:" . U('Mobile/Index/index'));
        exit();
    }

    /*
     * 账户资金
     */
    public function account()
    {
        $user = session('user');
        //获取账户资金记录
        $logic = new UsersLogic();
        $data = $logic->get_account_log($this->user_id, I('get.type'));
        $account_log = $data['result'];

        $this->assign('user', $user);
        $this->assign('account_log', $account_log);
        $this->assign('page', $data['show']);

        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_account_list');
            exit;
        }
        return $this->fetch();
    }

    public function account_list()
    {
    	$type = I('type','all');
    	$usersLogic = new UsersLogic;
    	$result = $usersLogic->account($this->user_id, $type);
    
    	$this->assign('type', $type);
    	$this->assign('account_log', $result['account_log']);
    	if ($_GET['is_ajax']) {
    		return $this->fetch('ajax_account_list');
    	}
    	return $this->fetch();
    }

    public function account_detail(){
        $log_id = I('log_id/d',0);
        $detail = Db::name('account_log')->where(['log_id'=>$log_id])->find();
        $this->assign('detail',$detail);
        return $this->fetch();
    }
    
    /**
     * 优惠券
     */
    public function coupon()
    {
        $logic = new UsersLogic();
        $data = $logic->get_coupon($this->user_id, input('type'));
        foreach($data['result'] as $k =>$v){
            $user_type = $v['use_type'];
            $data['result'][$k]['use_scope'] = C('COUPON_USER_TYPE')["$user_type"];
            if($user_type==1){ //指定商品
                $data['result'][$k]['goods_id'] = M('goods_coupon')->field('goods_id')->where(['coupon_id'=>$v['cid']])->getField('goods_id');
            }
            if($user_type==2){ //指定分类
                $data['result'][$k]['category_id'] = Db::name('goods_coupon')->where(['coupon_id'=>$v['cid']])->getField('goods_category_id');
            }
        }
        $coupon_list = $data['result'];
        $this->assign('coupon_list', $coupon_list);
        $this->assign('page', $data['show']);
        if (input('is_ajax')) {
            return $this->fetch('ajax_coupon_list');
            exit;
        }
        return $this->fetch();
    }

    /**
     *  登录
     */
    public function login()
    {
        if ($this->user_id > 0) {
//            header("Location: " . U('Mobile/User/index'));
            $this->redirect('Mobile/User/index');
        }
        $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("Mobile/User/index");
        $this->assign('referurl', $referurl);
        return $this->fetch();
    }

    /**
     * 登录
     */
    public function do_login()
    {
        $username = trim(I('post.username'));
        $password = trim(I('post.password'));
        //验证码验证
        if (isset($_POST['verify_code'])) {
            $verify_code = I('post.verify_code');
            $verify = new Verify();
            if (!$verify->check($verify_code, 'user_login')) {
                $res = array('status' => 0, 'msg' => '验证码错误');
                exit(json_encode($res));
            }
        }
        $logic = new UsersLogic();
        $res = $logic->login($username, $password);
        if ($res['status'] == 1) {
            $res['url'] = htmlspecialchars_decode(I('post.referurl'));
            session('user', $res['result']);
            setcookie('user_id', $res['result']['user_id'], null, '/');
            setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
            $nickname = empty($res['result']['nickname']) ? $username : $res['result']['nickname'];
            setcookie('uname', urlencode($nickname), null, '/');
            setcookie('cn', 0, time() - 3600, '/');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($res['result']['user_id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
            $orderLogic = new OrderLogic();
            $orderLogic->setUserId($res['result']['user_id']);//登录后将超时未支付订单给取消掉
            $orderLogic->abolishOrder();
        }
        exit(json_encode($res));
    }

    /**
     *  注册
     */
    public function reg()
    {

        if($this->user_id > 0) {
            $this->redirect(U('Mobile/User/index'));
        }
        $reg_sms_enable = tpCache('sms.regis_sms_enable');
        $reg_smtp_enable = tpCache('sms.regis_smtp_enable');

        if (IS_POST) {
            $logic = new UsersLogic();
            //验证码检验
            //$this->verifyHandle('user_reg');
            $nickname = I('post.nickname', '');
            $username = I('post.username', '');
            $password = I('post.password', '');
            $password2 = I('post.password2', '');
            $is_bind_account = tpCache('basic.is_bind_account');
            //是否开启注册验证码机制
            $code = I('post.mobile_code', '');
            $scene = I('post.scene', 1);
            
            $session_id = session_id();

            //是否开启注册验证码机制
            if(check_mobile($username)){
                if($reg_sms_enable){
                    //手机功能没关闭
                    $check_code = $logic->check_validate_code($code, $username, 'phone', $session_id, $scene);
                    if($check_code['status'] != 1){
                        $this->ajaxReturn($check_code);
                    }
                }
            }
            //是否开启注册邮箱验证码机制
            if(check_email($username)){
                if($reg_smtp_enable){
                    //邮件功能未关闭
                    $check_code = $logic->check_validate_code($code, $username);
                    if($check_code['status'] != 1){
                        $this->ajaxReturn($check_code);
                    }
                }
            }
            
            $invite = I('invite');
            if(!empty($invite)){
                $invite = get_user_info($invite,2);//根据手机号查找邀请人
                if(empty($invite)){
                    $this->ajaxReturn(['status'=>-1,'msg'=>'推荐人不存在','result'=>null]);
                }
            }else{
                $invite = array();
            }
            if($is_bind_account && session("third_oauth")){ //绑定第三方账号
                $thirdUser = session("third_oauth");
                $head_pic = $thirdUser['head_pic'];
                $data = $logic->reg($username, $password, $password2, 0, $invite ,$nickname , $head_pic);
                //用户注册成功后, 绑定第三方账号
                $userLogic = new UsersLogic();
                $data = $userLogic->oauth_bind_new($data['result']);
            }else{
                $data = $logic->reg($username, $password, $password2,0,$invite);
            }
             
            
            if ($data['status'] != 1) $this->ajaxReturn($data);
            
            //获取公众号openid,并保持到session的user中
            $oauth_users = M('OauthUsers')->where(['user_id'=>$data['result']['user_id'] , 'oauth'=>'weixin' , 'oauth_child'=>'mp'])->find();
            $oauth_users && $data['result']['open_id'] = $oauth_users['open_id'];
            
            session('user', $data['result']);
            setcookie('user_id', $data['result']['user_id'], null, '/');
            setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($data['result']['user_id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
            $this->ajaxReturn($data);
            exit;
        }
        $this->assign('regis_sms_enable',$reg_sms_enable); // 注册启用短信：
        $this->assign('regis_smtp_enable',$reg_smtp_enable); // 注册启用邮箱：
        $sms_time_out = tpCache('sms.sms_time_out')>0 ? tpCache('sms.sms_time_out') : 120;
        $this->assign('sms_time_out', $sms_time_out); // 手机短信超时时间
        return $this->fetch();
    }

    public function bind_guide(){
        $data = session('third_oauth');
        //没有第三方登录的话就跳到登录页
        if(empty($data)){
            $this->redirect('User/login');
        }
        $this->assign("nickname", $data['nickname']);
        $this->assign("oauth", $data['oauth']);
        $this->assign("head_pic", $data['head_pic']);
        return $this->fetch();
    }

    /**
     * 绑定已有账号
     * @return \think\mixed
     */
    public function bind_account()
    {
        $mobile = input('mobile/s');
        $verify_code = input('verify_code/s');
        //发送短信验证码
        $logic = new UsersLogic();
        $check_code = $logic->check_validate_code($verify_code, $mobile, 'phone', session_id(), 1);
        if($check_code['status'] != 1){
            $this->ajaxReturn(['status'=>0,'msg'=>$check_code['msg'],'result'=>null]);
        }
        if(empty($mobile) || !check_mobile($mobile)){
            $this->ajaxReturn(['status' => 0, 'msg' => '手机格式错误']);
        }
        $users = Db::name('users')->where('mobile',$mobile)->find();
        if (empty($users)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '账号不存在']);
        }
        $user = new \app\common\logic\User();
        $user->setUserById($users['user_id']);
        $cartLogic = new CartLogic();
        try{
            $user->checkOauthBind();
            $user->oauthBind();
            $user->doLeader();
            $user->refreshCookie();
            $cartLogic->setUserId($users['user_id']);
            $cartLogic->doUserLoginHandle();
            $orderLogic = new OrderLogic();//登录后将超时未支付订单给取消掉
            $orderLogic->setUserId($users['user_id']);
            $orderLogic->abolishOrder();
            $this->ajaxReturn(['status' => 1, 'msg' => '绑定成功']);
        }catch (TpshopException $t){
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
        }
    }
    /**
     * 先注册再绑定账号
     * @return \think\mixed
     */
    public function bind_reg()
    {
        $mobile = input('mobile/s');
        $verify_code = input('verify_code/s');
        $password = input('password/s');
        $nickname = input('nickname/s', '');
        if(empty($mobile) || !check_mobile($mobile)){
            $this->ajaxReturn(['status' => 0, 'msg' => '手机格式错误']);
        }
        if(empty($password)){
            $this->ajaxReturn(['status' => 0, 'msg' => '请输入密码']);
        }
        $logic = new UsersLogic();
        $check_code = $logic->check_validate_code($verify_code, $mobile, 'phone', session_id(), 1);
        if($check_code['status'] != 1){
            $this->ajaxReturn(['status'=>0,'msg'=>$check_code['msg'],'result'=>null]);
        }
        $thirdUser = session('third_oauth');
        $data = $logic->reg($mobile, $password, $password, 0, [], $nickname, $thirdUser['head_pic']);
        if ($data['status'] != 1) {
            $this->ajaxReturn(['status'=>0,'msg'=>$data['msg'],'result'=>null]);
        }
        $user = new \app\common\logic\User();
        $user->setUserById($data['result']['user_id']);
        try{
            $user->checkOauthBind();
            $user->oauthBind();
            $user->refreshCookie();
            $this->ajaxReturn(['status' => 1, 'msg' => '绑定成功']);
        }catch (TpshopException $t){
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
        }
    }

    public function ajaxAddressList()
    {
        $UserAddress = new UserAddress();
        $address_list = $UserAddress->where('user_id', $this->user_id)->order('is_default desc')->select();
        if($address_list){
            $address_list = collection($address_list)->append(['address_area'])->toArray();
        }else{
            $address_list = [];
        }
        $this->ajaxReturn($address_list);
    }

    /*
     * 用户地址列表
     */
    public function address_list()
    {
        $address_lists = get_user_address_list($this->user_id);
        $region_list = get_region_list();
        $this->assign('region_list', $region_list);
        $this->assign('lists', $address_lists);
        return $this->fetch();
    }

    /**
     * 保存地址
     */
    public function addressSave()
    {
        $address_id = input('address_id/d',0);
        $data = input('post.');
        $userAddressValidate = Loader::validate('UserAddress');
        if (!$userAddressValidate->batch()->check($data)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '操作失败', 'result' => $userAddressValidate->getError()]);
        }
        if (!empty($address_id)) {
            //编辑
            $userAddress = UserAddress::get(['address_id'=>$address_id,'user_id'=> $this->user_id]);
            if(empty($userAddress)){
                $this->ajaxReturn(['status' => 0, 'msg' => '参数错误']);
            }
        } else {
            //新增
            $userAddress = new UserAddress();
            $user_address_count = Db::name('user_address')->where("user_id", $this->user_id)->count();
            if ($user_address_count >= 20) {
                $this->ajaxReturn(['status' => 0, 'msg' => '最多只能添加20个收货地址']);
            }
            $data['user_id'] = $this->user_id;
        }
        $userAddress->data($data);
        $userAddress['longitude'] = true;
        $userAddress['latitude'] = true;
        $row = $userAddress->save();
        if ($row !== false) {
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'result'=>['address_id'=>$userAddress->address_id]]);
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => '操作失败']);
        }
    }

    /*
     * 添加地址
     */
    public function add_address()
    {
        $source = input('source');
        if (IS_POST) {
            $post_data = input('post.');
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id, 0, $post_data);
            $goods_id = input('goods_id/d');
            $item_id = input('item_id/d');
            $goods_num = input('goods_num/d');
            $order_id = input('order_id/d');
            $action = input('action');
            if ($data['status'] != 1){
                $this->error($data['msg']);
            } elseif ($source == 'cart2') {
                $data['url']=U('/Mobile/Cart/cart2', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id,'action'=>$action));
                $this->ajaxReturn($data);
            } elseif ($_POST['source'] == 'integral') {
                $data['url']=U('/Mobile/Cart/integral', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id));
                $this->ajaxReturn($data);
            } elseif($source == 'pre_sell_cart'){
                $data['url']=U('/Mobile/Cart/pre_sell_cart', array('address_id' => $data['result'],'act_id'=>$post_data['act_id'],'goods_num'=>$post_data['goods_num']));
                $this->ajaxReturn($data);
            } elseif($_POST['source'] == 'team'){
                $data['url']= U('/Mobile/Team/order', array('address_id' => $data['result'],'order_id'=>$order_id));
                $this->ajaxReturn($data);
            } elseif ($_POST['source'] == 'pre_sell') {
                $prom_id = input('prom_id/d');
                $data['url'] = U('/Mobile/Cart/pre_sell', array('address_id' => $data['result'],'goods_num' => $goods_num,'prom_id' => $prom_id));
                $this->ajaxReturn($data);
            } else {
                $data['url']= U('/Mobile/User/address_list');
                $this->ajaxReturn($data);
            } 
            
        }
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $this->assign('province', $p);
        $this->assign('source', $source);
        return $this->fetch();

    }

    /*
     * 地址编辑
     */
    public function edit_address()
    {
        $id = I('id/d');
        $address = M('user_address')->where(array('address_id' => $id, 'user_id' => $this->user_id))->find();
        if (IS_POST) {
            $post_data = input('post.');
            $source = $post_data['source'];
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id, $id, $post_data);
            if ($source == 'cart2') {
                $data['url']=U('/Mobile/Cart/cart2', array('address_id' => $data['result'],'goods_id'=>$post_data['goods_id'],'goods_num'=>$post_data['goods_num'],'item_id'=>$post_data['item_id'],'action'=>$post_data['action']));
                $this->ajaxReturn($data);
            } elseif ($source == 'integral') {
                $data['url'] = U('/Mobile/Cart/integral', array('address_id' => $data['result'],'goods_id'=>$post_data['goods_id'],'goods_num'=>$post_data['goods_num'],'item_id'=>$post_data['item_id']));
                $this->ajaxReturn($data);
            } elseif($source == 'pre_sell_cart'){
                $data['url'] = U('/Mobile/Cart/pre_sell_cart', array('address_id' => $data['result'],'act_id'=>$post_data['act_id'],'goods_num'=>$post_data['goods_num']));
                $this->ajaxReturn($data);
            } elseif($source == 'team'){
                $data['url']= U('/Mobile/Team/order', array('address_id' => $data['result'],'order_id'=>$post_data['order_id']));
                $this->ajaxReturn($data);
            } elseif ($_POST['source'] == 'pre_sell') {
                $prom_id = input('prom_id/d');
                $data['url'] = U('/Mobile/Cart/pre_sell', array('address_id' => $data['result'],'goods_num' => $goods_num,'prom_id' => $prom_id));
                $this->ajaxReturn($data);
            } else {
                $data['url']= U('/Mobile/User/address_list');
                $this->ajaxReturn($data);
            }
        }
        //获取省份
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $c = M('region')->where(array('parent_id' => $address['province'], 'level' => 2))->select();
        $d = M('region')->where(array('parent_id' => $address['city'], 'level' => 3))->select();
        if ($address['twon']) {
            $e = M('region')->where(array('parent_id' => $address['district'], 'level' => 4))->select();
            $this->assign('twon', $e);
        }
        $this->assign('province', $p);
        $this->assign('city', $c);
        $this->assign('district', $d);
        $this->assign('address', $address);
        return $this->fetch();
    }

    /*
     * 设置默认收货地址
     */
    public function set_default()
    {
        $id = I('get.id/d');
        $source = I('get.source');
        M('user_address')->where(array('user_id' => $this->user_id))->save(array('is_default' => 0));
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->save(array('is_default' => 1));
        if ($source == 'cart2') {
            header("Location:" . U('Mobile/Cart/cart2'));
            exit;
        } else {
            header("Location:" . U('Mobile/User/address_list'));
        }
    }

    /*
     * 地址删除
     */
    public function del_address()
    {
        $id = I('get.id/d');

        $address = M('user_address')->where("address_id", $id)->find();
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->delete();
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if ($address['is_default'] == 1) {
            $address2 = M('user_address')->where("user_id", $this->user_id)->find();
            $address2 && M('user_address')->where("address_id", $address2['address_id'])->save(array('is_default' => 1));
        }
        if (!$row)
            $this->error('操作失败', U('User/address_list'));
        else
            $this->success("操作成功", U('User/address_list'));
    }


    /*
     * 个人信息
     */
    public function userinfo()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        if (IS_POST) {
        	if ($_FILES['head_pic']['tmp_name']) {
        		$file = $this->request->file('head_pic');
                $image_upload_limit_size = config('image_upload_limit_size');
        		$validate = ['size'=>$image_upload_limit_size,'ext'=>'jpg,png,gif,jpeg'];
        		$dir = UPLOAD_PATH.'head_pic/';
        		if (!($_exists = file_exists($dir))){
        			$isMk = mkdir($dir);
        		}
        		$parentDir = date('Ymd');
        		$info = $file->validate($validate)->move($dir, true);
        		if($info){
        			$post['head_pic'] = '/'.$dir.$parentDir.'/'.$info->getFilename();
        		}else{
        			$this->error($file->getError());//上传错误提示错误信息
        		}
        	}
            I('post.nickname') ? $post['nickname'] = I('post.nickname') : false; //昵称
            I('post.qq') ? $post['qq'] = I('post.qq') : false;  //QQ号码
            I('post.head_pic') ? $post['head_pic'] = I('post.head_pic') : false; //头像地址
            I('post.sex') ? $post['sex'] = I('post.sex') : $post['sex'] = 0;  // 性别
            I('post.birthday') ? $post['birthday'] = strtotime(I('post.birthday')) : false;  // 生日
            I('post.province') ? $post['province'] = I('post.province') : false;  //省份
            I('post.city') ? $post['city'] = I('post.city') : false;  // 城市
            I('post.district') ? $post['district'] = I('post.district') : false;  //地区
            I('post.email') ? $post['email'] = I('post.email') : false; //邮箱
            I('post.mobile') ? $post['mobile'] = I('post.mobile') : false; //手机

            $email = I('post.email');
            $mobile = I('post.mobile');
            $code = I('post.mobile_code', '');
            $scene = I('post.scene', 6);

            if (!empty($email)) {
                $c = M('users')->where(['email' => input('post.email'), 'user_id' => ['<>', $this->user_id]])->count();
                $c && $this->error("邮箱已被使用");
            }
            if (!empty($mobile)) {
                $c = M('users')->where(['mobile' => input('post.mobile'), 'user_id' => ['<>', $this->user_id]])->count();
                $c && $this->error("手机已被使用");
                if (!$code)
                    $this->error('请输入验证码');
                $check_code = $userLogic->check_validate_code($code, $mobile, 'phone', $this->session_id, $scene);
                if ($check_code['status'] != 1)
                    $this->error($check_code['msg']);
            }

            if (!$userLogic->update_info($this->user_id, $post))
                $this->error("保存失败");
            setcookie('uname',urlencode($post['nickname']),null,'/');
            $this->success("操作成功",U('User/userinfo'));
            exit;
        }
        //  获取省份
        $province = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        //  获取订单城市
        $city = M('region')->where(array('parent_id' => $user_info['province'], 'level' => 2))->select();
        //  获取订单地区
        $area = M('region')->where(array('parent_id' => $user_info['city'], 'level' => 3))->select();
        $this->assign('province', $province);
        $this->assign('city', $city);
        $this->assign('area', $area);
        $this->assign('user', $user_info);
        $this->assign('sex', C('SEX'));
        //从哪个修改用户信息页面进来，
        $dispaly = I('action');
        if ($dispaly != '') {
            return $this->fetch("$dispaly");
        }
        return $this->fetch();
    }

    /**
     * 修改绑定手机
     * @return mixed
     */
    public function setMobile(){
        $userLogic = new UsersLogic();
        if (IS_POST) {
            $mobile = input('mobile');
            $mobile_code = input('mobile_code');
            $scene = input('post.scene', 6);
            $validate = I('validate',0);
            $status = I('status',0);
            $c = Db::name('users')->where(['mobile' => $mobile, 'user_id' => ['<>', $this->user_id]])->count();
            $c && $this->error('手机已被使用');
            if (!$mobile_code)
                $this->error('请输入验证码');
            $check_code = $userLogic->check_validate_code($mobile_code, $mobile, 'phone', $this->session_id, $scene);
            if($check_code['status'] !=1){
                $this->error($check_code['msg']);
            }
            if($validate == 1 & $status == 0){
                $res = Db::name('users')->where(['user_id' => $this->user_id])->update(['mobile'=>$mobile]);
                if($res){
                    $source = I('source');
                    !empty($source) && $this->success('绑定成功', U("User/$source"));
                    $this->success('修改成功',U('User/userinfo'));
                }
                $this->error('修改失败');
            }
        }
        $this->assign('status',$status);
        return $this->fetch();
    }

    /*
     * 邮箱验证
     */
    public function email_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['email_validated'] == 0)
            $step = 2;
        //原邮箱验证是否通过
        if ($user_info['email_validated'] == 1 && session('email_step1') == 1)
            $step = 2;
        if ($user_info['email_validated'] == 1 && session('email_step1') != 1)
            $step = 1;
        if (IS_POST) {
            $email = I('post.email');
            $code = I('post.code');
            $info = session('email_code');
            if (!$info)
                $this->error('非法操作');
            if ($info['email'] == $email || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('email_code', null);
                    session('email_step1', null);
                    if (!$userLogic->update_email_mobile($email, $this->user_id))
                        $this->error('邮箱已存在');
                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('email_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/email_validate', array('step' => 2)));
                }
                exit;
            }
            $this->error('验证码邮箱不匹配');
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    /*
    * 手机验证
    */
    public function mobile_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['mobile_validated'] == 0)
            $step = 2;
        //原手机验证是否通过
        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') == 1)
            $step = 2;
        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') != 1)
            $step = 1;
        if (IS_POST) {
            $mobile = I('post.mobile');
            $code = I('post.code');
            $info = session('mobile_code');
            if (!$info)
                $this->error('非法操作');
            if ($info['email'] == $mobile || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('mobile_code', null);
                    session('mobile_step1', null);
                    if (!$userLogic->update_email_mobile($mobile, $this->user_id, 2))
                        $this->error('手机已存在');
                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('mobile_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/mobile_validate', array('step' => 2)));
                }
                exit;
            }
            $this->error('验证码手机不匹配');
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    /**
     * 用户收藏列表
     */
    public function collect_list()
    {
        $userLogic = new UsersLogic();
        $data = $userLogic->get_goods_collect($this->user_id);
        $this->assign('page', $data['show']);// 赋值分页输出
        $this->assign('goods_list', $data['result']);
        if (IS_AJAX) {      //ajax加载更多
            return $this->fetch('ajax_collect_list');
            exit;
        }
        return $this->fetch();
    }

    /*
     *取消收藏
     */
    public function cancel_collect()
    {
        $collect_id = I('collect_id/d');
        $user_id = $this->user_id;
        if (M('goods_collect')->where(['collect_id' => $collect_id, 'user_id' => $user_id])->delete()) {
            $this->success("取消收藏成功", U('User/collect_list'));
        } else {
            $this->error("取消收藏失败", U('User/collect_list'));
        }
    }

    /**
     * 我的留言
     */
    public function message_list()
    {
        C('TOKEN_ON', true);
        if (IS_POST) {
            if(!$this->verifyHandle('message')){
                $this->error('验证码错误', U('User/message_list'));
            };

            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $user = session('user');
            $data['user_name'] = $user['nickname'];
            $data['msg_time'] = time();
            if (M('feedback')->add($data)) {
                $this->success("留言成功", U('User/message_list'));
                exit;
            } else {
                $this->error('留言失败', U('User/message_list'));
                exit;
            }
        }
        $msg_type = array(0 => '留言', 1 => '投诉', 2 => '询问', 3 => '售后', 4 => '求购');
        $count = M('feedback')->where("user_id", $this->user_id)->count();
        $Page = new Page($count, 100);
        $Page->rollPage = 2;
        $message = M('feedback')->where("user_id", $this->user_id)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $showpage = $Page->show();
        header("Content-type:text/html;charset=utf-8");
        $this->assign('page', $showpage);
        $this->assign('message', $message);
        $this->assign('msg_type', $msg_type);
        return $this->fetch();
    }

    /**账户明细*/
    public function points()
    {
        $type = I('type', 'all');    //获取类型
        $this->assign('type', $type);
        if ($type == 'recharge') {
            //充值明细
            $count = M('recharge')->where("user_id", $this->user_id)->count();
            $Page = new Page($count, 16);
            $account_log = M('recharge')->where("user_id", $this->user_id)->order('order_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else if ($type == 'points') {
            //积分记录明细
            $count = M('account_log')->where(['user_id' => $this->user_id, 'pay_points' => ['<>', 0]])->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $this->user_id, 'pay_points' => ['<>', 0]])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else {
            //全部
            $count = M('account_log')->where(['user_id' => $this->user_id])->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $this->user_id])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        }
        $show = $Page->show();
        $this->assign('account_log', $account_log);
        $this->assign('page', $show);
        $this->assign('listRows', $Page->listRows);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_points');
            exit;
        }
        return $this->fetch();
    }

    
    public function points_list()
    {
    	$type = I('type','all');
    	$usersLogic = new UsersLogic;
    	$result = $usersLogic->points($this->user_id, $type);
    
    	$this->assign('type', $type);
    	$showpage = $result['page']->show();
    	$this->assign('account_log', $result['account_log']);
    	$this->assign('page', $showpage);
    	if ($_GET['is_ajax']) {
    		 return $this->fetch('ajax_points');
    	}
    	return $this->fetch();
    }
    
    
    /*
     * 密码修改
     */
    /*public function password()
    {
        if (IS_POST) {
            $logic = new UsersLogic();
            $data = $logic->get_info($this->user_id);
            $user = $data['result'];
            if ($user['mobile'] == '' && $user['email'] == '')
                $this->ajaxReturn(['status'=>-1,'msg'=>'请先绑定手机或邮箱','url'=>U('/Mobile/User/index')]);
            $userLogic = new UsersLogic();
            $data = $userLogic->password($this->user_id, I('post.old_password'), I('post.new_password'), I('post.confirm_password'));
            if ($data['status'] == -1)
                $this->ajaxReturn(['status'=>-1,'msg'=>$data['msg']]);
            $this->ajaxReturn(['status'=>1,'msg'=>$data['msg'],'url'=>U('/Mobile/User/index')]);
            exit;
        }
        return $this->fetch();
    }*/

    function forget_pwd()
    {
        if ($this->user_id > 0) {
            $this->redirect("User/index");
        }
        $username = I('username');
        if (IS_POST) {
            if (!empty($username)) {
                if(!$this->verifyHandle('forget')){
                    $this->ajaxReturn(['status'=>-1,'msg'=>"验证码错误"]);
                };
                $field = 'mobile';
                if (check_email($username)) {
                    $field = 'email';
                }
                $user = M('users')->where("email", $username)->whereOr('mobile', $username)->find();
                if ($user) {
                    $sms_status = checkEnableSendSms(2);
                    session('find_password', array('user_id' => $user['user_id'], 'username' => $username,
                        'email' => $user['email'], 'mobile' => $user['mobile'], 'type' => $field,'sms_status'=>$sms_status['status']));
                    $regis_smtp_enable = $this->tpshop_config['smtp_regis_smtp_enable'];
                    if(($field=='email' && $regis_smtp_enable==0) || ($field=='mobile' && $sms_status['status']<1)){
                        $this->ajaxReturn(['status'=>1,'msg'=>"用户验证成功",'url'=>U('User/set_pwd')]);
                    }
                    $this->ajaxReturn(['status'=>1,'msg'=>"用户验证成功",'url'=>U('User/find_pwd')]);
                    exit;
                } else {
                    $this->ajaxReturn(['status'=>-1,'msg'=>"用户名不存在，请检查"]);
                }
            }
        }
        return $this->fetch();
    }

    function find_pwd()
    {
        if ($this->user_id > 0) {
            header("Location: " . U('User/index'));
        }
        $user = session('find_password');
        if (empty($user)) {
            $this->error("请先验证用户名", U('User/forget_pwd'));
        }
        $this->assign('user', $user);
        return $this->fetch();
    }


    public function set_pwd()
    {
        if ($this->user_id > 0) {
            $this->redirect('Mobile/User/index');
        }
        $check = session('validate_code');
        $find_password = session('find_password');
        $field = $find_password['field'];
        $sms_status = session('find_password')['sms_status'];
        $regis_smtp_enable = $this->tpshop_config['smtp_regis_smtp_enable'];
        $is_check_code=false;
        //需要验证邮箱或者手机
        if($field=='email' && $regis_smtp_enable==1)$is_check_code = true;
        if($field=='mobile' && $sms_status['status']==1)$is_check_code = true;
        if ((empty($check) || $check['is_check'] == 0) && $is_check_code) {
            $this->error('验证码还未验证通过',U('User/forget_pwd'));
        }
        if (IS_POST) {
            $data['password'] = $password = I('post.password');
            $data['password2'] = $password2 = I('post.password2');
            $UserRegvalidate = Loader::validate('User');
            if(!$UserRegvalidate->scene('set_pwd')->check($data)){
                $this->error($UserRegvalidate->getError(),U('User/forget_pwd'));
            }
            M('users')->where("user_id", $find_password['user_id'])->save(array('password' => encrypt($password)));
            session('validate_code', null);
            return $this->fetch('reset_pwd_sucess');
        }
        $is_set = I('is_set', 0);
        $this->assign('is_set', $is_set);
        return $this->fetch();
    }

    /**
     * 验证码验证
     * $id 验证码标示
     */
    private function verifyHandle($id)
    {
        $verify = new Verify();
        if (!$verify->check(I('post.verify_code'), $id ? $id : 'user_login')) {
            return false;
        }
        return true;
    }

    /**
     * 验证码获取
     */
    public function verify()
    {
        //验证码类型
        $type = I('get.type') ? I('get.type') : 'user_login';
        $config = array(
            'fontSize' => 30,
            'length' => 4,
            'imageH' =>  60,
            'imageW' =>  300,
            'fontttf' => '5.ttf',
            'useCurve' => false,
            'useNoise' => false,
        );
        $Verify = new Verify($config);
        $Verify->entry($type);
		exit();
    }

    /**
     * 账户管理
     */
    public function accountManage()
    {
        return $this->fetch();
    }

    public function recharge()
    {
        $order_id = I('order_id/d');
        $paymentList = M('Plugin')->where(['type'=>'payment' ,'code'=>['neq','cod'],'status'=>1,'scene'=> ['in','0,1']])->select();
        $paymentList = convert_arr_key($paymentList, 'code');
        //微信浏览器
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            unset($paymentList['weixinH5']);
        }else{
            unset($paymentList['weixin']);
        }
        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if ($val['config_value']['is_bank'] == 2) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
        }
        $bank_img = include APP_PATH . 'home/bank.php'; // 银行对应图片
        $this->assign('paymentList', $paymentList);
        $this->assign('bank_img', $bank_img);
        $this->assign('bankCodeList', $bankCodeList);

        // 查找最近一次充值方式
        $recharge_arr = Db::name('Recharge')->field('pay_code')->where('user_id', $this->user_id)
            ->order('order_id desc')->find();
        $alipay = 'alipayMobile'; //默认支付宝支付
        if($recharge_arr){
            foreach ($paymentList as  $key=>$item) {
                if($key == $recharge_arr['pay_code']){
                    $alipay = $recharge_arr['pay_code'];
                }
            }
        }
        $this->assign('alipay', $alipay);

        if ($order_id > 0) {
            $order = M('recharge')->where("order_id", $order_id)->find();
            $this->assign('order', $order);
        }
        return $this->fetch();
    }
    
    public function recharge_list(){
    	$usersLogic = new UsersLogic;
    	$result= $usersLogic->get_recharge_log($this->user_id);  //充值记录
    	$this->assign('page', $result['show']);
    	$this->assign('lists', $result['result']);
    	if (I('is_ajax')) {
    		return $this->fetch('ajax_recharge_list');
    	}
    	return $this->fetch();
    }

    //添加、编辑提现账号
    public function add_card(){
        $user_id=$this->user_id;
        $data=I('post.');
        if($data['type']==0){
            $info['cash_alipay']=$data['card'];
        }
        if($data['type']==1){
            $info['cash_weixinpay']=$data['card'];
        }
        $info['realname']=$data['cash_name'];
        $info['user_id']=$user_id;
        $res=DB::name('user_extend')->where('user_id='.$user_id)->count();
        if($res){
            $res2=Db::name('user_extend')->where('user_id='.$user_id)->save($info);
        }else{
            $res2=Db::name('user_extend')->add($info);
        }
        $this->ajaxReturn(['status'=>1,'msg'=>'操作成功']);
    }

    /**
     * 申请提现记录
     */
    public function withdrawals()
    {
        C('TOKEN_ON', true);
        if (IS_POST) {

            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $data['create_time'] = time();
            $cash = tpCache('cash');

            if(encrypt($data['paypwd']) != $this->user['paypwd']){
                $this->ajaxReturn(['status'=>0, 'msg'=>'支付密码错误']);
            }
            if ($data['money'] > $this->user['user_money']) {
                $this->ajaxReturn(['status'=>0, 'msg'=>"本次提现余额不足"]);
            } 
            if ($data['money'] <= 0) {
                $this->ajaxReturn(['status'=>0, 'msg'=>'提现额度必须大于0']);
            }

            // 统计所有0，1的金额
            $status = ['in','0,1'];   
            $total_money = Db::name('withdrawals')->where(array('user_id' => $this->user_id, 'status' => $status))->sum('money');
            if ($total_money + $data['money'] > $this->user['user_money']) {
                $this->ajaxReturn(['status'=>0, 'msg'=>"您有提现申请待处理，本次提现余额不足"]);
            }

            if ($cash['cash_open'] == 1) {
                $taxfee =  round($data['money'] * $cash['service_ratio'] / 100, 2);
                // 限手续费
                if ($cash['max_service_money'] > 0 && $taxfee > $cash['max_service_money']) {
                    $taxfee = $cash['max_service_money'];
                }
                if ($cash['min_service_money'] > 0 && $taxfee < $cash['min_service_money']) {
                    $taxfee = $cash['min_service_money'];
                }
                if ($taxfee >= $data['money']) {
                    $this->ajaxReturn(['status'=>0, 'msg'=>'手续费超过提现额度了！']);
                }
                $data['taxfee'] = $taxfee;

                // 每次限最多提现额度
                if ($cash['min_cash'] > 0 && $data['money'] < $cash['min_cash']) {
                    $this->ajaxReturn(['status'=>0, 'msg'=>'每次最少提现额度' . $cash['min_cash']]);
                }
                if ($cash['max_cash'] > 0 && $data['money'] > $cash['max_cash']) {
                    $this->ajaxReturn(['status'=>0, 'msg'=>'每次最多提现额度' . $cash['max_cash']]);
                }

                // 今天限总额度
                if ($cash['count_cash'] > 0) {
                    $status = ['in','0,1,2,3'];
                    $create_time = ['gt',strtotime(date("Y-m-d"))];
                    $total_money2 = Db::name('withdrawals')->where(array('user_id' => $this->user_id, 'status' => $status, 'create_time' => $create_time))->sum('money');
                    if (($total_money2 + $data['money'] > $cash['count_cash'])) {
                        $total_money = $cash['count_cash'] - $total_money2;
                        if ($total_money <= 0) {
                            $this->ajaxReturn(['status'=>0, 'msg'=>"你今天累计提现额为{$total_money2},金额已超过可提现金额."]);
                        } else {
                            $this->ajaxReturn(['status'=>0, 'msg'=>"你今天累计提现额为{$total_money2}，最多可提现{$total_money}账户余额."]);
                        }
                    }
                }
                // 今天限申请次数
                if ($cash['cash_times'] > 0) {
                    $total_times = Db::name('withdrawals')->where(array('user_id' => $this->user_id, 'status' => $status, 'create_time' => $create_time))->count();
                    if ($total_times >= $cash['cash_times']) {
                        $this->ajaxReturn(['status'=>0, 'msg'=>"今天申请提现的次数已用完."]);
                    }
                }
            }else{
                $data['taxfee'] = 0;
            }

            if (M('withdrawals')->add($data)) {
                $this->ajaxReturn(['status'=>1,'msg'=>"已提交申请",'url'=>U('User/account',['type'=>2])]);
            } else {
                $this->ajaxReturn(['status'=>0,'msg'=>'提交失败,联系客服!']);
            }
        }
        $user_extend=Db::name('user_extend')->where('user_id='.$this->user_id)->find();

        //获取用户绑定openId
        $oauthUsers = M("OauthUsers")->where(['user_id'=>$this->user_id, 'oauth'=>'wx'])->find();
        $openid = $oauthUsers['openid']; 

        $this->assign('user_extend',$user_extend);
        $this->assign('cash_config', tpCache('cash'));//提现配置项
        $this->assign('user_money', $this->user['user_money']);    //用户余额
        $this->assign('openid',$openid);    //用户绑定的微信openid
        return $this->fetch();
    }

    /**
     * 申请记录列表
     */
    public function withdrawals_list()
    {
        $withdrawals_where['user_id'] = $this->user_id;
        $count = M('withdrawals')->where($withdrawals_where)->count();
        // $pagesize = C('PAGESIZE'); //10条数据，不显示滚动效果
        // $page = new Page($count, $pagesize);
        $page = new Page($count, 15);
        $list = M('withdrawals')->where($withdrawals_where)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();

        $this->assign('page', $page->show());// 赋值分页输出
        $this->assign('list', $list); // 下线
        if (I('is_ajax')) {
            return $this->fetch('ajax_withdrawals_list');
        }
        return $this->fetch();
    }

    /**
     * 我的关注
     * @author lxl
     * @time   2017/1
     */
    public function myfocus()
    {
        return $this->fetch();
    }

    /**
     *  用户消息通知
     * @author dyr
     * @time 2016/09/01
     */
    public function message_notice()
    {
        return $this->fetch();
    }

    /**
     * ajax用户消息通知请求
     * @author dyr
     * @time 2016/09/01
     */
    public function ajax_message_notice()
    {
        $type = I('type');
        $message_model = new MessageLogic();
        if ($type === '0') {
            //系统消息
            $user_sys_message = $message_model->getUserMessageNotice();
        } else if ($type == 1) {
            //活动消息：后续开发
            $user_sys_message = array();
        } else {
            //全部消息：后续完善
            $user_sys_message = $message_model->getUserMessageNotice();
        }
        $this->assign('messages', $user_sys_message);
        return $this->fetch('ajax_message_notice');

    }

    /**
     * ajax用户消息通知请求
     */
    public function set_message_notice()
    {
        $type = I('type');
        $msg_id = I('msg_id');
        $user_logic = new UsersLogic();
        $res =$user_logic->setMessageForRead($type,$msg_id);
        $this->ajaxReturn($res);
    }

    /**
     * 查看消息详情
     */
    public function message_detail()
    {
        $msg_id = I('id/d', 0);
        $user_message = Db::name('user_message')->alias('um')
            ->join('message m', 'um.message_id=m.message_id')
            ->where(['um.user_id' => $this->user_id, 'um.message_id' => $msg_id])->find();
        $user_message['category_name'] = C('CATEGORY')[$user_message['category']];
        $this->assign('user_message', $user_message);
        M('user_message')->where(['user_id' => $this->user_id, 'message_id' => $msg_id])->save();
        return $this->fetch();
    }


    /**
     * 设置消息通知
     */
    public function set_notice(){
        //暂无数据
        return $this->fetch();
    }

    /**
     * 浏览记录
     */
    public function visit_log()
    {
        $count = M('goods_visit')->where('user_id', $this->user_id)->count();
        $Page = new Page($count, 20);
        $visit = M('goods_visit')->alias('v')
            ->field('v.visit_id, v.goods_id, v.visittime, g.goods_name, g.shop_price, g.cat_id')
            ->join('__GOODS__ g', 'v.goods_id=g.goods_id')
            ->where('v.user_id', $this->user_id)
            ->order('v.visittime desc')
            ->limit($Page->firstRow, $Page->listRows)
            ->select();

        /* 浏览记录按日期分组 */
        $curyear = date('Y');
        $visit_list = [];
        foreach ($visit as $v) {
            if ($curyear == date('Y', $v['visittime'])) {
                $date = date('m月d日', $v['visittime']);
            } else {
                $date = date('Y年m月d日', $v['visittime']);
            }
            $visit_list[$date][] = $v;
        }

        $this->assign('visit_list', $visit_list);
        if (I('get.is_ajax', 0)) {
            return $this->fetch('ajax_visit_log');
        }
        return $this->fetch();
    }

    /**
     * 删除浏览记录
     */
    public function del_visit_log()
    {
        $visit_ids = I('get.visit_ids', 0);
        $row = M('goods_visit')->where('visit_id','IN', $visit_ids)->delete();

        if(!$row) {
            $this->error('操作失败',U('User/visit_log'));
        } else {
            $this->success("操作成功",U('User/visit_log'));
        }
    }

    /**
     * 清空浏览记录
     */
    public function clear_visit_log()
    {
        $row = M('goods_visit')->where('user_id', $this->user_id)->delete();

        if(!$row) {
            $this->error('操作失败',U('User/visit_log'));
        } else {
            $this->success("操作成功",U('User/visit_log'));
        }
    }

    /**
     * 支付密码
     * @return mixed
     */
    public function paypwd()
    {
        //检查是否第三方登录用户
        $user = M('users')->where('user_id', $this->user_id)->find();
        if ($user['mobile'] == '')
            $this->error('请先绑定手机号',U('User/setMobile',['source'=>'paypwd']));
        $step = I('step', 1);
        if ($step > 1) {
            $check = session('validate_code');
            if (empty($check)) {
                $this->error('验证码还未验证通过', U('mobile/User/paypwd'));
            }
        }
        if (IS_POST && $step == 2) {
            $new_password = trim(I('new_password'));
            $confirm_password = trim(I('confirm_password'));
            $oldpaypwd = trim(I('old_password'));
            //以前设置过就得验证原来密码
            if(!empty($user['paypwd']) && ($user['paypwd'] != encrypt($oldpaypwd))){
                $this->ajaxReturn(['status'=>-1,'msg'=>'原密码验证错误！','result'=>null]);
            }
            $userLogic = new UsersLogic();
            $data = $userLogic->paypwd($this->user_id, $new_password, $confirm_password);
            $this->ajaxReturn($data);
            exit;
        }
        $this->assign('step', $step);
        return $this->fetch();
    }


    /**
     * 会员签到积分奖励
     * 2017/9/28
     */
    public function sign()
    {
        $userLogic = new UsersLogic();
        $user_id = $this->user_id;
        $info = $userLogic->idenUserSign($user_id);//标识签到
        $this->assign('info', $info);
        return $this->fetch();
    }

    /**
     * Ajax会员签到
     * 2017/11/19
     */
    public function user_sign()
    {
        $userLogic = new UsersLogic();
        $user_id   = $this->user_id;
        $config    = tpCache('sign');
        $date      = I('date'); //2017-9-29
        //是否正确请求
        (date("Y-n-j", time()) != $date) && $this->ajaxReturn(['status' => false, 'msg' => '签到失败！', 'result' => '']);
        //签到开关
        if ($config['sign_on_off'] > 0) {
            $map['sign_last'] = $date;
            $map['user_id']   = $user_id;
            $userSingInfo     = Db::name('user_sign')->where($map)->find();
            //今天是否已签
            $userSingInfo && $this->ajaxReturn(['status' => false, 'msg' => '您今天已经签过啦！', 'result' => '']);
            //是否有过签到记录
            $checkSign = Db::name('user_sign')->where(['user_id' => $user_id])->find();
            if (!$checkSign) {
                $result = $userLogic->addUserSign($user_id, $date);            //第一次签到
            } else {
                $result = $userLogic->updateUserSign($checkSign, $date);       //累计签到
            }
            $return = ['status' => $result['status'], 'msg' => $result['msg'], 'result' => ''];
        } else {
            $return = ['status' => false, 'msg' => '该功能未开启！', 'result' => ''];
        }
        $this->ajaxReturn($return);
    }


    /**
     * vip充值
     */
    public function rechargevip(){
        $paymentList = M('Plugin')->where("`type`='payment' and code!='cod' and status = 1 and  scene in(0,1)")->select();
        //微信浏览器
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and code='weixin'")->select();
        }
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if ($val['config_value']['is_bank'] == 2) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
        }
        $bank_img = include APP_PATH . 'home/bank.php'; // 银行对应图片
        $payment = M('Plugin')->where("`type`='payment' and status = 1")->select();
        $this->assign('paymentList', $paymentList);
        $this->assign('bank_img', $bank_img);
        $this->assign('bankCodeList', $bankCodeList);
        return $this->fetch();
    }
}
