<?php
/**
 * YiiBillingPaypalTools
 *	various tools for paypal, this class is designed to be used in a static way
 * 
 * @author Cristian Salazar H. <christiansalazarh@gmail.com> @salazarchris74 
 * @license FreeBSD {@link http://www.freebsd.org/copyright/freebsd-license.html}
 */
class YiiBillingPaypalTools {
	/**
	 * instantPaymentForm 
	 * 
	 * @param array $params array('custom','item','qty','amount')
	 * @param array $setup array('paypal','email','return','cancel','callback')
	 * @static
	 * @access public
	 * @return string the form body ready to be used
	 */
	public static function instantPaymentForm($params,$setup) {
		list($custom,$itemname,$qty,$total) = $params;
		list($paypal_url, $merchant_email, $return, $cancel,$callback) = $setup;
		$html = "";
		$html.="<form action='$paypal_url' method='post'>\n";
		$html.="<input type='hidden' name='business' value='$merchant_email'>\n";
		$html.="<input type='hidden' name='notify_url' value='$callback'>\n";
		$html.="<input type='hidden' name='return' value='$return'>\n";
		$html.="<input type='hidden' name='cancel_url' value='$cancel'>\n";
		$html.="<input type='hidden' name='currency_code' value='USD'>\n";
		$html.="<input type='hidden' name='address_override' value='1'>\n";
		$html.="<input type='hidden' name='no_shipping' value='1'>\n";
		//$html.="<input type='hidden' name='tax' value='0'>\n";
		$html.="<input type='hidden' name='cmd' value='_xclick'>\n";
		$html.="<input type='hidden' name='custom' value='$custom' />\n";
		$html.="<input type='hidden' name='item_name' value='$itemname'>\n";
		//$html.="<input type='hidden' name='item_number' value='$itemnumber'>\n";
		$html.="<input type='hidden' name='quantity' value='$qty'>\n";
		$html.="<input type='hidden' name='amount' value='$total'>\n";
		$html.="<input type='submit' value='' class='paypalbutton'>\n";
		$html.="</form>";
		return $html;
	}
}
