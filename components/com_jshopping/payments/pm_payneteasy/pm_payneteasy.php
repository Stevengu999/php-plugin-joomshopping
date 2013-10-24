<?php

defined('_JEXEC') or die();

jimport('joomla.log.log');

require_once JPATH_ROOT . '/components/vendor/autoload.php';
require_once __DIR__ . '/payment_aggregate.php';
require_once __DIR__ . '/payment_state.php';

use PaynetEasy\PaynetEasyApi\PaymentData\QueryConfig;
use PaynetEasy\PaynetEasyApi\PaymentAggregate;

/**
 * Class pm_pay2pay
 */
class pm_payneteasy extends PaymentRoot
{
    /**
     * Default payment plugin parameters
     *
     * @var array
     */
    static protected $defaultParams = array
    (
        'end_point'                     => '',
        'login'                         => '',
        'production_gateway'            => '',
        'sandbox_gateway'               => '',
        'signing_key'                   => '',
        'gateway_mode'                  => '',
        'transaction_end_status'        => '',
        'transaction_pending_status'    => '',
        'transaction_failed_status'     => ''
    );

    /**
     * Gateway mode list
     *
     * @var array
     */
    static protected $gatewayModeList = array
    (
        array
        (
            'name'  => 'Sandbox',
            'value' => QueryConfig::GATEWAY_MODE_SANDBOX
        ),
        array
        (
            'name'  => 'Production',
            'value' => QueryConfig::GATEWAY_MODE_PRODUCTION
        )
    );

    /**
     * Load language file
     */
    public function __construct()
    {
        $lang_dir  = __DIR__ . '/lang/';
        $lang_file = $lang_dir . JFactory::getLanguage()->getTag() . '.php';

        if (file_exists($lang_file))
        {
            require_once $lang_file;
        }
        else
        {
            require_once $lang_dir . 'en-GB.php';
        }
    }

    /**
     * Display payment plugin parameters in admin interface
     *
     * @param       array       $savedParams         Saved payment plugin config
     */
    public function showAdminFormParams($savedParams)
    {
        if (empty($savedParams))
        {
            $params = static::$defaultParams;
        }
        else
        {
            $params          = array_merge(static::$defaultParams, $savedParams);
        }
        
        $orderStatusList = JModelLegacy::getInstance('orders', 'JshoppingModel')
                                ->getAllOrderStatus();

        $gatewayModeList = static::$gatewayModeList;

        require_once __DIR__ . '/admin_params_form.php';
    }

    /**
     * Show additional fields in payment form
     *
     * @param       array       $paymentParams              Payment plugin config
     * @param       array       $paymentMethodConfig        Payment method config
     */
    public function showPaymentForm($paymentParams, $paymentMethodConfig)
    {
        require_once __DIR__ . '/payment_form.php';
    }

    /**
     * Start payment
     *
     * @param       array           $paymentParams      Payment plugin config
     * @param       jshopOrder      $order              Joomla order
     */
    public function showEndForm($paymentConfig, $order)
    {
        if ($order->order_status != $paymentConfig['transaction_pending_status'])
        {
            return $this->showError(_JSHOP_PAYNETEASY_PENDING_STATUS_ERROR);
        }

        try
        {
            $response = $this
                ->getPaymentAggregate($paymentConfig)
                ->startSale($order, $this->getReturnUrl($order));
        }
        catch (Exception $e)
        {
            $this->logException($e);
            $this->savePaymentStatus($order);
            $this->showError(_JSHOP_PAYNETEASY_PAYMENT_NOT_PASSED);
            $this->cancelOrder($order);

            return;
        }

        $this->savePaymentStatus($order);

        $this->redirect($response->getRedirectUrl());
    }

    /**
     * Normalize input parameters
     *
     * @param       array           $paymentParams      Payment plugin config
     *
     * @return      array                               Normalized input parameters
     */
    public function getUrlParams($paymentParams)
    {
        $input                       = JFactory::$application->input;

        return array
        (
            'order_id'          => $input->getInt('order_id', null),
            'hash'              => '',
            'checkHash'         => 0,
            'checkReturnParams' => 1
        );
    }

