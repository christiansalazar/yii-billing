<?php
require_once("../../../../wp-config.php");
require_once('../omf/OmfPdo.php');
require_once('YiiBillingPaymentsInAdvance.php');
class YiiBillingPaymentsInAdvanceTest extends YiiBillingPaymentsInAdvance {
	private $sto;
	protected function sto() {
		if(null==$this->sto){
			$this->sto = new OmfPdo(); 
		}
		return $this->sto;
	}
	protected function log($text,$info){
		printf("[%s][%s]",$text,$info);
	}
	protected function logger($text,$extra){
		$this->log("[".$text."][".$extra."]","info");
	}
	protected function BillAccountClassName(){
		return "BillAccountTest";
	}
	public function run(){
		$this->clear();
		$who = "123";
		$this->testNewIdentity($who);
		$this->clear();
		$this->testNewIdentity($who);
		$this->testLowLevelQuoteSplitter();
		$this->testHighLevelQuoteSplitter($who);
		$this->testHighLevelAccountTester($who);
		$this->testIssueNumber4($who);
		$this->clear();
	}
	protected function clear(){
		$tt = microtime(true);
		printf("[clear]");
		$this->sto()->deleteObjects($this->BillAccountClassName());
		$this->clearCache();
		printf("[clear.%s]",microtime(true) - $tt);
	}
	public function testNewIdentity($who){
		printf("[%s][who=%s]",__METHOD__,$who);

		if(false === $this->requireNewIdentity($who)) throw new Exception("error");
		$id = $this->newIdentity($who);
		if(empty($id)) throw new Exception("error");
		$id2 = $this->billaccount($who);
		if($id != $id2) throw new Exception("error");
		if(true === $this->requireNewIdentity($who)) throw new Exception("error");

		if("noplan" != $this->getCurrentPlan($who)) throw new Exception("error");
		$this->setCurrentPlan($who, "testplan");
		if("testplan" != $this->getCurrentPlan($who)) throw new Exception("error");
		$this->setCurrentPlan($who, "noplan");

		if(true != $this->getRenewPlanFlag($who)) throw new Exception("error");
		$this->setRenewPlanFlag($who,false);
		if(false != $this->getRenewPlanFlag($who)) throw new Exception("error");
		$this->setRenewPlanFlag($who,true);

		if("plan-required" != $this->billAccountStatus($who)) throw new Exception("error");
		if(true != $this->canSelectPlan($who)) throw new Exception("error");

		printf("[%s.ID=%s]",$this->BillAccountClassName(),$id);
		printf("OK\n");
	}
	public function testLowLevelQuoteSplitter(){
		printf("[%s]",__METHOD__);
		$tt['begin'] = microtime(true);


		$tt['quoteSplitter_a'] = microtime(true);
		$r=$this->_quoteSplitter(200, 0, '5%', '1year', 'yearly', '2014-06-08');
		$tt['quoteSplitter_b'] = microtime(true);
		if(1 != count($r)) throw new Exception("error");
		list($a, $name, $from, $to)=$r[0];
		if(190 != $a) throw new Exception("error ".$a);
		if('yearly' != $name) throw new Exception("error ".$name);
		if('2014-06-08' != $from) throw new Exception("error ".$from);
		if('2015-06-08' != $to) throw new Exception("error ".$to);

		$r=$this->_quoteSplitter(100, 10, 5, '1year', 'yearly', '2014-06-08');
		if(1 != count($r)) throw new Exception("error");
		list($a, $name, $from, $to)=$r[0];
		if((220-5) != $a) throw new Exception("error ".$a);
		if('yearly' != $name) throw new Exception("error ".$name);
		if('2014-06-08' != $from) throw new Exception("error ".$from);
		if('2015-06-08' != $to) throw new Exception("error ".$to);

		$r=$this->_quoteSplitter(100, 10, 0, '3months', 'quote', '2014-06-08');
		if(4 != count($r)) throw new Exception("error");
		$ar = array(
			array((100 + 10*3)	,'quote-1','2014-06-08','2014-09-08'),		
			array((10*3)		,'quote-2','2014-09-08','2014-12-08'),		
			array((10*3)		,'quote-3','2014-12-08','2015-03-08'),		
			array((10*3)		,'quote-4','2015-03-08','2015-06-08'),		
		);
		foreach($r as $k=>$_r){
			list($a1, $b1, $c1, $d1) = $_r;
			list($a2, $b2, $c2, $d2) = $ar[$k];
			if($a1 != $a2) throw new Exception(
				printf("error #%s. resp[%s] mustbe[%s]",$k,$a1,$a2));
			if($b1 != $b2) throw new Exception(
				printf("error #%s. resp[%s] mustbe[%s]",$k,$b1,$b2));
			if($c1 != $c2) throw new Exception(
				printf("error #%s. resp[%s] mustbe[%s]",$k,$c1,$c2));
			if($d1 != $d2) throw new Exception(
				printf("error #%s. resp[%s] mustbe[%s]",$k,$d1,$d2));
		}

		$this->printprofile($tt);
		printf("OK\n");
	}
	public function testHighLevelQuoteSplitter($who){
		printf("[%s]",__METHOD__);
		$tt['begin'] = microtime(true);

		$billkeys = $this->createBillQuotes(
			$who,100,10,0,'3months','quote','2014-01-01');
		if(4 != count($billkeys)) throw new Exception("error");
		$tt['createBillQuotes'] = microtime(true);

		printf("\nBillKeys:\n");
		foreach($this->listBillQuotes($who) as $k=>$quote){
			list($id,$key,$item,$amount,$from,$to,$txn_id) = $quote;
			printf("#%02d key:%s item: %s, amount: %s, from: %s, to: %s, txn_id: %s\n"
				,$k,$key,$item,$amount,$from,$to,$txn_id);
			if($k==0){
				if($item != 'quote-1') throw new Exception("error");
				if($amount != 130) throw new Exception("error.".$amount);
				if($from != '2014-01-01') throw new Exception("error.".$from);
				if($to != '2014-04-01') throw new Exception("error".$to);
				if($txn_id != '') throw new Exception("error");
			}
			if($k==1){
				if($item != 'quote-2') throw new Exception("error");
				if($amount != 30) throw new Exception("error");
				if($from != '2014-04-01') throw new Exception("error.".$from);
				if($to != '2014-07-01') throw new Exception("error.".$to);
				if($txn_id != '') throw new Exception("error");
			}
			if($k==2){
				if($item != 'quote-3') throw new Exception("error");
				if($amount != 30) throw new Exception("error");
				if($from != '2014-07-01') throw new Exception("error.".$from);
				if($to != '2014-10-01') throw new Exception("error.".$to);
				if($txn_id != '') throw new Exception("error");
			}
			if($k==3){
				if($item != 'quote-4') throw new Exception("error");
				if($amount != 30) throw new Exception("error");
				if($from != '2014-10-01') throw new Exception("error.".$from);
				if($to != '2015-01-01') throw new Exception("error.".$to);
				if($txn_id != '') throw new Exception("error");
			}
		}

		$an = self::$account;
		if($billkeys[1] != $this->getNextBillKey($who,$an, $billkeys[0])) throw new Exception("error");
		if($billkeys[2] != $this->getNextBillKey($who,$an, $billkeys[1])) throw new Exception("error");
		if($billkeys[3] != $this->getNextBillKey($who,$an, $billkeys[2])) throw new Exception("error");
		if(null != $this->getNextBillKey($who,$an, $billkeys[3])) throw new Exception("error");

		$this->printprofile($tt);
		printf("OK\n");
	}

