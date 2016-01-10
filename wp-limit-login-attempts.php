<?php
/*
  Plugin Name: WP Limit Login Attempts  
  Plugin URI: http://ciphercoin.com/
  Description: Limit rate of login attempts and block ip temporarily (Up to 10 minutes). It is protecting from brute force attack.
  Author: Arshid 
  Author URI: http://ciphercoin.com/
  Text Domain: wp-limit-login-attempts
  Version: 2.3.2
*/ 

/*  create or update table */
register_activation_hook(__FILE__,'wp_limit_login_update_tables');
function wp_limit_login_update_tables(){
    global $wpdb;
    $tablename = $wpdb->prefix."limit_login"; 
    if($wpdb->get_var("SHOW TABLES LIKE '$tablename'") != $tablename ){
        
        $sql = "CREATE TABLE `$tablename`  (
		`login_id` INT(11) NOT NULL AUTO_INCREMENT,
		`login_ip` VARCHAR(100) NOT NULL,
        `login_attempts` INT(11) NOT NULL,
		`attempt_time` DATETIME,
		`locked_time` VARCHAR(100) NOT NULL,
		PRIMARY KEY  (login_id)
		);";
 
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
    }

    wp_redirect( home_url( '/wp-limit-login-attempts/' ) );

}

/* plugin deactivation */
register_deactivation_hook(__FILE__,'wp_limit_login_deactivation');
function wp_limit_login_deactivation(){
    error_log("Plugin deactivated..!");
}
/* Plugin Style  */  
   function wp_limit_login_stylesheet() {
        wp_enqueue_style( 'login_captcha_style',  plugin_dir_url( __FILE__ )  . 'style.css');
        wp_enqueue_script( 'login_captcha_script', 'https://code.jquery.com/jquery-1.8.2.js',1);
        wp_enqueue_script( 'login_captcha_main_script', plugin_dir_url( __FILE__ ). 'js/main.js',2);
    }
