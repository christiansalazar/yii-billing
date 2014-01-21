YII-BILLING
===========

Provides your application with a billing-extensible model, you can adapt it to 
your needs by subclassing it.

* author: Christian Salazar <christiansalazarh@gmail.com>  
* Twitter: @salazarchris74
* Yii Framework ID: bluyell. http://www.yiiframework.com/user/48374/
* My Blog (spanish): http://trucosdeprogramacionmovil.blogspot.com/

#The YiiBilling Architecture

This package offers you a billing model, adaptable to your needs, it is layered
in three levels, as following and having this responsabilities:

in a resume, check this picture:

![Class Diagram][1]

* ###YiiBillingBase  (core level)
	
	offering you basic math methods, events that must be called at some 
	time (all them abstract because a subclass may define them), and
	the ablity to provides an identity of having an account and 
	bills attached to it.
	
	#### BillAccount
	
	Someone (the identity) must have attached a BillAccount, because 
	this model is enabled to handle various accounts for the same identity
	then at this level (in this core class) is required to specify two
	arguments at any time:
	
		$who and $accountname  // two key arguments at this level
		
	At this point the class diagram looks like this one:
	
		[jhonndoe]---has--->[123:BillAccount {some accountname}]
		[jhonndoe]---has--->[456:BillAccount {other accountname}]

	when we want to know the bill account of somebody the we call:
	
		$api = new SomesubclassOfYiiBillingBase;
		$api->getBillAccount($who, "some");
	
	in where "$who" is any somebody identificator, an integer or string.
	
	##### How do we create a BillAccount ?
	
	At core level by calling: 
		
		YiiBillingBase::createBillAccount($who, $accountname)
		(this notation warns you about this method is not public nor 
		static, is a protected method used by subclasses)
		
	Later we just use:
	 
		$api = new SomeSubclassYiiBillingPaymentInAdvance;
		$api->newIdentity($who);
	 
	 in where we create a BillAccount for $who, initialize the machine 
	 status to 'plan-required' and fire an event.

	####Bill
	
	Now, introducing the Bill, this is somewhat to be paid, identified by a
	key number and not the secuencial id for security reasons, but the key
	is unique.  
	
	This is the class model for a Bill:
	
		[jhonndoe]---has--->[123:BillAccount]
		
			[123:BillAccount]---has--->[Bill, key=100]
			[123:BillAccount]---has--->[Bill, key=101]
			[123:BillAccount]---has--->[Bill, key=102]
			[123:BillAccount]---has--->[Bill, key=103]
	
	Ok, a BillAccount HAS MANY Bill instances, depending on the selected
	plan (later) a BillAccount for someone may have one or more Bills to
	be paid.

	#####How do we create bills for a BillAccount ?

	At core level by calling:
	
		YiiBillingBase::createNewBillKey($who,$accountname, .. );
		(this notation warns you about this method is not public nor 
		static, is a protected method used by subclasses)		
	
	this will create a Bill instance for a given $accountname of $who, and
	no more, the way in how Bills are created in the future to follow
	a specialized payment model is responsability of a subclass.  In this
	package i offer you a yet specialized class named:
	
		YiiBillingPaymentInAdvance
	
	in which a business logic is implemented creating Bills for 
	a somebody BillAccount having a simple use case:
	
		"i want to pay my full year bill"
		or
		"i want to pay by three months quote"
	
	In that business logic the way in how Bill are created is specialized
	for a given Billing model, you can create more models following this
	api.

* ###YiiBillingOmfStorage  (provides persistence at core level)

This class is designed to provide Persistence to the YiiBilling system, 
providing body for some specialized methods defined at core level (in 
YiiBillingBase), those methods are expected to persist objects.

The YiiBilling package is aimed to be non dependent of the persistence model, so
i provide a specialized class for this goal. In this default package 
implementation i use OMF (a framework build by me too for handling objects,
properties and relationships).  It is very easy to install please
read more about in GitHub:

https://github.com/christiansalazar/omf.git

The logic here is:

***You must not extend your final class from YiiBillingBase, instead you must 
extend from some class declaring those one labeled as "low level persistence api" 
on YiiBillingBase***

Thats why the final class YiiBillingPaymentInAdvance is a subclass of
YiiBillingOmfStorage, to get persistence from it.  

If you may want to select another distinct one persistence model other than OMF
then you are required to create a new class and subclass from it.

* ###YiiBillingPaymentInAdvance

This class implements a business logic of having payments for yearly quotes or
monthly quotes (3 months per quote), creating the appropiated bills for the guy
you want payments from.

This class is the final product of this YiiBilling package, you must create
your sub class in your own components directory extending from it.  

