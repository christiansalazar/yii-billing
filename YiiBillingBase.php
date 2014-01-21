<?php
/**
 * YiiBillingBase 
 *
 *	the identity has 3 status:	
 *
 *		'plan-required'
 *		'need-payment'
 *		'up-to-date'
 *
 *	initialize with a call to newIdentity, after this step:
 *		the identity will have a BillAccount
 *		the BillAccount status is: 'plan-required'
 *		the identity is enabled to select a plan
 *		the selected plan is: noplan
 *
 *	at any moment you can make a call to any of: 
 *		checkAccountStatus
 *		canSelectPlan
 *
 *	for moving the machine status from 'plan-required' to 'need-payment' :
 *		selectPlan
 *			only can continue if canSelectPlan
 *			the Bill quotes has been created depending on the selected plan
 *			the BillAccount status now is: 'need-payment'
 *			the current Bill is the first one of the Bill set created
 *			user can no longer select a plan again until all bill expires.
 *
 *	for moving the machine status from 'need-payment' to 'up-to-date':
 *		call  makePayment
 *		or
 *		when callback is called when a remote payment arrives, remember to
 *		return true when receiving the event: 'onPaymentReceived'
 *		
 *	for moving the machine status from 'up-to-date' back to 'need-payment':
 *	for moving the machine status from 'up-to-date' back to 'plan-required':
 *		call checkAccountStatus
 *		it will check expiration etc and move the status when required.
 *		in case of 'plan-required': this status arrives when all bills expires.
 *
 * @abstract
 * @author Christian Salazar <christiansalazarh@gmail.com> 
 * @license FREE BSD
 */
