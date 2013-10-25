<?php

namespace PaynetEasy\PaynetEasyApi;

use jshopOrder;

class SalePaymentAggregate extends AbstractPaymentAggregate
{
    /**
     * {@inheritdoc}
     */
    protected $initialApiMethod = 'sale';

    /**
     * {@inheritdoc}
     */
    protected function getPaymentTransaction(jshopOrder $joomlaOrder, $redirectUrl = null)
    {
        $paynetTransaction = parent::getPaymentTransaction($joomlaOrder, $redirectUrl);

        $this->addCreditCardData($paynetTransaction, $joomlaOrder);

        return $paynetTransaction;
    }
}