    /**
     * Finish payment
     *
     * @param       array           $paymentParams      Payment plugin config
     * @param       jshopOrder      $order              Joomla order
     * @param       string          $action             Checkout action (notify, return, cancel)
     *
     * @return      array                               [<check_result_code>, <check_result_message>]
     */
    public function checkTransaction($paymentParams, $order, $action)
    {
        $this->loadPaymentStatus($order);

        try
        {
            $response = $this->getPaymentAggregate($paymentParams)
                ->finishSale($order, $_REQUEST);
        }
        catch (Exception $e)
        {
            $this->logException($e);
            $this->savePaymentStatus($order, true);

            return array(3, _JSHOP_PAYNETEASY_PAYMENT_NOT_PASSED);
        }

        $this->savePaymentStatus($order, true);

        if ($response->isApproved())
        {
            return array(1, '');
        }
        else
        {
            return array(3, _JSHOP_PAYNETEASY_PAYMENT_DECLINED);
        }
    }

    /**
     * Get aggregate for order processing
     *
     * @param       array       $paymentConfig      Payment plugin config
     *
     * @return      PaymentAggregate
     */
    protected function getPaymentAggregate(array $paymentConfig)
    {
        static $paynetProcessorAggregate = null;

        if (!$paynetProcessorAggregate)
        {
            $paynetProcessorAggregate = new PaymentAggregate($paymentConfig);
        }

        return $paynetProcessorAggregate;
    }

    /**
     * Save payment status to DB
     *
     * @param       jshopOrder      $order      Joomla order
     */
    protected function savePaymentStatus(jshopOrder $order, $alreadyExists = false)
    {
        $paymentStatus = JTable::getInstance('paymentState', 'jshop');

        $paymentStatus->client_id             = $order->order_number;
        $paymentStatus->paynet_id             = $order->paynet_order_id;
        $paymentStatus->payment_status        = $order->payment_status;
        $paymentStatus->transaction_status    = $order->transaction_status;

        unset
        (
            $order->order_number,
            $order->paynet_order_id,
            $order->payment_status,
            $order->transaction_status
        );

        if ($alreadyExists)
        {
            $paymentStatus->store();
        }
        else
        {
            $paymentStatus->create();
        }
    }

    /**
     * Load payment status from DB
     *
     * @param       jshopOrder      $order      Joomla order
     */
    protected function loadPaymentStatus(jshopOrder $order)
    {
        $paymentStatus = JTable::getInstance('paymentState', 'jshop');
        $paymentStatus->load($order->order_number);

        $order->paynet_order_id     = $paymentStatus->paynet_id;
        $order->payment_status      = $paymentStatus->payment_status;
        $order->transaction_status  = $paymentStatus->transaction_status;
    }

    /**
     * Get url for final payment processing
     *
     * @param       jshopOrder      $order      Joomla order
     *
     * @return      string
     */
    protected function getReturnUrl(jshopOrder $order)
    {
        return $this->getActionUrl('return', $order->order_id);
    }

    /**
     * Display message as error
     *
     * @param       string      $message        Message text
     */
    protected function showError($message)
    {
        JFactory::getApplication()->enqueueMessage($message, 'error');
    }

    /**
     * Cancel order if error occured
     *
     * @param       jshopOrder      $order      Order
     */
    protected function cancelOrder(jshopOrder $order)
    {
        $this->redirect($this->getActionUrl('cancel', $order->order_id));
    }

    protected function getActionUrl($action, $orderId)
    {
        $host = JURI::getInstance()->toString(array("scheme",'host', 'port'));

        return $host . SEFLink('index.php?option=com_jshopping&' .
                                         'controller=checkout&' .
                                         'task=step7&' .
                                         "act={$action}&" .
                                         'js_paymentclass=pm_payneteasy&' .
                                         "order_id={$orderId}");
    }

    /**
     * Redirect to given URL
     *
     * @param       string      $url        Url to redirect
     */
    protected function redirect($url)
    {
        JFactory::getApplication()->redirect($url);
    }

    /**
     * Log exception
     *
     * @param       Exception       $exception          Exception to log
     */
    protected function logException(Exception $exception)
    {
        JLog::add($exception, JLog::ERROR);
    }
}