Why a subclass again ?

Because events and persistence.  Events: because you may want to hear about
what happens: new payments, quotes expired etc, so using that events you may
hook emails to the final user and so on.  Persistence: because you may have
a specialized database connection or in case of OMF storage you may have 
a different omf instance other than the default: Yii::app()->omf.

Another profit of subclassing it is that you can customize the behaviour of
some default methods provided in YiiBillingPaymentInAdvance, as an example 
you may want to have a third payment option: every 6 months, so by overriding
the following method you can do so:

	YiiBillingPaymentInAdvance::createBillQuotes

This method uses the core api to create Bills for a given account, but using
a specific business logic.

* ###SampleBillingSubClass  (a template to be copied into your components)

This final class is yours, here you can hear about fired events, change 
behaviour and some other customizations, inclusive if your done like the logic
at YiiBillingPaymentInAdvance you can create your own one and extends from it.

The body class is:

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

### How do i use all this stuff ?

It is very easy to use, no matter how complex the architecture is, billing is 
complex.

Please have in mind the BillAccount status, it is very important and will guide
the behaviour of this package:

	CURRENT_STATUS      ACTION                    NEW_STATUS
	==============================================================
	null----------------call:newIdentity--------->[plan-required]
	[plan-required]-----call:selectPlan---------->[need-payment]
	[need-payment]------call:makePayment--------->[up-to-date]
	[up-to-date]--------call:checkAccountStatus-->[need-payment]
	[up-to-date]--------call:checkAccountStatus-->[plan-required]

The key is a call to ***checkAccountStatus*** or its variants: 

	* $this->isAccountUpToDate($who)
	* $this->isAccountNeedPayment($who)
	* $this->isAccountPlanRequired($who)
	
	(being $this the instance of your final class)

this last threee methods makes a call to checkAccountStatus

In checkAccountStatus we do some business logic (having a default specialization
 at YiiBillingPaymentInAdvance), this method will change the status of your
 machine to the given machine status:
 
	* plan-required
	* need-payment
	* up-to-date
 
The return value of checkAccountStatus is one of:

	* -1	plan is required
	* -2	bill not found. (very rare, may never occur)
	* -3	no more bill (all are expired)
	* -4	no billAccount created (you may forget to call newIdentity )
	*  0	bill need payment and out of 30 days range limit
	*  2	bill need payment but in range of 30 days limit
	*  1	bill is paid and up to date
	
You may figured out that there is no "payment expired" status, thats because
in the business logic provided by YiiBillingPaymentsInAdvance when a bill
expires then a next one is automatically selected until no more bills found,
in that case -3 were returned by checkAccountStatus and the appropiated event
will be fired.

When an specific bill expires then an event is fired. Look at your events they 
are very clear to let you know what they are for.

	
#Secuence required to use this package

1. initialize with a call to newIdentity, after this step:

		* the identity will have a BillAccount
		* the BillAccount status is: 'plan-required'
		* the identity is enabled to select a plan
		* the selected plan is: noplan
		
	you may initialize your identity at any moment commonly when somebody gets 
	registered into your application.

		$api = new YourBillingSubClass; 
		$api->newIdentity("some_guy_id");

	or you can test for a preexisting BillAccount, it helps in avoiding a machine reset:
	
		$api = new YourBillingSubClass; 
		if($api->requireNewIdentity("some_guy_id"))
			$api->newIdentity("some_guy_id");
	
	that last case is usefull when you are implementing yii-billing in a system
	which user accounts are already created, so only init a new BillAccount 
	for each one if previously was not created.

2. at any moment you can make a call to any of this methods:

		* checkAccountStatus (returns integer reflecting status)
		* canSelectPlan	 (boolean)
		* isAccountUpToDate this next are boolean too
		* isAccountNeedPayment
		* isAccountPlanRequired
		
	check the status at any moment by calling:
		
		$api = new YourBillingSubClass; 
		$who = "some_guy_id";
		if($api->isAccountNeedPayment($who))
			..redirectTo('your payment url')..
		
3. call selectPlan for moving the machine status from 'plan-required' to 'need-payment' 

		* only can continue if canSelectPlan
		* the Bill quotes has been created depending on the selected plan
		* the BillAccount status now is: 'need-payment'
		* the current Bill is the first one of the Bill set created
		* user can no longer select a plan again until all bill expires.

	the selectPlan methods requires a $plan argument which is an array
	having values in this given order:
	
		array($name, $yearly, $monthly, $discount, $is_fullyear)
		as an example:
		$plan = array("yearly account",100,10, '5%', true);
		
	this plan configuration allows you to create bills for it in this
	way:
	
		bill#1	(100+10*12)-5%  fromdate to:from+1year
	
	when the flag is "false" then 4 quotes will be created, 3 months each.
	
	so having a plan selected proceed to tell the billing system about
	select it for a given user:
	
		$api = new YourBillingSubClass; 
		$who = "some_guy_id";
		$api->selectPlan($who, $plan);
		
	after this call you may check the bills created by calling:
	
		foreach($api->listBillQuotes($who) as $q){
			list($id,$billkey,$item,$amount,$from,$to,$txn_id) = $q;
		} 