/* Plugin main functions */  
add_action( 'login_enqueue_scripts', 'wp_limit_login_stylesheet');
add_action('plugins_loaded', 'wp_limit_login_init', 99999);
function wp_limit_login_init(){
 
      function is_session_started(){
            if ( php_sapi_name() !== 'cli' ) {
                if ( version_compare(phpversion(), '5.4.0', '>=') ) {
                    return session_status() === PHP_SESSION_ACTIVE ? TRUE : FALSE;
                } else {
                    return session_id() === '' ? FALSE : TRUE;
                }
            }
            return FALSE;
        }
              
      if (is_session_started() === FALSE ) session_start();
        if(isset($_SESSION["popup_flag"]) && ($_SESSION["popup_flag"] == null)){
             $_SESSION["popup_flag"] = "first" ;
        }
      if(isset($_GET['captcha'])){
	       if($_GET['captcha']==$_SESSION["captcha"]){
               $_SESSION["popup_flag"] = "true_0152" ;
	    }else{
               $_SESSION["popup_flag"] = "false_0152";
           }
      }

      add_action('login_head', 'wp_limit_login_head');
      add_action('wp_login_failed', 'wp_limit_login_failed');
      add_action('login_errors','wp_limit_login_errors');
      add_filter( 'authenticate', 'wp_limit_login_auth_signon', 30, 3 );
      add_action( 'admin_init', 'wp_limit_login_admin_init' );  

  function wp_limit_login_head(){ 
      
?>
        <script>var popup_flag = "<?php    echo $_SESSION["popup_flag"] ?>";
        </script>
        <div class='popup' style="display: none;">
        <div class='popup_box'>
        <p class='x' id='x'> &times </p>
        <p>Please enter captcha text</p>
        <img class="captcha" src="<?php echo  plugin_dir_url( __FILE__ ).'/captcha.php';?>" />
            <form class="captcha_form" action="" method="GET">
                <input type="text" placeholder="Enter here.." name="captcha">
                <input class="submit" type="submit" value="Submit">
        </form>

        </div>
        </div>
<?php }  
    
 function wp_limit_login_failed($username){   

     global $msg,$ip,$wpdb; 
  if ($_SESSION["popup_flag"] == "true_0152"){
     $ip = getip(); 
     $tablename = $wpdb->prefix."limit_login";
     $tablerows = $wpdb->get_results( "SELECT `login_id`, `login_ip`,`login_attempts`,`attempt_time`,`locked_time` FROM  `$tablename`   WHERE `login_ip` =  '$ip'  ORDER BY `login_id` DESC LIMIT 1 " );
    
     if(count($tablerows)==1){
         $attempt =$tablerows[0]->login_attempts ;
         if( $attempt<=5){
              $attempt = $attempt +1; 
              $update_table = array(
              'login_id' =>  $tablerows[0]->login_id ,
              'login_attempts'  =>   $attempt  
              //'attempt_time' => date('Y-m-d G:i:s')
               );
               $wpdb->update($tablename,$update_table,array('login_id'=>$tablerows[0]->login_id ) );
               $remain_attempt = 6 - $attempt;
               $msg = $remain_attempt.' attempts remaining..!';
               return $msg;
         }else{
                 if(is_numeric($tablerows[0]->locked_time)){
                  $attempt = $attempt +1; 
                  $update_table = array(
                  'login_id' =>  $tablerows[0]->login_id ,
                  'login_attempts'  =>   $attempt , 
                 // 'attempt_time' => date('Y-m-d G:i:s'),
                  'locked_time' => date('Y-m-d G:i:s')
                   );
                   $wpdb->update($tablename,$update_table,array('login_id'=>$tablerows[0]->login_id ) );
                 }else{
                  $attempt = $attempt +1; 
                  $update_table = array(
                  'login_id' =>  $tablerows[0]->login_id ,
                  'login_attempts'  =>   $attempt   
                  //'attempt_time' => date('Y-m-d G:i:s')
                   );
                   $wpdb->update($tablename,$update_table,array('login_id'=>$tablerows[0]->login_id ) );
                 }
                 $msg = "The maximum number of login attempts has been reached. Please try again in 10 minutes";
                 return $msg;
             }
         
            $time_now = date_create(date('Y-m-d G:i:s'));
            $attempt_time = date_create($tablerows[0]->attempt_time);
            $interval = date_diff($attempt_time, $time_now);

            if(($interval->format("%s")) <= 2){
              //wp_redirect(home_url()); 
              //exit; 
            } 
         
         }else{
           global $wpdb;
           $tablename = $wpdb->prefix."limit_login";
           $newdata = array(
            'login_ip' => $ip,
            'login_attempts' =>  1 , 
            'attempt_time' => date('Y-m-d G:i:s'),
            'locked_time' =>0
            );
         $wpdb->insert($tablename,$newdata);
               $remain_attempt = 5;
               $msg = $remain_attempt.' attempts remaining..!';
               return $msg;
     }
    }else{
       $_SESSION["popup_flag"] = "first";
              $error = new WP_Error();
              $error->remove('wp_captcha', "Sorry..! captcha");
          return $error; 
   }
     
 }
    

function wp_limit_login_admin_init(){ 
    if(is_user_logged_in()){
    global $wpdb;
    $tablename = $wpdb->prefix."limit_login";
    $ip = getip(); 
    wp_limit_login_nag_ignore();
     $tablerows = $wpdb->get_results( "SELECT `login_id`, `login_ip`,`login_attempts`,`locked_time` FROM  `$tablename`   WHERE `login_ip` =  '$ip'  ORDER BY `login_id` DESC LIMIT 1 " );
     if(count($tablerows)==1){
        $update_table = array(
                      'login_id' =>  $tablerows[0]->login_id ,
                      'login_attempts'  =>   0 , 
                     // 'attempt_time' => date('Y-m-d G:i:s'),
                      'locked_time' => 0
                       );
       $wpdb->update($tablename,$update_table,array('login_id'=>$tablerows[0]->login_id ) );
       //update table 
     }
    }
}
    


function wp_limit_login_errors($error){
    global $msg;
     $pos_first = strpos($error, 'Proxy');
     $pos_second = strpos($error, 'wait');
     $pos_third = strpos($error, 'captcha');
    if (is_int($pos_first)) {
        $error = "Sorry..! Proxy detected..!";
    }else if($pos_second){
        $error = "Sorry..! Please wait 10 minutes..!";
    }else if($pos_third){
        $error = "Sorry..! Please enter correct captcha..!";
    }else{
            $error = "<strong>Login Failed</strong>: Sorry..! Wrong information..!  </br>".$msg;
    }
    return $error;

 }
    
 
    
  

function wp_limit_login_auth_signon( $user, $username, $password ) {
    
    global $ip , $msg,$wpdb;
    $ip = getip();
    
  
    
    if ( empty( $username ) || empty( $password ) ) {
       // do_action( 'wp_login_failed' );
    }
    if ($_SESSION["popup_flag"] == "true_0152"){
 
     $tablename = $wpdb->prefix."limit_login";
     $tablerows = $wpdb->get_results( "SELECT `login_id`, `login_ip`,`login_attempts`,`attempt_time`,`locked_time` FROM  `$tablename`   WHERE `login_ip` =  '$ip'  ORDER BY `login_id` DESC LIMIT 1 " );
   if(count($tablerows)==1){
    $time_now = date_create(date('Y-m-d G:i:s'));
    $attempt_time = date_create($tablerows[0]->attempt_time);
    $interval = date_diff($attempt_time, $time_now);

    if(($interval->format("%s")) <= 1){
      if(($tablerows[0]->login_attempts)!=0){  
          wp_redirect(home_url()); 
          exit;
      }else{
          return $user;
      }
    }else{
    
      /*$url_first = "http://www.shroomery.org/ythan/proxycheck.php?ip=".$ip;
      $url_second = "http://check.getipintel.net/check.php?ip=".$ip;
      $response_first = wp_remote_get($url_first); 
      $response_second = wp_remote_get($url_second);
        
        $ip_check = false;
       
       if(($response_first['body']=="N")|| ($response_second['body']<=0.99)){
            $ip_check = true;
        } */
       $ip_check = true;
     
        if((($tablerows[0]->login_attempts) % 7) ==0){
            if (($tablerows[0]->login_attempts) != 0){
             
                $attempts = $tablerows[0]->login_attempts;
                $attempts = $attempts + 1;
                $_SESSION["popup_flag"] = "first";
                $update_table = array(
                  'login_id' =>  $tablerows[0]->login_id ,
                  'login_attempts'  =>   $attempts , 
                  // 'attempt_time' => date('Y-m-d G:i:s'),
                  //'locked_time' => date('Y-m-d G:i:s')
                   );
                   $wpdb->update($tablename,$update_table,array('login_id'=>$tablerows[0]->login_id ) );
            }
        }
        
        
        // proxy or not 
        if($ip_check == true){
            if(!is_numeric($tablerows[0]->locked_time)){
                $locked_time = date_create($tablerows[0]->locked_time);
                $time_now = date_create(date('Y-m-d G:i:s'));
                $interval = date_diff($locked_time, $time_now);
                if(($interval->format("%i")) <= 10){
                     $msg = "Sorry..! Please wait 10 minutes..!";  
                     $error = new WP_Error();
              $error->add('wp_to_many_try', $msg);
              return $error;
                }else{
                   $update_table = array(
                   'login_id' =>  $tablerows[0]->login_id ,
                   'login_attempts'  =>   0 , 
                   'attempt_time' => date('Y-m-d G:i:s'),
                   'locked_time' => 0
                   );
                   $wpdb->update($tablename,$update_table,array('login_id'=>$tablerows[0]->login_id ) );
                  return $user; 
                }
          }else{
               return $user; 
            }
         }else{ 
              $_SESSION["popup_flag"] = "first";
              $error = new WP_Error();
              $error->add('wp_proxy_detection', "Sorry..! Proxy detected..!");
          return $error;
         } 
     }
   }else{
       return $user; 
   }
     } else{
        $_SESSION["popup_flag"] = "first";
              $error = new WP_Error();
              $error->remove('wp_captcha', "Sorry..! captcha");
          return $error; 
    }
}

    
    
    
    function getip(){
           if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip =esc_sql($_SERVER['HTTP_CLIENT_IP']);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = esc_sql($_SERVER['HTTP_X_FORWARDED_FOR']);
        } else {
            $ip =  esc_sql($_SERVER['REMOTE_ADDR']);
             if($ip=='::1'){
                  $ip = '127.0.0.1';
        
             }
        }
        return $ip;
    }
    
    
    
    function wp_limit_login_nag_ignore() {
	global $current_user;
        $user_id = $current_user->ID;
        /* If user clicks to ignore the notice, add that to their user meta */
        if ( isset($_GET['wp_limit_login_nag_ignore']) && '0' == $_GET['wp_limit_login_nag_ignore'] ) {
             add_user_meta($user_id, 'wp_limit_login_nag_ignore', 'true', true);
	}
  }
    
    
}
 
