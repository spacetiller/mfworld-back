<?php

namespace app\controllers;
/**
 * 我的
 */
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\HttpException;
use yii\filters\VerbFilter;
use app\helpers\CommonHelper;
use app\helpers\SmsHelper;
use app\helpers\CurlHelper;


class UserController extends BaseController
{

	//发送验证码
	public function actionSmscode(){
		$arr = Yii::$app->request->post();
		$mobile = $arr['mobile'];
		$type = $arr['type']; 
		if(empty($mobile)){
			return $this->responseHelper([], '201', '201', '手机号不能为空');
		}
		//验证手机号格式
		if (!CommonHelper::isMobiles($mobile)) {
			return $this->responseHelper([], '205', '205', "手机号码格式不正确!");
		}
		//发送短信
		$code = rand(1000, 9999);			
		$res = SmsHelper::sendSMS($mobile,array($code,1),"257854");
		if ($res != 1) {
			return $this->responseHelper([], '203', '203', '发送失败');
		}else{
			$sql = "INSERT INTO {{%code}} (`type`, `code`, `mobile`,  `created_at`, `ip`) VALUES ('".$type."', '".$code."', '".$mobile."', '".time()."', '".CommonHelper::getClientIp()."')";				
			$command = Yii::$app->db->createCommand($sql)->execute();
			return $this->responseHelper([], '211', '211', '发送成功');
		} 
	}


	//用户注册
	public function actionSignup()
	{
		$arr = Yii::$app->request->post();
		$mobile = $arr['mobile'];
		$code = $arr['code'];
		$pwd = $arr['pwd'];
		$user_name = $arr['user_name'];
		$sex = $arr['sex'];
		if (!empty($mobile) && !CommonHelper::isMobiles($mobile)) {
			return $this->responseHelper([], '212', '212', "手机号码格式不正确!");
		}
		if (!empty($pwd) && !CommonHelper::isPassWord($pwd)){
			return $this->responseHelper([], '213', '213', '请输入正确格式的密码!');
		}
		$newtime = time()-600;//10分钟有效
		$sql = "SELECT id,code FROM {{%code}} WHERE mobile = '".$mobile."' 
		 AND type ='2' AND created_at >= '".$newtime."' order by id desc limit 1";
		$code_arr = Yii::$app->db->createCommand($sql)->queryOne();
		if (!$code_arr){
			return $this->responseHelper([], '214', '214', "请获取验证码!");
		} 
    	if ($code != $code_arr['code']){
			return $this->responseHelper([], '215', '215', "您输入的验证码有误!");	
    	}
    	$sql2 = "select * from {{%user}} where user_mobile='".$mobile."'";
    	$res = Yii::$app->db->createCommand($sql2)->queryOne();
    	if(!empty($res)){
    		return $this->responseHelper([], '210', '210', "该手机号已被注册");
    	}
    	if(empty($user_name)){
    		return $this->responseHelper([], '216', '216', "名字不能为空");	
    	}
      if(empty($sex)){
        return $this->responseHelper([], '217', '217', "性别不能为空");
      }
    	$post_data['action']       = '';
    	$res=CurlHelper::curl_post('',$post_data);
    	$node = json_decode($res,true);
    	$address = explode("|", $node['data']);
    	$private = substr($address[0],2);
    	if($node['code'] == 200){
        if(!empty($arr['invite_code'])){
          $inviteSql = "select user_id,invite_num,eth_addr from {{%user}} where invite_code='".strtoupper($arr['invite_code'])."'";
          $inviteData = Yii::$app->db->createCommand($inviteSql)->queryOne();
          if(empty($inviteData)){
            return $this->responseHelper([], '220', '220', "邀请码有误");
          }
          if($inviteData['invite_num']<10){
            $invite_num=$inviteData['invite_num']+1;
          }else{
            $invite_num =10;
          }
          $sql3 = "INSERT INTO {{%user}} (`user_nick`, `user_pwd`,`user_sex`, `user_mobile`,`eth_addr`,`eth_key`,`invite_code`,`create_time`, `update_time`,`invite_id`) VALUES ('".$user_name."', '".md5($pwd)."','".$sex."', '".$mobile."','".$address[1]."','".$private."','".$this->make_coupon_card()."', '".time()."', '".time()."',".$inviteData['user_id'].")";
          $insert = Yii::$app->db->createCommand($sql3)->execute();
          $sql4 = "update {{%user}} set invite_num=".$invite_num." where user_id=".$inviteData['user_id']."";
          $update = Yii::$app->db->createCommand($sql4)->execute();
          if($insert && $update){
          if($invite_num<=10){
             if(!empty($arr['invite_code'])){
            $post_data['action'] = 'energy';
            $post_data['value'] = 10;
            $post_data['user'] = $inviteData['eth_addr'];
            $res=CurlHelper::curl_post('',$post_data);
            $json = json_decode($res,true);
            if($json['code']==200){
               $sql7 = "update {{%user}} set energy=".$json['data']." where user_id = '".$inviteData['user_id']."'";
               $updates = Yii::$app->db->createCommand($sql7)->execute();
               $sql8 = "insert into {{%color_log}} (user_id,type,value,create_time) VALUES (".$inviteData['user_id'].",'6',".$post_data['value'].",".time().")";
                $inserts = Yii::$app->db->createCommand($sql8)->execute();
             if($updates && $inserts){
                return $this->responseHelper([], '207', '207', "注册成功");
             }
            }
          } 
          }         
          return $this->responseHelper([], '207', '207', "注册成功");
        }
        }else{
          $sql4 = "INSERT INTO {{%user}} (`user_nick`, `user_pwd`,`user_sex`, `user_mobile`,`eth_addr`,`eth_key`,`invite_code`,  `create_time`, `update_time`) VALUES ('".$user_name."', '".md5($pwd)."','".$sex."', '".$mobile."','".$address[1]."','".$private."','".$this->make_coupon_card()."', '".time()."', '".time()."')";
          $insert = Yii::$app->db->createCommand($sql4)->execute();
          if($insert){      
          return $this->responseHelper([], '207', '207', "注册成功");
        }
        }   		
    		
    	}else{
          return $this->responseHelper([], '218', '218', "fail");
      }    	
	}


