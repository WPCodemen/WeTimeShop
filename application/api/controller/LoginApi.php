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
 * 微信交互类
 */
namespace app\api\controller;
use app\common\logic\UsersLogic;
use app\common\logic\CartLogic;
use think\Request;
use think\Db;
use think\Controller;
use think\cache\driver\Redis;
class LoginApi extends Controller{
    public $config;
    public $oauth;
    public $class_obj;

    public function __construct(){

    }

    public function login(){
        $redis = new Redis();
        $mobile = input('post.mobile');
        $password = input('post.password');
        //$phone_code = input('post.phone_code');
        //验证码验证
        /*if (isset($_POST['verify_code'])) {
            $verify_code = I('post.verify_code');
            $verify = new Verify();
            if (!$verify->check($verify_code, 'user_login')) {
                $res = array('status' => 0, 'msg' => '验证码错误');
                exit(json_encode($res));
            }
        }*/
        /*if(empty($phone_code)){
            return json_encode(array('status'=>0,'msg'=>"短信验证码不能为空",'result'=>null));
        }else{
            $sms_has = $redis->has($mobile);
            if(!$sms_has){
                return json_encode(array('status'=>0,'msg'=>"短信验证码失效",'result'=>null));
            }
            $sms_code = $redis->get($mobile);

            if($sms_code != $phone_code)
                 return json_encode(array('status'=>0,'msg'=>"短信验证码错误",'result'=>null));

        }*/
        //验证之后删除验证码
        //$redis->rm($mobile);
        $password = encrypt($password);
        $logic = new UsersLogic();
        $data = $logic->app_login($mobile,$password);

        return json_encode($data);
    }

    public function app_reg(){
        $redis = new Redis();
        $mobile = input('mobile');
        $password = input('password');
        $password2 = input('password2');
        //$paypwd = input('paypwd');
        //$paypwd2 = input('paypwd2');
        $phone_code = input('phone_code');
        $invite_mobile = input('invite_mobile');
        //$verify_code = input('verify_code');
        //先把短信验证码隐藏了
        /*if(empty($phone_code)){
            return json_encode(array('status'=>0,'msg'=>"短信验证码不能为空",'result'=>null));
        }else{
            $sms_has = $redis->has($mobile);
            if(!$sms_has){
                return json_encode(array('status'=>0,'msg'=>"短信验证码失效",'result'=>null));
            }
            $sms_code = $redis->get($mobile);

            if($sms_code != $phone_code)
                return json_encode(array('status'=>0,'msg'=>"短信验证码错误",'result'=>null));

        }*/

        if(!empty($invite_mobile)){
            $select = "user_id,mobile,first_leader,second_leader,p_ids,g_ids,is_lock,is_real";
            $inviter = M('users')->where(['mobile'=>$invite_mobile])->field($select)->find();
        }
        //验证之后删除验证码
        //$redis->rm($mobile);
        $logic = new UsersLogic();
        $rt_data = $logic->reg($mobile,$password,$password2,$inviter);
        return json_encode($rt_data);

    }

    //找回密码
    public function findPassword(){
        $redis = new Redis();
        $mobile = input('post.mobile');
        $phone_code = input('post.phone_code');
        $password = input('post.password');
        $password2 = input('post.password2');
        if($password != $password2){
            return json_encode(array('status'=>0,"msg"=>"两次密码不相同！",'result'=>null));
        }
        //判断验证码是否正确
        if(empty($phone_code)){
            return json_encode(array('status'=>0,'msg'=>"短信验证码不能为空",'result'=>null));
        }else{
            $sms_has = $redis->has($mobile);
            if(!$sms_has){
                return json_encode(array('status'=>0,'msg'=>"短信验证码失效",'result'=>null));
            }
            $sms_code = $redis->get($mobile);

            if($sms_code != $phone_code)
                return json_encode(array('status'=>0,'msg'=>"短信验证码错误",'result'=>null));

        }

        $up_array = array('password'=>encrypt($password));
        $ret = Db::name('users')->where('mobile',$mobile)->update($up_array);
        if($ret){
            //验证之后删除验证码
            $redis->rm($mobile);
            return json_encode(array('status'=>1,'msg'=>"找回密码成功",'result'=>null));
        }
        return json_encode(array('status'=>0,'msg'=>"找回密码失败或和原密码相同",'result'=>null));
    }

    public function callback(){
        
        $data = $this->class_obj->respon();
        $logic = new UsersLogic();
        
        //手机端登录, 标识该openid来微信自公众号
        if($data['oauth'] == 'weixin')$data['oauth_child'] = 'mp';
         
        $is_bind_account = tpCache('basic.is_bind_account');
        
        if($is_bind_account){
            
            if($data['unionid']){
                $thirdUser = M('OauthUsers')->where(['unionid'=>$data['unionid'], 'oauth'=>$data['oauth']])->find();
            }else{
                $thirdUser = M('OauthUsers')->where(['openid'=>$data['openid'], 'oauth'=>$data['oauth']])->find();
            }
            
            //1. 第二种方式:第三方账号首次登录必须绑定账号
            if(empty($thirdUser)){
                //用户未关联账号, 跳到关联账号页
                session('third_oauth',$data);
                return $this->redirect(U('Mobile/User/bind_guide'));
            }else{
                //微信自动登录
                $data = $logic->thirdLogin_new($data);
            }
        }else{
            //2.第一种方式:第三方账号首次直接创建账号, 不需要额外绑定账号
            $data = $logic->thirdLogin($data);
        }
        
        if($data['status'] == 1){
            session('user',$data['result']);
            setcookie('user_id',$data['result']['user_id'],null,'/');
            setcookie('is_distribut',$data['result']['is_distribut'],null,'/');
            setcookie('uname',$data['result']['nickname'],null,'/');
            // 登录后将购物车的商品的 user_id 改为当前登录的id
            M('cart')->where("session_id" ,$this->session_id)->save(array('user_id'=>$data['result']['user_id']));
            $cartLogic = new CartLogic();
            $cartLogic->doUserLoginHandle($this->session_id,$data['result']['user_id']);  //用户登录后 需要对购物车 一些操作
            $this->success('登陆成功',U('Mobile/User/index'));
        }else{
            $this->success('登陆失败: '.$data['msg']);
        }
    }
}