<?php

namespace Mrkody\Teko;

use Mrkody\Teko\Exception\InvalidOrderException;
use Mrkody\Teko\Exception\InvalidSignatureException;

use Illuminate\Support\Facades\Log;

/**
 * Class Payment
 *
 * @author mrkody kody1994@mail.ru
 *
 * @package Mrkody\Teko
 */

class Payment {
    private $baseUrl = 'http://pg.teko.io:3005/init_session';
    private $valid   = false;
    private $client;
    private $payment;
    private $order;
    private $account;
    private $data;

    private $redirectURL;
    private $product;
    private $locale;
    private $currency; // "ISO"
    private $theme; // "light", "dark"
    private $url;
    private $icon;
    private $retry; // undefined, "url", "widget-close"
    private $secret;

    private $signature;

    private $tx;

    public function __construct(
        $base_url,
        $client_id, 
        $client_showcase,
        $currency, 
        $secret, 
        $locale = 'ru',
        $theme = 'light'
    ) {
        $this->baseUrl    = $base_url;
        $this->currency   = $currency;
        $this->secret     = $secret;
        $this->locale     = $locale;
        $this->theme      = $theme;

        $this->createClient($client_id, $client_showcase);
    }

    private function createClient($id, $showcase)
    {
        $this->client = [
            'id' => $id,
            'showcase' => $showcase,
        ];
    }

    public function setProduct($value)
    {
        $this->product = $value;
    } 

    public function setAccount($id, $extra = [])
    {
        $this->account = [
            'id' => $id,
        ];
        if(count($extra)) $this->account['extra'] = $extra;
    }

    public function setRedirectURL($value)
    {
        $this->redirectURL = $value;
    }

    public function setUrl($value)
    {
        $this->url = $value;
    }

    public function setIcon($value)
    {
        $this->icon = $value;
    }

    public function setRetry($value)
    {
        $this->retry = $value;
    }

    public function setOrder($cls, $data = [], $extra = [])
    {
        if($cls == 'item_list')
        {
            $this->order = [
                'cls' => $cls,
                $cls => $data, // [[id, name, count],...]
            ];
        } elseif ($cls == 'transaction') {
            $this->order = [
                'cls' => $cls,
                $cls => $data, // [id, start_t]
            ];
        } else {
            throw new InvalidOrderException();
        }
        if(count($extra)) $this->order['extra'] = $extra;
    }

    public function setPayment($amount)
    {
        $this->payment = [
            'amount' => (int)ceil($amount),
            'currency' => $this->currency,
            'exponent' => 2,
        ];
    }

    public function getPaymentForm()
    {
        if (empty($this->baseUrl)) {
            throw new \Exception();
        }

        if (empty($this->redirectURL)) {
            throw new \Exception();
        }

        if (empty($this->account)) {
            throw new \Exception();
        }

        if (empty($this->payment)) {
            throw new \Exception();
        }

        if (empty($this->client)) {
            throw new \Exception();
        }

        if (empty($this->order)) {
            throw new \Exception();
        }

        $data = [
            'dst' => $this->account, 
            'initiator' => $this->client,
            'locale' => $this->locale, 
            'order' => $this->order, 
            'payment' => $this->payment, 
            'product' => $this->product, 
            'redirect_url' => $this->redirectURL, 
        ];

        if(isset($this->icon))
        {
            $data['icon'] = $this->icon;
        }
        if(isset($this->url))
        {
            $data['url'] = $this->url;
        }
        if(isset($this->retry))
        {
            $data['retry'] = $this->retry;
        }

       /* $data = [
            'order' => ['cls' => 'transaction', 'transaction' => ['id' => '77964', 'start_t' => 1496834313]], 
            'payment' => ['amount' => 100, 'currency' => 840, 'exponent' => 2], 
            'redirect_url' => 'google.com', 
            'inner_cur_amount' => '',
            'inner_cur_name' => '',
            'comment' => '',
        ];*/

        $this->signature = $this->makeSignature($data);

        $data['theme'] = $this->theme;

        $string = "<iframe with='100%' height='100%' src=" . $this->makeURL($data) . " onload='resizeIframe(this)'></iframe>";

        return $string;
    }