	//忘记密码
	public function actionPasswordsReses()
    {
    $arr = Yii::$app->request->post();
		$mobile = $arr['mobile'];
		$code = $arr['code'];
		$pwd = $arr['pwd'];
		$type = $arr['type'];
		if (!empty($mobile) && !CommonHelper::isMobiles($mobile)) {
			return $this->responseHelper([], '205', '205', "手机号码格式不正确!");
		}
		if (!empty($pwd) && !CommonHelper::isPassWord($pwd)){
			return $this->responseHelper([], '206', '206', '请输入正确格式的密码!');
		}
		$newtime = time()-600;//10分钟有效
		$sql1 = "SELECT id,code FROM {{%code}} WHERE mobile = '".$mobile."' 
		AND type ='4' AND created_at >= '".$newtime."' order by id desc limit 1";
		$code_arr = Yii::$app->db->createCommand($sql1)->queryOne();
		if (!$code_arr){
			return $this->responseHelper([], '207', '207', "请获取验证码!");
		} 
		if ($code != $code_arr['code']){
			return $this->responseHelper([], '208', '208', "您输入的验证码有误!");	
		}
		$sql2 = "update {{%user}} set user_pwd = '".md5($pwd)."' where user_mobile='".$mobile."'";
		$re = Yii::$app->db->createCommand($sql2)->execute();
		if($re){
			return $this->responseHelper([], '209', '209', "设置成功!");
		}else{
			return $this->responseHelper([], '221', '221', "设置失败!");
		}
    }