	public function testHighLevelAccountTester($who){
		printf("[%s]",__METHOD__);
		$dt = "2014-01-01";
		$dt2= strtotime($dt." +1 months");

		// cleanup and create the identity and billaccount again
		$this->clear();

		$tt['begin'] = microtime(true);
		if(-4 != $this->checkAccountStatus($who,$dt))
			throw new Exception("error");
		$tt['testNewIdentity'] = microtime(true);
		$this->testNewIdentity($who);
		$tt['step1'] = microtime(true);
		if(-1 != $this->checkAccountStatus($who,$dt))
			throw new Exception("error");
		if("plan-required" != $this->billAccountStatus($who))
			throw new Exception("error");
		if(true != $this->canSelectPlan($who)) 
			throw new Exception("error");
		if(count($this->listBillQuotes($who)))
			throw new Exception("error");
		if(-1 != $this->checkAccountStatus($who,$dt))
			throw new Exception("error");

		$tt['step2'] = microtime(true);
		if(false == $this->selectPlan($who,
			array("testplan", 100, 10, 0, false),$dt))
				throw new Exception("error");
		if("testplan" !== $this->getCurrentPlan($who))
			throw new Exception("error");
		$tt['step2aa'] = microtime(true);
		if("need-payment" != $this->billAccountStatus($who))
			throw new Exception("error");
		$tt['step2ab'] = microtime(true);
		if(false != $this->canSelectPlan($who)) 
			throw new Exception("error");
		$quotes = $this->listBillQuotes($who);
		if(!count($quotes))
			throw new Exception("error");
		$tt['step2ac'] = microtime(true);

		list($id1,$key1,$item1,$amount1,$from1,$to1) = $quotes[0];
		list($id2,$key2,$item2,$amount2,$from2,$to2) = $quotes[1];
		list($id3,$key3,$item3,$amount3,$from3,$to3) = $quotes[2];
		list($id4,$key4,$item4,$amount4,$from4,$to4) = $quotes[3];
		
		printf("\n");
		printf("1 [%s,%s,%s,%s,%s,%s]\n",$id1,$key1,$item1,$amount1,$from1,$to1);
		printf("2 [%s,%s,%s,%s,%s,%s]\n",$id2,$key2,$item2,$amount2,$from1,$to2);
		printf("3 [%s,%s,%s,%s,%s,%s]\n",$id3,$key3,$item3,$amount3,$from1,$to3);
		printf("4 [%s,%s,%s,%s,%s,%s]\n",$id4,$key4,$item4,$amount4,$from1,$to4);
		printf("\n");
		
		if($key1 != $this->getCurrentBillKey($who,parent::$account))
			throw new Exception("error");
		$tt['step2b'] = microtime(true);
		if( (100+10*3) != $amount1)
			throw new Exception("error. am=".$amount1);
		if(2 != $this->checkAccountStatus($who,$dt))
			throw new Exception("error");
		$tt['step2c'] = microtime(true);
		if(0 != $this->checkAccountStatus($who,$dt2))
			throw new Exception("error");
		$tt['step2d'] = microtime(true);
		$this->makePayment($who, "9991");
		$tt['step2e'] = microtime(true);
		if(1 != $this->checkAccountStatus($who,$dt))
			throw new Exception("error");
		if("up-to-date" != $this->billAccountStatus($who))
			throw new Exception("error");

		$tt['step3'] = microtime(true);
		$test = strtotime($dt." +1 days");
		if(1 != $this->checkAccountStatus($who,$test))
			throw new Exception("error");
		if("up-to-date" != $this->billAccountStatus($who))
			throw new Exception("error");
		$test = strtotime($dt." +30 days");
		$retval = $this->checkAccountStatus($who,$test);
		if(1 != $retval)
			throw new Exception("error. retval is: ".$retval.", must be 1. dt is: ".date("Y-m-d",$test));
		if("up-to-date" != $this->billAccountStatus($who))
			throw new Exception("error");

		$tt['step4'] = microtime(true);
		$dt = $to1;
		if(2 != $this->checkAccountStatus($who,$dt))
			throw new Exception("error");
		if($key2 != $this->getCurrentBillKey($who,parent::$account))
			throw new Exception("error");
		if("need-payment" != $this->billAccountStatus($who))
			throw new Exception("error");
		$this->makePayment($who, "9992");
		if(1 != $this->checkAccountStatus($who,$dt))
			throw new Exception("error");
		if("up-to-date" != $this->billAccountStatus($who))
			throw new Exception("error");

		$tt['step5'] = microtime(true);
		$dt = $to2;
		if(2 != $this->checkAccountStatus($who,$dt))
			throw new Exception("error");
		if($key3 != $this->getCurrentBillKey($who,parent::$account))
			throw new Exception("error");
		if("need-payment" != $this->billAccountStatus($who))
			throw new Exception("error");
		$this->makePayment($who, "9993");
		if(1 != $this->checkAccountStatus($who,$dt))
			throw new Exception("error");
		if("up-to-date" != $this->billAccountStatus($who))
			throw new Exception("error");

		$tt['step6'] = microtime(true);
		$dt = $to3;
		if(2 != $this->checkAccountStatus($who,$dt))
			throw new Exception("error");
		if($key4 != $this->getCurrentBillKey($who,parent::$account))
			throw new Exception("error");
		if("need-payment" != $this->billAccountStatus($who))
			throw new Exception("error");
		$this->makePayment($who, "9994");
		if(1 != $this->checkAccountStatus($who,$dt))
			throw new Exception("error");
		if("up-to-date" != $this->billAccountStatus($who))
			throw new Exception("error");

		$tt['step7'] = microtime(true);
		$dt = $to4;
		if(-3 != $this->checkAccountStatus($who,$dt))
			throw new Exception("error");
		if("" != $this->getCurrentBillKey($who,parent::$account))
			throw new Exception("error");
		if("plan-required" != $this->billAccountStatus($who))
			throw new Exception("error");
		if(true != $this->canSelectPlan($who)) 
			throw new Exception("error");
		
		if(-1 != $this->checkAccountStatus($who,$dt))
			throw new Exception("error");

		// 	at this moment all bills are paid and expired,
		//	so a new plan can be selected and start again..

		$dt = date("Y-m-d",strtotime($to4." +1 days"));
		//printf("[new period starts at: %s]",$dt);

		$tt['step8'] = microtime(true);
		if(false == $this->selectPlan($who,
			array("testplan", 100, 10, 0, false),$dt))
				throw new Exception("error");
		if("need-payment" != $this->billAccountStatus($who))
			throw new Exception("error");
		if(false != $this->canSelectPlan($who)) 
			throw new Exception("error");
		$quotes = $this->listBillQuotes($who);
		if(8 != count($quotes))
			throw new Exception("error");
		list($id5,$key5,$item5,$amount5,$from5,$to5) = $quotes[4];
		list($id6,$key6,$item6,$amount6,$from6,$to6) = $quotes[5];
		list($id7,$key7,$item7,$amount7,$from7,$to7) = $quotes[6];
		list($id8,$key8,$item8,$amount8,$from8,$to8) = $quotes[7];
		/*
		printf("\n");
		foreach($quotes as $k=>$q){
			if($k > 3){
			list($_i, $_k, $_it, $_a,$_f,$_t) = $q;
			printf("%s [%s,%s,%s,%s,%s,%s]\n",$k,
				$_i,$_k,$_it,$_a,$_f,$_t);
			}
		}
		printf("\n");
		*/
		if($key5 != $this->getCurrentBillKey($who,parent::$account))
			throw new Exception("error");
		$this->makePayment($who, "9995");
		if(1 != $this->checkAccountStatus($who,$dt))
			throw new Exception("error");
		if("up-to-date" != $this->billAccountStatus($who))
			throw new Exception("error");

		$tt['step9'] = microtime(true);
		$dt = $to5;
		if(2 != $this->checkAccountStatus($who,$dt))
			throw new Exception("error");
		if($key6 != $this->getCurrentBillKey($who,parent::$account))
			throw new Exception("error");
		if("need-payment" != $this->billAccountStatus($who))
			throw new Exception("error");
		$this->makePayment($who, "9997");
		if(1 != $this->checkAccountStatus($who,$dt))
			throw new Exception("error");
		if("up-to-date" != $this->billAccountStatus($who))
			throw new Exception("error");

		$tt['step10'] = microtime(true);
		$dt = $to6;
		if(2 != $this->checkAccountStatus($who,$dt))
			throw new Exception("error");
		if($key7 != $this->getCurrentBillKey($who,parent::$account))
			throw new Exception("error");
		if("need-payment" != $this->billAccountStatus($who))
			throw new Exception("error");
		$this->makePayment($who, "9998");
		if(1 != $this->checkAccountStatus($who,$dt))
			throw new Exception("error");
		if("up-to-date" != $this->billAccountStatus($who))
			throw new Exception("error");

		$tt['step11'] = microtime(true);
		$dt = $to6;
		$dt = $to7;
		if(2 != $this->checkAccountStatus($who,$dt))
			throw new Exception("error");
		if($key8 != $this->getCurrentBillKey($who,parent::$account))
			throw new Exception("error");
		if("need-payment" != $this->billAccountStatus($who))
			throw new Exception("error");
		$this->makePayment($who, "9999");
		if(1 != $this->checkAccountStatus($who,$dt))
			throw new Exception("error");
		if("up-to-date" != $this->billAccountStatus($who))
			throw new Exception("error");

		$tt['step12'] = microtime(true);
		$dt = $to8;
		if(-3 != $this->checkAccountStatus($who,$dt))
			throw new Exception("error");
		if("" != $this->getCurrentBillKey($who,parent::$account))
			throw new Exception("error");
		if("plan-required" != $this->billAccountStatus($who))
			throw new Exception("error");
		if(true != $this->canSelectPlan($who)) 
			throw new Exception("error");
		
		if(-1 != $this->checkAccountStatus($who,$dt))
			throw new Exception("error");

		$tt['step13'] = microtime(true);
		$retval = $this->listBillAccountsByStatus(null,0,-1,true);
		if(0 !== $this->listBillAccountsByStatus(null,0,-1,true)) throw new Exception("error.rv=".$retval);
		if(null === $this->listBillAccountsByStatus(null,0,-1,false)) throw new Exception("error");
		if(0 !== $this->listBillAccountsByStatus("null",0,-1,true)) throw new Exception("error");
		if(null === $this->listBillAccountsByStatus("null",0,-1,false)) throw new Exception("error");

		foreach($this->listBillAccountsByStatus(null,0,-1,false) as $dummy) { }

		$this->printprofile($tt);
			
		printf("OK\n");
	}