    private function makeURL(&$data)
    {
        if(empty($this->signature))
        {
            throw new \Exception();
        }

        $data['signature'] = $this->signature;

        $string = '';
        foreach($data as $key => $value)
        {
            if(is_array($value))
            {
                $string .= urlencode($key) . '=' . str_replace('"', '%22', json_encode($value, JSON_HEX_QUOT)); 
            } elseif($key == 'signature') {
                $string .= urlencode($key) . '=' . $value;
            } else {
                $string .= urlencode($key) . '=' . urlencode($value);
            }
            $string .= '&';
        }

        return $this->baseUrl . '?' . rtrim($string, '&');
    }

    private function setTx($id, $start_t)
    {
        $this->tx = [
            'id' => $id,
            'start_t' => $start_t,
        ];
    }

    public function validate($data, $signature, $post_body)
    {
        if(!empty($signature))
        {
            $this->signature = $signature;
            $signature = $this->makeHash($post_body);

            if($this->valid = ($signature === $this->signature))
            {
                if(isset($data['product']))
                {
                    $this->setProduct($data['product']);
                }
                if(isset($data['payment']))
                {
                    $this->setPayment($data['payment']['amount']);
                }
                if(isset($data['order']))
                {
                    $this->setOrder($data['order']['cls'], $data['order'][$data['order']['cls']]);
                }
                if(isset($data['order']))
                {
                    $this->setOrder($data['order']['cls'], $data['order'][$data['order']['cls']]);
                }
                if(isset($data['tx']))
                {
                    $this->setTx($data['tx']['id'], $data['tx']['start_t']);
                }
            }

            return $this->valid;
        }
        throw new InvalidSignatureException();
    }

    public function getSuccessAnswer() {
        if(isset($this->tx))
        {
            $res = [
                'success' => true,
                'result' => [
                    'tx' => $this->tx,
                ],
            ];
            Log::info(json_encode($res));
            return response()->json($res);
        }
        return $this->getFailAnswer();
    }

    public function getFailAnswer() {
        $res = [
            'success' => false,
            'result' => [
                'code' => 302,
                'description' => 'Incorrect request.',
            ],
        ];
        Log::info(json_encode($res));
        return response()->json($res);
    }

    public function getTransactionId()
    {
        if(isset($this->order))
        {
            if(isset($this->order['transaction']))
            {
                return $this->order['transaction']['id'];
            }
        }
        return false;
    }

    public function getAmount()
    {
        return $this->payment['amount'];
    }

    public function getAccountId()
    {
        if(isset($this->account['id']))
        {
            return $this->account['id'];
        }
        return false;
    }

    private function makeSignature(array &$data)
    {
        $string = $this->_toByteValueOrderedQueryString($data);

        $signature = $this->makeHash($string);
      
        return $signature;
    }

    private function makeHash($string)
    {
        $hash = base64_encode(hash_hmac('sha1', $string, $this->secret, true));

        Log::info([$hash, $string]);

        return $hash;
    }

    private function _toByteValueOrderedQueryString(array &$data)
    {
        $this->ksortRecursive($data);

        $string = '';
        foreach($data as $key => $value)
        {
            if(!empty($value) || true)
            {
                if(
                    !in_array(
                        $key, 
                        [
                            'initiator', 
                            'dst', 
                            'payment', 
                            'order', 
                            'product', 
                            'redirect_url', 
                            'locale',
                            'comment',
                            'inner_cur_amount',
                            'inner_cur_name',
                        ]
                    )
                ) {
                    continue;
                }
                if(is_array($value))
                {
                    $string .= $key . '|' . json_encode($value);
                } else {
                    $string .= $key . '|' . $value;
                }
                $string .= '|';
            }
        }

        return rtrim($string, '|');
    }

    private function ksortRecursive(&$array, $sort_flags = SORT_REGULAR) {
        if (!is_array($array)) return false;
        ksort($array, $sort_flags);
        foreach ($array as &$arr) {
            $this->ksortRecursive($arr, $sort_flags);
        }
        return true;
    }

}
