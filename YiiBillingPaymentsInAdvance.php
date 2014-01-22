<?php
/**
 * YiiBillingPaymentsInAdvance
 *
 *	this class provides a billing model based on payments in advance, 
 *	user may choose to select a plan and pay it via two modalities:
 *		pay full year, and optionally having a discount.
 *		pay in 4 quotes, 3 months each. having no discount.
 *
 *	you can make a payment by calling makePayment or by receiving a
 *	remote payment notification. In case of PayPal a special Action
 *	was designed for this goal:  PaypalCallbackAction.
 *
 *	storage is provided via OMF, so if you want a different storage model
 *	the you may build a new base class having implemented the same
 *	methods as YiiBillingOmfStorage.
 *
 *	**IN ORDER TO USE THIS CLASS YOU MUST SUBCLASS FROM IT**
 *	declare the method sto() to provide the storage api instance
 *	declare all the events, see also YiiBillingBase, search events.
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
 * 		isAccountUpToDate
 * 		isAccountNeedPayment
 * 		isAccountPlanRequired
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
 * @uses YiiBillingOmfStorage
 * @author Christian Salazar <christiansalazarh@gmail.com>
 * @license FREE BSD
 */
abstract class YiiBillingPaymentsInAdvance extends YiiBillingOmfStorage {
	private $_accountid = null;
	public static $account="account1";

// PUBLIC METHODS
	public function newIdentity($who){
		$id = $this->createBillAccount($who,self::$account);
		$this->setBillAccountStatus($who,self::$account,"plan-required");
		$this->setCurrentBillKey($who, self::$account, "");
		$this->setCurrentPlan($who, "noplan");
		$this->setRenewPlanFlag($who, true);
		return $id;
	}
	/**
	 * requireNewIdentity 
	 * 	detects is the given identity must call newIdentity. needed when
	 *	a given identity was never initialized and then has no BillAccount.
	 * @param mixed $who 
	 * @access public
	 * @return bool true if newIdentity must be called
	 */
	public function requireNewIdentity($who){
		return (null == $this->getBillAccount($who,self::$account));
	}
	/**
	 * canSelectPlan 
	 *	returns true if the identity account is marked to receive a new plan.
	 *	when account is created for a given identity then it is marked as 'true'
	 *	when 
	 * @param mixed $who 
	 * @access public
	 * @return void
	 */
	public function canSelectPlan($who){
		return $this->getRenewPlanFlag($who);
	}
	/**
	 * selectPlan 
	 *	 
	 *	about $plan:
	 *		is an object deployed as an array having this values:
	 *			plan_name
	 *			amount to pay each year
	 *			amount to pay each month
	 *			have discount (abs float value or percent '5%')
	 *			true if full year will be paid, else pay 3 months in advance
	 *
	 *		array($name, $yearly, $monthly, $discount, $fullyear)
	 *
	 *	restrictions:
	 *		can be used only if getRewnewPlanFlag returns true
	 *	events:
	 *		onNewPlanSelected	
	 *
	 * @param string $who 
	 * @param array $plan see note
	 * @access public
	 * @return bool true if success.
	 */
	public function selectPlan($who, $plan, $dt=null) {
		if(!$dt) $dt = date('Y-m-d');
		if(false === $this->canSelectPlan($who)) return false;
		list($name, $yearly, $monthly, $discount, $fullyear) = $plan;
		$billkeys = $this->createBillQuotes(
			$who, $yearly, $monthly, $discount,
			$fullyear ? '1year' : '3months', $name, $dt);
		$this->setRenewPlanFlag($who, false);
		$this->setCurrentPlan($who, $name);
		$this->setCurrentBillKey($who, self::$account,$billkeys[0]);
		$this->setBillAccountStatus($who, self::$account, 'need-payment');
		$this->onNewPlanSelected($who, $plan, $billkeys);
		return true;
	}
	/**
	 * listBillQuotes
	 *	return all the quotes created for this identity into its account.
	 * 
	 *	reading the result:
	 *
	 *	foreach($this->listBillQuotes($who) as $quote){
	 *		list($id,$key,$item,$amount,$from,$to,$txn_id) = $quote;
	 *		...
	 *	}
	 *
	 * @param string $who 
	 * @access public
	 * @return array see note
	 */
	public function listBillQuotes($who){
		return $this->listBillKeys($who, self::$account);	
	}
	public function makePayment($who, $txn_id, $bill_key=null){
		if($bill_key == null)
			$bill_key =$this->getCurrentBillKey(
				$who,self::$account);
		$this->setBillPaid($bill_key,$txn_id);
		$this->onPaymentReceived($bill_key, "accepted", $txn_id, false);
	}
	public function isAccountUpToDate($who,$dt=null){
		$this->checkAccountStatus($who, $dt);
		return "up-to-date" === 
		$this->getBillAccountStatus($who, self::$account);
	}
	public function isAccountNeedPayment($who,$dt=null){
		$this->checkAccountStatus($who, $dt);	
		return "need-payment" === 
		$this->getBillAccountStatus($who, self::$account);
	}
	public function isAccountPlanRequired($who,$dt=null){
		$this->checkAccountStatus($who, $dt);	
		return "plan-required" === 
		$this->getBillAccountStatus($who, self::$account);
	}
	public function getActiveBillKey($who){
		return $this->getCurrentBillKey($who, self::$account);
	}
	public function getBillInfo($billkey){
		return $this->findBill($billkey);
	}
	/**
	 * checkAccountStatus 
	 * 
	 *	return values:
	 *
	 *		-1:	plan is required
	 *		-2:	no bill account set (error)
	 *		-3:	no more bills. status changed to 'plan-required'
	 *		-4:	no bill account
	 *		 0: bill unpaid, out of range.
	 *		+2:	bill unpaid, but in range of 30 days
	 *		+1:	bill up to date
	 *	
	 * @param mixed $who 
	 * @access public
	 * @return integer see note
	 */
	public function checkAccountStatus($who,$dt=null){
		if(!$dt) $dt = date('Y-m-d');
		if(!$this->getBillAccountStatus($who, self::$account)) return -4;
		if("plan-required" == 
			$this->getBillAccountStatus($who, self::$account)){
			$this->onPlanRequired($who);
			return -1; // a plan is required
		}
		$billkey = $this->getCurrentBillKey($who, self::$account);	
		if(empty($billkey)) {
			Yii::log(__METHOD__.". no current bill key.","error");
			$this->setBillAccountStatus($who, self::$account, 'need-payment');
			return -2; // no bill account set. rare. maybe an error.
		}
		if($this->isBillExpired($billkey, $dt)){
			$this->onPaymentExpired($billkey);
			if($billkey = $this->getNextBillKey($who, self::$account, $billkey)){
				$this->setCurrentBillKey($who, self::$account,$billkey);
				$this->onNextBillSelected($billkey);
				return $this->checkAccountStatus($who,$dt);
			}else{
				$this->setCurrentBillKey($who, self::$account,"");
				$this->setCurrentPlan($who, "noplan");
				$this->setRenewPlanFlag($who, true);
				$this->setBillAccountStatus($who, self::$account, 'plan-required');
				$this->onNoMoreBills($who);
				return -3; // no more bill. system reset.
			}
		}else{
			if("" == $this->getBillPaid($billkey)){
				if("need-payment" != 
					$this->getBillAccountStatus($who, self::$account)){
					$this->setBillAccountStatus($who, self::$account, 'need-payment');
					$this->onBillNeedPayment($billkey, true);
				}else{
					$this->onBillNeedPayment($billkey, false);
				}
				// payment range check
				list($_who, $_item, $_amount, $_from, $_to, $_tr, $_bill_id)
					= $this->findBill($billkey);
				return $this->checkInRange($_from,$dt) ? 2 : 0; // bill unpaid
			}else{
				if("up-to-date" != 
					$this->getBillAccountStatus($who, self::$account)){
					$this->setBillAccountStatus($who, self::$account, 'up-to-date');
					$this->onBillUpToDate($billkey, true);
				}else{
					$this->onBillUpToDate($billkey, false);
				}
				return 1; // bill is paid
			}
		}
	}
// PROTECTED AREA
	protected function clearCache(){
		$this->_accountid = null;
	}
	protected function billaccount($who){
		if($this->_accountid == null)
			$this->_accountid = $this->getBillAccount($who, self::$account);
		return $this->_accountid;
	}
	protected function _set($who,$property, $value){
		$this->sto()->set($this->billaccount($who), $property, $value);
	}
	protected function _get($who,$property){
		return $this->sto()->get($this->billaccount($who), $property);
	}
	protected function setCurrentPlan($who, $plan){
		$this->_set($who, "plan", $plan);
	}
	protected function getCurrentPlan($who){
		return $this->_get($who, "plan");
	}
	protected function setRenewPlanFlag($who, $bool){
		$this->_set($who,"renew_plan",($bool==true) ? 'TRUE' : 'FALSE');
	}
	protected function getRenewPlanFlag($who){
		return ($this->_get($who,"renew_plan")==='TRUE');
	}
	protected function billAccountStatus($who){
		return $this->getBillAccountStatus($who, self::$account);
	}
	/**
	 * _quoteSplitter
	 *	 divide a bill into quotes.
	 * 
	 * @param float $ybill yearly bill amount
	 * @param float $mbill monthly bill amount
	 * @param string $dsc absolute discount value (50) or a percentage '5%'
	 * @param string $mode how to split: '1year' '3months'
	 * @param string $name the bill name.
	 * @param string $dt bill start date (string yyyy-dd-mm)
	 * @access protected
	 * @return array array structure is: array(array(amount, name, from, to))
	 */
	protected function _quoteSplitter($ybill, $mbill, $dsc, $mode, $name, $dt){
		if(!is_string($dt)) throw new Exception("must be a string date yyyy-mm-dd");
		$from = date("Y-m-d",strtotime(trim($dt." 00:00:00")));
		if(($mode == '1year') || ($mbill == 0)){
			return array(
				array(
					$this->_discountCalculator($ybill + 12*$mbill, $dsc),
					$name,$dt,
					$this->_calculateNextMonth($from, 12, 0))
			);
		}elseif($mode == '3months'){
			$monthly_bill_0 = $this->_discountCalculator($ybill+3*$mbill, 0);
			$monthly_bill = $this->_discountCalculator(3 * $mbill, 0);
			$previous_date = $from;
			for($n=0; $n <= 3; $n++){
				$to=$this->_calculateNextMonth($from, 3, $n);
				$amount = $n==0 ? $monthly_bill_0 : $monthly_bill;
				$ar[] = array($amount, $name.'-'.($n+1),$previous_date, $to);
				$previous_date = $to;
			}
			return $ar;
		}else
		throw new Exception("invalid split option");
	}
	/**
	 * createBillQuotes
	 *	create the billing quotes to be paid later by some identity ($who)
	 *	splitted according to the given modality ($mode: '1year', '3months').
	 *
	 *	after a call to createBillQuotes you are required to set: 
	 *		setRenewPlanFlag(false);
	 *
	 * @param string $who 
	 * @param float $yearlybill 
	 * @param float $monthlybill 
	 * @param mixed $discount see also _quoteSplitter
	 * @param string $mode see also _quoteSplitter
	 * @param string $item_name 
	 * @param string $dt start billing period. yyyy-dd-mm
	 * @access public
	 * @return array array of bill_key values created or null if forbiden.
	 */
	protected function createBillQuotes($who, $yearlybill, $monthlybill,$discount, 
		$mode, $item_name, $dt=null){
		if($dt == null) $dt = date('Y-m-d');
		$quotes = $this->_quoteSplitter(
			$yearlybill, $monthlybill, $discount, $mode, $item_name, $dt);
		if(empty($quotes)) throw new Exception("error when splitting quotes");
		$billkeys = array();
		//printf("%s\n",__METHOD__);
		foreach($quotes as $q){
			list($amount, $itemname, $from, $to) = $q;
			//printf("[%s][%s]\n",$from,$to);
			$bill_key = $this->createNewBillKey($who, self::$account, $itemname, 
				$amount, $from, $to);
			$billkeys[] = $bill_key;
		}
		//printf("\n");
		return $billkeys;
	}
}
