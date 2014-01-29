<?php
/**
 * PaypalCallbackAction 
 * 
	Provides the functionality to connect your application to Paypal via
	callback URL.
	
  	install it your controller by copying this code snippet:

		public function actions()
		{
			return array(
				'paypalcallback'=>array(
					'class'=>
						'application.extensions.yii-billing.PaypalCallbackAction',
					'api' =>new MyBilling(),
					'use_sandbox' => Yii::app()->params['paypal_use_sandbox'],
				),
				'backtomerchant'=>array(
					'class'=>
						'application.extensions.yii-billing.BacktoMerchantAction',
				),
			);
		}

	$txn_type = $_POST['txn_type'];
	$payer_id = $_POST['payer_id'];
	$txn_id = $_POST['txn_id'];
	$payment_date = $_POST['payment_date'];
	$custom = $_POST['custom'];
	$payment_status = $_POST['payment_status'];
	$mc_gross_1 = $_POST['mc_gross'];
	$mc_fee = $_POST['mc_fee'];


	possible payment_status values:

	Canceled_Reversal: 
		A reversal has been canceled. For example, you won a dispute with 
		the customer, and the funds for the transaction that was reversed 
		have been returned to you.
	Completed: 
		The payment has been completed, and the funds have been added 
		successfully to your account balance.
	Created: 
		A German ELV payment is made using Express Checkout.
	Denied: 
		You denied the payment. This happens only if the payment was 
		previously pending because of possible reasons described for the 
		pending_reason variable or the Fraud_Management_Filters_x variable.
	Expired: 
		This authorization has expired and cannot be captured.
	Failed: 
		The payment has failed. This happens only if the payment was made 
		from your customer’s bank account.
	Pending: 
		The payment is pending. See pending_reason for more information.
	Refunded: 
		You refunded the payment.
	Reversed: 
		A payment was reversed due to a chargeback or other type of reversal. 
		The funds have been removed from your account balance and returned 
		to the buyer. The reason for the reversal is specified in the 
		ReasonCode element.
	Processed: 
		A payment has been accepted.
	Voided: 
		This authorization has been voided.
 * @uses CAction
 * @author Christian Salazar <christiansalazarh@gmail.com> 
 * @license FREE BSD
 */
class PaypalCallbackAction extends CAction {
	public $api;
	public $use_sandbox;  // boolean

	public function getPost($name){
		if(isset($_POST[$name]))
			return $_POST[$name];
		return null;
	}

	/**
	 * run 
	 *
	 *	sample URL to invoke this callback from paypal:
	 *
	 *	http://coquito/wh/index.php?r=/pwacore/billing/paypalcallback&custom=123456
	 *	
	 * @access public
	 * @return void
	 */
	public function run(){
		Yii::log(__METHOD__." Callback Invoked. POST:\n[BEGIN POST]\n"
				.json_encode($_POST)."\n[END POST]\n","paypal");
		$bill_key = $this->getPost('custom');
		$data = $this->api->getBillInfo($bill_key);
		if(empty($data)){
			Yii::log(__METHOD__.". invalid bill key. ".$bill_key,"paypal");
		}elseif($this->validateIPN($this->use_sandbox)){
			$payment_status = $this->getPost('payment_status');
			if($payment_status == 'Completed'){
				Yii::log(__METHOD__.".OK.call receivePayment.","paypal");
				$this->api->receivePayment(
					$bill_key,'accepted',$this->getPost('txn_id'));
			}
		}else{
			Yii::log(__METHOD__." IPN not validated. bill_key="
				.$bill_key,"paypal");
		}
	}

	private function validateIPN($use_sandbox){
		$paypal_url = "https://www.paypal.com/cgi-bin/webscr";
		if($use_sandbox == true) 
		   $paypal_url = "https://www.sandbox.paypal.com/cgi-bin/webscr";
		$raw_post_data = file_get_contents('php://input');
		$raw_post_array = explode('&', $raw_post_data);
		$myPost = array();
		foreach ($raw_post_array as $keyval) {
			$keyval = explode ('=', $keyval);
			if (count($keyval) == 2)
				$myPost[$keyval[0]] = urldecode($keyval[1]);
		}
		$req = 'cmd=_notify-validate';
		if(function_exists('get_magic_quotes_gpc')) {
			$get_magic_quotes_exists = true;
		}
		foreach ($myPost as $key => $value) {
			if($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
				$value = urlencode(stripslashes($value));
			} else {
				$value = urlencode($value);
			}
			$req .= "&$key=$value";
		}
		$ch = curl_init($paypal_url);
		if ($ch == FALSE) return FALSE;
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));
		$res = curl_exec($ch);
		if (curl_errno($ch) != 0) {
			$errstr = curl_error($ch);
			Yii::log(__METHOD__." curl error: ".$errstr,"paypal");
			curl_close($ch);
			exit;

		} else {
			curl_getinfo($ch, CURLINFO_HEADER_OUT);
			curl_close($ch);
		}
		Yii::log(__METHOD__." response=".$res,"paypal");
		if (strcmp ($res, "VERIFIED") == 0) {
			// check whether the payment_status is Completed
			$payment_status = $_POST['payment_status'];
			if($payment_status == 'Completed'){
				return true;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}
}