//auto fill login 
add_action("login_form", "wp_login_attempt_focus_start");
function wp_login_attempt_focus_start() {
    ob_start("wp_login_attempt_focus_replace");
}

function wp_login_attempt_focus_replace($html) {
    return preg_replace("/d.value = '';/", "", $html);
}

add_action("login_footer", "wp_login_attempt_focus_end");
function wp_login_attempt_focus_end() {
    ob_end_flush();
} 

/* Display a notice that can be dismissed */

add_action('admin_notices', 'wp_limit_login_admin_notice');

function wp_limit_login_admin_notice() {
	global $current_user ;
        $user_id = $current_user->ID;
        /* Check that the user hasn't already clicked to ignore the message */
	if ( ! get_user_meta($user_id, 'wp_limit_login_nag_ignore') ) {
        echo '<div style="border-radius: 4px; -moz-border-radius: 4px; -webkit-border-radius: 4px; background: #EBF8A4; border: 1px solid #a2d246; color: #066711; font-size: 14px; font-weight: bold; height: auto; margin: 30px 15px 15px 0px; overflow: hidden; padding: 4px 10px 6px; line-height: 30px;"><p>'; 
        printf(__('Your admin is protected. Light wordpress plugin -  <a href="http://ciphercoin.com" target="_blank">CipherCoin</a>  | <a href="options-general.php?page=wp-limit-login-attempts">Settings</a> |<a href="%1$s">Hide Notice</a>'), '?wp_limit_login_nag_ignore=0');
        echo "</p></div>";
	}
}

