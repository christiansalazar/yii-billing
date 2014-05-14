<?php
/**
 * YiiBillingOmfStorage 
 * 
 *	Implements the storage for a YiiBillingBase
 *                                                                        
 *	==Architecture==                                                    
 *                                                                        
 *	when you're using the OMF based storage:                            
 *                                                                        
 *		[YourClass]---ext--->YiiBillingOmfStorage---ext--->YiiBillingBase 
 *                                                                        
 *	when you're using a distinct one storage:                           
 *                                                                        
 *		[YourClass]---ext--->YourOwnStorageClass---ext--->YiiBillingBase 
 *		                                                                
 *	==Notes==                                                           
 *		                                                                
 *		YourClass can be (and must be) stored in your components        
 *		and having declared the method: onPaymentReceived               
 *                                                                        
 *		YourClass must extend from any YiiBillingBase class but         
 *		taking care of implementing this methods to provide storage:    
 *                                                                        
 *		_createBillAccount                                              
 *		getBillAccountAccountStatus                                     
 *		setBillAccountStatus                                            
 *		createNewBillKey                                                
 *		findBill                                                        
 *                                                                        
 *	==About OMF==                                                       
 *                                                                        
 *	this is a Yii Framework extension for providing objects storage     
 *	(OMF = Object Modeling Framework)                                   
 *		                                                                
 *	see also:                                                           
 *                                                                        
 *		http://github.com/christiansalazar/omf.git                      
 *                                                                        
 *	if you dont like OMF then must implement your own storage class.    
 * 
 * @abstract
 * @author Christian Salazar <christiansalazarh@gmail.com> 
 * @license FREE BSD
 */
require_once('YiiBillingBase.php');
abstract class YiiBillingOmfStorage extends YiiBillingBase {
	abstract protected function sto();

	protected function BillAccountClassName(){
		return "BillAccount";
	}

	/**
	 * _createBillAccount 
	 * 
	 * @param mixed $who 
	 * @param mixed $accountname 
	 * @access protected
	 * @return string the BillAccount ID created.
	 */
	protected function _createBillAccount($who, $accountname) {
		if($id = $this->getBillAccount($who, $accountname))
			return $id;
		list($id) = $this->sto()->create($this->BillAccountClassName());
		$this->sto()->set($id,'who',$who);
		$this->sto()->set($id,'account_name',$accountname);
		$this->setBillAccountStatus($who, $accountname, 'need-payment');
		return $id;
	}

	/**
	 * resetAccount
	 *	put the machine status back to 'plan-required' deleting all unpaid bills
	 * 
	 * @param mixed $who 
	 * @param mixed $accountname 
	 * @access public
	 * @return void
	 */
	public function resetAccount($who,$accountname){
		$id = $this->getBillAccount($who, $accountname);
		$this->sto()->set($id, "renew_plan", 'TRUE'); // 'TRUE', not: true
		$this->sto()->set($id, "account_status", "plan-required");
		$this->sto()->set($id, "plan", "noplan");
		$this->sto()->set($id, "current_bill", "");
		foreach($this->listBillKeys($who,$accountname) as $quote){
	 		list($bill_id,$key,$item,$amount,$from,$to,$txn_id) = $quote;
			if(empty($txn_id))
				$this->sto()->deleteObject($bill_id);
		}
	}