   //登录
   public function actionLogin()
   {

		date_default_timezone_set("Asia/Shanghai");
   		$arr = Yii::$app->request->post();
   		$mobile = $arr['mobile'];
   		$pwd = md5($arr['pwd']);
   		$login_type = $arr['login_type'];
   		$token =  Yii::$app->security->generateRandomString() . '_' . time();
   		$sql = "select user_id,user_nick,user_mobile,user_pwd,user_sex,invite_code,physical,energy,eth_addr,color from {{%user}} where user_mobile='".$mobile."' and user_pwd='".$pwd."'";
   		$re = Yii::$app->db->createCommand($sql)->queryOne();
   		$data=[];
   		if($re){
          $start = strtotime(date('Y-m-d'), time());
          $end = $start + 60 * 60 * 24;
          $sql6 = "select *  from {{%color_log}} where `type`=3 and create_time>".$start." and create_time<".$end." and user_id=".$re['user_id']."";
          $history = Yii::$app->db->createCommand($sql6)->queryAll();
          if(count($history)<1){
   				$post_data['action'] = '';
    			$post_data['value'] = 2;
    			$post_data['user'] = $re['eth_addr'];
    			$res=CurlHelper::curl_post('',$post_data);
    			$json = json_decode($res,true);
    			$color=$this->color($re['eth_addr']);
    			$pow = pow(10, 18);
				  $num = number_format($color['data']/$pow, 5);
    			if($json['code']==200 && $color['code']==200){	   			
       			$sql2 = "insert into {{%login_logs}} (user_id,client_name,login_time,login_ip,login_type) VALUES (".$re['user_id'].",'".$re['user_nick']."','".date('Y-m-d H:i:s',time())."','".CommonHelper::getClientIp()."',".$login_type.")";
       			$sql3 = "update {{%user}} set token='".$token."',energy=".$json['data']." where user_id = '".$re['user_id']."'";
       			$sql4 = "insert into {{%color_log}} (user_id,type,value,create_time) VALUES (".$re['user_id'].",'3',".$post_data['value'].",".time().")";
       			 $insert = Yii::$app->db->createCommand($sql2)->execute();

       			 $update = Yii::$app->db->createCommand($sql3)->execute();
       			 $energy = Yii::$app->db->createCommand($sql4)->execute();
       			 if($insert && $update && $energy){
       			 	$sql3 = "select energy from {{%user}} where user_id=".$re['user_id']."";
       				$res = Yii::$app->db->createCommand($sql3)->queryOne();
       			 	$data['mobile'] = substr_replace($re['user_mobile'],'****',3,4);
       			 	$data['user_nick'] = $re['user_nick'];
       			 	$data['user_sex'] = $re['user_sex'];
       			 	$data['token']  = $token;
       			 	$data['invite_code'] = $re['invite_code'];
       			 	$data['user_id'] = $re['user_id'];
       			 	$data['physical'] = $re['physical'];
              if($num=='0.00000'){
                $data['color'] = $num;
              }else{
                $time = time()-$color['time'];
                $c = 120 * 24;
                $perce = round($time / $c,2);
                $data['color'] = array(
                    'num'=>$num,
                    'perce'=>$perce
                  );
              }
              $data['ucolor'] = $re['color'];
       			 	$data['energy'] = $res['energy'];
       				return $this->responseHelper($data, '209', '209', "登录成功");
   			 }
   			}else{
          return $this->responseHelper([], '211', '211', "fail");
        }
      }
      $sql7 = "insert into {{%login_logs}} (user_id,client_name,login_time,login_ip,login_type) VALUES (".$re['user_id'].",'".$re['user_nick']."','".date('Y-m-d H:i:s',time())."','".CommonHelper::getClientIp()."',".$login_type.")";
      $sql8 = "update {{%user}} set token='".$token."' where user_id = '".$re['user_id']."'";
      $insert = Yii::$app->db->createCommand($sql7)->execute();
      $update = Yii::$app->db->createCommand($sql8)->execute();
      $color=$this->color($re['eth_addr']);
      $pow = pow(10, 18);
      $num = number_format($color['data']/$pow, 5);
      if($color['code']==200){
        if($insert && $update){
          $data['mobile'] = substr_replace($re['user_mobile'],'****',3,4);
          $data['user_nick'] = $re['user_nick'];
          $data['user_sex'] = $re['user_sex'];
          $data['token']  = $token;
          $data['invite_code'] = $re['invite_code'];
          $data['user_id'] = $re['user_id'];
          $data['physical'] = $re['physical'];
           if($num=='0.00000'){
            $data['color'] = $num;
          }else{
            $time = time()-$color['time'];
            $c = 120 * 24;
            $perce = round($time / $c,2);
            $data['color'] = array(
                'num'=>$num,
                'perce'=>$perce
              );
          }
          $data['ucolor'] = $re['color'];
          $data['energy'] = $re['energy'];
          return $this->responseHelper($data, '208', '208', "登录成功");
      } 
    }else{
      return $this->responseHelper([], '211', '211', "fail");
    }   		 
   		}else{
   			return $this->responseHelper([], '210', '210', "登陆失败");
   		}
   }

