<?php

namespace Mrkody\Payanyway;

use Idma\Robokassa\Exception\InvalidSumException;
use Idma\Robokassa\Exception\InvalidParamException;
use Idma\Robokassa\Exception\InvalidInvoiceIdException;
use Idma\Robokassa\Exception\EmptyDescriptionException;

use Illuminate\Support\Facades\Log;


/**
 * Class Payment
 *
 * @author mrkody kody1994@mail.ru
 *
 * @package Mrkody\Payanyway
 */
class Payment {
    private $baseUrl      = 'https://www.moneta.ru/assistant.htm';
    private $isTestMode   = false;
    private $valid        = false;
    private $data;
    private $customParams = [];
    private $submit_button_class;
    private $submit_button_name;

    private $mnt_id;
    private $mnt_currency_code;
    private $moneta_locale;
    private $paymentPassword;
    private $signature;

    private $mnt_user;
    private $paymentSystem_unitId;
    private $mnt_corraccount;

    public function __construct(
        $mnt_id, 
        $mnt_currency_code, 
        $moneta_locale, 
        $paymentPassword, 
        $testMode = false, 
        $submit_button_class = '', 
        $submit_button_name = 'Pay'
    ) {
        $this->mnt_id              = $mnt_id;
        $this->mnt_currency_code   = $mnt_currency_code;
        $this->moneta_locale       = $moneta_locale;
        $this->isTestMode          = $testMode;
        $this->paymentPassword     = $paymentPassword;
        $this->submit_button_class = $submit_button_class;
        $this->submit_button_name  = $submit_button_name;

        /*if($this->isTestMode)
        {
            $this->baseUrl = 'https://demo.moneta.ru/assistant.htm';
        }*/
        $this->data['MNT_COMMAND'] = false;
        $this->data['MNT_ID'] = $this->mnt_id;
        $this->data['MNT_TRANSACTION_ID'] = false;
        $this->data['MNT_OPERATION_ID'] = false;
        $this->data['MNT_AMOUNT'] = false;
        $this->data['MNT_CURRENCY_CODE'] = $this->mnt_currency_code;
        $this->data['MNT_SUBSCRIBER_ID'] = '';
        $this->data['MNT_TEST_MODE'] = (int)$this->isTestMode;
    }

    public function getPaymentForm()
    {
        if (empty($this->customParams['MNT_DESCRIPTION'])) {
            throw new EmptyDescriptionException();
        }

        if ($this->data['MNT_TRANSACTION_ID'] <= 0) {
            throw new InvalidInvoiceIdException();
        }

        $this->data['MNT_SIGNATURE'] = $this->makeSignature($this->data);

        //$this->data['MNT_SUBSCRIBER_ID'] = 7;

        if(!$this->isTestMode)
        {
            unset($this->data['MNT_TEST_MODE']);
        }

        $string = "<form action='{$this->baseUrl}' method='post' style='display:inline-block;'>";
        foreach(array_merge($this->data, $this->customParams) as $name => $item)
        {
            if($item !== false)
            {
                $string .= "<input type='hidden' name='$name' value='$item'>";
            }
        }
        $string .= "<input type='hidden' name='moneta.locale' value='$this->moneta_locale'>";
        $string .= "<input type='submit' class='{$this->submit_button_class}' value='{$this->submit_button_name}'></form>";

        return $string;
    }

    public function validateResult($data)
    {
        return $this->validate($data);
    }

    public function validateCheck($data)
    {
        $this->data['MNT_COMMAND'] = 'CHECK';

        return $this->validate($data);
    }

    public function checkResponseAmount()
    {
        return $this->checkResponse(100);
    }

    public function checkResponseSuccess()
    {
        return $this->checkResponse(402);
    }

    public function checkResponseFail()
    {
        return $this->checkResponse(302);
    }

    public function checkResponseDecline()
    {
        return $this->checkResponse(500);
    }