/*  add menue in admin */
add_action( 'admin_menu', 'wp_limit_login_plugin_menu' );

/** Step 1. */
function wp_limit_login_plugin_menu() {
	wp_enqueue_style( 'login_captcha_style',  plugin_dir_url( __FILE__ )  . 'style.css');
	add_options_page( 'My Plugin Options', 'WP Limit Login', 'manage_options', 'wp-limit-login-attempts', 'wp_limit_login_plugin_options' );
}

/** Step 3. */
function wp_limit_login_plugin_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	echo '<div class="warn_msg">
    <img src="'.plugin_dir_url( __FILE__ )  .'/images/warn.png""> <b>WP Limit Login attempts Lite</b>
     is a fully functional but limited version of <b><a href="http://ciphercoin.com" target="_blank">WP Limit Login attempts Pro</a></b>. Consider upgrading to get access to all premium features and premium support.
  </div>';
  
	echo '<div class="admin_menu">';	
	echo '<h2>WP Limit Login Attempts</h2>';
	echo '<div class="row1"><label>No of login attempts :</label><input type="number" value="5" class="attempts" disabled></div>';
	echo '<div class="row2"><label>Delay time in minutes:</label><input type="number" value="10" class="delay" disabled></div>';
	echo '<div class="row3"><label>No of attempts for captcha:</label><input type="number" value="5" class="delay" disabled></div>';
	echo '<div class="row4"><input type="submit" class="submit_admin" value="Submit"></div>';
	echo '</div>';
	echo '<div class="warn_msg">
    <img src="'.plugin_dir_url( __FILE__ )  .'/images/warn.png"">   Please consider upgrading to <b><a href="http://www.ciphercoin.com/" target="_blank">WP Limit Login attempts Pro</a></b> if you want to use this feature.
  </div>';
  /*echo '<div class="banner_img">
    <a href="http://www.ciphercoin.com/premium/" target="_blank"><img src="'.plugin_dir_url( __FILE__ )  .'/images/banner.png""></a></div>';
  */
}
 

// Add settings link on plugin page
function wp_limit_login_settings_link($links) { 
  $settings_link = '<a href="options-general.php?page=wp-limit-login-attempts">Settings</a>'; 
  array_unshift($links, $settings_link); 
  return $links; 
}
 
$plugin = plugin_basename(__FILE__); 
add_filter("plugin_action_links_$plugin", 'wp_limit_login_settings_link' );

?>
