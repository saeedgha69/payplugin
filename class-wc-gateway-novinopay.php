<?php

if (!defined('ABSPATH')) {
    exit;
}


function Load_novinopay_Gateway()
{

    if (!function_exists('Woocommerce_Add_novinopay_Gateway') && class_exists('WC_Payment_Gateway') && !class_exists('WC_novino')) {


        add_filter('woocommerce_payment_gateways', 'Woocommerce_Add_novinopay_Gateway');

        function Woocommerce_Add_novinopay_Gateway($methods)
        {
            $methods[] = 'WC_novino';
            return $methods;
        }

        add_filter('woocommerce_currencies', 'add_IR_currency_novinopay');

        function add_IR_currency_novinopay($currencies)
        {
            $currencies['IRR'] = __('ریال', 'woocommerce');
            $currencies['IRT'] = __('تومان', 'woocommerce');
            $currencies['IRHR'] = __('هزار ریال', 'woocommerce');
            $currencies['IRHT'] = __('هزار تومان', 'woocommerce');

            return $currencies;
        }

        add_filter('woocommerce_currency_symbol', 'add_IR_currency_symbol_novinopay', 10, 2);

        function add_IR_currency_symbol_novinopay($currency_symbol, $currency)
        {
            switch ($currency) {
                case 'IRR':
                    $currency_symbol = 'ریال';
                    break;
                case 'IRT':
                    $currency_symbol = 'تومان';
                    break;
                case 'IRHR':
                    $currency_symbol = 'هزار ریال';
                    break;
                case 'IRHT':
                    $currency_symbol = 'هزار تومان';
                    break;
            }
            return $currency_symbol;
        }

        class WC_novino extends WC_Payment_Gateway
        {


            private $merchantCode;
            private $failedMassage;
            private $successMassage;

            public function __construct()
            {

                $this->id = 'WC_novino';
                $this->method_title = __('نوینو', 'woocommerce');
                $this->method_description = __('تنظیمات درگاه نونینو برای افزونه فروشگاه ساز ووکامرس', 'woocommerce');
                $this->icon = apply_filters('WC_novino_logo', WP_PLUGIN_URL . '/' . plugin_basename(__DIR__) . '/assets/images/logo.png');
                $this->has_fields = false;

                $this->init_form_fields();
                $this->init_settings();

                $this->title = $this->settings['title'];
                $this->description = $this->settings['description'];

                $this->merchantCode = $this->settings['merchantcode'];

                $this->successMassage = $this->settings['success_massage'];
                $this->failedMassage = $this->settings['failed_massage'];

                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                } else {
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
                }

                add_action('woocommerce_receipt_' . $this->id . '', array($this, 'Send_to_novinopay_Gateway'));
                add_action('woocommerce_api_' . strtolower(get_class($this)) . '', array($this, 'Return_from_novinopay_Gateway'));


            }

            public function init_form_fields()
            {
                $this->form_fields = apply_filters('WC_novino_Config', array(
                        'base_config' => array(
                            'title' => __('تنظیمات پایه ای', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'enabled' => array(
                            'title' => __('فعالسازی/غیرفعالسازی', 'woocommerce'),
                            'type' => 'checkbox',
                            'label' => __('فعالسازی درگاه نوینو', 'woocommerce'),
                            'description' => __('برای فعالسازی درگاه پرداخت نوینو باید چک باکس را تیک بزنید', 'woocommerce'),
                            'default' => 'yes',
                            'desc_tip' => true,
                        ),
                        'title' => array(
                            'title' => __('عنوان درگاه', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'woocommerce'),
                            'default' => __('پرداخت با درگاه پرداخت نوینو', 'woocommerce'),
                            'desc_tip' => true,
                        ),
                        'description' => array(
                            'title' => __('توضیحات درگاه', 'woocommerce'),
                            'type' => 'text',
                            'desc_tip' => true,
                            'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'woocommerce'),
                            'default' => __('پرداخت امن با کلیه کارت های عضو شبکه شتاب', 'woocommerce')
                        ),
                        'account_config' => array(
                            'title' => __('تنظیمات حساب', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'merchantcode' => array(
                            'title' => __('مرچنت', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('مرچنت کد درگاه', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => true
                        ),

                        'payment_config' => array(
                            'title' => __('تنظیمات عملیات پرداخت', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'success_massage' => array(
                            'title' => __('پیام پرداخت موفق', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری داخلی نوینو استفاده نمایید .', 'woocommerce'),
                            'default' => __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'woocommerce'),
                        ),
                        'failed_massage' => array(
                            'title' => __('پیام پرداخت ناموفق', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید . این دلیل خطا از سامانه نوینو ارسال میگردد .', 'woocommerce'),
                            'default' => __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'woocommerce'),
                        ),
                    )
                );
            }

            public function process_payment($order_id)
            {
                $order = new WC_Order($order_id);
                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true)
                );
            }


            public function SendRequestTonovinopay($action, $params)
            {
                try {
                    $curl = curl_init('https://api.novinopay.com/payment/ipg/v2/' . $action);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type' => 'application/json'));
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
                    curl_setopt($curl, CURLOPT_TIMEOUT, 50);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    $result = curl_exec($curl);

                    return json_decode($result, true);
                } catch (Exception $ex) {
                    return false;
                }
            }

            public function Send_to_novinopay_Gateway($order_id)
            {

                global $woocommerce;
                $woocommerce->session->order_id_novinopay = $order_id;
                $order = new WC_Order($order_id);
                $currency = $order->get_currency();
                $currency = apply_filters('WC_novino_Currency', $currency, $order_id);


                $form = '<form action="" method="POST" class="novinopay-checkout-form" id="novinopay-checkout-form">
						<input type="submit" name="novinopay_submit" class="button alt" id="novinopay-payment-button" value="' . __('پرداخت', 'woocommerce') . '"/>
						<a class="button cancel" href="' . $woocommerce->cart->get_checkout_url() . '">' . __('بازگشت', 'woocommerce') . '</a>
					 </form><br/>';
                $form = apply_filters('WC_novino_Form', $form, $order_id, $woocommerce);

                do_action('WC_novino_Gateway_Before_Form', $order_id, $woocommerce);
                echo $form;
                do_action('WC_novino_Gateway_After_Form', $order_id, $woocommerce);

                $Amount = (int)$order->order_total;
                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
                $strToLowerCurrency = strtolower($currency);
                if (
                    ($strToLowerCurrency === strtolower('IRT')) ||
                    ($strToLowerCurrency === strtolower('TOMAN')) ||
                    $strToLowerCurrency === strtolower('Iran TOMAN') ||
                    $strToLowerCurrency === strtolower('Iranian TOMAN') ||
                    $strToLowerCurrency === strtolower('Iran-TOMAN') ||
                    $strToLowerCurrency === strtolower('Iranian-TOMAN') ||
                    $strToLowerCurrency === strtolower('Iran_TOMAN') ||
                    $strToLowerCurrency === strtolower('Iranian_TOMAN') ||
                    $strToLowerCurrency === strtolower('تومان') ||
                    $strToLowerCurrency === strtolower('تومان ایران'
                    )
                ) {
                    $Amount *= 10;
                } else if (strtolower($currency) === strtolower('IRHT')) {
                    $Amount *= 10000;
                } else if (strtolower($currency) === strtolower('IRHR')) {
                    $Amount *= 1000;
                } else if (strtolower($currency) === strtolower('IRR')) {
                    $Amount *= 1;
                }

                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_irt', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_novinopay_gateway', $Amount, $currency);

                $CallbackUrl = add_query_arg('wc_order', $order_id, WC()->api_request_url('WC_novino'));

                $products = array();
                $order_items = $order->get_items();
                foreach ($order_items as $product) {
                    $products[] = $product['name'] . ' (' . $product['qty'] . ') ';
                }
                $products = implode(' - ', $products);

                $Description = 'خرید به شماره سفارش : ' . $order->get_order_number() . ' | خریدار : ' . $order->billing_first_name . ' ' . $order->billing_last_name;
                $Mobile = get_post_meta($order_id, '_billing_phone', true) ?: '-';
                $Email = $order->billing_email;
                $Payer = $order->billing_first_name . ' ' . $order->billing_last_name;
                $ResNumber = (int)$order->get_order_number();

                //Hooks for iranian developer
                $Description = apply_filters('WC_novino_Description', $Description, $order_id);
                $Mobile = apply_filters('WC_novino_Mobile', $Mobile, $order_id);
                $Email = apply_filters('WC_novino_Email', $Email, $order_id);
                $Payer = apply_filters('WC_novino_Paymenter', $Payer, $order_id);
                $ResNumber = apply_filters('WC_novino_ResNumber', $ResNumber, $order_id);
                do_action('WC_novino_Gateway_Payment', $order_id, $Description, $Mobile);
                $Email = !filter_var($Email, FILTER_VALIDATE_EMAIL) === false ? $Email : '';
                $Mobile = preg_match('/^09[0-9]{9}/i', $Mobile) ? $Mobile : '';

                $data = array(
                    'merchant_id' => $this->merchantCode,
                    'amount' => (int)$Amount,
                    'callback_url' => $CallbackUrl,
                    'callback_method' => 'GET',
                    'invoice_id' => "$ResNumber",
                    'description' => $Description,
                    "email" => $Email,
                    "plugin" => "WOOCOMMERCE_v" . WOOCOMMERCE_VERSION,
                    "mobile" => $Mobile
                );

                $result = $this->SendRequestTonovinopay('request', json_encode($data, JSON_UNESCAPED_UNICODE));

                if (isset($result['status']) && $result['status'] == 100 && isset($result['data']['payment_url'])) {
                    ///send for payment
//                    wp_redirect(sprintf($result['data']['payment_url']));
                    header('Location: ' . $result['data']['payment_url']);
                    exit;
                } else {
                    $Message = 'متاسفانه در حال حاضر امکان اتصال به درگاه وجود ندارد';
                    $Fault = json_encode($result, JSON_UNESCAPED_UNICODE);
                }

                if (!empty($Message) && $Message) {

                    $Note = sprintf("بروز خطا در اتصال به نوینو | دلیل: " . json_encode($result, JSON_UNESCAPED_UNICODE));
                    $Note = apply_filters('WC_novino_Send_to_Gateway_Failed_Note', $Note, $order_id, $Fault);
                    $order->add_order_note($Note);


                    $Message = isset($result['message']) ? $result['message'] : $Message;
                    $Notice = sprintf("در هنگام اتصال به نوینو خطای زیر رخ داده است : <br/>" . $Message);
                    $Notice = apply_filters('WC_novino_Send_to_Gateway_Failed_Notice', $Notice, $order_id, $Fault);
                    if ($Notice) {
                        wc_add_notice($Notice, 'error');
                    }

                    do_action('WC_novino_Send_to_Gateway_Failed', $order_id, $Fault);
                }
            }


            public function Return_from_novinopay_Gateway()
            {


                $Authority = (isset($_REQUEST['Authority']) && $_REQUEST['Authority'] != "") ? $_REQUEST['Authority'] : "";
                $Authority = sanitize_text_field($Authority);

                $InvoiceNumber = (isset($_REQUEST['InvoiceID']) && $_REQUEST['InvoiceID'] != "") ? $_REQUEST['InvoiceID'] : "";
                $InvoiceNumber = sanitize_text_field($InvoiceNumber);

                global $woocommerce;


                if (isset($_GET['wc_order'])) {
                    $order_id = $_GET['wc_order'];
                } else if ($InvoiceNumber) {
                    $order_id = $InvoiceNumber;
                } else {
                    $order_id = $woocommerce->session->order_id_novinopay;
                    unset($woocommerce->session->order_id_novinopay);
                }

                if ($order_id) {

                    $order = new WC_Order($order_id);
                    $currency = $order->get_currency();
                    $currency = apply_filters('WC_novino_Currency', $currency, $order_id);

                    if ($order->status !== 'completed') {

                        if ($_REQUEST['PaymentStatus'] === 'OK') {

                            $MerchantID = $this->merchantCode;
                            $Amount = (int)$order->order_total;
                            $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
                            $strToLowerCurrency = strtolower($currency);
                            if (
                                ($strToLowerCurrency === strtolower('IRT')) ||
                                ($strToLowerCurrency === strtolower('TOMAN')) ||
                                $strToLowerCurrency === strtolower('Iran TOMAN') ||
                                $strToLowerCurrency === strtolower('Iranian TOMAN') ||
                                $strToLowerCurrency === strtolower('Iran-TOMAN') ||
                                $strToLowerCurrency === strtolower('Iranian-TOMAN') ||
                                $strToLowerCurrency === strtolower('Iran_TOMAN') ||
                                $strToLowerCurrency === strtolower('Iranian_TOMAN') ||
                                $strToLowerCurrency === strtolower('تومان') ||
                                $strToLowerCurrency === strtolower('تومان ایران'
                                )
                            ) {
                                $Amount *= 10;
                            } else if (strtolower($currency) === strtolower('IRHT')) {
                                $Amount *= 10000;
                            } else if (strtolower($currency) === strtolower('IRHR')) {
                                $Amount *= 1000;
                            } else if (strtolower($currency) === strtolower('IRR')) {
                                $Amount *= 1;
                            }

                            $data = [
                                'merchant_id' => $MerchantID,
                                'authority' => $Authority,
                                'amount' => $Amount
                            ];
                            $result = $this->SendRequestTonovinopay('verification', json_encode($data));

                            if ($result['status'] == 100) {
                                $Status = 'completed';
                                $Transaction_ID = $Authority;
                                $Fault = '';
                                $Message = '';
                            } elseif ($result['status'] == 101) {
                                $Message = 'این تراکنش قبلا تایید شده است';
                                $Notice = wpautop(wptexturize($Message));
                                wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                                exit;
                            } else {
                                $Status = 'failed';
                                $Fault = $result['status'];
                                $Message = $result['message'] ?? 'تراکنش ناموفق بود';
                            }
                        } else {
                            $Status = 'failed';
                            $Fault = '';
                            $Message = 'تراکنش ناموفق بود';
                        }

                        if ($Status === 'completed' && isset($Transaction_ID) && $Transaction_ID !== 0) {

                            update_post_meta($order_id, '_transaction_id', $Transaction_ID);
                            update_post_meta($order_id, 'novinopay_payment_card_number', $result['data']['card_pan'] ?? "");
                            update_post_meta($order_id, 'novinopay_psp_rrn', $result['data']['ref_id'] ?? "");

                            $order->payment_complete($Transaction_ID);
                            $woocommerce->cart->empty_cart();

                            $Note = sprintf(__('پرداخت موفقیت آمیز بود .<br/> کد رهگیری : %s', 'woocommerce'), $Transaction_ID);
                            $Note = apply_filters('WC_novino_Return_from_Gateway_Success_Note', $Note, $order_id, $Transaction_ID);
                            if ($Note)
                                $order->add_order_note($Note, 1);


                            $Notice = wpautop(wptexturize($this->successMassage));

                            $Notice = str_replace('{transaction_id}', $Transaction_ID, $Notice);

                            $Notice = apply_filters('WC_novino_Return_from_Gateway_Success_Notice', $Notice, $order_id, $Transaction_ID);
                            if ($Notice)
                                wc_add_notice($Notice, 'success');

                            do_action('WC_novino_Return_from_Gateway_Success', $order_id, $Transaction_ID);

                            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                            exit;
                        }

                        if (($Transaction_ID && ($Transaction_ID != 0))) {
                            $tr_id = ('<br/>توکن : ' . $Transaction_ID);
                        } else {
                            $tr_id = '';
                        }

                        $Note = sprintf(__('پرداخت ناموفق |  : %s %s', 'woocommerce'), $Message, $tr_id);

                        $Note = apply_filters('WC_novino_Return_from_Gateway_Failed_Note', $Note, $order_id, $Transaction_ID, $Fault);
                        if ($Note) {
                            $order->add_order_note($Note, 1);
                        }

                        $Notice = wpautop(wptexturize($this->failedMassage));

                        $Notice = str_replace(array('{transaction_id}', '{fault}'), array($Transaction_ID, $Message), $Notice);
                        $Notice = apply_filters('WC_novino_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $Transaction_ID, $Fault);
                        if ($Notice) {
                            wc_add_notice($Notice, 'error');
                        }

                        do_action('WC_novino_Return_from_Gateway_Failed', $order_id, $Transaction_ID, $Fault);

                        wp_redirect($woocommerce->cart->get_checkout_url());
                        exit;
                    }

                    $Transaction_ID = get_post_meta($order_id, '_transaction_id', true);

                    $Notice = wpautop(wptexturize($this->successMassage));

                    $Notice = str_replace('{transaction_id}', $Transaction_ID, $Notice);

                    $Notice = apply_filters('WC_novino_Return_from_Gateway_ReSuccess_Notice', $Notice, $order_id, $Transaction_ID);
                    if ($Notice) {
                        wc_add_notice($Notice, 'success');
                    }

                    do_action('WC_novino_Return_from_Gateway_ReSuccess', $order_id, $Transaction_ID);

                    wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                    exit;
                }

                $Fault = __('شماره سفارش وجود ندارد .', 'woocommerce');
                $Notice = wpautop(wptexturize($this->failedMassage));
                $Notice = str_replace('{fault}', $Fault, $Notice);
                $Notice = apply_filters('WC_novino_Return_from_Gateway_No_Order_ID_Notice', $Notice, $order_id, $Fault);
                if ($Notice) {
                    wc_add_notice($Notice, 'error');
                }

                do_action('WC_novino_Return_from_Gateway_No_Order_ID', $order_id, '0', $Fault);

                wp_redirect($woocommerce->cart->get_checkout_url());
                exit;
            }

        }

    }
}

add_action('plugins_loaded', 'Load_novinopay_Gateway', 0);
