<?php
ini_set('display_errors', 1);
error_reporting(E_ALL ^ E_NOTICE);

define('IN_API', true);
define('CURSCRIPT', 'api');

require_once '../source/class/class_core.php';

$cachelist = array();
$discuz = C::app();
$discuz->init_setting = true;
$discuz->init();


if ( !function_exists( 'hex2bin' ) ) {
  function hex2bin( $str ) {
    $sbin = "";
    $len = strlen( $str );
    for ( $i = 0; $i < $len; $i += 2 ) {
      $sbin .= pack( "H*", substr( $str, $i, 2 ) );
    }

    return $sbin;
  }
}

/**
 * UCenter 应用程序开发 doaction
 *
 * UCenter 简易应用程序，应用程序无数据库

 */

include '../config/config_ucenter.php';

include '../uc_client/client.php';

if(isset($_REQUEST)){
  $dec_ic = "加密的常量字符串";
  $dec_key = $_REQUEST['sys'];
  $data = $_REQUEST['data'];

  $jsonArr = json_decode(decrypt($dec_key, $dec_ic, $data)); 
 
  $userinfo = array(
    		"username"=>$jsonArr->phoneNumber,
    		"password"=>$jsonArr->pw
  );

  extract($userinfo);
}




$db_sql = "SELECT
    p.mobile,
    m.username,
    p.binduser,
    u.salt
    FROM
    ".DB::table('common_member_profile')." AS p
        LEFT JOIN ".DB::table('common_member')." AS m ON p.uid = m.uid
            LEFT JOIN ".DB::table('ucenter_members')." AS u ON u.username = m.username

            WHERE
            p.binduser = '$username'
            LIMIT 1";





$query = DB::query($db_sql);
$userArr = array();
while($result = DB::fetch($query)){
  $userArr['mobile'] = $result['mobile'];
  $userArr['username'] = $result['username'];
  $userArr['binduser'] = $result['binduser'];
  $userArr['salt'] = $result['salt'];
}

if(isset($userArr['username'])){
  $username = $userArr['username'];
  $pw = md5(md5($jsonArr->pw).$userArr['salt']);
  $sql = "update pre_ucenter_members  set password='$pw' where username='$username'";
  DB::query($sql);
}else{
  $username = $jsonArr->phoneNumber;
}



//UCenter 用户登录的 doaction 代码
$email = $username."@error404.com";

switch($_GET['doaction']) {
  case 'login':


    if(uc_get_user($username)){

      $uid = uc_user_login($username, $password);

      $arr = uc_user_synlogin($uid[0]);
      print_r($arr);

    }else{
      $id = uc_user_register($username, $password, $email); //email为空;
      $time = time();
      $sql1 = "INSERT INTO ".DB::table('common_member')." (uid,email,username,adminid,regdate) VALUES ('$id','$email','$username','0','$time')";
      $sql2 = "INSERT INTO ".DB::table('common_member_count')." (uid) values ('$id')";
      $sql3 = "INSERT INTO ".DB::table('common_member_profile')." (uid,mobile,binduser) values ('$id','$username','$username')";
      DB::query($sql1);
      DB::query($sql2);
      DB::query($sql3);
      $uid = uc_user_login($username, $password);
      $arr = uc_user_synlogin($uid[0]);
      echo $arr; //一定要打印
    }
    break;
  case 'logout':
    setcookie('doaction_auth', '', -86400);
    //生成同步退出的代码
    $ucsynlogout = uc_user_synlogout();
    break;
  default:
    break;
}

/**
 * AES 加密
 * @param String $enc_key 16位密钥
 * @param String $enc_iv 16位位移
 * @param String $data 加密串
 * @return string
 */
function encrypt($enc_key, $enc_iv, $data){
  $pad = str_pad($data, ceil(strlen($data)/16.0)*16, " ");
  $method = MCRYPT_RIJNDAEL_128;
  $mode = MCRYPT_MODE_CBC;
  $td = mcrypt_module_open($method, '', $mode, '');
  mcrypt_generic_init ( $td , $enc_key , $enc_iv);
  $encrypt = mcrypt_generic($td, $pad);
  mcrypt_generic_deinit($td);
  mcrypt_module_close($td);

  return bin2hex($encrypt);
}

/**
 * AES 解密
 * @param String $enc_key 16位密钥
 * @param String $enc_iv 16位位移
 * @param String $data 加密串
 * @return string
 */
function decrypt($dec_key, $dec_iv, $data){
  $method = MCRYPT_RIJNDAEL_128;

  $mode = MCRYPT_MODE_CBC;

  $td = mcrypt_module_open($method, '', $mode, '');

  mcrypt_generic_init ( $td , $dec_key , $dec_iv);
  $decrypt = mdecrypt_generic($td, hex2bin($data));

  mcrypt_generic_deinit($td);
  mcrypt_module_close($td);
  $decrypt = trim($decrypt);
  return $decrypt;
}





