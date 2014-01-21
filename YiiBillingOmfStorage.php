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
		foreach($this->sto()->find(
			$this->BillAccountClassName(), 'who', $who) as $account){
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
		if(null == ($bill_list = $this->sto()->find('Bill','key',$bill_key)))
			return null;
		list($bill_id) = $bill_list[0];
		list($billaccount_id) = $this->sto()->getParent($bill_id);
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
		if(null == ($list = $this->sto()->find('Bill','key',$bill_key)))
			return null;
		list($bill_id) = $list[0];
		list($billaccount_id) = $this->sto()->getParent($bill_id);
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
