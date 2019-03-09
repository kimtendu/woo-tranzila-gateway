<?php

/**
 * @author  Anton Bond
 */

if (!class_exists('WC_Payment_Gateway')){
	//Woocommerce is not active.
	return;
}

class WC_Tranzila_Payment_Gateway extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                  = 'tranzillapayment';//ID needs to be ALL lowercase or it doens't work
		$this->title               = $this->get_option('title');
		$this->method_title        = 'Tranzila';
		$this->description         = $this->get_option('description');
		$this->method_description  = 'Pay securely with an Israeli credit card using Tranzila.';
		$this->has_fields          = false;
		$this->icon                = plugins_url( 'logo.png', dirname(__FILE__) );
		$this->terminal_name       = $this->get_option('terminal_name');
		$this->iframeWidth       = $this->get_option('iframeWidth');
		$this->iframeHeight       = $this->get_option('iframeHeight');
		$this->trBgColor       = $this->get_option('trBgColor');
		$this->trTextColor       = $this->get_option('trTextColor');
		$this->trButtonColor       = $this->get_option('trButtonColor');
		$this->buttonText       = $this->get_option('buttonText');
		$this->cancelText       = $this->get_option('cancelText');


		$this->init_form_fields();
		$this->init_settings();

		//save admin options:
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action('woocommerce_receipt_tranzillapayment', array($this, 'tgwc_receipt_page'));
		add_action('woocommerce_thankyou_tranzillapayment', array($this, 'tgwc_thankyou_page'));
		add_action('woocommerce_api_tranzila_payment_callback', array( $this, 'tgwc_callback_handler' ) );
	}

	function init_form_fields(){
		$this->form_fields = array(
			'tranzila_settings_steps' => array(
				'title'       => __( 'Tranzila plugin settings steps:', 'woocommerce-gateway-paypal-express-checkout' ),
				'type'        => 'title',
				'description' => __( '<p>1. Get terminal name from the Tranzila company and provide it in <strong>Terminal Name</strong> field below.</p><p>2. Copy and insert next URL for <strong>success URL</strong> and <strong>fail URL</strong> fields in Tranzila Sales page settings:</p><code>'. get_home_url() .'/wc-api/tranzila_payment_callback</code>' ),
			),
			'enabled' => array(
				'title' => __('Enable/Disable', 'wctranzillapc'),
				'type' => 'checkbox',
				'label' => __('Enable Tranzila Payment Module.', 'wctranzillapc'),
				'default' => 'no'),
			'title' => array(
				'title' => __('Title:', 'wctranzillapc'),
				'type'=> 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'wctranzillapc'),
				'default' => __('Tranzila', 'wctranzillapc')),
			'description' => array(
				'title' => __('Description:', 'wctranzillapc'),
				'type' => 'textarea',
				'description' => __('This controls the description which the user sees during checkout.', 'wctranzillapc'),
				'default' => __('Pay securely with an Israeli credit card using Tranzila.', 'wctranzillapc')),
			'terminal_name' => array(
				'title' => __('Terminal Name', 'wctranzillapc'),
				'type' => 'text',
				'description' =>  __('The Tranzila Terminal Name', 'wctranzillapc'),
			),
			'trBgColor' => array(
				'title' => __('Background color', 'wctranzillapc'),
				'type' => 'text',
				'description' =>  __('The Tranzila Background color', 'wctranzillapc'),
			),
			'trTextColor' => array(
				'title' => __('Text color', 'wctranzillapc'),
				'type' => 'text',
				'description' =>  __('The Tranzila Text color', 'wctranzillapc'),
			),
			'trButtonColor' => array(
				'title' => __('button color', 'wctranzillapc'),
				'type' => 'text',
				'description' =>  __('The Tranzila button color', 'wctranzillapc'),
			),
			'iframeWidth' => array(
				'title' => __('iFrame width', 'wctranzillapc'),
				'type' => 'text',
				'description' =>  __('The Tranzila iFrame width', 'wctranzillapc'),
			),
			'iframeHeight' => array(
				'title' => __('iFrame Height', 'wctranzillapc'),
				'type' => 'text',
				'description' =>  __('The Tranzila iFrame height', 'wctranzillapc'),
			),
			'buttonText' => array(
				'title' => __('Button text', 'wctranzillapc'),
				'type' => 'text',
				'description' =>  __('The Tranzila Button send text', 'wctranzillapc'),
			),
			'cancelText' => array(
				'title' => __('Cancel button text', 'wctranzillapc'),
				'type' => 'text',
				'description' =>  __('The Tranzila Cancel Button text', 'wctranzillapc'),
			),

		);
	}


	function tgwc_receipt_page( $order ) {

		$order = new WC_Order($order);
		$order_id      = $order->get_id();
		$order_total   = $order->get_total();
		$order_email   = $order->get_billing_email();
		$order_phone   = $order->get_billing_phone();
		$order_address = $order->get_billing_address_1();
		$order_first_n = $order->get_billing_first_name();
		$order_last_n  = $order->get_billing_last_name();
		$order_contact = $order_first_n.' '.$order_last_n;
		$validate_code = $this->tgwc_genereate_random_md5();

		$woo_data      = array(
			'order_id'      => $order_id,
			'validate_code' => $validate_code
		);

		$woo_data = base64_encode( serialize( $woo_data ) );

		update_post_meta( $order_id, 'validate_code', $validate_code );

		$iframe = '
		<form
		accept-charset="UTF-8"
		action="https://direct.tranzila.com/'.$this->terminal_name.'/iframe.php"
		method="post"
		id="sp_tranzila_payment_form"
		target="tranzila_iframe">
		<input type="hidden" name="sum" value="'.$order_total.'">
		<input type="hidden" name="phone" value="'.$order_phone.'">
		<input type="hidden" name="contact" value="'.$order_contact.'">
		<input type="hidden" name="email" value="'.$order_email.'">
		<input type="hidden" name="address" value="'.$order_address.'">
		<input type="hidden" name="key" value="wc_order_5c8418aadde13">
		<input type="hidden" name="order" value="'.$order_id.'">
		<input type="hidden" name="order_id" value="'.$order_id.'">
		<input type="hidden" name="currency" value="1">
		<input type="hidden" name="pdesc" value="">
		<input type="hidden" name="nologo" value="1">
		<input type="hidden" name="trBgColor" value="'.$this->trBgColor.'">
		<input type="hidden" name="trTextColor" value="'.$this->trTextColor.'">
		<input type="hidden" name="trButtonColor" value="'.$this->trButtonColor.'">
		<input type="hidden" name="lang" value="il">
		<input type="hidden" name="pdesk" value="WooCommerce Data: '.$woo_data.'">
		<input type="hidden" name="o_tranmode" value="V">
		<input type="submit" class="button alt" id="sp_submit_tranzila_payment_form" value="'.$this->buttonText.'"  style="display: none;">
		<a class="button cancel" href="/">'.$this->cancelText.'</a>
</form>


		<iframe src="https://direct.tranzila.com/'.$this->terminal_name.'/iframe.php" frameborder="0" width="'.$this->iframeWidth.'" height="'.$this->iframeHeight.'"></iframe>';

		echo $iframe;
	}

	function tgwc_genereate_random_md5() {
		//generate random md5:
		return md5( uniqid( rand(), true ) );
	}

	function process_payment( $order_id ) {
		global $woocommerce;
		$order = new WC_Order($order_id);

		return array(
			'result' => 'success',
			'redirect' => $order->get_checkout_payment_url( true )
		);
	}

	 /**
	 * Thankyou Page
	 **/
	function tgwc_thankyou_page( $order ){
		echo 'Tranzila Payment success.';
	}

	function tgwc_callback_handler( $order ) {
		global $woocommerce;

		if ( !empty($_POST) ) {

			//keep only data:
			$woo_data          = str_replace('WooCommerce Data: ', '', sanitize_text_field( $_POST['pdesc'] ) );
			$woo_data          = unserialize( base64_decode( $woo_data ) );
			$received_order_id = sanitize_text_field( $woo_data['order_id'] );
			$received_code     = sanitize_text_field( $woo_data['validate_code'] );
			$real_code         = get_post_meta( $received_order_id, 'validate_code' );

			if ( !empty( $woo_data ) && !empty( $real_code ) ) :

				//if order ID and real order ID is same
				if ( $received_code == $real_code[0] ) {
					$order = new WC_Order($received_order_id);

					$real_total     = $order->get_total();
					$recieved_total = sanitize_text_field( $_POST['sum'] );

					//if total amout and real total amout is same
					if ( $real_total == $recieved_total ) {
						//checking passed
						if (!isset($_POST['Response'])) {
							/**
							 * When there is no 'Response' parameter it either means
							 * that some pre-transaction error happened (like authentication
							 * problems), in which case the result string will be in HTML format,
							 * explaining the error, or the request was made for generate token only
							 * (in this case the response string will contain only 'TranzilaTK'
							 * parameter)
							 */
							// $order->update_status('on-hold', 'order_note');
							$order->add_order_note( 'Tranzila Payment proccess failed. Result - ' . $result );
							die ($result . "\n");
						} else if ( sanitize_text_field($_POST['Response']) !== '000') {
							// Any other than '000' code means transaction failure
							// (bad card, expiry, etc..)
							// $order->update_status('on-hold', 'order_note');
							$order->add_order_note( 'Tranzila Payment proccess failed. Response - ' . sanitize_text_field( $_POST['Response'] ) . ' ('.$this->tgwc_getTextForResponseCode( sanitize_text_field( $_POST['Response'] ) ).')' );
							die ('Payment error: '. $this->tgwc_getTextForResponseCode( sanitize_text_field( $_POST['Response'] ) ) . "\n");
						} else {
							$order->update_status('processing', 'order_note');
							$order->add_order_note('Tranzila Payment success. Amount received - ' . $recieved_total);
							$order->payment_complete();
							$woocommerce->cart->empty_cart();
							//redirect parent window to thank you page
							die('<script>window.top.location.href = "'.$this->get_return_url( $order ).'";</script>');
							//wp_redirect( $this->get_return_url( $order ) );
						}
					} else {
						die('Hacking attempt');
					}
				} else {
					die('Hacking attempt');
				}
			endif;
		}

		die();
	}

	function tgwc_getTextForResponseCode( $code ) {
		$response_messages = array(
			'000' => 'Transaction approved',
			'001' => 'Blocked confiscate card.',
			'002' => 'Stolen confiscate card.',
			'003' => 'Contact credit company.',
			'004' => 'Refusal.',
			'005' => 'Forged. confiscate card.',
			'006' => 'Identity Number of CVV incorrect.',
			'007' => 'Must contact Credit Card Company',
			'008' => 'Fault in building of access key to blocked cards file.',
			'009' => 'Contact unsuccessful.',
			'010' => 'Program ceased by user instruction (ESC).',
			'011' => 'No confirmation for the ISO currency clearing.',
			'012' => 'No confirmation for the ISO currency type.',
			'013' => 'No confirmation for charge/discharge transaction.',
			'014' => 'Unsupported card',
			'015' => 'Number Entered and Magnetic Strip do not match',
			'017' => 'Last 4 digets not entered',
			'019' => 'Record in INT_IN shorter than 16 characters.',
			'020' => 'Input file (INT_IN) does not exist.',
			'021' => 'Blocked cards file (NEG) non-existent or has not been updated - execute transmission or request authorization for each transaction.',
			'022' => 'One of the parameter files or vectors do not exist.',
			'023' => 'Date file (DATA) does not exist.',
			'024' => 'Format file (START) does not exist.',
			'025' => 'Difference in days in input of blocked cards is too large - execute transmission or request authorization for each transaction.',
			'026' => 'Difference in generations in input of blocked cards is too large - execute transmission or request authorization for each transaction.',
			'027' => 'Where the magnetic strip is not completely entered',
			'028' => 'Central terminal number not entered into terminal defined for work as main supplier.',
			'029' => 'Beneficiary number not entered into terminal defined as main beneficiary.',
			'030' => 'Terminal not updated as main supplier/beneficiary and supplier/beneficiary number entered.',
			'031' => 'Terminal updated as main supplier and beneficiary number entered',
			'032' => 'Old transactions - carry out transmission or request authorization for each transaction.',
			'033' => 'Defective card',
			'034' => 'Card not permitted for this terminal or no authorization for this type of transaction.',
			'035' => 'Card not permitted for transaction or type of credit.',
			'036' => 'Expired.',
			'037' => 'Error in instalments - Amount of transaction needs to be equal to the first instalment + (fixed instalments times no. of instalments)',
			'038' => 'Cannot execute transaction in excess of credit card ceiling for immediate debit.',
			'039' => 'Control number incorrect.',
			'040' => 'Terminal defined as main beneficiary and supplier number entered.',
			'041' => 'Exceeds ceiling where input file contains J1 or J2 or J3 (contact prohibited).',
			'042' => 'Card blocked for supplier where input file contains J1 or J2 or J3 (contact prohibited).',
			'043' => 'Random where input file contains J1 (contact prohibited).',
			'044' => 'Terminal prohibited from requesting authorization without transaction (J5)',
			'045' => 'Terminal prohibited for supplier-initiated authorization request (J6)',
			'046' => 'Terminal must request authorization where input file contains J1 or J2 or J3 (contact prohibited).',
			'047' => 'Secret code must be entered where input file contains J1 or J2 or J3 (contact prohibited).',
			'051' => ' Vehicle number defective.',
			'052' => 'Distance meter not entered.',
			'053' => 'Terminal not defined as gas station. (petrol card passed or incorrect transaction code).',
			'057' => 'Identity Number Not Entered',
			'058' => 'CVV2 Not Entered',
			'059' => 'Identiy Number and CVV2 Not Entered',
			'060' => 'ABS attachment not found at start of input data in memory.',
			'061' => 'Card number not found or found twice',
			'062' => 'Incorrect transaction type',
			'063' => 'Incorrect transaction code.',
			'064' => 'Type of credit incorrect.',
			'065' => 'Incorrect currency.',
			'066' => 'First instalment and/or fixed payment exists for non-instalments type of credit.',
			'067' => 'Number of instalments exists for type of credit not requiring this.',
			'068' => 'Linkage to dollar or index not possible for credit other than instalments.',
			'069' => 'Length of magnetic strip too short.',
			'070' => 'PIN terminal not defined',
			'071' => 'PIN must be enetered',
			'072' => 'Secret code not entered.',
			'073' => 'Incorrect secret code.',
			'074' => 'Incorrect secret code - last try.',
			'079' => 'Currency is not listed in vector 59.',
			'080' => '"Club code" entered for unsuitable credit type',
			'090' => 'Transaction cancelling is not allowed for this card.',
			'091' => 'Transaction cancelling is not allowed for this card.',
			'092' => 'Transaction cancelling is not allowed for this card.',
			'099' => 'Cannot read/write/open TRAN file.',
			'100' => 'No equipment for inputting secret code.',
			'101' => 'No authorization from credit company for work.',
			'107' => 'Transaction amount too large - split into a number of transactions.',
			'108' => 'Terminal not authorized to execute forced actions.',
			'109' => 'Terminal not authorized for card with service code 587.',
			'110' => 'Terminal not authorized for immediate debit card.',
			'111' => 'Terminal not authorized for instalments transaction.',
			'112' => 'Terminal not authorized for telephone/signature only instalments transaction.',
			'113' => 'Terminal not authorized for telephone transaction.',
			'114' => 'Terminal not authorized for "signature only" transaction.',
			'115' => 'Terminal not authorized for dollar transaction.',
			'116' => 'Terminal not authorized for club transaction.',
			'117' => 'Terminal not authorized for stars/points/miles transaction.',
			'118' => 'Terminal not authorized for Isracredit credit.',
			'119' => 'Terminal not authorized for Amex Credit credit.',
			'120' => 'Terminal not authorized for dollar linkage.',
			'121' => 'Terminal not authorized for index linkage.',
			'122' => 'Terminal not authorized for index linkage with foreign cards.',
			'123' => 'Terminal not authorized for stars/points/miles transaction for this type of credit.',
			'124' => 'Terminal not authorized for Isracredit payments.',
			'125' => 'Terminal not authorized for Amex payments.',
			'126' => 'Terminal not authorized for this club code.',
			'127' => 'Terminal not authorized for immediate debit transaction except for immediate debit cards.',
			'128' => 'Terminal not authorized to accept Visa card staring with 3.',
			'129' => 'Terminal not authorized to execute credit transaction above the ceiling.',
			'130' => 'Card not permitted for execution of club transaction.',
			'131' => 'Card not permitted for execution stars/points/miles transaction.',
			'132' => 'Card not permitted for execution of dollar transactions (regular or telephone).',
			'133' => 'Card not valid according Isracard list of valid cards.',
			'134' => 'Defective card according to system definitions (Isracard VECTOR1) - no. of figures on card - error.',
			'135' => 'Card not permitted to execute dollar transactions according to system definition (Isracard VECTOR1).',
			'136' => 'Card belongs to group not permitted to execute transactions according to system definition (Visa VECTOR 20).',
			'137' => 'Card prefix (7 figures) invalid according to system definition (Diners VECTOR21)',
			'138' => 'Card not permitted to carry out instalments transaction according to Isracard list of valid cards.',
			'139' => 'Number of instalments too large according to Isracard list of valid cards.',
			'140' => 'Visa and Diners cards not permitted for club instalments transactions.',
			'141' => 'Series of cards not valid according to system definition (Isracard VECTOR5).',
			'142' => 'Invalid service code according to system definition (Isracard VECTOR6).',
			'143' => 'Card prefix (2 figures) invalid according to system definition (Isracard VECTOR7).',
			'144' => 'Invalid service code according to system definition (Visa VECTOR12).',
			'145' => 'Invalid service code according to system definition (Visa VECTOR13).',
			'146' => 'Immediate debit card prohibited for execution of credit transaction.',
			'147' => 'Card not permitted to execute instalments transaction according to Leumicard vector no. 31.',
			'148' => 'Card not permitted for telephone and signature only transaction according to Leumicard vector no. 31',
			'149' => 'Card not permitted for telephone transaction according to Leumicard vector no. 31',
			'150' => 'Credit not approved for immediate debit cards.',
			'151' => 'Credit not approved for foreign cards.',
			'152' => 'Club code incorrect.',
			'153' => 'Card not permitted to execute flexible credit transactions (Adif/30+) according to system definition (Diners VECTOR21).',
			'154' => 'Card not permitted to execute immediate debit transactions according to system definition (Diners VECTOR21).',
			'155' => 'Amount of payment for credit transaction too small.',
			'156' => 'Incorrect number of instalments for credit transaction',
			'157' => '0 ceiling for this type of card for regular credit or Credit transaction.',
			'158' => '0 ceiling for this type of card for immediate debit credit transaction',
			'159' => '0 ceiling for this type of card for immediate debit in dollars.',
			'160' => '0 ceiling for this type of card for telephone transaction.',
			'161' => '0 ceiling for this type of card for credit transaction.',
			'162' => '0 ceiling for this type of card for instalments transaction.',
			'163' => 'American Express card issued abroad not permitted for instalments transaction.',
			'164' => 'JCB cards permitted to carry out regular credit transactions.',
			'165' => 'Amount in stars/points/miles larger than transaction amount.',
			'166' => 'Club card not in terminal range.',
			'167' => 'Stars/points/miles transaction cannot be executed.',
			'168' => 'Dollar transaction cannot be executed for this type of card.',
			'169' => 'Credit transaction cannot be executed with other than regular credit.',
			'170' => 'Amount of discount on stars/points/miles greater than permitted.',
			'171' => 'Forced transaction cannot be executed with credit/immediate debut card.',
			'172' => 'Previous transaction cannot be cancelled (credit transaction or card number not identical).',
			'173' => 'Double transaction.',
			'174' => 'Terminal not permitted for index linkage for this type of credit.',
			'175' => 'Terminal not permitted for dollar linkage for this type of credit.',
			'176' => 'Card invalid according to system definition (Isracard VECTOR1)',
			'177' => 'Cannot execute "Self-Service" transaction at gas stations except at "Self-Service at gas stations".',
			'178' => 'Credit transaction forbidden with stars/points/miles.',
			'179' => 'Dollar credit transaction forbidden on tourist card.',
			'180' => 'Club Card can not preform Telephone Transactions',
			'200' => 'Application error.',
			'700' => 'Approved TEST Masav transaction',
			'701' => 'Invalid Bank Number',
			'702' => 'Invalid Branch Number',
			'703' => 'Invalid Account Number',
			'704' => 'Incorrect Bank/Branch/Account Combination',
			'705' => 'Application Error',
			'706' => 'Supplier directory does not exist',
			'707' => 'Supplier configuration does not exist',
			'708' => 'Charge amount zero or negative',
			'709' => 'Invalid configuration file',
			'710' => 'Invalid date format',
			'711' => 'DB Error',
			'712' => 'Required parameter is missing',
			'800' => 'Transaction Canceled',
			'900' => '3D Secure Failed',
			'903' => 'Fraud suspected',
			'951' => 'Protocol Error',
			'952' => 'Payment not completed',
			'954' => 'Payment Failed',
			'955' => 'Payment status error',
			'959' => 'Payment completed unsuccessfully',
		);

		if (array_key_exists( $code, $response_messages) ) {
			return $response_messages[$code];
		} else {
			return $code;
		}
	}
}