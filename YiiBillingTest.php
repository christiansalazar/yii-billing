<?php
/**
 * YiiBillingTest 
 * 
 * @uses YiiBillingOmfStorage
 * @author Christian Salazar <christiansalazarh@gmail.com> 
 * @license FREE BSD
 */
class YiiBillingTest extends YiiBillingOmfStorage {
	private $eventcalled=null;
	protected function sto() {
		return Yii::app()->omf;
	}
	protected function BillAccountClassName(){
		return "BillAccountTest";
	}
	private function log($what,$data){
		printf("[%s][%s]",$what,$data);
	}
	public function run(){
		$this->clearAll();
		$this->lowleveltest();
		$this->lowleveltest2();
		$this->clearAll();
		$this->highleveltest();
		$this->clearAll();
		$this->simplepayment();
		$this->simplepayment();
		$this->clearAll();
		$this->callbacktest();
		$this->clearAll();
	}
	private function clearAll(){
		$this->sto()->deleteObjects($this->BillAccountClassName());
	}
	private function lowleveltest(){
		$this->log(__METHOD__,"running...");

		$who = "994";
		$an = "test";

		$id = $this->_createBillAccount($who,$an);
		if(empty($id)) throw new Exception("error");
		if($who!==$this->sto()->get($id, 'who')) throw new Exception("error");
		if($an!==$this->sto()->get($id, 'account_name')) throw new Exception("error");
		if($id != $this->getBillAccount($who,$an)) throw new Exception("error");
		$objects = $this->sto()->find($this->BillAccountClassName(),'who', $who);
		if(count($objects) !== 1) throw new Exception("error");

		$tmp=$this->getBillAccountStatus($who, $an);
		if('need-payment' != $tmp) throw new Exception("error. tmp=".$tmp);
		$this->setBillAccountStatus($who, $an, 'test');
		if('test' != $this->getBillAccountStatus($who, $an)) 
			throw new Exception("error");
		$this->setBillAccountStatus($who, $an, 'need-payment');

		$bk = $this->createNewBillKey(
			$who, $an, 'item',999, '2014/01/01', '2014/01/31');
		if(empty($bk)) throw new Exception("error");
		$tmp=null;
		if(null == ($tmp = $this->findBill($bk))) throw new Exception("error");
		list($_who, $_item, $_amount, $_from, $_to, $txn_id, $bill_id) = $tmp;
		if(empty($bill_id)) throw new Exception("error");
		if(!empty($txn_id)) throw new Exception("error");
		if($_who != $who) throw new Exception("error");
		if($_item != 'item') throw new Exception("error");
		if($_amount != 999) throw new Exception("error");
		if($_from != '2014/01/01') throw new Exception("error");
		if($_to != '2014/01/31') throw new Exception("error");

		$bk2 = $this->createNewBillKey(
			$who, $an, 'item2',9991, '2014/01/01', '2014/01/31');
		$bk3 = $this->createNewBillKey(
			$who, $an, 'item3',9992, '2014/01/01', '2014/01/31');
		$list = $this->listBillKeys($who, $an);
		if(empty($list)) throw new Exception("error");
		if(count($list) != 3) throw new Exception("error");
		foreach($list as $bill){
			list($id, $key, $name, $am, $from, $to, $txn_id) = $bill;
			$tmp = $this->findBill($key);
			list($_who, $_item, $_amount, $_from, $_to, $_txn_id, $_id) = $tmp;
			if($id != $_id) throw new Exception("error");
			if($am != $_amount) throw new Exception("error");
			if($name != $_item) throw new Exception("error");
			if($from != $_from) throw new Exception("error");
			if($to != $_to) throw new Exception("error");
		}

		$data = $this->getBillAccountInfo($bk);
		if(empty($data)) throw new Exception("error");
		list($_who, $_an) = $data;
		if($_who != $who) throw new Exception("error");
		if($_an != $an) throw new Exception("error");

		$txn_id = '123';
		$this->setBillPaid($bk, $txn_id);
		if($txn_id != $this->getBillPaid($bk)) throw new Exception("error");

		$this->setCurrentBillKey($who, $an, $bk);
		if($bk != $this->getCurrentBillKey($who, $an)) throw new Exception("error");

		print("OK\n");
	}
	public function lowleveltest2(){
		printf("[%s]",__METHOD__);
		if(1000 != $this->_discountCalculator(1000, 0)) throw new Exception("error");
		if(1000 != $this->_discountCalculator(1000, '0%')) throw new Exception("error");
		if(950 != $this->_discountCalculator(1000, '5%')) throw new Exception("error");
		if(round(123-6.15,2) != $this->_discountCalculator(123, '5%')) throw new Exception("error");
		if(round(123-0.33,2) != $this->_discountCalculator(123, 0.33)) throw new Exception("error");
		foreach(array(
			array('2014-01-01',array(0,'2014-01-01')),
			array('2014-01-01',array(3,'2014-04-01')),
			array('2014-01-01',array(1,'2014-02-01')),
			array('2014-01-01',array(2,'2014-03-01')),
			array('2014-01-01',array(12,'2015-01-01')),
			array('2014-01-31',array(1,'2014-03-03')),
		) as $k){
			$from = $k[0];
			$mustbe = $k[1];
			$r=$this->_calculateNextMonth($from, $mustbe[0], 0);
			if($mustbe[1] != $r) { throw new Exception("error"); }
		}
		if(true != $this->checkInRange("2014-01-01","2014-01-01")) throw new Exception("error");
		if(false != $this->checkInRange("2014-01-01","2013-01-01")) throw new Exception("error");
		if(true != $this->checkInRange("2014-01-01","2014-01-03")) throw new Exception("error");
		if(false != $this->checkInRange("2014-01-01","2014-02-31")) throw new Exception("error");
		if(false != $this->checkInRange("2014-01-01","2014-02-01")) throw new Exception("error");
		printf("OK\n");
	}
	private function highleveltest(){
		$this->log(__METHOD__,"running...");
		$who = "1994";
		$an = "test2";
		$from = '2014/01/01';
		$to = '2014/01/31';
		$test0 = '2013/12/31';
		$test1 = '2014/01/15';
		$test2 = '2014/02/01';

		$this->eventcalled = 0;
		$id = $this->createBillAccount($who, $an);
		if($id != $this->getBillAccount($who,$an)) throw new Exception("error");
		if($this->eventcalled != 1) throw new Exception("error");
	
		if(!$this->canMakeAPayment($who,$an)) throw new Exception("error");

		$this->eventcalled = 0;
		$bk = $this->createNewBillKey($who, $an, 'item',999, $from,$to);
		if(empty($bk)) throw new Exception("error");
		$tmp=null;
		if(null == ($tmp = $this->findBill($bk))) throw new Exception("error");
		list($_who, $_item, $_amount, $_from, $_to, $txn_id, $bill_id) = $tmp;
		if(empty($bill_id)) throw new Exception("error");
		if(!empty($txn_id)) throw new Exception("error");
		if($_who != $who) throw new Exception("error");
		if($_item != 'item') throw new Exception("error");
		if($_amount != 999) throw new Exception("error");
		if($_from != $from) throw new Exception("error");
		if($_to != $to) throw new Exception("error");
		if($this->getBillAccountStatus($who, $an) !== 'need-payment')
			throw new Exception('error');

		$this->eventcalled = 0;
		$txn_id = '991991';
		$this->receivePayment($bk, 'accepted', $txn_id);
		if($this->eventcalled != 2) throw new Exception("error");
		if($txn_id != $this->getBillPaid($bk))
			throw new Exception("error");
		if($this->getBillAccountStatus($who, $an)
			!== 'up-to-date') throw new Exception("error");
		if($bk !== $this->getCurrentBillKey($who, $an))
			throw new Exception("error");

		if(true !== $this->isBillExpired($bk, $test0))
			throw new Exception("error");
		if(false !== $this->isBillExpired($bk, $test1))
			throw new Exception("error");
		if(true !== $this->isBillExpired($bk, $test2))
			throw new Exception("error");
		if(false !== $this->isBillExpired($bk, $from))
			throw new Exception("error");
		if(true !== $this->isBillExpired($bk, $to))
			throw new Exception("error");

		$to=$from="dummy";
		$bk1 = $this->createNewBillKey($who, $an, 'item',999, $from,$to);
		$bk2 = $this->createNewBillKey($who, $an, 'item',999, $from,$to);
		$bk3 = $this->createNewBillKey($who, $an, 'item',999, $from,$to);
		$bk4 = $this->createNewBillKey($who, $an, 'item',999, $from,$to);

		if($bk2 != $this->getNextBillKey($who,$an, $bk1)) throw new Exception("error");
		if($bk3 != $this->getNextBillKey($who,$an, $bk2)) throw new Exception("error");
		if($bk4 != $this->getNextBillKey($who,$an, $bk3)) throw new Exception("error");
		if(null != $this->getNextBillKey($who,$an, $bk4)) throw new Exception("error");

		print("OK\n");
	}
	private function simplepayment() {
		$this->log(__METHOD__,"running...");
		$who = "1000";
        $an = "test";
        $from = '2014/01/01';
        $to = '2014/01/31';
        $test0 = '2014/01/15';

		$id = $this->createBillAccount($who, $an);
		if(!$this->canMakeAPayment($who,$an)) 
			throw new Exception("error");
		$billkey = $this->createNewBillKey($who, $an,'item',999,$from,$to);
		printf("[%s]",$billkey);
		if(false !== $this->isBillExpired($billkey, $test0)) 
			throw new Exception("error");
		printf("[%s,%s]",$id,$this->getBillAccountStatus($who,$an));
		$this->receivePayment($billkey, 'accepted','999999');
		printf("[%s]",$this->getBillAccountStatus($who,$an));
		if(false !== $this->isBillExpired($billkey, $test0)) 
			throw new Exception("error");
		print("OK\n");
	}
	private function callbacktest() {
		/*
		$this->log(__METHOD__,"running...");
		$who = "1000";
        $an = "test";
        $from = '2014/01/01';
        $to = '2014/01/31';
        $test0 = '2014/01/15';

		$id = $this->createBillAccount($who, $an);
		$billkey = $this->makeAPayment($who, $an,'item',999,$from,$to);
		printf("[%s]",$billkey);
		printf("[%s,%s]",$id,$this->getBillAccountStatus($who,$an));


		print("OK\n");
		*/
	}
	// EVENTS:
	protected function onNewBillAccount($who, $accountname){
		$this->eventcalled += 1;
		//printf("\n[EVENT][%s][%s]\n",__METHOD__,json_encode(array($who,$accountname)));
	}
	protected function onBacktoMerchant(){
		$this->eventcalled += 1;
		//printf("\n[EVENT][%s][%s]\n",__METHOD__,json_encode('void'));
	}
	protected function onPaymentReceived($bill_key, $status, $txn_id, $ready){
		$this->eventcalled += 1;
		//printf("\n[EVENT][%s][%s]\n",__METHOD__,json_encode(
		//	array($bill_key, $status, $txn_id, $ready)));
		if($ready == true)
			return true;
		return false;
	}
	protected function onPaymentExpired($bill_key){
		$this->eventcalled += 1;
	//	printf("\n[EVENT][%s][%s]\n",__METHOD__,json_encode($bill_key));
	}

	protected function onNewPlanSelected($who, $plan, $billkeys){
		
	}
	protected function onPlanRequired($who){
		
	}
	protected function onNextBillSelected($billkey){
			
	}
	protected function onBillNeedPayment($billkey,$flagFirst){
		
	}
	protected function onBillUpToDate($billkey,$flagfirst){
				
	}
	protected function onNoMoreBills($who){

	}
	// public high level api not tested here
	public function newIdentity($who){}
	public function requireNewIdentity($who){ return true; }
	public function canSelectPlan($who){}
	public function selectPlan($who, $plan, $dt=null){}
	public function listBillQuotes($who){}
	public function makePayment($who, $txn_id, $bill_key=null){}
	public function checkAccountStatus($who,$dt=null){}
}
