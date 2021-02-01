<?php

/**
 * Plugin Name: Investment Calculator
 * Plugin URI: 
 * Description: Investment Calculator
 * Version: 1.0
 * Author: James Allen
 * Author URI:
 */

if ( !class_exists('investCalculator')){
	class investCalculator{
		function __construct(){
			register_activation_hook( __FILE__, array(&$this, 'install') );
			register_deactivation_hook(__FILE__,  array(&$this,'my_deactivation'));
			add_action('wp_enqueue_scripts', array(&$this, 'includeScript') );
			add_action('admin_enqueue_scripts', array(&$this, 'includeScript') );
			add_action( 'init', array(&$this, 'feInit') );
			add_action( 'wp_ajax_statesGet',  array(&$this,'getStates' ));
			add_action( 'wp_ajax_nopriv_statesGet',  array(&$this,'getStates' ));
			add_action( 'wp_ajax_citiesGet',  array(&$this,'getcities' ));
			add_action( 'wp_ajax_nopriv_citiesGet',  array(&$this,'getcities' ));
			add_action( 'wp_ajax_yezter_request_sign',  array(&$this,'send_request_signin' ));	
			add_action( 'wp_ajax_nopriv_yezter_request_sign',  array(&$this,'send_request_signin' ));	
			add_shortcode( '_login_portal_screen', array(&$this,'portal_sign_in'));
			add_filter('wp_mail_from_name', array(&$this,'from_yezter_name'));	
			add_filter('wp_mail_from', array(&$this,'from_yezter_from'));
			add_action('admin_menu', array(&$this,'admin_menu'));
			add_shortcode( 'my_dashboard', array(&$this,'dashboard'));
			add_action( 'my_task_hook',  array(&$this, 'my_task_function' ));
			//add_action( 'my_task_hook_monthly',  array(&$this, 'my_task_hook_monthly_function' ));
			add_filter('cron_schedules', array(&$this,'addCronMinutes'));
			add_action("admin_init", array( $this,  "admin_init"));
			add_action('save_post', array( $this, 'save_post_data'));
			add_shortcode( 'Login-Check', array(&$this,'checkLogin'));
			add_action('woocommerce_order_status_completed', array(&$this, 'custom_process_order' ), 10, 1);
			add_shortcode( '_calculator', array(&$this,'invs_calculator'));
			add_shortcode( '_login_screen', array(&$this,'signIn'));
		}
		
		function my_task_hook_monthly_function(){
			global $wpdb;
			/* ini_set('display_errors', 1);
			ini_set('display_startup_errors', 1);
			error_reporting(E_ALL); */
			
			$stripe_key = get_option( 'yezter_stripe_key' );
			$memberFee = get_option( '_membership_fee' );
			$memberFee_expire = get_option( '_membership_fee_expire_card' );
			
			require_once(ABSPATH.'wp-content/stripe-php/init.php');
			
			$secret_key = get_option( 'yezter_secret_key' );
			$stripe_api_key = $secret_key;               
              \Stripe\Stripe::setApiKey($stripe_api_key);
			
			$sql = "SELECT * FROM {$wpdb->prefix}investment_users WHERE status = '1' ";
			$results =  $wpdb->get_results($sql);
			$date = date('Y-m-d');
		
			if($results){
				foreach($results as $user){
					$customerID = $user->customerID;
					$user_idd = $user->uid;
					
					// get last subscribtion date in subscribtion table_name
					$sql = "SELECT subscribe_date FROM {$wpdb->prefix}investment_customers_subscribtion WHERE user_id = '{$user_idd}'  ORDER BY  subscribe_date DESC ";
					$date_subscibe =  $wpdb->get_var($sql);
					if( empty($date_subscibe) ){ /// if no subscribtion added yet
						if(!empty( $customerID ) ){
							$charge = \Stripe\Charge::create(array(
								'customer'  => $customerID,
								'amount'    => ceil($memberFee*100),
								'currency'  => 'USD'
							));
							
							if($charge->paid == true){
								$sql = "INSERT INTO {$wpdb->prefix}investment_customers_subscribtion(user_id,amount,subscribe_date,status,deducted_from) VALUES('{$user_idd}','{$memberFee}','{$date}','1','card')";
								$wpdb->query($sql);
							}else{
								$sql = "INSERT INTO {$wpdb->prefix}investment_customers_subscribtion(user_id,amount,subscribe_date,status,deducted_from) VALUES('{$user_idd}','{$memberFee_expire}','{$date}','0','investments')";
								$wpdb->query($sql);
							}
						}else{
							$sql = "INSERT INTO {$wpdb->prefix}investment_customers_subscribtion(user_id,amount,subscribe_date,status,deducted_from) VALUES('{$user_idd}','{$memberFee_expire}','{$date}','0','investments')";
							$wpdb->query($sql);
						}
					}elseif( !empty($date_subscibe) ) {
						$nextM_date = date("Y-m-d", strtotime($date_subscibe . " +1 month") );
						//$date = date( "Y-m-d" , strtotime($date_subscibe . " +1 month") );
						
						if( $date == $nextM_date ){
							//  check if three affiliate user under of this user
							$sql = "SELECT count(refer_uid) FROM {$wpdb->prefix}investment_users WHERE  refer_uid = '{$user_idd}' " ;
							$totrefID = $wpdb->get_var($sql);
							if( $totrefID >= 3 ){
								//nothing no monthly subscribtion added
							}elseif(!empty( $customerID ) ){
								$charge = \Stripe\Charge::create(array(
									'customer'  => $customerID,
									'amount'    => ceil($memberFee*100),
									'currency'  => 'USD'
								));
								
								if($charge->paid == true){
									$sql = "INSERT INTO {$wpdb->prefix}investment_customers_subscribtion(user_id,amount,subscribe_date,status,deducted_from) VALUES('{$user_idd}','{$memberFee}','{$date}','1','card')";
									$wpdb->query($sql);
								}else{
									$sql = "INSERT INTO {$wpdb->prefix}investment_customers_subscribtion(user_id,amount,subscribe_date,status,deducted_from) VALUES('{$user_idd}','{$memberFee_expire}','{$date}','0','investments')";
									$wpdb->query($sql);
								}
							}else{
								$sql = "INSERT INTO {$wpdb->prefix}investment_customers_subscribtion(user_id,amount,subscribe_date,status,deducted_from) VALUES('{$user_idd}','{$memberFee_expire}','{$date}','0','investments')";
								$wpdb->query($sql);
							}
						}
					}else{
						// nothing
					}
				}
			}
		}
		
		function newPasswordMail(){
			ob_start();
			?>
			<div id=":au" class="ii gt "><div id=":b7" class="a3s aXjCH m16069246ea9540fa"><div class="adM">
			</div><u></u>
			<div marginwidth="0" marginheight="0" style="width:100%;margin:0;padding:0;background-color:#f5f5f5;font-family:Helvetica,Arial,sans-serif">
			<div id="m_-2785494934782886297top-orange-bar" style="display:block;height:5px;background-color:rgb(26, 85, 145);"></div>
			<center>
			<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" class="m_-2785494934782886297background-table">
			<tbody><tr>
			<td align="center" valign="top" style="border-collapse:collapse">
			<table border="0" cellpadding="0" cellspacing="0" width="638" id="m_-2785494934782886297main-table">
			<tbody><tr>
			<td align="center" valign="top" height="20" style="border-collapse:collapse">
			</td>
			</tr>
			<tr>
			<td align="center" valign="top" style="border-collapse:collapse"><table width="100%" border="0">
			<tbody><tr>
			<td width="127" align="center" style="border-collapse:collapse"><a href="<?php echo home_url(); ?>"  ><img src="http://ulynd.com/wp-content/uploads/2018/01/logoinvs-small.png" alt="Zendesk Chat"   border="0" class="m_-2785494934782886297logo CToWUd" style="border:0;height:auto;line-height:120%;outline:none;text-decoration:none;color:#d86c00;padding-left:10px;font-family:Helvetica,Arial,sans-serif;font-size:25px;vertical-align:text-bottom;text-align:center"></a></td>
			</tr>
			<tr>
			<td align="center" colspan="3" class="m_-2785494934782886297email-title" style="border-collapse:collapse;font-family:Helvetica,Arial,sans-serif;font-size:30px;font-weight:bold;line-height:120%;color:rgb(82,82,82);text-align:center;zoom:1">Dear #username#</td>
			</tr>
			</tbody></table></td>
			</tr>
			<tr>
				<td class="m_-2785494934782886297white-space m_-2785494934782886297headnote" align="center" style="padding: 2em;border-collapse:collapse;background-color:rgb(255,255,255);border-color:rgb(221,221,221);border-width:1px;border-radius:5px;border-style:solid;padding:12px;font-size:13px;line-height:2em;color:rgb(119,119,119);text-align:justify"><p>Welcome to Ulynd.com.</p>Your new Password is: #password#</p>
					<p>Best Regards,</p>
					<p>Ulynd.com Team.</p>
				</td>
			</tr>

			</tbody></table>
			</td>
			</tr>
			<tr>
			<td align="center" valign="top" height="30" style="border-collapse:collapse"></td>
			</tr>
			</tbody></table>
			</td>
			</tr>
			</tbody></table>
			</center><div class="yj6qo"></div><div class="adL">
			</div></div><div class="adL">
			</div></div></div>
			<?php
			$html = ob_get_clean();
			return $html;
		}
		
		function signIn(){
			if(isset($_POST['gst_son_submit'])){
			
				$u_email = $_POST['gst_son_email'];
				$u_pswd = $_POST['gst_son_pswd'];
				
				$creds = array();
				
				$creds['user_login'] = $u_email;
				$creds['user_password'] = $u_pswd;
				$creds['remember'] = true;
				$user = wp_signon( $creds, false );
				if ( is_wp_error($user) ){
					?>
					<script>
						jQuery(document).ready(function(){
							jQuery("#welldoneapp_msg212").html('Incorrect user name or password');
						})
					</script>
					<?php
				}else{
					$user_id = $user->ID;
					$userstatus = get_user_meta($user_id, '_user_login_status', true);
					wp_redirect( home_url('investment') );
				}
			}
			if(isset($_POST['gst_forgot_submit'])){
				$forgot_email = $_POST['email_adress_forgot'];
				
				if (  email_exists($forgot_email) == true ) { 
					$user = get_user_by( 'email', $forgot_email );
					$user_id = $user->ID;
					$first_name = get_user_meta($user_id, 'first_name', true);
					$last_name = get_user_meta($user_id, 'last_name', true);
					$nickname = get_user_meta($user_id, 'nickname', true);
					$fullname = $first_name.' '.$last_name;
				
					$password = $this->random();
					wp_set_password( $password, $user_id );
					
					$changepassmail = $this->newPasswordMail();
					$changepassmail = str_replace( '#username#' ,$fullname, $changepassmail );
					$changepassmail = str_replace( '#password#' ,$password,$changepassmail );
					add_filter( 'wp_mail_content_type',array(&$this,'wp_set_content_type' ));
					
					wp_mail( $forgot_email, 'Your New Password', $changepassmail );
					
					remove_filter( 'wp_mail_content_type', array(&$this,'wp_set_content_type' ));
					?>
					<script>
						jQuery(document).ready(function(){
							jQuery("#welldoneapp_msg212").html('Please check your email for password retrieval');
						})
					</script>
					<?php
				}else{
					?>
					<script>
						jQuery(document).ready(function(){
							jQuery("#welldoneapp_msg212").html('Email not exists.');
						})
					</script>
					<?php
				}
			}
			if (  is_user_logged_in() ) {
				echo wp_redirect( home_url('/dashboard/') );
			}else{
				
			ob_start();
			?>
			<style>
			#g-recaptcha-response {
				display: block !important;
				position: absolute;
				margin: -78px 0 0 0 !important;
				width: 302px !important;
				height: 76px !important;
				z-index: -999999;
				opacity: 0;
			}
			.leftpading{
				padding-left:3em;
			}
			</style>
			<script src='https://www.google.com/recaptcha/api.js'></script>
			<div class="tw-bs container" >
				<div class="row">
					<div class="col-md-4 "></div>
					<div class="col-md-4 " style="border: 1px solid lightblue;padding: 1em;">
						<h3 style="text-align:center;">Login</h3>
						<div class="row">
							<div class="col-md-12" style="color:red;font-size: 15px;padding-bottom: 1em;text-align:center;" id="welldoneapp_msg212"></div>
						</div>
						<form method="post" action="" id="signin22_welldone_app">
							<div class="row">
								<div class="col-md-3"><b>Username:</b></div>
								<div class="col-md-9"><input type="text" name="gst_son_email" required class="form-control"></div>
							</div>
							<div class="row">
								<div class="col-md-3"><b>Password:</b></div>
								<div class="col-md-9"><input type="password" name="gst_son_pswd" required class="form-control"></div>
							</div>
							<div class="row">
								 <div class="col-md-10 leftpading">
									<div class="form-group">
										<!-- Replace data-sitekey with your own one, generated at https://www.google.com/recaptcha/admin -->
										<div class="g-recaptcha"
												   data-callback="capcha_filled"
												   data-expired-callback="capcha_expired"
												   data-sitekey="6LdprT4UAAAAADHPcnap3hBAjJpszFaCja5e_Cv6"></div>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-md-4 yezter_top"></div>
								<div class="col-md-8 yezter_top">
									<input type="submit" name="gst_son_submit" value="Sign In" class="btn btn-danger">
								</div>
							</div>
							<div class="row" style="clear:left;">
								<div class="col-md-12 yezter_top" style="text-align: center;">
									<a href="#!" onclick="form_aspk_replace();" style="">Forgot Username?/ Forgot Pass?</a>
								</div>
							</div>
							<div class="row" style="clear:left;">
								<div class="col-md-12 yezter_top" style="text-align: center;">
									<a href="<?php echo home_url().'/registration' ; ?>"  style="">Ready to Sign up?</a>
								</div>
							</div>
						</form><!-- end sign in div -->
						<div style="clear:left;display:none;" id="gst_forgot_soni">
							<form method="post" action="">
								<div class="row" style="clear:left;margin-top:3em">
									<div class="col-md-12 yezter_top">
										<input id="aspk_f_email" style="width: 100%;" required  class="form-control" type="email" style="" placeholder="EMAIL ADDRESS" name="email_adress_forgot">
									</div>
								</div>
								<div class="row">
									<div class="col-md-4 yezter_top"></div>
									<div class="col-md-8 yezter_top">
										<input type="submit" name="gst_forgot_submit" value="Reset" class="btn btn-danger">
									</div>
								</div>
								<div class="row">
									<div class="col-md-12 yezter_top" style="text-align: center;">
										<a href="#" id="signin_link" style="font-size: 18px;" onclick="aspk_signin_click()">SIGN IN</a>
									</div>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>
			 
			<script>
				function form_aspk_replace(){
					jQuery("#signin22_welldone_app").hide();
					jQuery("#gst_forgot_soni").show();
				}
				function aspk_signin_click(){
					jQuery("#signin22_welldone_app").show();
					jQuery("#gst_forgot_soni").hide();
				}
				window.onload = function() {
					var $recaptcha = document.querySelector('#g-recaptcha-response');

					if($recaptcha) {
						$recaptcha.setAttribute("required", "required");
					}
				};
			</script>
			<?php
			}
		}
		
		function invs_calculator(){
			ob_start();
			
			$link = get_permalink();
			?>
			<style>
			.topmargin{
				margin-top:2em;
			}
			.paddiv{
				padding:2em;
			}
			.bgcolor{
				background: rgba(128, 128, 128, 0.16);
			}
			</style>
			<div class="tw-bs container topmargin" >
				<form method="post" action="<?php echo $link.'#calculate'; ?>">
					<div class="row paddiv" style="background-color:rgba(52, 148, 212, 0.35); font-size:14px;font-weight:300; text-align:center; color:black;">
						<div class="col-md-12">
							<h3>Calculate Your Daily Interest for a Fixed Amount of Days</h3>
							<div class="row" >
								<div class="col-md-4">Initial Purchase Amount</div>
								<div class="col-md-4"><input required type="text" name="amount" value="1000" size="5" maxlength="8" ></div>
							</div>
							<div class="row" >
								<div class="col-md-4">Daily Interest Rate</div>
								<div class="col-md-4"><input type="text" name="inter_rate" value="1" size="5" maxlength="5" ></div>
							</div>
							<div class="row" >
								<div class="col-md-4">Length of Term (in days)</div>
								<div class="col-md-4"><input type="text" name="days" value="270" size="5" maxlength="3" ></div>
							</div>
							<div class="row" >
								<div class="col-md-4">Daily Reinvest Rate</div>
								<div class="col-md-4">
									<span>
										<select name="reinvperc" rows="6" >
											<option value="100" selected="">&nbsp;100&nbsp;</option>
											<option value="95">95</option>
											<option value="90">90</option>
											<option value="85">85</option>
											<option value="80">80</option>
											<option value="75">75</option>
											<option value="70">70</option>
											<option value="65">65</option>
											<option value="60">60</option>
											<option value="55">55</option>
											<option value="50">50</option>
											<option value="45">45</option>
											<option value="40">40</option>
											<option value="35">35</option>
											<option value="30">30</option>
											<option value="25">25</option>
											<option value="20">20</option>
											<option value="15">15</option>
											<option value="10">10</option>
											<option value="5">5</option>
											<option value="zero">0</option>
										</select>
									</span>
								</div>
							</div>
							<div class="row" >
								<div class="col-md-10 paddiv">
									<font size="2"><a href="">Reset Values</a></font> <input name="invs_calculate" type="SUBMIT" value="Calculate NOW!" class="btn btn-primary">
								</div>
							</div>
						</div>
					</div>
				</form>
			</div>
			<?php
			
			if(isset($_POST['invs_calculate']) ){
				$total_principal = $_POST['amount'];
				$inv_rate = $_POST['inter_rate'];
				$re_inv_rate = $_POST['reinvperc'];
				$days = $_POST['days'];
				
				?>
				<div class="tw-bs container paddiv bgcolor" >
					<div class="row">
						<div class="col-md-1"><b>Days</b></div>
						<div class="col-md-2"><b>Earnings</b></div>
						<div class="col-md-2"><b>Reinvest</b></div>
						<div class="col-md-2"><b>Cash Out</b></div>
						<div class="col-md-3"><b>TOTAL Principal</b></div>
						<div class="col-md-2"><b>Date</b></div>
					</div>
					<?php
					$Date = date('Y-m-d');
					for( $i = 1 ; $i <= $days ; $i++ ){
						
						$releaseDate = date('Y-m-d', strtotime($Date. ' + '. $i .'days'));
						
						$inter_profit = $total_principal/100 * $inv_rate;
						$inter_profit = round( $inter_profit , 2 );
						$reinvest_earning = $inter_profit/100 * $re_inv_rate; 
						$reinvest_earning = round( $reinvest_earning , 2 );
						$cashout = $inter_profit - $reinvest_earning; 
						$cashout = round( $cashout , 2 );
						$total_principal = $total_principal + $reinvest_earning;
						$total_principal = round( $total_principal , 2 );
						?>
						<div class="row">
							<div class="col-md-1"><?php echo $i; ?></div>
							<div class="col-md-2"><?php echo $inter_profit ; ?></div>
							<div class="col-md-2"><?php echo $re_inv_rate.'%' ; ?></div>
							<div class="col-md-2"><?php echo $cashout ; ?></div>
							<div class="col-md-3"><?php echo $total_principal ; ?></div>
							<div class="col-md-2"><?php echo $releaseDate ; ?></div>
						</div>
						<?php 
					} ?>
				</div> <?php
			}
			
			$html = ob_get_clean();
			return $html;
		}
		
		function rec($id,$inc,$p_amount,$mychild_user_id){
			global $wpdb;
			
				
			$sql = "SELECT refer_uid FROM {$wpdb->prefix}investment_users WHERE uid='{$id}' ORDER BY  id DESC";
			$refferid =  $wpdb->get_var($sql);
			
			//for get user level
			$sql = "SELECT refer_uid FROM {$wpdb->prefix}investment_users WHERE uid='{$mychild_user_id}' ORDER BY  id DESC";
			$referral_uid =  $wpdb->get_var($sql);
		   
		    // get user level
			$sql = "SELECT level FROM {$wpdb->prefix}investment_users WHERE uid = '{$referral_uid}' ";
			$userLevel =  $wpdb->get_var($sql);
			$user_id = $id;
			$date = date('Y-m-d');
			
			if( $refferid ){
				if( $userLevel == 'affiliate' ){
					if( $inc == 1 ){
						$profitRate = 3;
					}elseif( $inc == 2 ){
						$profitRate = 1.5;
					}else{
						$profitRate = 0;
					}
					
					$ref_amount = ($p_amount/100) * $profitRate;
					
					$sql = "INSERT INTO {$wpdb->prefix}log_referred_amount (user_id,reffer_uid,ref_amount,referred_date,status) VALUES('{$user_id}','{$refferid}','{$ref_amount}','{$date}','1' )";
					$wpdb->query($sql);
					
					if($inc<2){
						$inc+=1;
						$this->rec($refferid,$inc,$p_amount,$mychild_user_id);
					}
				}elseif( $userLevel == 'gold' ){
					if( $inc == 1 ){
						$profitRate = 4;
					}elseif( $inc == 2 ){
						$profitRate = 2;
					}elseif( $inc == 3 ){
						$profitRate = 1;
					}
					
					$ref_amount = ($p_amount/100) * $profitRate;
					// give points
					
					$sql = "INSERT INTO {$wpdb->prefix}log_referred_amount (user_id,reffer_uid,ref_amount,referred_date,status) VALUES('{$user_id}','{$refferid}','{$ref_amount}','{$date}','1'  )";
					$wpdb->query($sql);
					 
					if($inc<3){
						$inc+=1;
						$this->rec($refferid,$inc,$p_amount,$mychild_user_id);
					}
				}
			}
		}
		
		function custom_process_order($order_id) {
			global $wpdb;
			
			$order = new WC_Order( $order_id );
			$myuser_id = (int)$order->user_id;
			$user_info = get_userdata($myuser_id);
			$items = $order->get_items();
			
			foreach ($items as $item) {
				$productID = $item['product_id'];
				$product = wc_get_product( $productID );
				$productTitle = $product->get_title();
				$totalPrice = $item['total'];
				$min_amount = get_post_meta($productID, '_hypertext_min_amount', true);
				$max_amount = get_post_meta($productID, '_hypertext_max_amount', true);
				$inv_rate = get_post_meta($productID, '_hypertext_inv_rate', true);
				$no_days = get_post_meta($productID, '_hypertext_no_of_days', true);
				$date = date('Y-m-d');
				
				$sql = "SELECT p_orderID FROM {$wpdb->prefix}investments WHERE p_orderID = '{$order_id}' ORDER BY  invest_id DESC";
				$p_orderID =  $wpdb->get_var($sql);
				/* if( empty( $p_orderID ) ){
					$sql = "INSERT INTO {$wpdb->prefix}investments(userid,p_orderID,amount,no_of_days,invest_rate,created_date,status,invest_type,invest_title) VALUES('{$myuser_id}','{$order_id}','{$totalPrice}','{$no_days}','{$inv_rate}','{$date}','1','invest' , '{$productTitle}' )";
					$wpdb->query($sql);
					//$lastid = $wpdb->insert_id;
					$this->rec($myuser_id,1,$totalPrice);
				} */
				$sql = "INSERT INTO {$wpdb->prefix}investments(userid,p_orderID,amount,no_of_days,invest_rate,created_date,status,invest_type,invest_title) VALUES('{$myuser_id}','{$order_id}','{$totalPrice}','{$no_days}','{$inv_rate}','{$date}','1','invest' , '{$productTitle}' )";
				$wpdb->query($sql);
				//$lastid = $wpdb->insert_id;
				
				//get referid
				$this->rec($myuser_id,1,$totalPrice,$myuser_id);
			}
			return $order_id;
		}
		
		function checkLogin(){
			if ( is_user_logged_in() ) {
				//nothing
			}else{
				echo wp_redirect( home_url('/login') );
			}
		}
		
		function refferalEmail(){
			ob_start();
			?>
			<div id=":au" class="ii gt ">
				<div id=":b7" class="a3s aXjCH m16069246ea9540fa">
					<div class="adM">
					</div><u></u>
					<div marginwidth="0" marginheight="0" style="width:100%;margin:0;padding:0;background-color:#f5f5f5;font-family:Helvetica,Arial,sans-serif">
					<div id="m_-2785494934782886297top-orange-bar" style="display:block;height:5px;background-color:rgb(26, 85, 145);"></div>
					<center>
					<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" class="m_-2785494934782886297background-table">
					<tbody><tr>
					<td align="center" valign="top" style="border-collapse:collapse">
					<table border="0" cellpadding="0" cellspacing="0" width="638" id="m_-2785494934782886297main-table">
					<tbody><tr>
					<td align="center" valign="top" height="20" style="border-collapse:collapse">
					</td>
					</tr>
					<tr>
					<td align="center" valign="top" style="border-collapse:collapse"><table width="100%" border="0">
					<tbody><tr>
					<td width="127" align="center" style="border-collapse:collapse"><a href="<?php echo home_url(); ?>"  ><img src="http://ulynd.com/wp-content/uploads/2018/01/logoinvs-small.png" alt="Zendesk Chat"   border="0" class="m_-2785494934782886297logo CToWUd" style="border:0;height:auto;line-height:120%;outline:none;text-decoration:none;color:#d86c00;padding-left:10px;font-family:Helvetica,Arial,sans-serif;font-size:25px;vertical-align:text-bottom;text-align:center"></a></td>
					</tr>
					<tr>
					<td align="center" colspan="3" class="m_-2785494934782886297email-title" style="border-collapse:collapse;font-family:Helvetica,Arial,sans-serif;font-size:30px;font-weight:bold;line-height:120%;color:rgb(82,82,82);text-align:center;zoom:1">Dear #username#</td>
					</tr>
					</tbody></table></td>
					</tr>
					<tr>
						<td class="m_-2785494934782886297white-space m_-2785494934782886297headnote" align="center" style="padding: 2em;border-collapse:collapse;background-color:rgb(255,255,255);border-color:rgb(221,221,221);border-width:1px;border-radius:5px;border-style:solid;padding:12px;font-size:13px;line-height:2em;color:rgb(119,119,119);text-align:justify"><p>Welcome to Ulynd.com.</p>Registered by clicking the link below and earn money.</p>
							<p><a href="#yezter_link#">Referral Link</a> </p>
							<p>Best Regards,</p>
							<p>Ulynd.com Team.</p>
						</td>
					</tr>

					</tbody></table>
					</td>
					</tr>
					<tr>
					<td align="center" valign="top" height="30" style="border-collapse:collapse"></td>
					</tr>
					</tbody></table>
					</td>
					</tr>
					</tbody></table>
					</center><div class="yj6qo"></div><div class="adL">
					</div></div><div class="adL">
					</div>
				</div>
			</div>
			<?php
			$html = ob_get_clean();
			return $html;
		}
		
		// Add cron interval of  daily
		function addCronMinutes($array) {
			
			$schedules['twise_daily'] = array(
			'interval' => 43200, 
			'display' => __( 'Twice Daily' , 'textdomain' )
			);
			
			/* $schedules['every_three_minutes'] = array(
				'interval'  => 43200,
				'display'   => __( 'Every 3 Minutes', 'textdomain' )
			); */
			return $schedules;

		}
		
		function my_task_function(){
			global $wpdb;
			
			// add interests in investment daily 
			$sql = "SELECT * FROM {$wpdb->prefix}investments WHERE status=1";
			$results =  $wpdb->get_results($sql); 
			
			if( count( $results ) > 0 ){
				foreach( $results as $rs ){
					$invest_id = $rs->invest_id; 
					$no_ofdays = $rs->no_of_days;
					$inv_rate = $rs->invest_rate;
					$re_inv_rate = $rs->re_invest_rate;
					$p_amount = $rs->amount;
					$UserID = $rs->userid;
					$date = date('Y-m-d');
					$days = 1;
					
					//check all investments update_user_meta
					$sql = "SELECT days FROM {$wpdb->prefix}all_interest WHERE invest_id = '{$invest_id}' AND user_id = '{$UserID}'  ORDER BY  interest_id DESC ";
					$totdays = $wpdb->get_var($sql);
					if( $totdays == $no_ofdays ){
						//nothing
					}else{
					
						// get interest log from interest table_name
						$sql = "SELECT * FROM {$wpdb->prefix}all_interest WHERE invest_id = '{$invest_id}' AND user_id = '{$UserID}' AND status='active' ORDER BY  interest_id DESC ";
						$row_interests =  $wpdb->get_row($sql);
						if( count( $row_interests ) > 0 ){
							$total_principal = $row_interests->total_int_principal;
							
							$date_now = date('Y-m-d');

							$date_convert = $row_interests->interest_date;
							
							/* //will remove after testing
							$date_now = date('Y-m-d', strtotime($date_convert. ' + '. 1 .'days'));
							$date = $date_now;
							//end will remove after test */
							alog('date_convert_111',$date_convert,__FILE__,__LINE__);
							//insert when next day come
							if ($date_now > $date_convert) {
							alog('date_convertyess',$date_convert,__FILE__,__LINE__);
								$inter_profit = $total_principal/100 * $inv_rate;
								$inter_profit = round( $inter_profit , 2 );
								$reinvest_earning = $inter_profit/100 * $re_inv_rate; 
								$reinvest_earning = round( $reinvest_earning , 2 );
								//$reinvest_earning = $inter_profit/100 * 100; // 100 % reinvest because reinvest is not defined
								$cashout = $inter_profit - $reinvest_earning; 
								$cashout = round( $cashout , 2 );
								//$cashout = $inter_profit ;  // cashout is also 100 % because reinvest is not defined
								$total_principal_amount = $total_principal + $reinvest_earning;
								$total_principal_amount = round( $total_principal_amount , 2 );
								
								$days = $days + $totdays;
								
								if( $no_ofdays == $days ){ 
									$sql = "UPDATE {$wpdb->prefix}all_interest SET status = 'deactive' WHERE invest_id = '{$invest_id}' ";
									$wpdb->query($sql);
									
									//update investments status 
									$sql = "UPDATE {$wpdb->prefix}investments SET status = '0' WHERE invest_id = '{$invest_id}' ";
									$wpdb->query($sql);
									
									// get total profit of investment
									$sql = "SELECT SUM(total_int_principal) as totamount FROM {$wpdb->prefix}all_interest WHERE invest_id='{$invest_id}' AND user_id = '{$UserID}' AND status = 'deactive'";
									$tot_amount_profit =  $wpdb->get_var($sql); 
									$tot_amount_profit = round( $tot_amount_profit , 2 );
									
									// get negative amount from montly subscribtion if not give then deduct from their investments
									$tot_neg_subcAmount = $tot_amount_profit;
									$sql = "SELECT * FROM {$wpdb->prefix}investment_customers_subscribtion WHERE status='0' AND user_id={$UserID} order by id ASC ";
									$al_neg_subsc = $wpdb->get_results($sql);
									if( count( $al_neg_subsc ) > 0 ){
										foreach($al_neg_subsc as $neg_subc){
											$subsc_ID = $neg_subc->id;
											$subsc_date = $neg_subc->subscribe_date;
											$subc_user_id = $neg_subc->user_id;
											if( $tot_neg_subcAmount > $neg_subc->amount ){
												$sql = "UPDATE {$wpdb->prefix}investment_customers_subscribtion SET status = '1' , deducted_from = 'investments'  WHERE id = '{$subsc_ID}' AND user_id = '{$subc_user_id}' ";
												$wpdb->query($sql);
												$tot_neg_subcAmount = $tot_neg_subcAmount - $neg_subc->amount ;
												//alog('tot_neg_subcAmount',$tot_neg_subcAmount,__FILE__,__LINE__);
											}else{
												break;
											}
											
										}
									}
									//end code that is used for deduct amount from their investments
									
									//insert in total_Investment_balance
									$sql = "INSERT INTO {$wpdb->prefix}total_Investment_balance(investment_id,user_id,tot_invs_amount,created_date,status,amount_type ) VALUES('{$invest_id}','{$UserID}','{$tot_amount_profit}','{$date}','1' , 'credit' )";
									$wpdb->query($sql);
								}else{
									$sql = "INSERT INTO {$wpdb->prefix}all_interest(invest_id,days,interest_date,earning,cashout,total_int_principal,status,user_id ) VALUES('{$invest_id}','{$days}','{$date}','{$inter_profit}','{$cashout}','{$total_principal_amount}','active','{$UserID}' )";
									$wpdb->query($sql);
								}
							}else{
								// can not insert on the same day when already inserted 
							}
							//end if current date statement
						}else{
							$days = $days + $totdays;
							if( $no_ofdays == $days){
								$sql = "UPDATE {$wpdb->prefix}all_interest SET status = 'deactive' WHERE invest_id = '{$invest_id}' ";
								$wpdb->query($sql);
								//update investments status 
								$sql = "UPDATE {$wpdb->prefix}investments SET status = '0' WHERE invest_id = '{$invest_id}' ";
								$wpdb->query($sql);
								
								// get total profit of investment
								$sql = "SELECT SUM(total_int_principal) as totamount FROM {$wpdb->prefix}all_interest WHERE invest_id='{$invest_id}' AND user_id = '{$UserID}' AND status = 'deactive'";
								$tot_amount_profit =  $wpdb->get_var($sql); 
								$tot_amount_profit = round( $tot_amount_profit , 2 );
								//insert in total_Investment_balance
								$sql = "INSERT INTO {$wpdb->prefix}total_Investment_balance(investment_id,user_id,tot_invs_amount,created_date,status,amount_type ) VALUES('{$invest_id}','{$UserID}','{$tot_amount_profit}','{$date}','1' , 'credit' )";
								$wpdb->query($sql);
							}else{
								$inter_profit = $p_amount/100 * $inv_rate;
								$inter_profit = round( $inter_profit , 2 );
								$reinvest_earning = $inter_profit/100 * $re_inv_rate; 
								$reinvest_earning = round( $reinvest_earning , 2 );
								//$reinvest_earning = $inter_profit/100 * 100; // 100 % reinvest because reinvest is not defined
								$cashout = $inter_profit - $reinvest_earning; 
								$cashout = round( $cashout , 2 );
								//$cashout = $inter_profit ;  // cashout is also 100 % because reinvest is not defined
								$total_principal_amount = $p_amount + $reinvest_earning;
								$total_principal_amount = round( $total_principal_amount , 2 );
								
								$sql = "INSERT INTO {$wpdb->prefix}all_interest(invest_id,days,interest_date,earning,cashout,total_int_principal,status,user_id ) VALUES('{$invest_id}','{$days}','{$date}','{$inter_profit}','{$cashout}','{$total_principal_amount}','active','{$UserID}' )";
								$wpdb->query($sql);
							}
						}
					}
				}
			}
			// end crown job for putting interest in investment daily
			
			/* Monthly Subscribtion crown job */
			$this->my_task_hook_monthly_function();
		}
		
		function countTotalinvestments($userid){
			global $wpdb;
			
			$sql = "SELECT SUM( amount ) FROM {$wpdb->prefix}investments WHERE userid = '{$userid}' AND status='0' ";
			return $wpdb->get_var($sql);
		}
		
		function totReferralamount($userid){
			global $wpdb;
			
			$sql = "SELECT SUM( ref_amount ) FROM {$wpdb->prefix}log_referred_amount WHERE reffer_uid = '{$userid}' AND status='1'";
			return $wpdb->get_var($sql);
		}
		
		function totalInvestedbalance($userid){
			global $wpdb;
			
			$sql = "SELECT SUM( tot_invs_amount ) FROM {$wpdb->prefix}total_Investment_balance WHERE user_id = '{$userid}' AND amount_type = 'credit' ";
			return $wpdb->get_var($sql);
		}
		
		function withDrawRequestMail(){
			ob_start();
			?>
			<div id=":au" class="ii gt "><div id=":b7" class="a3s aXjCH m16069246ea9540fa"><div class="adM">
			</div><u></u>
			<div marginwidth="0" marginheight="0" style="width:100%;margin:0;padding:0;background-color:#f5f5f5;font-family:Helvetica,Arial,sans-serif">
			<div id="m_-2785494934782886297top-orange-bar" style="display:block;height:5px;background-color:rgb(26, 85, 145);"></div>
			<center>
			<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" class="m_-2785494934782886297background-table">
			<tbody><tr>
			<td align="center" valign="top" style="border-collapse:collapse">
			<table border="0" cellpadding="0" cellspacing="0" width="638" id="m_-2785494934782886297main-table">
			<tbody><tr>
			<td align="center" valign="top" height="20" style="border-collapse:collapse">
			</td>
			</tr>
			<tr>
			<td align="center" valign="top" style="border-collapse:collapse"><table width="100%" border="0">
			<tbody><tr>
			<td width="127" align="center" style="border-collapse:collapse"><a href="<?php echo home_url(); ?>"  ><img src="http://ulynd.com/wp-content/uploads/2018/01/logoinvs-small.png" alt="Zendesk Chat"   border="0" class="m_-2785494934782886297logo CToWUd" style="border:0;height:auto;line-height:120%;outline:none;text-decoration:none;color:#d86c00;padding-left:10px;font-family:Helvetica,Arial,sans-serif;font-size:25px;vertical-align:text-bottom;text-align:center"></a></td>
			</tr>
			</tbody></table></td>
			</tr>
			<tr>
				<td class="m_-2785494934782886297white-space m_-2785494934782886297headnote" align="center" style="padding: 2em;border-collapse:collapse;background-color:rgb(255,255,255);border-color:rgb(221,221,221);border-width:1px;border-radius:5px;border-style:solid;padding:12px;font-size:13px;line-height:2em;color:rgb(119,119,119);text-align:justify"><p>Welcome to Ulynd.com.</p><p>#username# has request to Withdrawal money $#money# </p>
					<p>Best Regards,</p>
					<p>Ulynd.com Team.</p>
				</td>
			</tr>

			</tbody></table>
			</td>
			</tr>
			<tr>
			<td align="center" valign="top" height="30" style="border-collapse:collapse"></td>
			</tr>
			</tbody></table>
			</td>
			</tr>
			</tbody></table>
			</center><div class="yj6qo"></div><div class="adL">
			</div></div><div class="adL">
			</div></div></div>
			<?php
			$html = ob_get_clean();
			return $html;
		}
		
		function dashboard(){
			global $wpdb;
			
			ob_start();
			if ( is_user_logged_in() ) {
				$userid = get_current_user_id();
				
				//total count investments
				$tot_amountInvestments = $this->countTotalinvestments($userid);
				
				// total invested balance earn
				$all_inv_profits = $this->totalInvestedbalance($userid);
				
				//total referral amounts
				$total = $this->totReferralamount($userid);
				$total = round( $total , 2 );
				
				$sql = "SELECT SUM( withdraw_amount ) FROM {$wpdb->prefix}request_withdrawal WHERE user_id = '{$userid}' AND status !='Reject' ";
				$total_req_amount = $wpdb->get_var($sql);
				$total_req_amount = round($total_req_amount , 2 );
				
				$all_inv_profits = round( $all_inv_profits , 2 );
				// get total negative monthly subscribtion balance if exist and deduct from total amount
				$sql = "SELECT SUM(amount) FROM {$wpdb->prefix}investment_customers_subscribtion WHERE status='1' AND user_id={$userid} AND deducted_from = 'investments' ";
				$totnegativeSubc = $wpdb->get_var($sql);
				$totnegativeSubc = round($totnegativeSubc , 2 );
				$all_inv_profits = $all_inv_profits - $totnegativeSubc;
				//end code that is used for deduction amount
				
				$totalwithdrawmoney = $all_inv_profits + $total;
				$totalwithdrawmoney = round($totalwithdrawmoney , 2 );
				
				// Gross Profit
				$grossProfit = $totalwithdrawmoney - $tot_amountInvestments;
				// end 
				
				// minus reinvest amount from total balance
				$sql = "SELECT SUM( amount ) FROM {$wpdb->prefix}investments WHERE userid = '{$userid}' AND invest_type = 're_invest' ";
				$tot_amount_reinvestments = $wpdb->get_var($sql);
				
				$totalAmount = $totalwithdrawmoney - $total_req_amount;
				$totalAmount = $totalAmount - $tot_amount_reinvestments;
				$totalAmount = round($totalAmount , 2 );
				$date = date('Y-m-d');
				if(isset($_POST['submitmoney'])){
					$amount = $_POST['hyp_amount'];
					$gateway = $_POST['selectgate'];
					$nickname = get_user_meta($userid, 'nickname', true);
					$f_name = get_user_meta($userid, 'first_name', true);
					$l_name = get_user_meta($userid, 'last_name', true);
					$name = $f_name.' '.$l_name ;
					
					if (! is_numeric($amount)) {
						echo '<h1 style="color:red;padding:2em;" class="form-group">Please enter valid amount</h1>';
					}else{
						$deduct_amount = $total_req_amount + $amount;
						if( $totalAmount >= $deduct_amount ){
							add_filter( 'wp_mail_content_type',array(&$this,'wp_set_content_type' ));
							//$adminemail = 'jamesallen.hts@gmail.com';
							$adminemail =  get_option( 'admin_email' );
							
							$requestWithDrawn = $this->withDrawRequestMail();
							$requestWithDrawn = str_replace( '#username#' ,$name, $requestWithDrawn );
							$requestWithDrawn = str_replace( '#money#' ,$amount,$requestWithDrawn );
							wp_mail( $adminemail, 'Your withdrawal request has been received', $requestWithDrawn );							
							remove_filter( 'wp_mail_content_type', array(&$this,'wp_set_content_type' ));
							
							$sql = "INSERT INTO {$wpdb->prefix}request_withdrawal(user_id,withdraw_amount,pay_gateway,created_date,status ) VALUES('{$userid}','{$amount}','{$gateway}','{$date}','pending' )";
							$wpdb->query($sql);
							echo '<h1 style="color:green;padding:2em;" class="form-group">Request has been sent successfully to admin</h1>';
						}else{
							echo '<h1 style="color:green;padding:2em;" class="form-group">Insufficient funds to withdraw</h1>';
						}
					}
				}
				// Reinvest Money
				if(isset($_POST['submitreinvest'])){
					$reinvestAmount = $_POST['hyp_reinvest'];
					if (! is_numeric($reinvestAmount)) {
						echo '<h1 style="color:red;padding:2em;" class="form-group">Please enter valid amount</h1>';
					}elseif ( $reinvestAmount % 10 !== 0) {
						echo '<h1 style="color:red;padding:2em;" class="form-group">Please enter amount that is divisible by 10</h1>';
					}elseif( $reinvestAmount < 10 || $reinvestAmount > $totalAmount ){ 
						echo '<h1 style="color:red;padding:2em;" class="form-group">Please enter that amount that is within the investment range</h1>';
					}else{
						if( $reinvestAmount > 9 && $reinvestAmount < 99 ){
							$nodays = 270 ;
							$invRate = 0.7 ; 
							//$inv_title = 'Reinvestment';
							$inv_title = 'Silver ';
						}elseif( $reinvestAmount > 99 && $reinvestAmount < 999 ){
							$nodays = 270 ;
							$invRate = 0.6 ; 
							//$inv_title = 'Silver';
							$inv_title = 'Silver';
						}elseif( $reinvestAmount > 999 && $reinvestAmount < 4999 ){
							$nodays = 210 ;
							$invRate = 0.1 ; 
							//$inv_title = 'Gold';
							$inv_title = 'Silver';
						}elseif( $reinvestAmount > 4999 && $reinvestAmount < 20000 ){
							$nodays = 150 ;
							$invRate = 0.6 ; 
							//$inv_title = 'Platinum';
							$inv_title = 'Silver';
						}else{
							$nodays = 150 ;
							$invRate = 0.6 ; 
							//$inv_title = 'Platinum';
							$inv_title = 'Silver';
						}
						$sql = "INSERT INTO {$wpdb->prefix}investments(userid,amount,no_of_days,invest_rate,created_date,status,invest_type,invest_title) VALUES('{$userid}','{$reinvestAmount}','{$nodays}','{$invRate}','{$date}','1','re_invest' , '{$inv_title}' )";
						$wpdb->query($sql);
						//$lastid = $wpdb->insert_id;
						$this->rec($userid,1,$reinvestAmount,$userid);
						echo '<h1 style="color:green;padding:2em;" class="form-group">Your amount Reinvested successfully.</h1>';
					}
				}
				//Refer to friend handle
				if( isset($_POST['send_email_friend']) ){
					$email = $_POST['email_friend'];
					if( !empty($email) ){
						$provider_user_info = get_userdata($userid);
						$email_current = $provider_user_info->data->user_email;
						if( $email == $email_current ){
							echo '<h4 style="color:red;">You cannot enter to your own email address</h4>';
						}else{
							$emailText = $this->refferalEmail();
							$emailText = str_replace( '#username#' ,$email, $emailText );
							$emailText = str_replace( '#yezter_link#' , home_url().'/registration?referID=referalid-'.$userid, $emailText );
							
							add_filter( 'wp_mail_content_type',array(&$this,'wp_set_content_type' ));
							wp_mail( $email, 'Referral link', $emailText  );
							remove_filter( 'wp_mail_content_type', array(&$this,'wp_set_content_type' ));
							
							echo '<h1 style="color:green;padding:2em;" class="form-group">Referral done successfully</h1>';
						}
					}else{
						echo '<h1>Email required</h1>';
					}
				}
				
				//update  use information
				if( isset($_POST['hypsaveInfo']) ){
					$stripeToken = $_POST['stripeToken'];
					$urpaypalId = $_POST['ur_paypal_id'];
					update_user_meta($userid, 'first_name', $_POST['hyp_fname'] );
					update_user_meta($userid, 'last_name', $_POST['hyp_lname'] );
					update_user_meta($userid, 'user_cell_num', $_POST['phnNumber'] );
					update_user_meta($userid, 'nickname', $_POST['userName'] );
					
					if(!empty($stripeToken) ){
						require_once(ABSPATH.'wp-content/stripe-php/init.php');
						
						$provider_user_info = get_userdata($userid);
						$user_email = $provider_user_info->data->user_email;
					
						$secret_key = get_option( 'yezter_secret_key' );
						$stripe_api_key = $secret_key;               
						\Stripe\Stripe::setApiKey($stripe_api_key);

						$customer = \Stripe\Customer::create(array(
							'email' => $user_email, // customer email id
							'card'  => $stripeToken
						));
						$customID = $customer->id;
						
						$sql = "UPDATE {$wpdb->prefix}investment_users SET  customerID = '{$customID}' WHERE uid = '{$userid}' ";
						$wpdb->query($sql);
					}

					$sql = "UPDATE {$wpdb->prefix}investment_users SET address = '{$_POST["hypaddress1"]}' , address2  = '{$_POST["hypaddress2"]}' , date_of_birth = '{$_POST["dateOfBirth"]}' , paypal_id = '{$urpaypalId}' , bank_wallet = '{$_POST["hypbitAccount"]}' WHERE uid = '{$userid}' ";
					$wpdb->query($sql);
				}
				
				
			?>
			<style>
				.borderRightpad{
					border-right:1px solid black;
					padding:1em;
				}
				.padd1em{
					padding:1em;
				}
				.whitecolor{
					color:black;
				}
				.padtopbtom{
					padding-top:2em;
					padding-bottom:1em;
				}
				.textcenter{
					text-align:center;
				}
			</style>
			<div class="tw-bs container2 padtopbtom" >
				<div class="row">
					<div class="col-md-2">
						<div style="padding: 1em; border: 1px solid #e6b05a;border-radius: 10px;">
							<h5 class="textcenter">Total Invested</h5>
							<h3 class="textcenter">
							<?php 
							if( $tot_amountInvestments ){
								?><b>$<?php echo $tot_amountInvestments ;  ?></b><?php
							}else{
								?>$0<?php
							}
							?>
							</h3>
						</div>
					</div>
					<div class="col-md-3">
						<div style="padding: 1em; border: 1px solid #e6b05a;border-radius: 10px;">
						<h5 class="textcenter">Total Interest Earned</h5>
						<h3 class="textcenter">
						<?php
						if($all_inv_profits){
						?><b>$<?php echo  $all_inv_profits;  ?></b><?php
						}else{
							?>$0<?php
						}
						?>
						</h3>
						</div>
					</div>
					<div class="col-md-3">
						<div style="padding: 1em; border: 1px solid #e6b05a;border-radius: 10px;">
							<h5 class="textcenter">Total Affiliate Income</h5>
							<h3 class="textcenter">
							<?php
							if($total){
							?><b>$<?php echo round( $total , 2 );  ?></b><?php
							}else{
								?>$0<?php
							}
							?>
							</h3>
						</div>
					</div>
					<div class="col-md-2">
						<div style="padding: 1em; border: 1px solid #e6b05a;border-radius: 10px;">
							<h5 class="textcenter">Total Gross Profit</h5>
							<h3 class="textcenter">
								<?php
								if($grossProfit){
								?><b>$<?php echo $grossProfit;  ?></b><?php
								}else{
									?>$0<?php
								}
								?>
							</h3>
						</div>
					</div>
					<div class="col-md-2">
						<button type="button" class="" data-toggle="modal" data-target="#myProfile_<?php echo $userid; ?>">Update Profile</button>
					</div>
				</div>
				<?php $this->profileInfo($userid); ?>
				<!--
				<div class="row">
					<div class="col-md-2">
						<?php 
						if( $tot_amountInvestments ){
							?><b>$<?php echo $tot_amountInvestments ;  ?></b><?php
						}else{
							?>$0<?php
						}
						?>
					
					</div>
					<div class="col-md-3">
						<?php
						if($all_inv_profits){
						?><b>$<?php echo  $all_inv_profits;  ?></b><?php
						}else{
							?>$0<?php
						}
						?>
					</div>
					<div class="col-md-3">
						<?php
						if($total){
						?><b>$<?php echo round( $total , 2 );  ?></b><?php
						}else{
							?>$0<?php
						}
						?>
					</div>
					<div class="col-md-3">
						<?php
						if($grossProfit){
						?><b>$<?php echo $grossProfit;  ?></b><?php
						}else{
							?>$0<?php
						}
						?>
					</div>
				</div>-->
				<!-- show all  products investment -->
				<div class="row">
					<div class="col-md-12 padtopbtom" ><h2>Start a new investment </h2></div>
				</div>
				<?php
				$this->showAllProducts();
				?>
				<!-- End all  products investment -->
				<div class="row">
					<div class="col-md-12 padtopbtom" ><h2>Ulynd Wallet Balance – Reinvest / Withdraw</h2></div> 
				</div>
				<div class="row">
					<div class="col-md-2">
						<h4 style="padding-top: 1.5em;" >Balance:</h4> 
					</div>
					<div class="col-md-2">
						<h4 style="padding-top: 1.5em;"><?php echo $totalAmount; ?></h4>
					</div>
					<div class="col-md-1"><h4 style="padding-top: 1.5em;">Reinvest</h4></div>
					<div class="col-md-1">
						<span id="hideReinterest" ><img style="width:4em;" onclick="show_Reinvest();" src="<?php echo plugins_url('img/downn.png', __FILE__); ?>"></span>
						<span id="showReinterest" style="display:none;"><img style="width:4em;" onclick="hide_Reinvest();" src="<?php echo plugins_url('img/up.png', __FILE__); ?>"></span>
					</div>
					<div class="col-md-1"><h4 style="padding-top: 1.5em;">Withdraw</h4></div>
					<div class="col-md-1">
						<span id="hidewithDraw" ><img style="width:4em;" onclick="show_withdraw();" src="<?php echo plugins_url('img/downn.png', __FILE__); ?>"></span>
						<span id="showwithDraw" style="display:none;"><img style="width:4em;" onclick="hide_withdraw();" src="<?php echo plugins_url('img/up.png', __FILE__); ?>"></span>
					</div>
				</div>
				<div class="row">
					<div class="col-md-12"> <?php $this->viewWithDraw_detailed($userid); ?> </div>
				</div>
				<!-- Show WithDraw form -->
				<div class="row" id="divWithshow" style="background-color:rgba(128, 128, 128, 0.23);padding:2em;display:none;margin-top:2em;">
					<div class="col-md-12">
						<?php
						if(!empty( $total_req_amount) ){
							$total_requests = $total_req_amount ;  ?>
						<?php
						}else{
							$total_requests = 0;
						}
						?>
						<div class="row">
							<div class="col-md-5">
								<h4>Withdrawn Amount: <?php echo $total_requests; ?></h4>
								<h4>Total Amount: <?php echo $totalAmount; ?></h4>
							</div>
							<div class="col-md-5">
								<button type="button" class="" data-toggle="modal" data-target="#withdraw_detail_<?php echo $userid; ?>">View WithDraw Detail</button>
							</div>
						</div>
						<?php
						if( $grossProfit > 9 ){
						?>
						<p style="color: #2991d6; padding-top: 1em;font-size: 17px;font-weight: bold;">Congratulations!  You have enough balance to withdraw!</p>
						<h5>Enter amount that you want to Withdraw</h5>
						<form method="post" action="" >
							<div class="row">
								<div class="col-md-12">
									<input type="number" class="form-control" name="hyp_amount" required placeholder="Enter  amount">
								</div>
							</div>
							<div class="row">
								<div class="col-md-12" style="padding-top: 1.5em;">
									<p><b>Choose gateway where you want to withdraw </b></p>
									<p><input type="radio" name="selectgate" value="bank">&nbsp;Bank Account</p>
									<p><input type="radio" name="selectgate" checked value="paypal">&nbsp;Paypal</p>
									<p><input type="radio" name="selectgate" value="bitcoin">&nbsp;Bitcoin Wallet</p>
								</div>
							</div>
							<div class="row" style="padding-top:1em;">
								<div class="col-md-12"><input type="submit" class="btn btn-primary" name="submitmoney" value="Send"></div>
							</div>
						</form>
						<?php
						}else{ ?>
							<p style="color:red;">Sorry!  You have not enough balance to withdraw!</p>
							<?php
						}
						?>
					</div>
				</div>
				<!-- end WithDraw form -->

				<!-- Show Reinvest form -->
				<div class="row" id="divReinvest" style="background-color:rgba(128, 128, 128, 0.23);padding:2em;display:none;margin-top:2em;">
					<div col="md-12">
						<h3>Reinvest</h3>
						<?php
						if(!empty($all_inv_profits)){
						?>
						<p>Congratulations!  You have enough for a reinvestment!</p>
						<p class="form-group">Investment Range <b>($10 - <?php echo $totalAmount; ?>%)</b></p>
						<form method="post" action="" >
							<div class="row">
								<div class="col-md-12">
									<input type="number" class="form-control" name="hyp_reinvest" required placeholder="Enter  amount">
								</div>
							</div>
							<div class="row" style="padding-top:1em;">
								<div class="col-md-12"><input type="submit" class="btn btn-primary" name="submitreinvest" value="Send"></div>
							</div>
						</form>
						<?php
						}else{ ?>
							<p style="color:red;">Sorry!  You have not enough balance to Reinvest!</p>
							<?php
						}
						?>
					</div>
				</div>
				<!-- End Reinvest form -->
				
				<!-- start Current investment  -->
				<div class="row">
					<div class="col-md-12 padtopbtom" ><h2>Current Investments </h2></div>
				</div>
				<?php 
				
				$sql = "SELECT * FROM {$wpdb->prefix}investments WHERE userid = '{$userid}' AND status='1' AND invest_type = 'invest' ";
				$all_cu_reinvestments = $wpdb->get_results($sql);
				if($all_cu_reinvestments){
				?>
				<div class="row" style="background-color:rgba(128, 128, 128, 0.45);border:1px solid black;">
					<div class="col-md-2 borderRightpad" ><b class="whitecolor">Date Created </b></div>
					<div class="col-md-2 borderRightpad" ><b class="whitecolor">Principle </b></div>
					<div class="col-md-1 borderRightpad" ><b class="whitecolor">Rate </b></div>
					<div class="col-md-2 borderRightpad" ><b class="whitecolor">Release </b></div>
					<div class="col-md-2 borderRightpad" ><b class="whitecolor">Level </b></div>
					<div class="col-md-1 padd1em" ><b class="whitecolor">Details </b></div>
				</div>
				<?php
					foreach($all_cu_reinvestments as $cuInvestment){ 
					$this->interestByInvestId( $cuInvestment->invest_id ,$userid);
					
					$Date = $cuInvestment->created_date;
					$no_ofDays = $cuInvestment->no_of_days;
					$releaseDate = date('Y-m-d', strtotime($Date. ' + '. $no_ofDays .'days'));
					?>
					
						<div class="row" style="border-bottom: 1px solid;border-right: 1px solid black;border-left: 1px solid black;">
							<div class="col-md-2 borderRightpad" ><?php echo $cuInvestment->created_date; ?></div>
							<div class="col-md-2 borderRightpad" ><?php echo '$'.$cuInvestment->amount; ?></div>
							<div class="col-md-1 borderRightpad" ><?php echo $cuInvestment->invest_rate .'%'; ?></div>
							<div class="col-md-2 borderRightpad" ><?php echo $releaseDate; ?></div>
							<div class="col-md-2 borderRightpad" ><?php echo $cuInvestment->invest_title; ?></div>
							<div class="col-md-1 padd1em"  ><button type="button" class="" data-toggle="modal" data-target="#myModal_<?php echo $cuInvestment->invest_id; ?>">View</button></div>
						</div><?php
					}
				}else{ ?>
					<div class="row" >
						<div class="col-md-12" ><h5 style="color:red;">No record of current investments</h5></div>
					</div>
					<?php
				}
				?>
				<!-- End Current investment -->
				
				<!-- start Current Reinvestment  -->
				<div class="row">
					<div class="col-md-12 padtopbtom" ><h2>Current Reinvestments </h2></div>
				</div>
				<?php 
				
				$sql = "SELECT * FROM {$wpdb->prefix}investments WHERE userid = '{$userid}' AND status='1' AND invest_type = 're_invest' ";
				$all_cu_investments = $wpdb->get_results($sql);
				if($all_cu_investments){
				?>
				<div class="row" style="background-color:rgba(128, 128, 128, 0.45);border:1px solid black;">
					<div class="col-md-2 borderRightpad" ><b class="whitecolor">Date Created </b></div>
					<div class="col-md-2 borderRightpad" ><b class="whitecolor">Principle </b></div>
					<div class="col-md-1 borderRightpad" ><b class="whitecolor">Rate </b></div>
					<div class="col-md-2 borderRightpad" ><b class="whitecolor">Release </b></div>
					<div class="col-md-2 borderRightpad" ><b class="whitecolor">Level </b></div>
					<div class="col-md-1 padd1em" ><b class="whitecolor">Details </b></div>
				</div>
				<?php
					foreach($all_cu_investments as $cuInvestment){
						// show all interests by investId through lightbox
						$this->interestByInvestId( $cuInvestment->invest_id ,$userid);
						
						$Date = $cuInvestment->created_date;
						$no_ofDays = $cuInvestment->no_of_days;
						$releaseDate = date('Y-m-d', strtotime($Date. ' + '. $no_ofDays .'days'));
						?>
						<div class="row" style="border-bottom: 1px solid;border-right: 1px solid black;border-left: 1px solid black;">
							<div class="col-md-2 borderRightpad" ><?php echo $cuInvestment->created_date; ?></div>
							<div class="col-md-2 borderRightpad" ><?php echo '$'.$cuInvestment->amount; ?></div>
							<div class="col-md-1 borderRightpad" ><?php echo $cuInvestment->invest_rate .'%'; ?></div>
							<div class="col-md-2 borderRightpad" ><?php echo $releaseDate; ?></div>
							<div class="col-md-2 borderRightpad" ><?php echo $cuInvestment->invest_title; ?></div>
							<div class="col-md-1 padd1em"  >
							 <button type="button" class="" data-toggle="modal" data-target="#myModal_<?php echo $cuInvestment->invest_id; ?>">View</button>
							</div>
						</div><?php
					}
				}else{ ?>
					<div class="row" >
						<div class="col-md-12" ><h5 style="color:red;">No record of current Reinvestments</h5></div>
					</div>
					<?php
				}
				?>
				<!-- End Current Reinvestment -->
				
				<!-- show all  products investment -->
				<div class="row">
					<div class="col-md-12 padtopbtom"  ><h2>Affiliate Center </h2></div>
				</div>
				<div class="row">
					<div class="col-md-2" ><p class="form-control" style="font-weight:bold;text-align: right;border:none;">My Affiliate Code:</p></div>
					<div class="col-md-4" style="padding: 0px 0px 0px 0px;" >
						<input class="form-control" style="width:100%;" readonly type="text" value="<?php echo home_url().'/registration?referID=referalid-'.$userid; ?>" id="myInput">
					</div>
					<div class="col-md-2" style="padding:0px 0px 0px 6px;" ><button style="padding:8px 15px;" onclick="myFunction()">Copy</button></div>
					<script>
						function myFunction() {
						  var copyText = document.getElementById("myInput");
						  copyText.select();
						  document.execCommand("Copy");
						 // alert("Copied the text: " + copyText.value);
						}
					</script>
				</div>
				<?php
				// Reffer to friend
				?>
				
				<div class="row padtopbtom">
					<div class="col-md-12"><h4>Refer to a friend and earn money</h4></div>
				</div>
				<form method="post" action="">
					<div class="row padd1em">
						<div class="col-md-3 " style="padding:0px 0px 0px 0px;" ><input style="width:100%;" placeholder="Email address" type="email" required name="email_friend" class="form-control"></div>
						<div class="col-md-2 " style="padding:0px 0px 0px 6px;" ><input style="padding:8px 15px" type="submit" value="Send" name="send_email_friend" ></div>
					</div>
				</form>
				<div class="row">
					<div class="col-md-12 padtopbtom" >
						<h2>My team </h2>
						<?php
						$sql = "SELECT uid  FROM {$wpdb->prefix}investment_users WHERE refer_uid ='{$userid}' ";
						$refUids = $wpdb->get_results($sql);
						if( $refUids ){
							?>
							<div class="row" style="background-color:rgba(128, 128, 128, 0.45);border:1px solid black;">
								<div class="col-md-3 borderRightpad" ><b class="whitecolor">Name </b></div>
								<div class="col-md-3 borderRightpad" ><b class="whitecolor">Active Investment </b></div>
								<div class="col-md-2 borderRightpad" ><b class="whitecolor">Direct Referrals </b></div>
								<div class="col-md-2 padd1em" ><b class="whitecolor">My Earnings </b></div>
							</div>
							<?php
							foreach($refUids as $uidd){
								$refid = $uidd->uid; 
								$fName = get_user_meta($refid,'first_name',true);
								$lName = get_user_meta($refid,'last_name',true);
								$full_name = $fName.' '.$lName;
								
								$sql = "SELECT SUM( amount ) FROM {$wpdb->prefix}investments WHERE userid = '{$refid}' AND status='1' ";
								$activeInvests = $wpdb->get_var($sql);
								if(empty($activeInvests )){
									$activeInvests = 0;
								}
								
								$sql = "SELECT COUNT( refer_uid ) FROM {$wpdb->prefix}investment_users WHERE refer_uid = '{$refid}' ";
								$totalRefer =  $wpdb->get_var($sql);
								if(empty($totalRefer )){
									$totalRefer = 0;
								}
								
								$sql = "SELECT SUM( ref_amount ) FROM {$wpdb->prefix}log_referred_amount WHERE user_id = '{$refid}' AND  reffer_uid = {$userid} AND status='1' ";
								$logrefAmount = $wpdb->get_var($sql);
								if(empty($logrefAmount )){
									$logrefAmount = 0;
								}
								?>
								<div class="row" style="border-bottom: 1px solid;border-right: 1px solid black;border-left: 1px solid black;">
									<div class="col-md-3 borderRightpad" ><?php echo $full_name; ?></div>
									<div class="col-md-3 borderRightpad" ><?php echo '$'.$activeInvests; ?></div>
									<div class="col-md-2 borderRightpad" ><?php echo $totalRefer; ?></div>
									<div class="col-md-2 padd1em" ><?php echo '$'.$logrefAmount; ?></div>
								</div><?php
							}
						}else{ ?>
							<div class="row">
								<div class="col-md-12 invspad">
									<h5 style="color:red;">No record found</h5>
								</div>
							</div><?php
						} ?>
								
					</div>
				</div>
				
				
				<script>
					//show withdraw form 
					
					function show_withdraw(){
						jQuery("#divWithshow").show();
						jQuery("#hidewithDraw").hide();
						jQuery("#showwithDraw").show();
						
						//hide reinvest div when click on withdraw button
						jQuery("#divReinvest").hide();
						jQuery("#hideReinterest").show();
						jQuery("#showReinterest").hide();
					}
					function hide_withdraw(){
						jQuery("#divWithshow").hide();
						jQuery("#hidewithDraw").show();
						jQuery("#showwithDraw").hide();
					}
					//end withdraw form

					// function reinterest from
					function show_Reinvest(){  divWithshow
						jQuery("#divReinvest").show();
						jQuery("#hideReinterest").hide();
						jQuery("#showReinterest").show();
						
						//hide other div of withdraw form
						jQuery("#divWithshow").hide();
						jQuery("#hidewithDraw").show();
						jQuery("#showwithDraw").hide();
					}
					function hide_Reinvest(){
						jQuery("#divReinvest").hide();
						jQuery("#hideReinterest").show();
						jQuery("#showReinterest").hide();
					}
				</script>
			</div>			
			<?php
			}else{
				echo wp_redirect( home_url('/login') );
				?><!--<h1>Please <a href="<?php echo home_url('/login'); ?>" title="Members Area Login" rel="home">Click Here</a> to Login.</h1>--><?php
			}
			$html = ob_get_clean();
			return $html;
		}
		
		//View WithDraw Detailed
		function viewWithDraw_detailed($userid){
			global $wpdb;
			
			$sql = "SELECT * FROM {$wpdb->prefix}request_withdrawal WHERE user_id = '{$userid}'  ";
			$withdrawRequests = $wpdb->get_results($sql);
			$fname = get_user_meta($userid,'first_name',true);
			$lname = get_user_meta($userid,'last_name',true);
			$name = $fname.' '.$lname;
			?>
			<div class="modal fade"  id="withdraw_detail_<?php echo $userid; ?>" role="dialog">
				<div class="modal-dialog" style="max-width:800px;">
					<!-- Modal content-->
					<div class="modal-content" style="max-height: 80%;overflow-y: scroll;min-height: 25em;padding: 2em;">
						<div class="modal-header">
							<h4 class="modal-title text-capitalize"><?php echo $name.' WithDraw Details'; ?></h4>
							<button type="button" class="close" data-dismiss="modal">&times;</button>
						</div>
						<?php
						if($withdrawRequests){ ?>
							<div class="row">
								<div class="col-md-3"><b>Withdrawa Amount</b> </div>
								<div class="col-md-2"><b>Payment method</b> </div>
								<div class="col-md-2"><b>Date</b> </div>
								<div class="col-md-3"><b>Reason</b> </div>
								<div class="col-md-2"><b>status</b> </div>
							</div><?php
							foreach($withdrawRequests as $request){ ?>
								<div class="row">
									<div class="col-md-3">$<?php echo $request->withdraw_amount; ?> </div>
									<div class="col-md-2" style="text-transform:capitalize;"><?php echo $request->pay_gateway; ?> </div>
									<div class="col-md-2" ><?php echo $request->created_date; ?> </div>
									<div class="col-md-3" ><p><?php echo $request->reason; ?></p> </div>
									<div class="col-md-2" style="text-transform:capitalize;"><?php echo $request->status; ?> </div>
								</div><?php
							}
						}else{ ?>
							<div class="row">
								<div class="col-md-12 invspad">
									<h5 style="color:red;">No record found</h5>
								</div>
							</div><?php
						} ?>
					</div>
				</div>
			</div>
			<?php
		}	
		
		//get profile detail
		function profileInfo($userid){
			global $wpdb;
			
			$user_meta = get_userdata($userid);
			$user_roles = $user_meta->roles;
			$user_email = $user_meta->data->user_email;
			$user_login = $user_meta->data->user_login;
			$role = $user_roles[0];
			
			$fname = get_user_meta($userid,'first_name',true);
			$lname = get_user_meta($userid,'last_name',true);
			$name = $fname.' '.$lname;
			
			$sql = "SELECT * FROM {$wpdb->prefix}investment_users WHERE uid = '{$userid}'";
			$rs =  $wpdb->get_row($sql);
			$bankac = $rs->bank_wallet;
			$paypalID = $rs->paypal_id;
			$bank_cardNum = $rs->credit_card_number;
			$d_o_birth = $rs->date_of_birth;
			$address1 = $rs->address;
			$address2 = $rs->address2;
			$customerID = $rs->customerID;
			
			$sql = "select name from countries where id='{$rs->country}'";
			$country =  $wpdb->get_var($sql);
			
			$sql = "select name from states where id='{$rs->state}'";
			$state =  $wpdb->get_var($sql);
			
			$sql = "select name from cities where id='{$rs->city}'";
			$city =  $wpdb->get_var($sql);
			?>
			<div class="modal fade"  id="myProfile_<?php echo $userid; ?>" role="dialog">
				<div class="modal-dialog" >
					<!-- Modal content-->
					<div class="modal-content" style="max-height: 80% !important;overflow-y: scroll;">
						<div class="modal-header">
							<h4 class="modal-title text-capitalize"><?php echo $name.' Information'; ?></h4>
							<button type="button" class="close" data-dismiss="modal">&times;</button>
						</div>
						<div class="modal-body">
						<form method="post" action="" id="mem_form">
							<?php 
							if( empty($customerID) ){
							?>
							<div class="row" style="margin-top:1em;">
								<div class="col-md-12" >
									<input type="hidden" id="memberPricebit" value="<?php echo get_option( '_membership_fee' ); ?>" />
									<p style="color:green;display:none;font-weight:bold;font-size:18px;" id="msgcreditsaved">Your Credit Card has been saved successfully</p>
									<input type="button" onclick="saveinfo();" id="credit_cardinfo22" class="btn btn-primary" value="Add Credit Card" />
								</div>
							</div>
							
							<script src="https://checkout.stripe.com/checkout.js"></script>
							<script>
								var handler = StripeCheckout.configure
								({
									key: '<?php echo get_option( 'yezter_stripe_key' ); ?>',
									image: 'https://stripe.com/img/documentation/checkout/marketplace.png',
									token: function(token) 
									{
										jQuery('#mem_form').append("<input type='hidden' name='stripeToken' value='" + token.id + "' />"); 
										jQuery('#credit_cardinfo22').hide(); 
										jQuery('#msgcreditsaved').show(); 
										setTimeout(function(){
											//jQuery('#mem_form').submit(); 
											
										}, 200); 
									}
								}); 
								function saveinfo(){
									var amount = 5;
									total=amount*100;
									handler.open({
									//name: "Membership Fee",
									name: "Add Credit Card",
									currency : 'USD',
									panelLabel: "Save",
									//description: 'Charges( '+amount+' USD )',
									description: 'Save card information',
									//amount: total
									});
								}
							</script>
							<?php
							}
							?>
							<div class="row" style="margin-top:1em;">
								<div class="col-md-4 form-group"><p class="text-right2"><b>First Name:</b></p></div>
								<div class="col-md-6 form-group">
									<input type="text" value="<?php  echo get_user_meta($userid, 'first_name', true); ?>" name="hyp_fname"  required />
								</div>
							</div>
							<div class="row">
								<div class="col-md-4 form-group"><p class="text-right2"><b>Last Name:</b></p></div>
								<div class="col-md-6 form-group">
									<input type="text" value="<?php echo get_user_meta($userid, 'last_name', true); ?>" name="hyp_lname" required />
								</div>
							</div>
							<div class="row">
								<div class="col-md-4 form-group"><p class="text-right2"><b>User Name:</b></p></div>
								<div class="col-md-6 form-group">
									<input type="text" name="userName" value="<?php echo $user_login; ?>" readonly />
								</div>
							</div>
							<div class="row">
								<div class="col-md-4 form-group"><p class="text-right2"><b>User Email:</b></p></div>
								<div class="col-md-6 form-group">
									<input type="email"  value="<?php echo $user_email; ?>" readonly name="usEmail" />
								</div>
							</div>
							<div class="row">
								<div class="col-md-4 form-group"><p class="text-right2"><b>Phone Number:</b></p></div>
								<div class="col-md-6 form-group">
									<input type="number" value="<?php echo get_user_meta($userid, 'user_cell_num', true); ?>" name="phnNumber" required />
								</div>
							</div>
							<div class="row">
								<div class="col-md-4 form-group"><p class="text-right2"><b>Date of Birth:</b></p></div>
								<div class="col-md-6 form-group">
									<input type="date" value="<?php echo $d_o_birth; ?>" name="dateOfBirth" required />
								</div>
							</div>
							<div class="row">
								<div class="col-md-4 form-group"><p class="text-right2"><b>Address Line 1:</b></p></div>
								<div class="col-md-6 form-group">
									<textarea   name="hypaddress1"  required><?php echo $address1; ?></textarea>
								</div>
							</div>
							<div class="row">
								<div class="col-md-4 form-group"><p class="text-right2"><b>Address Line 2:</b></p></div>
								<div class="col-md-6 form-group">
									<textarea   name="hypaddress2"  ><?php echo $address2; ?></textarea>
								</div>
							</div>
							<!--<div class="row">
								<div class="col-md-4 form-group"><p class="text-right2"><b>Country:</b></p></div>
								<div class="col-md-6 form-group">
									<?php echo $country; ?>
								</div>
							</div>
							<div class="row">
								<div class="col-md-4 form-group"><p class="text-right22"><b>State:</b></p></div>
								<div class="col-md-6 form-group"><?php echo $state; ?></div>
							</div>
							<div class="row">
								<div class="col-md-4 form-group"><p class="text-right2"><b>City:</b></p></div>
								<div class="col-md-6 form-group"><?php echo $city; ?></div>
							</div>-->
							<div class="row">
								<div class="col-md-4 form-group"><p class="text-right2"><b>Bitcoin Wallet:</b></p></div>
								<div class="col-md-6 form-group">
									<input type="text" name="hypbitAccount" value="<?php echo $bankac; ?>" />
								</div>
							</div>
							<!--<div class="row">
								<div class="col-md-4 form-group"><p class="text-right2"><b>Credit Card Number:</b></p></div>
								<div class="col-md-6 form-group">
									<input type="text" name="hypcreditCard" value="<?php echo $bank_cardNum; ?>" />
								</div>
							</div>-->
							<div class="row">
								<div class="col-md-4 form-group"><p class="text-right2"><b>Paypal ID:</b></p></div>
								<div class="col-md-6 form-group">
									<input type="email" name="ur_paypal_id" value="<?php echo $paypalID; ?>" />
								</div>
							</div>
							<div class="row">
								<div class="col-md-6 form-group">
									<input type="submit" name="hypsaveInfo" value="Update Info" class="btn btn-primary" >
								</div>
							</div>
						</form>
						</div>
					</div>
				</div>
			</div>
			<?php
		}
		
		//get interest from investments
		function interestByInvestId($investId,$userid){
			global $wpdb;
			
			/* $sql = "SELECT * FROM {$wpdb->prefix}investments WHERE userid = '{$userid}' AND status='1' AND invest_id = '{$investId}' ";
			$investment = $wpdb->get_row($sql);
			$inv_rate = $investment->invest_rate;
			$re_inv_rate = $investment->re_invest_rate;
			$total_principal = $investment->amount;
			$days = $investment->no_of_days; */
			
			$sql = "SELECT * FROM {$wpdb->prefix}all_interest WHERE user_id = '{$userid}' AND status='active' AND invest_id = '{$investId}' ";
			$all_interests = $wpdb->get_results($sql);
			
				?>
				<!-- pop up modal -->
				 <!-- Modal approve -->
				<div class="modal fade"  id="myModal_<?php echo $investId; ?>" role="dialog">
					<div class="modal-dialog" style="max-width:80%;">
						<!-- Modal content-->
						<div class="modal-content" style="max-height: 95% !important;overflow-y: scroll;">
							<div class="modal-header">
							<h4 class="modal-title">All Interests</h4>
							<button type="button" class="close" data-dismiss="modal">&times;</button>
							</div>
							<div class="modal-body">
								<div class="row">
									<div class="col-md-2"></div>
									<div class="col-md-1"><b>Days</b></div>
									<div class="col-md-2"><b>Earnings</b></div>
									<div class="col-md-2"><b>Date</b></div>
									<div class="col-md-2"><b>Cash Out</b></div>
									<div class="col-md-3"><b>TOTAL Principal</b></div>
								</div>
									<?php
									//for( $i = 1 ; $i <= $days ; $i++ ){
								if( $all_interests ){
									foreach( $all_interests as $interest){
									/* 	$inter_profit = $total_principal/100 * $inv_rate;
										$inter_profit = round( $inter_profit , 2 );
										$reinvest_earning = $inter_profit/100 * $re_inv_rate; 
										$reinvest_earning = round( $reinvest_earning , 2 );
										$cashout = $inter_profit - $reinvest_earning; 
										$cashout = round( $cashout , 2 );
										$total_principal = $total_principal + $reinvest_earning;
										$total_principal = round( $total_principal , 2 ); */
										
										$days = $interest->days;
										$inter_profit = $interest->earning;
										$interest_date = $interest->interest_date;
										$cashout = $interest->cashout;
										$total_principal = $interest->total_int_principal;
										?>
										<div class="row">
											<div class="col-md-2"></div>
											<div class="col-md-1"><?php echo $days; ?></div>
											<div class="col-md-2"><?php echo '$'.$inter_profit ; ?></div>
											<div class="col-md-2"><?php echo $interest_date ; ?></div>
											<div class="col-md-2"><?php echo $cashout ; ?></div>
											<div class="col-md-3"><?php echo '$'.$total_principal ; ?></div>
										</div>
										<?php 
									}
								}
									?>
							</div>
							<div class="modal-footer">
							<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
							</div>
						</div>
					</div>
				</div> 
				<!-- Modal Approve closed -->
			<?php
		}
		
		function showAllProducts(){
			$args     = array( 'post_type' => 'product' , 'post_status' => 'publish' );
			$products = get_posts( $args ); 
			
			?>
			<!-- all products -->
			<div class="row">
				<?php
				if($products){
					foreach( $products as $rs ){
						$productID = $rs->ID;
						$post_name = $rs->post_name;
						$min_amount = get_post_meta($productID, '_hypertext_min_amount', true);
						$max_amount = get_post_meta($productID, '_hypertext_max_amount', true);
						$inv_rate = get_post_meta($productID, '_hypertext_inv_rate', true);
						$no_days = get_post_meta($productID, '_hypertext_no_of_days', true);
					?>
					<div class="col-md-3">
						<div style="padding:1em;border:1px solid #e6b05a;border-radius:10px;">
							<h4 class="padd1em text-center"><?php echo $rs->post_title ; ?></h4>
							<p class="text-center">Investment Range <b>(<?php echo '$'.$min_amount.' - '. '$'.$max_amount ; ?>)</b></p>
							<p class="text-center">Interest Rate <b>(<?php echo $inv_rate ; ?>%)</b></p>
							<p class="text-center">NO. of days <b>(<?php echo $no_days ; ?>)</b></p>
							<p class="text-center">
								<a href="<?php echo home_url().'/product/'.$post_name; ?>">
								<input type="button" name="invest_submit" value="Add Investment" class="btn btn-primary" >
								</a>
							</p>
						</div>
					</div>
						<?php
					}
				}
				?>
			</div>
			<!-- end all products -->
			<?php
		}
		
		function admin_menu(){
			//add_menu_page('Add Investment Packages', 'Investment Packages', 'manage_options', 'invest_packages', array(&$this, 'packages') );
			//add_menu_page('Investment Packages', 'Investment Packages', 'manage_options', 'invest_packages', array(&$this, 'edit_packages') );
			add_menu_page('Withdraw Requests', 'Withdraw Requests', 'manage_options', 'inv_requests', array(&$this, 'requests_withdraw') );
			add_menu_page('Investment Settings', 'Investment Settings', 'manage_options', 'inv_settings', array(&$this, 'investmentSettings') );
			add_menu_page('', '', 'manage_options', 'user_req_detail', array(&$this, 'details_request') );
		}
		
		function investmentSettings(){
			if(isset($_POST['_investmentSbtn'] ) ){
				$memberfee = $_POST['_membershipFee'];
				$memberfeeExpire = $_POST['_membershipFee_expire'];
				$investFee = $_POST['_investmentFee'];
				$feeDate = $_POST['_investmentFeeDate'];
				$launch_date = $_POST['launch_date'];
				$stripeKey = $_POST['_stripe_key'];
				$stripeSecret_Key = $_POST['_secret_key'];
				update_option( '_membership_fee', $memberfee );
				update_option( '_membership_fee_expire_card', $memberfeeExpire );
				update_option( '_investment_fee', $investFee );
				update_option( '_investment_feeDate', $feeDate );
				update_option( '_launch_date', $launch_date );
				update_option( 'yezter_stripe_key', $stripeKey );
				update_option( 'yezter_secret_key', $stripeSecret_Key );
				echo '<h1>Your settings has been saved successfully.</h1>';
			}
			$memberFee = get_option( '_membership_fee' );
			$memberFee_expire = get_option( '_membership_fee_expire_card' );
			$invs_fee = get_option( '_investment_fee' );
			$invs_feeDate = get_option( '_investment_feeDate' );
			$launch_date = get_option( '_launch_date' );
			$stripe_key = get_option( 'yezter_stripe_key' );
			$secret_key = get_option( 'yezter_secret_key' );
			
			?>
			<style>
			.padd1em{
				padding:1em;
			}
			.textright{
				text-align:right;
			}
			</style>
			<div class="tw-bs container">
				<div class="row">
					<div class="col-md-12">
						<h2>Investment Settings</h2>
					</div>
				</div>
				<form method="post" action="" >
					<div class="row padd1em">
						<div class="col-md-4 textright"><b>Monthly membership fee</b></div>
						<div class="col-md-7"><input type="text" name="_membershipFee" value="<?php echo $memberFee; ?>" required ></div>
					</div>
					<div class="row padd1em">
						<div class="col-md-4 textright"><b>Monthly membership fee incase of card expire or not provided</b></div>
						<div class="col-md-7"><input type="text" name="_membershipFee_expire" value="<?php echo $memberFee_expire; ?>" required ></div>
					</div>
					<div class="row padd1em">
						<div class="col-md-4 textright"><b>Investment fee </b></div>
						<div class="col-md-7"><input type="text" name="_investmentFee" value="<?php echo $invs_fee;  ?>" required ></div>
					</div>
					<div class="row padd1em">
						<div class="col-md-4 textright"><b>Investment fee Date </b></div>
						<div class="col-md-7"><input type="date" name="_investmentFeeDate" value="<?php echo $invs_feeDate;  ?>" required ></div>
					</div>
					<div class="row padd1em">
						<div class="col-md-4 textright"><b>Launch date </b></div>
						<div class="col-md-7"><input type="date" name="launch_date" value="<?php echo $launch_date;  ?>" required ></div>
					</div>
					<div class="row padd1em">
						<div class="col-md-4 textright"><b>Stripe Key </b></div>
						<div class="col-md-7"><input type="text" name="_stripe_key" value="<?php echo $stripe_key;  ?>" required ></div>
					</div>
					<div class="row padd1em">
						<div class="col-md-4 textright"><b>Secret Key </b></div>
						<div class="col-md-7"><input type="text" name="_secret_key" value="<?php echo $secret_key;  ?>" required ></div>
					</div>
					<div class="row padd1em">
						<div class="col-md-4"></div>
						<div class="col-md-7" style="padding-top:1em;"><input type="submit" name="_investmentSbtn" value="Save" class="btn btn-primary"  ></div>
					</div>
				</form>
			</div>
			<?php
		}
		
		function approverRequestAdmainMail(){
			ob_start();
			?>
			<div id=":au" class="ii gt "><div id=":b7" class="a3s aXjCH m16069246ea9540fa"><div class="adM">
				</div><u></u>
				<div marginwidth="0" marginheight="0" style="width:100%;margin:0;padding:0;background-color:#f5f5f5;font-family:Helvetica,Arial,sans-serif">
				<div id="m_-2785494934782886297top-orange-bar" style="display:block;height:5px;background-color:rgb(26, 85, 145);"></div>
				<center>
				<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" class="m_-2785494934782886297background-table">
				<tbody><tr>
				<td align="center" valign="top" style="border-collapse:collapse">
				<table border="0" cellpadding="0" cellspacing="0" width="638" id="m_-2785494934782886297main-table">
				<tbody><tr>
				<td align="center" valign="top" height="20" style="border-collapse:collapse">
				</td>
				</tr>
				<tr>
				<td align="center" valign="top" style="border-collapse:collapse"><table width="100%" border="0">
				<tbody><tr>
				<td width="127" align="center" style="border-collapse:collapse"><a href="<?php echo home_url(); ?>"  ><img src="http://ulynd.com/wp-content/uploads/2018/01/logoinvs-small.png" alt="Zendesk Chat"   border="0" class="m_-2785494934782886297logo CToWUd" style="border:0;height:auto;line-height:120%;outline:none;text-decoration:none;color:#d86c00;padding-left:10px;font-family:Helvetica,Arial,sans-serif;font-size:25px;vertical-align:text-bottom;text-align:center"></a></td>
				</tr>
				<tr>
				<td align="center" colspan="3" class="m_-2785494934782886297email-title" style="border-collapse:collapse;font-family:Helvetica,Arial,sans-serif;font-size:30px;font-weight:bold;line-height:120%;color:rgb(82,82,82);text-align:center;zoom:1">Dear #username#</td>
				</tr>
				</tbody></table></td>
				</tr>
				<tr>
					<td class="m_-2785494934782886297white-space m_-2785494934782886297headnote" align="center" style="padding: 2em;border-collapse:collapse;background-color:rgb(255,255,255);border-color:rgb(221,221,221);border-width:1px;border-radius:5px;border-style:solid;padding:12px;font-size:13px;line-height:2em;color:rgb(119,119,119);text-align:justify"><p>Welcome to Ulynd.com.</p><p>Admin has been approved the withdrawal request of $#money# </p>
						<p>Best Regards,</p>
						<p>Ulynd.com Team.</p>
					</td>
				</tr>

				</tbody></table>
				</td>
				</tr>
				<tr>
				<td align="center" valign="top" height="30" style="border-collapse:collapse"></td>
				</tr>
				</tbody></table>
				</td>
				</tr>
				</tbody></table>
				</center><div class="yj6qo"></div><div class="adL">
				</div></div><div class="adL">
				</div></div>
			</div>
			<?php
			$html = ob_get_clean();
			return $html;
		}
		
		function details_request(){
			global $wpdb;
			
			$use_reqId = $_GET['req_userId'];
			if( $use_reqId ){
				$date = date('Y-m-d');
				//approve request withdraw money
				if(isset($_POST['approve_request']) ){
					$reqid = $_POST['reqId'];
					$req_uid = $_POST['useridReq'];
					$money = $_POST['aspk_money'];
					
					$provider_user_info = get_userdata($req_uid);
					$email = $provider_user_info->data->user_email;
					$display_name = $provider_user_info->data->display_name;
					
					$sql = "UPDATE  {$wpdb->prefix}request_withdrawal set status='Completed' , updated_date = '{$date}'   WHERE user_id = '{$req_uid}' AND  id='{$reqid}' ";
					$wpdb->query($sql);
					
					$approveRequest = $this->approverRequestAdmainMail();
					$approveRequest = str_replace( '#username#' ,$display_name, $approveRequest );
					$approveRequest = str_replace( '#money#' ,$money, $approveRequest );
					add_filter( 'wp_mail_content_type',array(&$this,'wp_set_content_type' ));
					
					wp_mail( $email, 'Your withdrawal request has approved', $approveRequest );	
					
					remove_filter( 'wp_mail_content_type', array(&$this,'wp_set_content_type' ));
				}
				//reject request withdraw money
				if(isset($_POST['reject_request']) ){
					$reqid = $_POST['reqId'];
					$req_uid = $_POST['useridReq'];
					$money = $_POST['aspk_money'];
					$rejectreason = $_POST['reject_text'];
					
					$sql = "UPDATE  {$wpdb->prefix}request_withdrawal set status='Reject' , updated_date = '{$date}' , reason = '{$rejectreason}'  WHERE user_id = '{$req_uid}' AND  id='{$reqid}' ";
					$wpdb->query($sql);
				}
				$nickname = get_user_meta($use_reqId, 'nickname', true);
				$fname = get_user_meta($use_reqId, 'first_name', true);
				$lname = get_user_meta($use_reqId, 'last_name', true);
				$fullName = $fname.' '. $lname;
				//get user detail
				$sql = "SELECT * FROM {$wpdb->prefix}investment_users WHERE uid = '{$use_reqId}'";
				$userDetail =  $wpdb->get_row($sql);
				$bankac =  $userDetail->bank_wallet;
				$paypal_id =  $userDetail->paypal_id;
				
				$sql = "SELECT SUM( tot_invs_amount ) FROM {$wpdb->prefix}total_Investment_balance WHERE user_id = '{$use_reqId}' AND amount_type = 'credit' ";
				$all_inv_profits = $wpdb->get_var($sql);
				
				$sql = "SELECT SUM( ref_amount ) FROM {$wpdb->prefix}log_referred_amount WHERE reffer_uid = '{$use_reqId}' AND status='1' ";
				$total = $wpdb->get_var($sql); // referal total money
				$total = round($total , 2 );
				
				// get total requested amount
				$sql = "SELECT SUM( withdraw_amount ) FROM {$wpdb->prefix}request_withdrawal WHERE user_id = '{$use_reqId}' AND status !='Reject' ";
				$total_req_amount = $wpdb->get_var($sql);
				$total_req_amount = round($total_req_amount , 2 );
				
				// get total negative monthly subscribtion balance if exist and deduct from total amount
				$sql = "SELECT SUM(amount) FROM {$wpdb->prefix}investment_customers_subscribtion WHERE status='1' AND user_id={$use_reqId} AND deducted_from = 'investments' ";
				$totnegativeSubc = $wpdb->get_var($sql);
				$totnegativeSubc = round($totnegativeSubc , 2 );
				$all_inv_profits = $all_inv_profits - $totnegativeSubc;
				//end code that is used for deduction amount
				
				$totalwithdrawmoney = $all_inv_profits + $total;
				$totalwithdrawmoney = round($totalwithdrawmoney , 2 );
				
				// minus reinvest amount from total balance
				$sql = "SELECT SUM( amount ) FROM {$wpdb->prefix}investments WHERE userid = '{$use_reqId}' AND invest_type = 're_invest' ";
				$tot_amount_reinvestments = $wpdb->get_var($sql);

				$totalAmount = $totalwithdrawmoney - $total_req_amount;
				$totalAmount = $totalAmount - $tot_amount_reinvestments;
				$totalAmount = round($totalAmount , 2 );
				
				$sql = "SELECT * FROM {$wpdb->prefix}request_withdrawal WHERE user_id = '{$use_reqId}'  ";
				$all_requests =  $wpdb->get_results($sql);
				
				//total requested money
				$sql = "SELECT SUM( withdraw_amount ) FROM {$wpdb->prefix}request_withdrawal WHERE user_id = '{$use_reqId}'  ";
				$tot_requested = $wpdb->get_var($sql);
				?>
				<script src="<?php echo plugins_url('js/bootstrap.min.js', __FILE__); ?>"></script>
				<div class="tw-bs container" style="padding-top:2em;">
					<div class="row">
						<div class="col-md-12">
							<h5><?php echo $fullName; ?> withdraw requests detais and their total profit amount</h5>
						</div>
					</div>
					<div class="row">
						<div class="col-md-2">
							<b>Earning</b>
						</div>
						<div class="col-md-2">
							<b>Referrals amount</b>
						</div>
						<div class="col-md-2">
							<b>Balance</b>
						</div>
						<div class="col-md-2">
							<b>Bitcoin wallet</b>
						</div>
						<div class="col-md-2">
							<b>Paypal ID</b>
						</div>
					</div>
					<div class="row">
						<div class="col-md-2">
							<?php 
							if( $all_inv_profits ){
								?>$<?php echo $all_inv_profits ;  ?><?php
							}else{
								?>$0<?php
							}
							?>
						</div>
						<div class="col-md-2">
							<?php
							if($total){
								?>$<?php echo $total;  ?><?php
							}else{
								?>$0<?php
							}
							?>
						</div>
						<div class="col-md-2">
							$<?php echo $totalAmount ;  ?>
						</div>
						<div class="col-md-2">
							<?php echo $bankac ;  ?>
						</div>
						<div class="col-md-2">
							<?php echo $paypal_id ;  ?>
						</div>
					</div>
					<div class="row" style="margin-top:2em;">
						<div class="col-md-12">
							<h2><?php echo $fullName; ?> withdraw requests</h2>
						</div>
					</div>
					<div class="row">
						<div class="col-md-2"><b>Request Amount</b></div>
						<div class="col-md-2"><b>Withdraw able Amount</b></div>
						<div class="col-md-2"><b>Gateway</b></div>
						<div class="col-md-2"><b>Date </b></div>
						<div class="col-md-3"><b>Status </b></div>
					</div>
					<?php
					if($all_requests){
						foreach( $all_requests as $rs ){ 
							$uid = $rs->user_id;
							$pay_gateway = $rs->pay_gateway;
							$withdrwan_reqAmount = $rs->withdraw_amount;
							if($pay_gateway == 'bank' || $pay_gateway == 'paypal' ){ 
								$withDrawa_able_amount = $withdrwan_reqAmount / 100 * 5;
								$withDrawa_able_amount = $withdrwan_reqAmount -  $withDrawa_able_amount;
							}else{
								$withDrawa_able_amount = $withdrwan_reqAmount / 100 * 3;
								$withDrawa_able_amount = $withdrwan_reqAmount -  $withDrawa_able_amount;
							}
							
							$nickname = get_user_meta($uid, 'nickname', true);
							$fname = get_user_meta($uid, 'first_name', true);
							$lname = get_user_meta($uid, 'last_name', true);
							$fullName = $fname.' '. $lname;
							$status = $rs->status;
						?>
							<!-- pop up modal -->
							 <!-- Modal approve -->
							<div class="modal fade" id="myModal_<?php echo $rs->id; ?>" role="dialog">
								<div class="modal-dialog">
									<!-- Modal content-->
									<div class="modal-content">
										<div class="modal-header">
										<button type="button" class="close" data-dismiss="modal">&times;</button>
										<h4 class="modal-title">Are you sure</h4>
										</div>
										<div class="modal-body">
											<form method="post" action="" >
												<div>
													<input type="hidden" name="aspk_money" value="<?php echo $withDrawa_able_amount; ?>">
													<input type="hidden" name="reqId" value="<?php echo $rs->id; ?>">
													<input type="hidden" name="useridReq" value="<?php echo $rs->user_id; ?>">
													<input type="submit" name="approve_request" value="Approve" class="btn-primary btn">
												</div>
											</form>
										</div>
										<div class="modal-footer">
										<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
										</div>
									</div>
								</div>
							</div> 
							<!-- Modal Approve closed -->
							<!-- Modal Rejected -->
							<div class="modal fade" id="reject_myModal_<?php echo $rs->id; ?>" role="dialog">
								<div class="modal-dialog">
									<!-- Modal content-->
									<div class="modal-content">
										<div class="modal-header">
										<button type="button" class="close" data-dismiss="modal">&times;</button>
										<h4 class="modal-title">Are you sure</h4>
										</div>
										<div class="modal-body">
											<form method="post" action="" >
												<div>
													<textarea name="reject_text" required></textarea>
												</div>
												<div>
													<input type="hidden" name="reqId" value="<?php echo $rs->id; ?>">
													<input type="hidden" name="useridReq" value="<?php echo $rs->user_id; ?>">
													<input type="submit" name="reject_request" value="Rejected" class="btn-primary btn">
												</div>
											</form>
										</div>
										<div class="modal-footer">
										<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
										</div>
									</div>
								</div>
							</div>
							<!-- Modal Approve closed -->
							<div class="row" style="padding-top:15px;">
								<div class="col-md-2">$<?php echo $rs->withdraw_amount; ?></div>
								<div class="col-md-2">$<?php echo $withDrawa_able_amount; ?></div>
								<div class="col-md-2" style="text-transform: capitalize;"><?php echo $pay_gateway; ?></div>
								<div class="col-md-2"><?php echo $rs->created_date; ?></div>
								<div class="col-md-4">
									<span style="float:left;text-transform: capitalize;"><?php echo $rs->status; ?></span>
									<?php
									if( $rs->status == 'pending' ){
									?>
									<span style="float:left;padding-left:5px;text-transform: capitalize;">
									 <button type="button" class="btn btn-info btn-lg" data-toggle="modal" data-target="#myModal_<?php echo $rs->id; ?>">Approve</button>
									
									</span>
									<span style="float:left;padding-left:5px;">
										 <button type="button" class="btn btn-info btn-lg" data-toggle="modal" data-target="#reject_myModal_<?php echo $rs->id; ?>">Rejected</button>
									</span>
									<?php
									}elseif( $rs->status == 'Reject'  ){ ?>
										<span style="float:left;padding-left:5px;">
											<b>Rejection Reason</b>
											<p><?php echo $rs->reason; ?></p>
										</span>
										<?php
									}
									?>
								</div>
							</div><?php
						} ?>
						<div class="row" style="margin-top:1em;">
							<div class="col-md-3"><b>Total</b></div>
							<div class="col-md-3"><b><?php echo $tot_requested; ?></b></div>
						</div><?php
					}else{
						?>
						<div class="row">
							<div class="col-md-3">No record found</div>
						</div>
						<?php
					}
					?>
				</div>
				<?php
			}
		}
		
		function requests_withdraw(){
			global $wpdb;
			
			//$sql = "SELECT * FROM {$wpdb->prefix}request_withdrawal  ";
			$sql = "SELECT DISTINCT user_id , (SELECT SUM( withdraw_amount )  FROM {$wpdb->prefix}request_withdrawal as  u where u.user_id=u1.user_id ) as Result FROM {$wpdb->prefix}request_withdrawal u1";
			$results =  $wpdb->get_results($sql);
			?>
			<h2 style="padding: 1em 0 0px 1em;">Withdrawal requests</h2>
			<div class="tw-bs container">
				<div class="row" style="padding-left:0.5em;">
					<div class="col-md-3" ><b>Name</b></div>
					<div class="col-md-2"><b>View detail User </b></div>
				</div>
				<?php
				if($results){
					foreach( $results as $rs ){ 
						$uid = $rs->user_id;
						$nickname = get_user_meta($uid, 'nickname', true);
						$fname = get_user_meta($uid, 'first_name', true);
						$lname = get_user_meta($uid, 'last_name', true);
						$fullName = $fname.' '. $lname;
						$status = $rs->status;
					?>
						<div class="row" style="padding: 0.5em 0 0px 0.5em;">
							<div class="col-md-3"><?php echo $fullName; ?></div>
							<div class="col-md-2"><a href="<?php echo admin_url().'admin.php?page=user_req_detail&req_userId='.$uid ;?>">View Detail</a></div>
						</div><?php
					}
				}
				?>
			</div>
			<?php
		}
		
		function edit_packages(){
			global $wpdb;
			
			$pkgid = $_GET['package_id'];	
			if( isset($_POST['submit_package']) ){
				$package_name = $_POST['package_name'];
				$rate = $_POST['hyper_invs_rate'];
				$re_rate = $_POST['hyper_re_invs_rate'];
				$no_days = $_POST['hyper_num_days'];
				$weekend = $_POST['hyper_weekends'];
				$min_amount = $_POST['hyper_min_amount'];
				$max_amount = $_POST['hyper_max_amount'];
				$date = date('Y-m-d');
				
				$sql = "UPDATE {$wpdb->prefix}investment_packages SET min_amount ='{$min_amount}' , max_amount ='{$max_amount}',updated_date='{$date}',package_name='{$package_name}' ,weekend_include='{$weekend}',re_invest_rate='{$re_rate}', invest_rate = '{$rate}',no_of_days = '{$no_days}'  WHERE package_id = '{$pkgid}'";
				$wpdb->query($sql);
				
				echo '<h1>Package updated successfully.</h1>';
			}

			if($pkgid){
				$sql = "SELECT * FROM {$wpdb->prefix}investment_packages WHERE package_id = '{$pkgid}'";
				$result =  $wpdb->get_row($sql);
			?>
			<div class="tw-bs container"><!-- start container -->
				<div class="row">
					<div class="col-md-12"><b style="color:green;" id="welldoneapp_create"></b></div>
				</div>
				<div class="row">
					<div class="col-md-12">
						<h3>Update Your Plan</h3>
					</div>
				</div>
				<form method="post" action="">
					<div class="row">
						<div class="col-md-12 yezter_top">Packages<span>*</span></div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">
							<input type="text"  name="package_name" value="<?php echo $result->package_name ; ?>" required class="form-control" >
						</div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">Interest Rate<span>*</span></div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">
							<span><input class="form-control" step="any" value="<?php echo $result->invest_rate ; ?>" type="number" name="hyper_invs_rate" placeholder="Interest Rate"  required ></span>
						</div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">Reinvest Rate</div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">
							<input class="form-control" step="any" value="<?php echo $result->re_invest_rate ; ?>" type="number" name="hyper_re_invs_rate" placeholder="Interest Rate"  >
						</div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">Minimum Amount</div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">
							<input class="form-control" step="any" type="number" value="<?php echo $result->min_amount ; ?>" name="hyper_min_amount" placeholder="Minimum Amount"  >
						</div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">Maximum Amount</div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">
							<input class="form-control" step="any" type="number" value="<?php echo $result->max_amount ; ?>" name="hyper_max_amount" placeholder="Maximum Amount"  >
						</div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">No. of days<span>*</span></div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">
							<input class="form-control" value="<?php echo $result->no_of_days ; ?>" type="number" name="hyper_num_days" placeholder="No. of days"  required>
						</div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">Include Weekends	</div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">
							<select class="form-control" name="hyper_weekends" >
								<option <?php if( $result->weekend_include == 'Yes' ){ echo 'selected'; } ?> value="Yes">Yes</option>
								<option <?php if( $result->weekend_include == 'No' ){ echo 'selected'; } ?> value="No">No</option>
							</select>
						</div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">
							<input type="submit" id="agile_submit_calculate_btn" class="btn btn-primary" name="submit_package" value="Save">
						</div>
					</div>
				</form>
			</div><!-- end container -->
			<?php
			}
			$sql = "SELECT * FROM {$wpdb->prefix}investment_packages ";
			$results =  $wpdb->get_results($sql);
			?>
			<div class="tw-bs container"><!-- start container -->
				<div class="row">
					<div class="col-md-12"><h2>All Packages</h2></div>
				</div>
				<div class="row">
					<div class="col-md-2"><b>Package Name</b></div>
					<div class="col-md-2"><b>Interest Rate</b></div>
					<div class="col-md-2"><b>Reinvest  Rate</b></div>
					<div class="col-md-2"><b>No. of days</b></div>
					<div class="col-md-2"><b>Include Weekends</b></div>
					<div class="col-md-2"><b>Edit</b></div>
				</div>
				<?php
				if($results){
					foreach( $results as $rs ){
				?>
					<div class="row">
						<div class="col-md-2"><?php echo $rs->package_name ; ?></div>
						<div class="col-md-2"><?php echo $rs->invest_rate ; ?></div>
						<div class="col-md-2"><?php echo $rs->re_invest_rate ; ?></div>
						<div class="col-md-2"><?php echo $rs->no_of_days ; ?></div>
						<div class="col-md-2"><?php echo $rs->weekend_include ; ?></div>
						<div class="col-md-2"><a href="<?php echo get_permalink().'admin.php?page=invest_packages&package_id='.$rs->package_id ; ?>" > Eidt</a></div>
					</div>
					<?php
					}
				}
				?>
			</div><!-- end container -->
			<?php
		}
		
		function my_deactivation() {
			wp_clear_scheduled_hook('my_task_hook');
			//wp_clear_scheduled_hook('my_task_hook_monthly');
		}
		
		function packages(){
			global $wpdb;
			
			if( isset($_POST['submit_package']) ){
				$package_name = $_POST['package_name'];
				$rate = $_POST['hyper_invs_rate'];
				$re_rate = $_POST['hyper_re_invs_rate'];
				$no_days = $_POST['hyper_num_days'];
				$weekend = $_POST['hyper_weekends'];
				$min_amount = $_POST['hyper_min_amount'];
				$max_amount = $_POST['hyper_max_amount'];
				$date = date('Y-m-d');
				
				$sql = "INSERT INTO {$wpdb->prefix}investment_packages(package_name,no_of_days,invest_rate,re_invest_rate,weekend_include,min_amount,max_amount,created_date,status) VALUES('{$package_name}','{$no_days}','{$rate}','{$re_rate}','{$weekend}','{$min_amount}','{$max_amount}','{$date}','1' )";
				$wpdb->query($sql);
				$lastid = $wpdb->insert_id;
				
				echo '<h1>Package added successfully.</h1>';
			}
			?>
			<div class="tw-bs container"><!-- start container -->
				<div class="row">
					<div class="col-md-12"><b style="color:green;" id="welldoneapp_create"></b></div>
				</div>
				<div class="row">
					<div class="col-md-12">
						<h3>Add Your Plan</h3>
					</div>
				</div>
				<form method="post" action="">
					<div class="row">
						<div class="col-md-12 yezter_top">Packages<span>*</span></div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">
							<input type="text" name="package_name" required class="form-control" >
						</div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">Interest Rate<span>*</span></div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">
							<input class="form-control" step="any" type="number" name="hyper_invs_rate" placeholder="Interest Rate"  required >
						</div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">Reinvest Rate</div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">
							<input class="form-control" step="any" type="number" name="hyper_re_invs_rate" placeholder="Interest Rate"  >
						</div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">Minimum Amount</div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">
							<input class="form-control" step="any" type="number" name="hyper_min_amount" placeholder="Minimum Amount"  >
						</div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">Maximum Amount</div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">
							<input class="form-control" step="any" type="number" name="hyper_max_amount" placeholder="Maximum Amount"  >
						</div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">No. of days<span>*</span></div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">
							<input class="form-control" type="number" name="hyper_num_days" placeholder="No. of days"  required>
						</div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">Include Weekends	</div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">
							<select class="form-control" name="hyper_weekends" >
								<option value="Yes">Yes</option>
								<option value="No">No</option>
							</select>
						</div>
					</div>
					<div class="row">
						<div class="col-md-12 yezter_top">
							<input type="submit" id="agile_submit_calculate_btn" class="btn btn-primary" name="submit_package" value="Save">
						</div>
					</div>
				</form>
			</div><!-- end container -->
			<?php
		}
		
		function send_request_signin(){
			global $wpdb;
			
			if( isset( $_GET['username'] ) ){
				$lastID = $_GET['last_id'];
				$useremail = $_GET['useremail'];
					
				$sql = "SELECT email FROM {$wpdb->prefix}investment_users WHERE id = '{$lastID}' ";
				$getExistEmail = $wpdb->get_var($sql); 
				
				if( $getExistEmail == $useremail ){
					
					$username = $_GET['username'];
					$user_pswd = $_GET['u_password'];
					$f_name = $_GET['fname'];
					$l_name = $_GET['lname'];
					$u_cell = $_GET['u_cell'];
					$u_address = $_GET['u_address'];
					$refId = $_GET['referID'];
					
					$date = date('Y-m-d');

					if (  email_exists($useremail) == false ) { 
				
						$user_id = wp_create_user( $username, $user_pswd, $useremail );
						$my_user = new WP_User( $user_id );
						$my_user->set_role( "investor" );

						$sql = "UPDATE {$wpdb->prefix}investment_users SET uid = '{$user_id}' , status= '1' ,updated_date = '{$date}' WHERE id = '{$lastID}'";
						$wpdb->query($sql);
						
						$sql = "SELECT count(id) FROM {$wpdb->prefix}investment_users WHERE status = '1' ";
						$usercount = $wpdb->get_var($sql);  // total users if user greater than 400 then add investment fee
						$invs_feeDate = get_option( '_investment_feeDate' );
						$invs_fee = get_option( '_investment_fee' );
						$current_date = date('Y-m-d');
						
						if($usercount < 400 && $current_date < $invs_feeDate ){
							$sql = "INSERT INTO {$wpdb->prefix}investments(userid,amount,no_of_days,invest_rate,created_date,status,invest_type,invest_title) VALUES('{$user_id}','25','270','0.7','{$date}','1','invest' , 'Reinvestment' )";
							$wpdb->query($sql);
						}
						
						// get refer id and update level
						$sql = "SELECT *  FROM {$wpdb->prefix}investment_users  WHERE id = '{$lastID}' ";
						$rowuser =  $wpdb->get_row($sql);
						$referralID = $rowuser->refer_uid;
						$customerID = $rowuser->customerID;
						
						$memberFee = get_option( '_membership_fee' );
						$memberFee_expire = get_option( '_membership_fee_expire_card' );
						
						require_once(ABSPATH.'wp-content/stripe-php/init.php');
				
						$secret_key = get_option( 'yezter_secret_key' );
						$stripe_api_key = $secret_key;               
						  \Stripe\Stripe::setApiKey($stripe_api_key);
						
						if(!empty( $customerID ) ){
							$charge = \Stripe\Charge::create(array(
								'customer'  => $customerID,
								'amount'    => ceil($memberFee*100),
								'currency'  => 'USD'
							));
							if($charge->paid == true){
								$sql = "INSERT INTO {$wpdb->prefix}investment_customers_subscribtion(user_id,amount,subscribe_date,status,deducted_from) VALUES('{$user_id}','{$memberFee}','{$date}','1','card')";
								$wpdb->query($sql);
							}else{
								$sql = "INSERT INTO {$wpdb->prefix}investment_customers_subscribtion(user_id,amount,subscribe_date,status,deducted_from) VALUES('{$user_id}','{$memberFee_expire}','{$date}','0','investments')";
								$wpdb->query($sql);
							}
						}else{
							$sql = "INSERT INTO {$wpdb->prefix}investment_customers_subscribtion(user_id,amount,subscribe_date,status,deducted_from) VALUES('{$user_id}','{$memberFee_expire}','{$date}','0','investments')";
							$wpdb->query($sql);
						}
						
						if( !empty( $referralID ) ){
							$sql = "SELECT COUNT( refer_uid ) FROM {$wpdb->prefix}investment_users WHERE refer_uid = '{$referralID}' ";
							$totalRefer =  $wpdb->get_var($sql);
							if( $totalRefer <= 3 ){
								$sql = "UPDATE {$wpdb->prefix}investment_users SET level = 'affiliate'  WHERE uid = '{$referralID}'";
								$wpdb->query($sql);
							}elseif( $totalRefer >= 5 ){
								$sql = "UPDATE {$wpdb->prefix}investment_users SET level = 'gold'  WHERE uid = '{$referralID}'";
								$wpdb->query($sql);
							}
						}
								
						update_user_meta($user_id, 'first_name', $f_name);
						update_user_meta($user_id, 'user_cell_num', $u_cell);
						update_user_meta($user_id, 'last_name', $l_name);
						update_user_meta($user_id, '_user_address', $u_address);
						
						$creds = array();
					
						$creds['user_login'] = $username;
						$creds['user_password'] = $user_pswd;
						$creds['remember'] = true;
						$user = wp_signon( $creds, false );
						wp_redirect( home_url('dashboard') );
						exit;
					}else{
						wp_redirect( home_url( ) );
						exit;
					}
				}else{
					wp_redirect( home_url( ) );
					exit;
				}
			}else{
				wp_redirect( home_url() );
				exit;
			}
		}
		
		function from_yezter_name(){
			$wpfrom = "Ulynd.com";
			return $wpfrom;
		}
		
		function from_yezter_from(){
			$wpfrom = 'admin@ulynd.com';
			return $wpfrom;
		}
		
		function confirm_user_mail(){
			ob_start();
			?>
			<div id=":au" class="ii gt "><div id=":b7" class="a3s aXjCH m16069246ea9540fa"><div class="adM">
</div><u></u>






<div marginwidth="0" marginheight="0" style="width:100%;margin:0;padding:0;background-color:#f5f5f5;font-family:Helvetica,Arial,sans-serif">
<div id="m_-2785494934782886297top-orange-bar" style="display:block;height:5px;background-color:rgb(26, 85, 145);"></div>
<center>
<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" class="m_-2785494934782886297background-table">
<tbody><tr>
<td align="center" valign="top" style="border-collapse:collapse">
<table border="0" cellpadding="0" cellspacing="0" width="638" id="m_-2785494934782886297main-table">
<tbody><tr>
<td align="center" valign="top" height="20" style="border-collapse:collapse">
</td>
</tr>
<tr>
<td align="center" valign="top" style="border-collapse:collapse"><table width="100%" border="0">
<tbody><tr>
<td width="127" align="center" style="border-collapse:collapse"><a href="<?php echo home_url(); ?>"  ><img src="http://ulynd.com/wp-content/uploads/2018/01/logoinvs-small.png" alt="Zendesk Chat"   border="0" class="m_-2785494934782886297logo CToWUd" style="border:0;height:auto;line-height:120%;outline:none;text-decoration:none;color:#d86c00;padding-left:10px;font-family:Helvetica,Arial,sans-serif;font-size:25px;vertical-align:text-bottom;text-align:center"></a></td>
</tr>
<tr>
<td align="center" colspan="3" class="m_-2785494934782886297email-title" style="border-collapse:collapse;font-family:Helvetica,Arial,sans-serif;font-size:30px;font-weight:bold;line-height:120%;color:rgb(82,82,82);text-align:center;zoom:1">Dear #username#</td>
</tr>
</tbody></table></td>
</tr>
<tr>
	<td class="m_-2785494934782886297white-space m_-2785494934782886297headnote" align="center" style="padding: 2em;border-collapse:collapse;background-color:rgb(255,255,255);border-color:rgb(221,221,221);border-width:1px;border-radius:5px;border-style:solid;padding:12px;font-size:13px;line-height:2em;color:rgb(119,119,119);text-align:justify"><p>Thank you for registering with us. You are now required to verify your email and activate your account.</p> <p>Please click on the following link to activate your account.</p> 
		<p>
		<a class="m_-2785494934782886297button_link" href="#yezter_link#" style="text-decoration:none;color:#fff;padding:5px 15px;background-color:#19558f;font-size:14px;border-radius:3px" target="_blank" >Click Here</a> to Verify
		</p>
		<p>Best Regards,</p>
		<p>Ulynd.com Team.</p>
	</td>
</tr>

</tbody></table>
</td>
</tr>
<tr>
<td align="center" valign="top" height="30" style="border-collapse:collapse"></td>
</tr>
</tbody></table>
</td>
</tr>
</tbody></table>
</center><div class="yj6qo"></div><div class="adL">
</div></div><div class="adL">
</div></div></div>
			<?php
			$html = ob_get_clean();
			return $html;
		}
		
		function wp_set_content_type(){
			return "text/html";
		}
		
		function random($length = 8){      
			$chars = '123456789bcdfghjklmnprstvwxzaeiou@#$%^';

			for ($p = 0; $p < $length; $p++)
			{
				$result .= ($p%2) ? $chars[mt_rand(19, 23)] : $chars[mt_rand(0, 18)];
			}

			return $result;
		}	
		
		function portal_sign_in(){
			global $wpdb;
			
			if(isset($_POST['aspk_create_account'])){
				$d_o_birth = $_POST['aspk_user_dob'];
				$f_name = $_POST['aspk_f_name'];
				$l_name = $_POST['aspk_l_name'];
				$user_email = $_POST['aspk_user_email'];
				$user_pswd = $_POST['aspk_user_pass'];
				$user_cell = $_POST['aspk_user_cell'];
				$bank_wallet = $_POST['aspk_bnk_walet'];
				$user_address = $_POST['aspk_user_address'];
				$user_address2 = $_POST['aspk_user_address_2'];
				$UserName = $_POST['aspk_user_name'];
				$state = $_POST['retState'];
				$city = $_POST['retCity'];
				$referUID = $_POST['hyperRefferUid'];
				$cardNum = $_POST['aspk_card_number'];
				$paypal_id = $_POST['aspk_paypal_id'];
				$stripeToken = $_POST['stripeToken'];
				$date = date('Y-m-d');
				if ( empty( $stripeToken ) ) {
					?>
					<script>
						jQuery(document).ready(function(){
							jQuery("#welldoneapp_create").html('Credit Card required');
						})
					</script>
					<?php
				}elseif ( username_exists( $UserName ) == true ) {
					?>
					<script>
						jQuery(document).ready(function(){
							jQuery("#welldoneapp_create").html('User name already exists.');
						})
					</script>
					<?php
				}elseif (  email_exists($user_email) == false ) { 
					$name = $f_name.' '.$l_name;
					
					if ( ! function_exists( 'wp_handle_upload' ) ) {
						require_once( ABSPATH . 'wp-admin/includes/file.php' );
					}
					$uploadedfile = $_FILES['retLogo'];
					$upload_overrides = array( 'test_form' => false );
					$movefile = wp_handle_upload( $uploadedfile, $upload_overrides );
					$useImg = $movefile['url'];
					
					if(!empty($stripeToken) ){
						require_once(ABSPATH.'wp-content/stripe-php/init.php');
						
						$secret_key = get_option( 'yezter_secret_key' );
						$stripe_api_key = $secret_key;               
						\Stripe\Stripe::setApiKey($stripe_api_key);
			  
						$customer = \Stripe\Customer::create(array(
							'email' => $user_email, // customer email id
							'card'  => $stripeToken
						));
						$customID = $customer->id;
					}
					
					$sql = "INSERT INTO {$wpdb->prefix}investment_users(refer_uid,email,password,address,address2,date_of_birth,paypal_id,bank_wallet,created_date,status,customerID) VALUES('{$referUID}','{$user_email}','{$user_pswd}','{$user_address}','{$user_address2}','{$d_o_birth}','{$paypal_id}','{$bank_wallet}','{$date}','0','{$customID}')";
					$wpdb->query($sql);
					$lastid = $wpdb->insert_id;
					
					$email_template_register = $this->confirm_user_mail();
						
					$email_template_register = str_replace( '#username#' ,$name, $email_template_register );
					$email_template_register = str_replace( '#yezter_link#' , home_url().'/wp-admin/admin-ajax.php?action=yezter_request_sign&username='.$UserName.'&useremail='.$user_email.'&u_password='.$user_pswd.'&fname='.$f_name.'&lname='.$l_name.'&u_cell='.$user_cell.'&u_address='.$user_address.'&last_id='.$lastid, $email_template_register );
						
					add_filter( 'wp_mail_content_type',array(&$this,'wp_set_content_type' ));
					
					wp_mail( $user_email, 'Please verify your account', $email_template_register  );
					//wp_mail( 'jamesallen.hts@gmail.com', 'Verification', $email_template_register  );
					
					remove_filter( 'wp_mail_content_type', array(&$this,'wp_set_content_type' ));
					
					?>
					<script>
						jQuery(document).ready(function(){
							jQuery("#welldoneapp_create").html("<p style='font-size:16px;'>A confirmation email has been sent to the email address supplied. You are required to click on the link in this email to activate your account.</p>");
							jQuery("#registerForm222").hide();
						})
					</script>
					<?php
					
				}else{
					?>
					<script>
						jQuery(document).ready(function(){
							jQuery("#welldoneapp_create").html('User with this email already exists.');
						})
					</script>
					<?php
				}
				
			}
			
			ob_start();
			
			if (  is_user_logged_in() ) {
				echo wp_redirect( home_url('/dashboard/') );
				?>
				<!--<h3 style="text-align:center;">Please <a style="color:blue;" href="<?php echo home_url('/dashboard/'); ?>">Click here</a> to view your profile</h3>-->
				<?php
			}else{
				$refferId = $_GET['referID'];
				if( isset( $_GET['referID'] ) ){
					$refferId = explode('-',$refferId);
					$referuserid = $refferId[1];
				} 	  
			?>
			
			<style>
			.form-control {
				margin-bottom: 1em;
				font-size: inherit !important;
			}
			input.form-control{
				width: 100%;
				border: 1px solid #3cb5d0;
			}
			select.form-control{
				width: 100%;
				border: 1px solid #3cb5d0;
			}
			textarea.form-control{
				width: 100%;
				border: 1px solid #3cb5d0;
			}
			.paddclass{
				padding:0.5em;
			}
			#g-recaptcha-response {
				display: block !important;
				position: absolute;
				margin: -78px 0 0 0 !important;
				width: 302px !important;
				height: 76px !important;
				z-index: -999999;
				opacity: 0;
			}
			.container22{
				border: 1px solid lightblue;
				padding:2em;
				margin-bottom: 2em;
			}
			.botbotm1em{
				padding-bottom: 1em;
			}
			#welldoneapp_create{
				color:green;
				font-size:19px;
			}
			</style>
			<script src='https://www.google.com/recaptcha/api.js'></script>
				<div class="row">
					<div class="col-md-12 botbotm1em" ><b  id="welldoneapp_create"></b></div>
				</div>
			<div class="tw-bs22 container22" id="registerForm222" ><!-- start container -->
				<div class="row">
					<div class="col-md-4 "></div>
					<div class="col-md-4 ">
						<h1 style="border-bottom:1px solid #3cb5d0;border-bottom-width: thick;" class="text-center paddclass">New Member Registration</h1>
					</div>
					<div class="col-md-4 "></div>
				</div>
				<form method="post" action="" onsubmit="downloadWin();" id="mem_form"  enctype="multipart/form-data">
					<div class="row">
						<div class="col-md-12">
							<div class="row paddclass"><!-- start row -->
								<div class="col-md-12">
									<div class="col-md-6"  style="float:left">
									<h3>Personal information</h3>
									<div class="row">
										<input type="hidden" value="<?php echo $referuserid; ?>" name="hyperRefferUid">
										<div class="col-md-5 yezter_top">First Name<span>*</span></div>
										<div class="col-md-7 yezter_top">
											<input class="form-control" type="text" name="aspk_f_name" value="<?php echo $_POST['aspk_f_name']; ?>"  required>
										</div>
									</div>
									<div class="row">
										<div class="col-md-5 yezter_top">Last Name<span>*</span></div>
										<div class="col-md-7 yezter_top">
											<input class="form-control" type="text" name="aspk_l_name" value="<?php echo $_POST['aspk_l_name']; ?>"  required>
										</div>
									</div>
									<div class="row">
										<div class="col-md-5 yezter_top">Date of Birth:<span>*</span></div>
										<div class="col-md-7 yezter_top">
											<input type="date" class="form-control" name="aspk_user_dob" value="<?php echo $_POST['aspk_user_dob']; ?>" required>
										</div>
									</div>
									<div class="row">
										<div class="col-md-5 yezter_top">Address Line 1:<span>*</span></div>
										<div class="col-md-7 yezter_top">
										  <textarea  class="form-control"  name="aspk_user_address" required ><?php echo $_POST['aspk_user_address']; ?></textarea>
										</div>
									</div>
									<div class="row">
										<div class="col-md-5 yezter_top">Address Line 2:</div>
										<div class="col-md-7 yezter_top">
										  <textarea  class="form-control"  name="aspk_user_address_2"  ><?php echo $_POST['aspk_user_address_2']; ?></textarea>
										</div>
									</div>
									<div class="row">
										<div class="col-md-5 yezter_top">Email Address:<span>*</span></div>
										<div class="col-md-7 yezter_top">
											<input id="aspk_ajax_mail" class="form-control" type="email" name="aspk_user_email" value="<?php echo $_POST['aspk_user_email']; ?>" required>
										</div>
									</div>
									<div class="row">
										<div class="col-md-5 yezter_top">Confirm Email:<span>*</span></div>
										<div class="col-md-7 yezter_top">
											<input id="aspk_ajax_mail_confirm" name="aspk_mail_confirm" class="form-control" value="<?php echo $_POST['aspk_mail_confirm']; ?>" type="email"  required>
										</div>
									</div>
									<div class="row">
										<div class="col-md-5" ></div>
										<div class="col-md-7 form-control" style="display:none;color:red;font-size:18px;" id="showmsg_email">Email does not match</div>
									</div>
									<div class="row">
										<div class="col-md-5 yezter_top">Phone Number<span>*</span></div>
										<div class="col-md-7 yezter_top">
											<input type="number" class="form-control" value="<?php echo $_POST['aspk_user_cell']; ?>" name="aspk_user_cell" required>
										</div>
									</div>
									</div>
									<div class="col-md-6"  style="float:left">
										<h3>Financial Information:</h3>
										
										<div class="row">
											<div class="col-md-12" >
												<input type="hidden" id="memberPricebit" value="<?php echo get_option( '_membership_fee' ); ?>" />
												<p style="color:green;display:none;font-weight:bold;font-size:18px;" id="msgcreditsaved">Your Credit Card has been saved successfully</p>
												<input type="button" onclick="saveinfo();" id="credit_cardinfo22" class="btn btn-primary" value="Add Credit Card" />
											</div>
										</div>
										<!--<div class="row">
											<div class="col-md-5 yezter_top">Credit Card Number:</div>
											<div class="col-md-7 yezter_top">
												<input id="aspk_cardnumber" class="form-control" type="text" name="aspk_card_number"  >
											</div>
										</div>-->
										<div class="row">
											<div class="col-md-5 yezter_top">Paypal ID:</div>
											<div class="col-md-7 yezter_top">
												<input id="aspk_routing" class="form-control" value="<?php echo $_POST['aspk_paypal_id']; ?>" type="email" name="aspk_paypal_id"  >
											</div>
										</div>
										<div class="row">
											<div class="col-md-5 yezter_top">Bitcoin Wallet:</div>
											<div class="col-md-7 yezter_top">
												<input type="text"  class="form-control" value="<?php echo $_POST['aspk_bnk_walet']; ?>"  name="aspk_bnk_walet"   >
											</div>
										</div>
										<h3>Account Information:</h3>
											
										<div class="row">
											<div class="col-md-5 yezter_top">Username:<span>*</span></div>
											<div class="col-md-7 yezter_top">
												<input id="aspk_ajax_mail" class="form-control" value="<?php echo $_POST['aspk_user_name']; ?>" type="text" name="aspk_user_name" value="" required="">
											</div>
										</div>
										<div class="row">
											<div class="col-md-5 yezter_top">Password<span>*</span></div>
											<div class="col-md-7 yezter_top">
												<input class="form-control" type="password" id="chk_pass" value="<?php echo $_POST['aspk_user_pass']; ?>" name="aspk_user_pass"  required="">
											</div>
										</div>
										<div class="row">
											<div class="col-md-5 yezter_top">Confirm Password<span>*</span></div>
											<div class="col-md-7 yezter_top">
												<input class="form-control" name="confirm_password22" type="password" value="<?php echo $_POST['confirm_password22']; ?>" id="confirm_password"  required="">
											</div>
										</div>
										<div class="row">
											<div class="col-md-5" ></div>
											<div class="col-md-7 form-control" style="display:none;color:red;font-size:18px;" id="showmsg_psd">Password does not match</div>
										</div>
										<div class="row">
											<div class="col-md-5 yezter_top">Affiliate Code (If Any)</div>
											<div class="col-md-7 yezter_top">
												<input class="form-control" type="text" id="aflitcode"  value="<?php if(!empty( $referuserid ) ){ echo 'referalid-'.$referuserid; } ?>" >
											</div>
										</div>
										<div class="row">
											<div class="col-md-5"></div>
											<div class="col-md-7">
											<div class="form-group">
												<!-- Replace data-sitekey with your own one, generated at https://www.google.com/recaptcha/admin -->
												<div class="g-recaptcha"
												   data-callback="capcha_filled"
												   data-expired-callback="capcha_expired"
												   data-sitekey="6LdprT4UAAAAADHPcnap3hBAjJpszFaCja5e_Cv6"></div>
											</div>
											</div>
										</div>
										<div class="row">
											<div class="col-md-12 yezter_top" style="margin-bottom:15px;display: flow-root;text-align:right;">
												<span style=""><input type="checkbox" name="check_cond"  required /></span>
												<span style="margin-left:5px;">I accept and Agree to the ULynd Terms of Service</span>
											</div>
										</div>
										<div class="row">
											<div class="col-md-6 yezter_top"></div>
											<div class="col-md-4 yezter_top">
												<input type="submit"  id="agile_submit_signup_btn" class="btn btn-danger" name="aspk_create_account" value="Sign Up">
											</div>
										</div>
									</div>
								</div>
							</div> 
						</div> 
					</div> 
				</form>
			</div><!-- end container -->
				
				<script src="https://checkout.stripe.com/checkout.js"></script>
				<script>
					var handler = StripeCheckout.configure
					({
						key: '<?php echo get_option( 'yezter_stripe_key' ); ?>',
						image: 'https://stripe.com/img/documentation/checkout/marketplace.png',
						token: function(token) 
						{
							jQuery('#mem_form').append("<input type='hidden' name='stripeToken' value='" + token.id + "' />"); 
							jQuery('#credit_cardinfo22').hide(); 
							jQuery('#msgcreditsaved').show(); 
							setTimeout(function(){
								//jQuery('#mem_form').submit(); 
								
							}, 200); 
						}
					}); 
					
					jQuery( document ).ready(function() {
						jQuery("#confirm_password").focusout(function(){
							var password = document.getElementById("chk_pass").value;
							var confirmPassword = document.getElementById("confirm_password").value;
							if (password != confirmPassword) {
								jQuery("#showmsg_psd").show();
								return false;
							}
							if (password == confirmPassword) {
								jQuery("#showmsg_psd").hide();
							}
						});
						jQuery("#aspk_ajax_mail_confirm").focusout(function(){
							var password = document.getElementById("aspk_ajax_mail").value;
							var confirmPassword = document.getElementById("aspk_ajax_mail_confirm").value;
							if (password != confirmPassword) {
								jQuery("#showmsg_email").show();
								return false;
							}
							if (password == confirmPassword) {
								jQuery("#showmsg_email").hide();
							}
						});
					});
					
					function saveinfo(){
						var amount = jQuery('#memberPricebit').val();
						total=amount*100;
						handler.open({
						//name: "Membership Fee",
						name: "Add Credit Card",
						panelLabel: "Save",
						currency : 'USD',
						//description: 'Charges( '+amount+' USD )',
						description: 'Save card information',
						//amount: total
						});
					}
					function downloadWin(){
						var password = document.getElementById("chk_pass").value;
						var confirmPassword = document.getElementById("confirm_password").value;
						if (password != confirmPassword) {
							jQuery("#showmsg_psd").show();
							return false;
						}
						if (password == confirmPassword) {
							jQuery("#showmsg_psd").hide();
						}
						jQuery("#aspk_ajax_mail_confirm").focusout(function(){
							var password = document.getElementById("aspk_ajax_mail").value;
							var confirmPassword = document.getElementById("aspk_ajax_mail_confirm").value;
							if (password == confirmPassword) {
								jQuery("#showmsg_email").hide();
							}
							if (password != confirmPassword) {
								jQuery("#showmsg_email").show();
								return false;
							}
						});
						
					}
					
					window.onload = function() {
						var $recaptcha = document.querySelector('#g-recaptcha-response');

						if($recaptcha) {
							$recaptcha.setAttribute("required", "required");
						}
					};
				</script>
			<?php
			}
			$html = ob_get_clean();
			
			return $html;
		}
		
		function feInit(){
			if( !session_id() ){
				session_start();
			}
			ob_start();
		}
		
		function getcities(){
			global  $wpdb ;
			
			$stateId = $_POST['idState'];
			
			$sql = "select * from cities where state_id = {$stateId}";	
			$rst = $wpdb->get_results($sql);
			if($rst){
				
				ob_clean();
				?>
					<select id="cityId" name="retCity" required class="form-control">
						<option value="">Select City</option>
				<?php
					foreach($rst as $city){
					?>
					<option value="<?php echo $city->id;?>"    ><?php echo $city->name;?></option>
					<?php 
					}
					?>
					</select>
				<?php
				$data = ob_get_clean();
				$ret = array('act' => 'show_state','st'=>'ok','msg'=>'send','html'=>$data);
				echo json_encode($ret);
				exit;
			}else{
				$rs = ob_get_clean();
				$ret = array('act' => 'show_state','st'=>'fail','msg'=>'fail');
				echo json_encode($ret);
				exit;
			}
		}
		
		function getStates(){
			global  $wpdb ;
			
			$countryId = $_POST['countID'];
			
			$sql = "select * from states where country_id = {$countryId}";	
			$rst = $wpdb->get_results($sql);
			if($rst){
				
				ob_clean();
				?>
					<select id="stateId" name="retState" required class="form-control">
						<option value="">Select State</option>
				<?php
					foreach($rst as $state){
					?>
					<option value="<?php echo $state->id;?>"    ><?php echo $state->name;?></option>
					<?php 
					}
					?>
					</select>
					<script>
						jQuery( "#stateId" ).change(function() {
						
							jQuery("#aspk_gif_img2").show();
							jQuery("#aspk_btn_sbnt").attr('disabled','disabled');
							jQuery("#stateId").attr('disabled','disabled');
							jQuery("#hideSelect").hide();
							jQuery("#cityId").html('<select class="form-control"  name="retCity"><option value="">Select City</option></select>');
							var cId = jQuery("#stateId").val();
							
							var data = {
									'action': 'citiesGet',
									'idState': cId
									
								};
							var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
							// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
							
							jQuery.post(ajaxurl, data, function(response) {
								jQuery("#aspk_gif_img2").hide();
								jQuery("#aspk_btn_sbnt").attr('disabled',false);
								jQuery("#stateId").attr('disabled',false);
								var obj = JSON.parse(response);
								jQuery("#cityId").html(obj.html);
							});
						
						});
				</script> 
				<?php
				$data = ob_get_clean();
				$ret = array('act' => 'show_state','st'=>'ok','msg'=>'send','html'=>$data);
				echo json_encode($ret);
				exit;
			}else{
				$rs = ob_get_clean();
				$ret = array('act' => 'show_state','st'=>'fail','msg'=>'fail');
				echo json_encode($ret);
				exit;
			}
		}
		
		function includeScript(){
			wp_enqueue_script('jquery');
			wp_enqueue_style( 'hypertext_autosearch', plugins_url('css/jq-ui.css', __FILE__) );
			wp_enqueue_style( 'hypertext_css_boot', plugins_url('css/bootstrap.min.css', __FILE__) );
			//wp_enqueue_script('hypertext_js_boot',plugins_url('js/jq-ui.js', __FILE__) );
			wp_enqueue_script('hypertext_js_boot',plugins_url('js/bootstrap.min.js', __FILE__) );
		}
		
		function install(){
			global $wpdb;
			
			
			/// testing install 
			
			if ( ! wp_next_scheduled( 'my_task_hook' ) ) {
				wp_schedule_event( time(), 'twise_daily', 'my_task_hook');
				//wp_schedule_event( time(), 'every_three_minutes', 'my_task_hook');
			}
			
			/* if ( ! wp_next_scheduled( 'my_task_hook_monthly' ) ) {
			   wp_schedule_event( time(), 'monthly', 'my_task_hook_monthly' );
			} */
			
			$table_name = $wpdb->prefix . 'investment_users';

			$sql = "CREATE TABLE $table_name (
			  id int(11) NOT NULL AUTO_INCREMENT,
			  email varchar(525) DEFAULT NULL,
			  password varchar(525) DEFAULT NULL,
			  address varchar(1000) DEFAULT NULL,
			  address2 varchar(1000) DEFAULT NULL,
			  date_of_birth varchar(225) DEFAULT NULL,
			  country varchar(525) DEFAULT NULL,
			  state varchar(525) DEFAULT NULL,
			  city varchar(525) DEFAULT NULL,
			  uid int(11) DEFAULT NULL,
			  refer_uid int(11) DEFAULT NULL,
			  license_Idcard_picture varchar(225) DEFAULT NULL,
			  bank_wallet varchar(225) DEFAULT NULL,
			  credit_card_number varchar(225) DEFAULT NULL,
			  paypal_id varchar(525) DEFAULT NULL,
			  created_date date DEFAULT NULL,
			  updated_date date DEFAULT NULL,
			  status varchar(225) DEFAULT NULL,
			  level varchar(225) DEFAULT NULL,
			  customerID varchar(225) DEFAULT NULL,
			  UNIQUE KEY id (id)
			)$charset_collate;";
			
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
			
			$t_name = $wpdb->prefix . 'investments';

			$sql = "CREATE TABLE $t_name (
			  invest_id int(11) NOT NULL AUTO_INCREMENT,
			  userid int(11) DEFAULT NULL,
			  p_orderID int(11) DEFAULT NULL,
			  amount varchar(225) DEFAULT NULL,
			  no_of_days varchar(225) DEFAULT NULL,
			  invest_rate varchar(225) DEFAULT NULL,
			  re_invest_rate varchar(225) DEFAULT '100',
			  weekend_include varchar(225) DEFAULT NULL,
			  created_date date DEFAULT NULL,
			  updated_date date DEFAULT NULL,
			  status varchar(225) DEFAULT NULL,
			  invest_type varchar(225) DEFAULT NULL,
			  invest_title varchar(525) DEFAULT NULL,
			  UNIQUE KEY id (invest_id)
			)$charset_collate;";
			
			
			//require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
			
			$t_name = $wpdb->prefix . 'investment_packages';

			$sql = "CREATE TABLE $t_name (
			  package_id int(11) NOT NULL AUTO_INCREMENT,
			  package_name varchar(225) DEFAULT NULL,
			  no_of_days varchar(225) DEFAULT NULL,
			  invest_rate varchar(225) DEFAULT NULL,
			  re_invest_rate varchar(225) DEFAULT NULL,
			  weekend_include varchar(225) DEFAULT NULL,
			  min_amount varchar(225) DEFAULT NULL,
			  max_amount varchar(225) DEFAULT NULL,
			  created_date date DEFAULT NULL,
			  updated_date date DEFAULT NULL,
			  status varchar(225) DEFAULT NULL,
			  UNIQUE KEY id (package_id)
			)$charset_collate;";
			
			
			//require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
			
			$t_name = $wpdb->prefix . 'all_interest';

			$sql = "CREATE TABLE $t_name (
			  interest_id int(11) NOT NULL AUTO_INCREMENT,
			  invest_id int(11) DEFAULT NULL,
			  days varchar(225) DEFAULT NULL,
			  interest_date date DEFAULT NULL,
			  earning varchar(225) DEFAULT NULL,
			  cashout varchar(225) DEFAULT NULL,
			  total_int_principal varchar(225) DEFAULT NULL,
			  status varchar(225) DEFAULT NULL,
			  user_id int(11) DEFAULT NULL,
			  UNIQUE KEY id (interest_id)
			)$charset_collate;";
			
			
			//require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
			
			$t_name = $wpdb->prefix . 'log_referred_amount';

			$sql = "CREATE TABLE $t_name (
			  id int(11) NOT NULL AUTO_INCREMENT,
			  user_id int(11) DEFAULT NULL,
			  reffer_uid int(11) DEFAULT NULL,
			  ref_amount varchar(225) DEFAULT NULL,
			  referred_date date DEFAULT NULL,
			  status varchar(225) DEFAULT NULL,
			  UNIQUE KEY id (id)
			)$charset_collate;";
			
			
			//require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
			
			$t_name = $wpdb->prefix . 'request_withdrawal';

			$sql = "CREATE TABLE $t_name (
			  id int(11) NOT NULL AUTO_INCREMENT,
			  user_id int(11) DEFAULT NULL,
			  withdraw_amount varchar(225) DEFAULT NULL,
			  pay_gateway varchar(225) DEFAULT NULL,
			  created_date date DEFAULT NULL,
			  updated_date date DEFAULT NULL,
			  status varchar(225) DEFAULT NULL,
			  reason longtext DEFAULT NULL,
			  UNIQUE KEY id (id)
			)$charset_collate;";
			
			
			//require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
			
			$t_name = $wpdb->prefix . 'total_Investment_balance';

			$sql = "CREATE TABLE $t_name (
			  id int(11) NOT NULL AUTO_INCREMENT,
			  investment_id int(11) DEFAULT NULL,
			  user_id int(11) DEFAULT NULL,
			  tot_invs_amount varchar(225) DEFAULT NULL,
			  created_date date DEFAULT NULL,
			  status varchar(225) DEFAULT NULL,
			  amount_type varchar(225) DEFAULT NULL,
			  UNIQUE KEY id (id)
			)$charset_collate;";
			
			
			//require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
			
			$table_name = $wpdb->prefix . 'investment_customers_subscribtion';

			$sql = "CREATE TABLE $table_name (
			  id int(11) NOT NULL AUTO_INCREMENT,
			  user_id int(11) DEFAULT NULL,
			  amount float DEFAULT NULL,
			  subscribe_date date DEFAULT NULL,
			  expiry_date date DEFAULT NULL,
			  status varchar(225) DEFAULT NULL,
			  deducted_from varchar(225) DEFAULT NULL,
			  UNIQUE KEY id (id)
			)$charset_collate;";
			
			dbDelta( $sql );
			
			add_role( 'investor', 'investor', array( 'read' => true, 'level_0' => true ) );
		}
		
		function admin_init(){
			$deleteAction = $_GET['action'];
			$deleteAction2 = $_GET['action2'];
			
			if( $deleteAction2 == 'delete'  ){
				$delusersID = $_GET['users'];
				if($delusersID){
					global $wpdb;
					foreach($delusersID as $delId){
						if( isset( $_POST['submit'] ) && $_POST['submit'] == 'Confirm Deletion'  ){
							$sql = "DELETE FROM {$wpdb->prefix}investment_users WHERE uid = {$delId}";
							$wpdb->query($sql);							
							// Update investment status to 0 when user deleted
							$sql = "UPDATE  {$wpdb->prefix}investments SET  status =  '0' WHERE  userid ={$delId}";
							$wpdb->query($sql);
							
							// Delet withdraw requests of specif user
							$sql = "DELETE FROM {$wpdb->prefix}request_withdrawal WHERE user_id = {$delId}";
							$wpdb->query($sql);
						}
					}
					
				}
			}elseif( $deleteAction == 'delete' ){
				$delUserID = $_GET['user'];
				if( isset( $_POST['submit'] ) && $_POST['submit'] == 'Confirm Deletion'  ){
					global $wpdb;
					
					$sql = "DELETE FROM {$wpdb->prefix}investment_users WHERE uid = {$delUserID}";
					$wpdb->query($sql);
					// Update investment status to 0 when user deleted
					$sql = "UPDATE  {$wpdb->prefix}investments SET  status =  '0' WHERE  userid ={$delUserID}";
					$wpdb->query($sql);
					
					// Delet withdraw requests of specif user
					$sql = "DELETE FROM {$wpdb->prefix}request_withdrawal WHERE user_id = {$delUserID}";
					$wpdb->query($sql);
				}
			}
			add_meta_box('investment_packages', 'Update Product Package', array( $this,'package_detail'),'product');
		}
		
		function save_post_data(){
			global $post;
			
			$post_id = $_POST['ID'];
			$min_amount['_hypertext_min_amount'] = $_POST['hyper_min_amount'];
			$max_amount['_hypertext_max_amount'] = $_POST['hyper_max_amount'];
			$investRate['_hypertext_inv_rate'] = $_POST['hyper_invest_rate'];
			$noOfdays['_hypertext_no_of_days'] = $_POST['hyper_num_days'];

			foreach ($min_amount as $key => $value) {
				update_post_meta( $post_id, $key, $value );
			}
			
			foreach ($max_amount as $key => $value) {
				update_post_meta( $post_id, $key, $value );
			}
			
			foreach ($investRate as $key => $value) {
				update_post_meta( $post_id, $key, $value );
			}
			foreach ($noOfdays as $key => $value) {
				update_post_meta( $post_id, $key, $value );
			}
		}
		
		function package_detail(){
			global $post;
		
			// Get the location data if its already been entered
			$min_amount = get_post_meta($post->ID, '_hypertext_min_amount', true);
			$max_amount = get_post_meta($post->ID, '_hypertext_max_amount', true);
			$inv_rate = get_post_meta($post->ID, '_hypertext_inv_rate', true);
			$no_days = get_post_meta($post->ID, '_hypertext_no_of_days', true);
			// Echo out the field
			?>
			<div class="inside">
				<p><label>Minimum Amount:</label><input type="number" step="any" required name="hyper_min_amount" value="<?php echo $min_amount ; ?>" class="widefat" /></p>
				<p><label>Maximum Amount:</label><input type="number" step="any" required name="hyper_max_amount" value="<?php echo $max_amount ; ?>" class="widefat" /></p>
				<p><label>Invest Rate:</label><input type="number" step="any" required name="hyper_invest_rate" value="<?php echo $inv_rate ; ?>" class="widefat" /></p>
				<p><label>NO. of Days</label><input type="number" required name="hyper_num_days" value="<?php echo $no_days ; ?>" class="widefat" /></p>
				<!--<p><label>Include Weekends</label><select  required name="hyper_num_days" value="<?php echo $no_days ; ?>" class="widefat" /></p>-->
			</div>
		<?php
		}
		
	}//end class
}//end main class
$investmentCalc = new investCalculator();	