	public function testIssueNumber4($who){
		printf("[%s]",__METHOD__);
		$this->clear();
		
		$dt = '2014-01-01';

		$this->testNewIdentity($who);
		if("plan-required" != $this->billAccountStatus($who))
			throw new Exception("error");
		if(true != $this->canSelectPlan($who)) 
			throw new Exception("error");
		if(count($this->listBillQuotes($who)))
			throw new Exception("error");

		if(-1 !== $this->checkAccountStatus($who,$dt))
			throw new Exception("error");

		if(false == $this->selectPlan($who,
			array("testplan", 100, 10, 0, false),$dt))
				throw new Exception("error");
		if("testplan" !== $this->getCurrentPlan($who))
			throw new Exception("error");
		if("need-payment" != $this->billAccountStatus($who))
			throw new Exception("error");
		if(false != $this->canSelectPlan($who)) 
			throw new Exception("error");
		$quotes = $this->listBillQuotes($who);
		if(!count($quotes))
			throw new Exception("error");

		if(2 !== $this->checkAccountStatus($who,$dt))
			throw new Exception("error");
		if("need-payment" != $this->billAccountStatus($who))
			throw new Exception("error");

		$index=0;
		$first_billkey=null;
		$next_billkey=null;
		foreach($quotes as $index=>$quote){
			list($obj_id,$key,$item,$amount,$from,$to,$txn_id) = $quote;
			if($index === 0){
				$first_billkey = $key;
			}else
			if($index === 1)
				$next_billkey = $key;
		}
		$current_bk = $this->getCurrentBillKey($who,self::$account);
		if($first_billkey != $current_bk)
			throw new Exception("error");
		// now proceeding to make the issue 4 to appear:
		//
		// put the first quote to start 1 day after of the full range
		// this action will put the account outside the range of any quotes
		$dt2 = '2014-01-02';
		foreach($quotes as $index=>$quote){
			list($obj_id,$key,$item,$amount,$from,$to,$txn_id) = $quote;
			if($index == 0){
				$this->sto()->set($obj_id,'from',$dt2);
			}
		}

		if($next_billkey !== $this->getNextBillKey($who, self::$account, $current_bk))
			throw new Exception("error getNextBillKey");

		if(-5 !== $this->checkAccountStatus($who,$dt))
			throw new Exception("error"); // error detected

		if("need-payment" != $this->billAccountStatus($who))
			throw new Exception("error");                   		
		if("testplan" !== $this->getCurrentPlan($who))
			throw new Exception("error");

		printf("OK\n");
	}

	private function printprofile($tt){
		return;
		$tt['final'] = microtime(true);
		printf("\nmicrotimes:\n");
		$_last = $tt['begin'];
		foreach($tt as $k=>$val){
			if(($k != 'begin') && ($k != 'final'))
			printf("[%-10s][%04f]\n",
				$k, round(($val - $_last)*1000,2));
			$_last = $val;
		}
		printf("TOTAL=[%04f]\n",
			round(($tt['final'] - $tt['begin'])*1000,2));
	}
// EVENTS
	protected function onNewBillAccount($who, $accountname){
	
	}
	protected function onBacktoMerchant(){
			
	}
	protected function onPaymentReceived($bill_key, $status, $txn_id, $ready) {
			
		return true;
	}
	protected function onPaymentExpired($bill_key){
			
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
}
printf("YiiBillingPaymentsInAdvance test in progress..\n");
$inst = new YiiBillingPaymentsInAdvanceTest();
$inst->run();
printf("\nend\n");
