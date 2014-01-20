<?php
/**
 	this is a sample subclass from YiiBillingPaymentsInAdvance,

	copy this class in your components.

	see doc at YiiBillingPaymentsInAdvance to know more about usage

	@author Christian Salazar <christiansalazarh@gmail.com>
	@package Yii-Billing	https://github.com/christiansalazar/yiibilling.git
	@license FREE BSD
 */
class SampleBillingSubClass extends YiiBillingPaymentsInAdvance {
	public function sto(){
		return Yii::app()->omf;
	}
	protected function onPaymentReceived($bill_key, $status, $txn_id, $ready) {
		//return true to accept remote payment
		return true;
	}
	protected function onNewBillAccount($who, $accountname){ }
	protected function onBacktoMerchant(){ }
	protected function onPaymentExpired($bill_key){	}
	protected function onNewPlanSelected($who, $plan, $billkeys){ }
	protected function onPlanRequired($who){ }
	protected function onNextBillSelected($billkey){ }
	protected function onBillNeedPayment($billkey,$flagFirst){ }
	protected function onBillUpToDate($billkey,$flagfirst){ }
	protected function onNoMoreBills($who){ }
}