    private function checkResponse($result_code)
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><MNT_RESPONSE/>');

        $response_array['MNT_RESULT_CODE'] = $result_code;
        $response_array['MNT_ID'] = $this->data['MNT_ID'];
        if(isset($this->data['MNT_TRANSACTION_ID']))
        {
            $response_array['MNT_TRANSACTION_ID'] = $this->data['MNT_TRANSACTION_ID'];
        }
        $signature = $this->makeSignature($response_array);

        if(isset($this->data['MNT_AMOUNT']))
        {
            $response_array['MNT_AMOUNT'] = $this->data['MNT_AMOUNT'];
        }
        $response_array['MNT_SIGNATURE'] = $signature;

        array_to_xml($response_array, $xml);

        return $xml->asXML();
    }

    public function validateSuccess($data)
    {
        return $this->data['MNT_TRANSACTION_ID'] = $data['MNT_TRANSACTION_ID'];
    }

    private function validate($data)
    {
        if(isset($this->data['MNT_TRANSACTION_ID']))
        {
            $this->data['MNT_TRANSACTION_ID'] = $data['MNT_TRANSACTION_ID'];
        }
        if(isset($data['MNT_OPERATION_ID']))
        {
            $this->data['MNT_OPERATION_ID'] = $data['MNT_OPERATION_ID'];
        }
        if(isset($data['MNT_AMOUNT']))
        {
            $this->data['MNT_AMOUNT'] = $data['MNT_AMOUNT'];
        }
        $this->data['MNT_SUBSCRIBER_ID'] = (!empty($data['MNT_SUBSCRIBER_ID']))?$data['MNT_SUBSCRIBER_ID']:'';
       
        if(isset($data['MNT_USER']))
        {
            $this->mnt_user = $data['MNT_USER'];
        }
        if(isset($data['paymentSystem_unitId']))
        {
            $this->paymentSystem_unitId = $data['paymentSystem_unitId'];
        }
        if(isset($data['MNT_CORRACCOUNT']))
        {
            $this->mnt_corraccount = $data['MNT_CORRACCOUNT'];
        }

        $this->signature = $data['MNT_SIGNATURE'];
        
        Log::info(print_r($this->data, true));

        $signature = $this->makeSignature($this->data);
        $this->valid = ($signature === strtolower($this->signature));

        return $this->valid;
    }

    public function isValid()
    {
        return $this->valid;
    }

    public function getSuccessAnswer() {
        return 'SUCCESS';
    }

    public function getTransactionId()
    {
        return $this->data['MNT_TRANSACTION_ID'];
    }

    public function setTransactionId($id)
    {
        $this->data['MNT_TRANSACTION_ID'] = (int) $id;

        return $this;
    }

    public function getSum()
    {
        return $this->data['MNT_AMOUNT'];
    }

    public function setSum($summ)
    {
        $summ = number_format($summ, 2, '.', '');

        if ($summ > 0) {
            $this->data['MNT_AMOUNT'] = $summ;

            return $this;
        } else {
            throw new InvalidSumException();
        }
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->customParams['MNT_DESCRIPTION'];
    }

    public function setDescription($description)
    {
        $this->customParams['MNT_DESCRIPTION'] = (string) $description;

        return $this;
    }

    public function getSubscriberId()
    {
        return $this->data['MNT_SUBSCRIBER_ID'];
    }

    public function setSubscriberId($id)
    {
        $this->data['MNT_SUBSCRIBER_ID'] = (int) $id;

        return $this;
    }

    public function getPaymentSystem()
    {
        return $this->paymentSystem_unitId;
    }

    private function makeSignature(&$data)
    {
        $signature = '';
        foreach($data as $item)
        {
            if($item !== false)
            {
                $signature .= $item;
            }
        }
        $signature .= $this->paymentPassword;

        Log::info($signature);

        return md5($signature);
    }
    
}