   //用户收取彩钻
   //
   public function actionCollect()
   {
    date_default_timezone_set("Asia/Shanghai");
   	$arr = Yii::$app->request->post();
   	$sql = "select eth_addr from {{%user}} where user_id=".$arr['user_id']."";
   	$re = Yii::$app->db->createCommand($sql)->queryOne();
   	$post_data['action'] = '';
    $post_data['user'] = $re['eth_addr'];
    $res=CurlHelper::curl_post('',$post_data);
    $json = json_decode($res,true);
    // print_r($json);die;
    $pow = pow(10, 18);
    $num = number_format($json['data']/$pow, 5);
	    if($json['code']==200)
	    {
        $sql1 = "insert into {{%color_log}} (user_id,type,value,create_time) VALUES (".$arr['user_id'].",'2',".$arr['color'].",'".time()."')";
        $insert = Yii::$app->db->createCommand($sql1)->execute();
        $sql2 = "update {{%user}} set color='".$num."' where user_id=".$arr['user_id']."";
        $update = Yii::$app->db->createCommand($sql2)->execute();
        if($insert && $update){
          $sql3 = "select color from {{%user}} where user_id=".$arr['user_id']."";
          $re = Yii::$app->db->createCommand($sql3)->queryOne();
          return $this->responseHelper($re, '201', '201', "收取成功");
        }else{
          return $this->responseHelper([], '202', '202', "收取失败");
        }

	    }
   }

   //个人信息
   public function actionInfo()
   {
      date_default_timezone_set("Asia/Shanghai");
      $arr = Yii::$app->request->get();
      if (empty($arr['user_id'])) {
            return $this->responseHelper([], '201', '201', '用户信息不存在');
        }
      $num = number_format('900000000',2);
      $start_time = strtotime(date('Y-m-d 00:00:00', time()-60*60*24));
      $end_time = strtotime(date('Y-m-d 23:59:59', time()-60*60*24));
      $sql = "select sum(value) as num from {{%color_log}} where create_time>".$start_time." and create_time<".$end_time." and `type`=2";
      $re = Yii::$app->db->createCommand($sql)->queryOne();
      if($re['num']>0){
        $yester = $re['num'];
      }else{
        $yester = 0;
      }
      $res['all'] = $num;
      $res['num'] = $yester;
      return $this->responseHelper($res, '200', '200', 'success');
   }

   //能量记录
   public function actionEnerlog()
   {
      $arr = Yii::$app->request->get();
      if (empty($arr['user_id'])) {
            return $this->responseHelper([], '201', '201', '用户信息不存在');
      }

      $sql3 = "select energy from {{%user}} where user_id=".$arr['user_id']."";
      $res = Yii::$app->db->createCommand($sql3)->queryOne();

      $sql = "select * from {{%color_log}} where `type` in () and user_id=".$arr['user_id']." order by create_time DESC";
      $history = Yii::$app->db->createCommand($sql)->queryAll();
      if(!empty($history)){
         foreach($history as $k=>$v){
          if($v['type']==3){
            $history[$k]['name'] = '登录';
          }else if ($v['type']==4){
            $history[$k]['name'] = '答题';
          }else if($v['type']==6){
            $history[$k]['name'] = '邀请好友';
          }else{
            $history[$k]['name'] = '完成任务';
          }
          $history[$k]['value']=(int)$v['value'];
        }
      }    
      $history[]['energy'] = $res['energy'];
      return $this->responseHelper($history, '200', '200', 'success');

   }

   public function actionPhysical()
   {
     $arr = Yii::$app->request->get();
      if (empty($arr['user_id'])) {
            return $this->responseHelper([], '201', '201', '用户信息不存在');
      }
      $sql3 = "select physical,invite_num from {{%user}} where user_id=".$arr['user_id']."";
      $res = Yii::$app->db->createCommand($sql3)->queryOne();
      $re['num'] = 10 - $res['invite_num'];
      $re['physical'] = $res['physical'];
      return $this->responseHelper($re, '200', '200', 'success');
   }


   //彩钻记录
   public function actionColorlog()
   {
      $arr = Yii::$app->request->get();
      if (empty($arr['user_id'])) {
            return $this->responseHelper([], '201', '201', '用户信息不存在');
      }
      $sql3 = "select color,eth_addr from {{%user}} where user_id=".$arr['user_id']."";
      $res = Yii::$app->db->createCommand($sql3)->queryOne();
      $sql = "select * from {{%color_log}} where `type` in () and user_id=".$arr['user_id']." order by create_time DESC";
      $history = Yii::$app->db->createCommand($sql)->queryAll();
      if(!empty($history)){
        foreach($history as $k=>$v)
        {
          if($v['type']==1){
            $history[$k]['name']="竞猜";
          }else{
            $history[$k]['name']="收取彩钻";
          }
        }
      }     
      $history[]['color'] = $res['color'];
      $history[]['addr'] = $res['eth_addr']; 
      return $this->responseHelper($history, '200', '200', 'success');
   }