4. call makePayment to obviously: pay a bill, given its billkey number.
it moves the machine status from 'need-payment' to 'up-to-date' 
 
		$api = new YourBillingSubClass; 
		$who = "some_guy_id";
		$txn_id = "19289189812";
		$bill_key = $api->getActiveBillKey($who);
		$api->makePayment($who, $txn_id, $bill_key);

		you can have more bill information by calling:

		list($who, $item, $amount, $from, $to, $txn_id, $id) 
			= $api->getBillInfo($bill_key);

	Remote payments: A remote payment arrives to you via callback. 
	(see PaypalCallbackAction as an implementation of an IPN paypal handler). When 
	so, remember to return true when receiving the event: 'onPaymentReceived'.

		// at any controller you want define a callback for 
		// receive remote payments via callback url:
		public function actions()
		{
			return array(
				'paypalcallback'=>array(
					'class'=>
						'application.extensions.yii-billing.PaypalCallbackAction',
					'api' =>new YourBillingSubClass(),
					'url' => Yii::app()->params['paypal_url'],
				),
				'backtomerchant'=>array(
					'class'=>
						'application.extensions.yii-billing.BacktoMerchantAction',
				),
			);
		}

5. for moving the machine status from 'up-to-date' back to 'need-payment', or 
for moving it from 'up-to-date' back to 'plan-required' then make a call to: 
checkAccountStatus.
 
	* it will check expiration and move the status when required.
	* machine will change to 'plan-required' when all bills expires.

#Persistence, where does this package store my objects ?

The answer is OMF. https://github.com/christiansalazar/omf.git
Please read more about OMF and how it works to get understanding on it. 
This package (YiiBilling) does not depends on the persistence model, but
OMF is a nice choise and is used instead of traditional persistence models.

The persistence is handled by default by: YiiBillingOmfStorage, 
remember you subclass from: YiiBillingPaymentsInAdvance and this class extends
from YiiBillingOmfStorage in which the persistence is made.

In this class: YiiBillingOmfStorage we look for an instance of OMF (the method
abstract public function sto(); ) this method takes body in your class: 
YourBillingSubClass.  

#Install

Prerequisites: you must first install OMF,  https://github.com/christiansalazar/omf.git

You must clone or download yii-billing by typing:

	 	yourshell# cd /yourapplication/protected/extensions
	 	yourshell# git clone https://github.com/christiansalazar/omf.git
	 	yourshell# git clone https://github.com/christiansalazar/yii-billing.git

Now you must check your extensions are created by listing your extensions dir, 
they must be there.

Now your protected/config/main.php file may include this imports:

	'import'=>array(
		'application.models.*',
		'application.components.*',
		'application.extensions.omf.*',				<-- this
		'application.extensions.yii-billing.*',		<-- and this
	),
	'components'=>array(
		...bla
		'omf' => array(
			'class'=>'application.extensions.omf.OmfDb',
		),
	),

Remember to install the OMF mysql script. see details in the OMF readme file.

Create your own subclass, this will be used by you or your callback (see later)

	 yourshell# cd /yourapplication/protected/extensions/yii-billing
	 yourshell# cp SampleBillingSubClass.php ../../components/MyBilling.php
	 #remember to edit the class name in the new file.

or create it by hand:

	---begin of file:  /yourapp/protected/components/MyBilling.php---
	<?php
	class MyBilling extends YiiBillingPaymentsInAdvance {
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
	?>
	---end of file:  /yourapp/protected/components/MyBilling.php---

If you want to have a paypal callback action then in any controlller:

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

Your callback action will be:

	http://yourapplication/index.php?r=/somecontroller/paypalcallback

Remember to pass the bill_key as the custom argument (paypal case), 
 yii-billing will require it.


#Testing this package

you can test it by creating an action in your application commands having
this body:

	public function actionTestBilling(){
		$test1 = new YiiBillingTest;
		$test1->run();
		$test2 = new YiiBillingPaymentsInAdvanceTest;
		$test2->run();
	}

[1]:https://github.com/christiansalazar/yii-billing/blob/master/yiibilling-classdiagram-2.png?raw=true