abstract class YiiBillingBase {
	// low level persistence api
	abstract public function getBillAccountStatus($who, $accountname);
	abstract public function setBillAccountStatus($who, $accountname, $status);
	abstract protected function _createBillAccount($who, $accountname);
	abstract protected function getBillAccount($who, $accountname);
	abstract protected function createNewBillKey($who, $accountname, $item, $amount, $from_date, $to_date);
	abstract protected function findBill($bill_key);
	abstract protected function listBillKeys($who,$accountname);
	abstract protected function getBillAccountInfo($bill_key);
	abstract protected function setBillPaid($bill_key, $txn_id);
	abstract protected function getBillPaid($bill_key);
	abstract protected function setCurrentBillKey($who, $accountname, $bill_key);
	abstract protected function getCurrentBillKey($who, $accountname);
	// events
	abstract protected function onNewBillAccount($who, $accountname);
	abstract protected function onBacktoMerchant();
	abstract protected function onPaymentReceived($bill_key, $status, $txn_id, $ready);
	abstract protected function onPaymentExpired($bill_key);
	abstract protected function onNewPlanSelected($who, $plan, $billkeys);
	abstract protected function onPlanRequired($who);
	abstract protected function onNextBillSelected($billkey);
	abstract protected function onBillNeedPayment($billkey,$flagFirst);
	abstract protected function onBillUpToDate($billkey,$flagfirst);
	abstract protected function onNoMoreBills($who);
	// high level public api
	abstract public function newIdentity($who);
	abstract public function requireNewIdentity($who);
	abstract public function canSelectPlan($who);
	abstract public function selectPlan($who, $plan, $dt=null);
	abstract public function listBillQuotes($who);
	abstract public function getActiveBillKey($who);
	abstract public function getBillInfo($billkey);
	abstract public function makePayment($who, $txn_id, $bill_key=null);
	abstract public function checkAccountStatus($who,$dt=null);
	abstract public function isAccountUpToDate($who,$dt=null);
	abstract public function isAccountNeedPayment($who,$dt=null);
	abstract public function isAccountPlanRequired($who,$dt=null);
	// high level base api
	public function createBillAccount($who, $accountname) {
		$id = $this->_createBillAccount($who, $accountname);
		$this->setBillAccountStatus($who, $accountname, 'need-payment');
		$this->onNewBillAccount($who, $accountname);
		return $id;
	}
	public function canMakeAPayment($who,$accountname){
		$account_status = $this->getBillAccountStatus($who,$accountname);
		if($account_status != 'plan-required'){
			return true;
		}else
		return false;
	}
	public function receivePayment($bill_key, $status, $txn_id){
		list($who, $accountname) = $this->getBillAccountInfo($bill_key);
		if(true == $this->onPaymentReceived($bill_key,$status,$txn_id,true)){
			if($status == 'accepted'){
				$this->setBillPaid($bill_key, $txn_id);
				$this->setBillAccountStatus(
					$who, $accountname,'up-to-date');
				$this->setCurrentBillKey($who, $accountname, $bill_key);
				$this->onPaymentReceived($bill_key, $status, $txn_id, false);
			}else{
				$this->setBillAccountStatus(
					$who, $accountname, 'need-payment');
			}
		}
	}
	/**
	 * isBillExpired
	 *	checks if the passed datex is between the bill range date.
	 * 
	 *	datex accepted values: 'yyyy/mm/dd' or integer (time())
	 *
	 * @param string $bill_key 
	 * @param mixed $datex a string date yyyy/mm/dd or an integer value
	 * @access public
	 * @return bool true if (from <= datex < to)
	 */
	public function isBillExpired($bill_key, $datex = null){
		$data = $this->findBill($bill_key);
		if(empty($data)) return true;
		list($who, $item, $amount, $from, $to) = $data;
		return !$this->dateInRange($datex, $from, $to);
	}
	protected function dateInRange($datex, $from, $to){
		$t1 = strtotime($from." 00:00:00");
		$t2 = strtotime($to." 00:00:00");
		if($datex == null){
			$tx = time();
		}else{
			if(is_string($datex)){
				$tx = strtotime($datex)." 00:00:00";
			}else
			$tx = $datex;
		}
		return (($t1 <= $tx) && ($tx < $t2));
	}
	/**
	 * _discountCalculator
	 *	calculate a total given a subtotal and a discount. round to 2 decimals.
	 * 
	 * @param float $subtotal
	 * @param mixed $discount 
	 * @access protected
	 * @return float total
	 */
	protected function _discountCalculator($subtotal, $discount){
		$discount_value=0;
		if(strstr($discount,'%')){
			$porc = trim($discount,'%');
			$discount_value = ($subtotal * $porc) / 100.0;
		}else
		$discount_value = (1.0 * $discount);
		$total = $subtotal - $discount_value;
		//return array(round($total,2), round($discount_value,2));
		return round($total,2);
	}
	/**
	 * _calculateNextMonth 
	 *	return the starting month for the next billing period. 
	 *
	 *	example:
	 *		$this->_calculateNextMonth('2014-01-01', 3 , 2);
	 *	returns:
	 *		'2014/10/01'
	 *	because:
	 *		
	 *		months: 3  (each 3 months)
	 *		n:  0       1        2        3
	 *		--------|--------|--------|--------|--...
	 *		JA FE MA AP MY JU JL AG SE OC NO DE JA
	 *		0  1  2  3  4  5  6  7  8  9  10 11 12
	 *	                               |
	 *	 so:  3*(n+1) when n is 2 then:9
	 *
	 *	JA{0} => AP
	 *	JA{1} => JL
	 *	JA{2} => OC
	 *	JA{3} => JA
	 *
	 * @param string $starting_date 'yyyy-mm-dd' month in which billing starts
	 * @param integer $months how many months is composed this period ? 3.
	 * @param integer $n desired period number, 0,1,2,3
	 *
	 * @access protected
	 * @return string the date of the next period.
	 */
	protected function _calculateNextMonth($starting_date, $months, $n){
		$nn = $months * ($n+1);
		return date("Y-m-d",strtotime(trim($starting_date." +".$nn." months")));
	}
	/**
	 * checkInRange 
	 * 	detects if a given $testdate is one month range starting at $from.
	 * @param string $from 'yyyy-mm-dd'
	 * @param string $testdate 'yyyy-mm-dd'
	 * @access protected
	 * @return bool if testdate between $from and ($from+'1months')
	 */
	protected function checkInRange($from,$testdate){
		$next = $this->_calculateNextMonth($from, 1, 0);
		return $this->dateInRange($testdate, $from, $next);
	}	
	/**
	 * getNextBillKey 
	 *	returns the next bill starting from a given one, or null if no more bills.
	 * 
	 * @param string $who 
	 * @param string $billkey 
	 * @access protected
	 * @return string the next billkey or null if no more bills
	 */
	protected function getNextBillKey($who, $accountname, $billkey){
		$next=false;
		foreach($this->listBillKeys($who, $accountname) as $quote){
			list($id,$key) = $quote;
			if($next == true)
				return $key;
			if($key == $billkey)
				$next=true;
		}
		return null;
	}
	public function calculatePages($total_items, $items_per_page){
		$pages = (int)($total_items / $items_per_page);
		$pages += (((int)($pages * $items_per_page)) !== $total_items);
		return $pages;
	}
	public function calculatePageOffset($items_per_page, $page){
		return $items_per_page * $page;
	}
}