   //邀请好友次数
   public function actionInvite()
   {
    $session = Yii::$app->session;
    $session->open();
    $arr = Yii::$app->request->get();
    $invite = "invite_" . $arr['user_id'];
    $data = $session->get($invite);
    if (!empty($data)) {
      $session->remove($invite);
    }
    $sql3 = "select invite_num,invite_code from {{%user}} where user_id=".$arr['user_id']."";
    $res = Yii::$app->db->createCommand($sql3)->queryOne();
    $res['invite_num'] = 10-$res['invite_num'];
    $session->set($invite, $res);
    $data = $session->get($invite);
    return $this->responseHelper($data, '200', '200', 'success');
   }


   //刷新
   public function actionRefresh()
   {
    date_default_timezone_set("Asia/Shanghai");  
    $arr = Yii::$app->request->get();
    if(empty($arr['user_id'])){
      return $this->responseHelper([], '201', '201', "未登录");
    }
    $sql = "select eth_addr,color,energy from {{%user}} where user_id=".$arr['user_id'];
    $re = Yii::$app->db->createCommand($sql)->queryOne();
    $color=$this->color($re['eth_addr']);
    $pow = pow(10, 18);
    $num = number_format($color['data']/$pow, 5);
    $res['color'] = $re['color'];
              if($num=='0.00000'){
                $res['num'] = $num;
              }else{
                $time = time()-$color['time'];
                $c = 120 * 24;
                $perce = (round($time / $c,2))*100;
                if($perce>100){
                  $perce=100;
                }
                $res['num'] = array(
                    'num'=>$num,
                    'perce'=>$perce
                  );
              }
    $res['energy'] = $re['energy'];
    $sql = "select * from {{%guessing}}";
    $arr = Yii::$app->db->createCommand($sql)->queryAll();
    if(!empty($arr)){
      foreach($arr as $k=>$v){
      if(time()>$v['close_time'] && $v['status']==1){
        $sql1 = "UPDATE {{%guessing}} SET `status` = 2 WHERE `id` = " . $v['id'];
        \Yii::$app->db->createCommand($sql1)->execute();
      }
    }
    $sql2 = "SELECT * FROM {{%guessing}} WHERE `status` =1  ORDER BY close_time ASC,sort ASC limit 5";

    $re = Yii::$app->db->createCommand($sql2)->queryAll();

    if(!empty($re)){
      foreach ($re as $key => $value) {
    	 $re[$key]['support1'] = @round(($value['option_one_total'] / $value['option_all_total'])*100);
    	$re[$key]['support2'] = @round(($value['option_two_total'] / $value['option_all_total'])*100);
    }
    $now = strtotime(date('Y-m-d H:i:s',time()));
    $re['now']= $now;
    }
    }
    $res['guess'] = $re;
    if($color['code']==200)
    {
      foreach($res['guess'] as $k=>$v){
	     if($v['support1']==0 && $v['support2']==0){
           return $this->responseHelper($res, '200', '200', "下注成功");
        }else{
          if($v['support1']!=0){
            $v['support2'] = 100-$v['support1'];
            }else if($v['support2']!=0){
            $v['support1'] = 100-$v['support2'];
            }
           return $this->responseHelper($res, '200', '200', "下注成功"); 
        }
	
      }
    }

   }

   //退出登录
    public function actionLoginout()
    {
    	$arr = Yii::$app->request->post();
    	$user_id = $arr['user_id'];
    	$sql = "update {{%user}} set token='' where user_id = '".$user_id."'";
    	$update = Yii::$app->db->createCommand($sql)->execute();
    	if($update)
    	{
    		return $this->responseHelper([], '201', '201', "退出登录");
    	}
    }
   //生成用户邀请码
  	public  function make_coupon_card()
  	 {
	    $code = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
	    $rand = $code[rand(0,25)]
	        .strtoupper(dechex(date('m')))
	        .date('d').substr(time(),-5)
	        .substr(microtime(),2,5)
	        .sprintf('%02d',rand(0,99));
	    for(
	        $a = md5( $rand, true ),
	        $s = '0123456789ABCDEFGHIJKLMNOPQRSTUV',
	        $d = '',
	        $f = 0;
	        $f < 6;
	        $g = ord( $a[ $f ] ),
	        $d .= $s[ ( $g ^ ord( $a[ $f + 6 ] ) ) - $g & 0x1F ],
	        $f++
	    );
	    return $d;
	}

	public function color($str){
		$post_data['action'] = '';
    	$post_data['user'] = $str;
    	$res=CurlHelper::curl_post('e',$post_data);
    	$color = json_decode($res,true);
    	return $color;
	}


}