	/**
	 * getBillAccount
	 *	returns the ID of the account having the required accountname
	 *
	 *	an identity must have more than one bill account attached to it.
	 * 
	 * @param string $who the identity primary ID
	 * @param string $accountname 
	 * @access private
	 * @return string the bill account id
	 */
	protected function getBillAccount($who, $accountname){
		$items = $this->sto()->findByAttribute(
			$this->BillAccountClassName(), 'who', $who);
		if($items)
		foreach($items as $account){
			list($account_id) = $account;
			$_name = $this->sto()->get($account_id, 'account_name');
			if($_name == $accountname)
				return $account_id;
		}
		return null;
	}
	public function getBillAccountStatus($who, $accountname){
		return $this->sto()->get($this->getBillAccount($who, $accountname), 'account_status');
	}
	public function setBillAccountStatus($who, $accountname, $status){
		$this->sto()->set($this->getBillAccount($who, $accountname), 
			'account_status', $status);
	}
	/**
	 * listBillAccountsByStatus
	 *	return BillAccount object array having the selected account status,
	 *	allowing pagination options. 
	 *
	 *	the returned array is composed in this way:
	 *
	 *		array(
	 			array($id, $who, $accountname, $accountstatus,$curr_billkey), 
				... ,
			)
	 *
	 *	so you can use it in this way:
	 *
	 *		
	 		foreach($this->listBillAccountsByStatus(...) as $ac){
				list($id, $who, $accountname, $accountstatus,$bk) = $ac;
				
	 		}
	 *
	 * 
	 * @param string $status 'plan-required','need-payment','up-to-date'
	 * @param integer $offset 
	 * @param integer $limit 
	 * @param bool $counter_only 
	 * @access public
	 * @return array array see note about returned array 
	 */
	public function listBillAccountsByStatus($status,$offset=0,$limit=-1,
	  $counter_only=false){
		if($counter_only == true){
			$items = $this->sto()->findByAttribute($this->BillAccountClassName(),
				'account_status',$status,$offset,$limit,true);
			if($items) return $items;
			return 0;
		}else{
			$objects = array();
			$_items = $this->sto()->findByAttribute($this->BillAccountClassName(),
				'account_status',$status,$offset,$limit,false);
			if(null !== $_items)
				foreach($_items as $obj){
					list($id) = $obj;
					$objects[] = array(
						$id,
						$this->sto()->get($id,'who'),
						$this->sto()->get($id,'account_name'),
						$this->sto()->get($id,'account_status'),
						$this->sto()->get($id,'current_bill'),
					);
				}
			return $objects;
		}
	}
	protected function createNewBillKey(
		$who, $accountname, $item, $amount, $from_date, $to_date){
			list($bill_id) = $this->sto()->create('Bill','','',
				$this->getBillAccount($who, $accountname));
			$bill_key = hash('crc32', $bill_id);
			$properties = array(
				'item' => $item,
				'amount' => $amount,
				'from' => $from_date,
				'to' => $to_date,
				'key' => $bill_key,
				'expired_flag' => 'false',
			);
			$this->sto()->set($bill_id, $properties);
		return $bill_key;
	}
	private function _getBillParent($bill_id){
		foreach($this->sto()->getParents($bill_id,"parent") as $p)
			return $p;
		return null; // has no parents
	}
	/**
	 * findBill
	 *	returns bill information
	 * 
	 *	the returned array can be readed as follow:
	 *	
	 *	list($who, $item, $amount, $from, $to, $txn_id, $id) 
	 *		= $some->findBill("somebillkey");
	 * 
	 * @param string $bill_key 
	 * @access protected
	 * @return array see note
	 */
	protected function findBill($bill_key){
		if(null == ($bill_list = $this->sto()->findByAttribute('Bill','key',$bill_key)))
			return null;
		list($bill_id) = $bill_list[0];
		list($billaccount_id) = $this->_getBillParent($bill_id);
		$who = $this->sto()->get($billaccount_id,'who');
		return array(
			$who,
			$this->sto()->get($bill_id, 'item'),
			$this->sto()->get($bill_id, 'amount'),
			$this->sto()->get($bill_id, 'from'),
			$this->sto()->get($bill_id, 'to'),
			$this->sto()->get($bill_id, 'txn_id'),
			$bill_id,
		);
	}
	/**
	 * getBillAccountInfo
	 *	returns the account name of the parent BillAccount for this Bill.
	 *	[:BillAccount]<>---[has many]---[:Bill]
	 *	
	 * @param mixed $bill_key 
	 * @access protected
	 * @return array  (who, accountname)
	 */
	protected function getBillAccountInfo($bill_key){
		if(null == ($list = $this->sto()->findByAttribute('Bill','key',$bill_key)))
			return null;
		list($bill_id) = $list[0];
		list($billaccount_id) = $this->_getBillParent($bill_id);
		return array($this->sto()->get($billaccount_id,'who'),
		$this->sto()->get($billaccount_id,'account_name'));
	}
	protected function setBillPaid($bill_key, $txn_id){
		if(null == ($data = $this->findBill($bill_key)))	
			return;
		list($who, $item, $amount, $from, $to, $tr, $bill_id) = $data;
		$this->sto()->set($bill_id, 'txn_id', $txn_id);
	}
	protected function getBillPaid($bill_key){
		if(null == ($data = $this->findBill($bill_key)))	
			return null;
		list($who, $item, $amount, $from, $to, $tr, $bill_id) = $data;
		return $this->sto()->get($bill_id, 'txn_id');
	}
	protected function setCurrentBillKey($who, $accountname, $bill_key){
		$this->sto()->set($this->getBillAccount($who, $accountname), 
			'current_bill', $bill_key);
	}
	protected function getCurrentBillKey($who, $accountname){
		return $this->sto()->get($this->getBillAccount($who, $accountname), 
			'current_bill');
	}
	protected function listBillKeys($who,$accountname){
		$list = array();
		foreach($this->sto()->getChilds($this->getBillAccount($who,$accountname),
			'parent', "Bill") as $obj){
			list($bill_id) = $obj;
			$item = array();
			$item[] = $bill_id;
			$item[] = $this->sto()->get($bill_id, 'key');
			$item[] = $this->sto()->get($bill_id, 'item');
			$item[] = $this->sto()->get($bill_id, 'amount');
			$item[] = $this->sto()->get($bill_id, 'from');
			$item[] = $this->sto()->get($bill_id, 'to');
			$item[] = $this->sto()->get($bill_id, 'txn_id');
			$list[] = $item;
		}
		return $list;
	}
}
