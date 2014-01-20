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
					'url' => Yii::app()->params['paypal_url'],
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
	public $url;

	public function getPost($name){
		if(isset($_POST[$name]))
			return $_POST[$name];
		return null;
	}

	public function run(){
		$bill_key = $this->getPost('custom');
		$data = $this->api->findBill($bill_key);
		if(empty($data)){
			Yii::log(__METHOD__.". invalid bill key. ".$bill_key,"info");
		}elseif($this->validateIPN($this->url)){
			$payment_status = $this->getPost('payment_status');
			if($payment_status == 'Completed'){
				$this->api->receivePayment(
					$bill_key,'accepted',$this->getPost('txn_id'));
			}
		}
	}

	private function validateIPN($url){
        error_reporting(E_ALL ^ E_NOTICE);
		$p_reception = '';

        $req = 'cmd=_notify-validate';
        foreach ($_POST as $key => $value) {
			if(function_exists('get_magic_quotes_gpc') 
				&& (get_magic_quotes_gpc()==1)) {
					$value = urlencode(stripslashes($value));
                }else{
					$value = urlencode($value);
				}
			$req .= "&$key=$value";
        }
        
		$header .= "POST /cgi-bin/webscr HTTP/1.0\r\n";
        $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $header .= "Content-Length: " . strlen($req) . "\r\n\r\n";

        $fp = @fsockopen ($url, 443, $errno, $errstr, 30);
        if (!$fp) {
			$p_reception = "HTTP-ERROR";
        } else {
			fputs ($fp, $header . $req);
			while (!feof($fp)){
				$res = fgets ($fp, 1024);
				if (strcmp ($res, "VERIFIED") == 0){
					$p_reception = "VERIFIED";
				}else if (strcmp ($res, "INVALID") == 0) {
					$p_reception = "INVALID";
			}}
        	fclose ($fp);
        }
        return ($p_reception == "VERIFIED");
	}
}
