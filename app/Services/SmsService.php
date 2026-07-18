<?php

namespace App\Services;

use App\SMSProviders\TonkraSms;

class SmsService
{
    private $_tonkraSms;

    public function __construct(TonkraSms $tonkraSms)
    {
        $this->_tonkraSms = $tonkraSms;
    }

    public function initialize($data)
    {
        $smsServiceProviderName = $data['sms_provider_name'];
        
        switch ($smsServiceProviderName) {
            case 'tonkra':
                return $this->_tonkraSms->send($data);
            default:
                break;
        }
    }
}