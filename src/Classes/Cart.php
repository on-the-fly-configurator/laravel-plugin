<?php

namespace OnTheFlyConfigurator\LaravelPlugin\Classes;

use Illuminate\Support\Facades\Http;

class Cart
{
    protected $response;
    protected $subdomain;
    protected $api_key;

    public function getOrderItems($token = false)
    {
        if(!$token) {
            return false;
        }

        $this->getSettings();
        $this->getItemsFromOtf($token);

        if(!$this->canGetItems()) {
            return false;
        }

        return $this->getItemsFromResponse();
    }

    public function getSettings()
    {
        $this->subdomain = config('ontheflyconfigurator.subdomain');
        $this->api_key = config('ontheflyconfigurator.api_key');
    }


    public function getItemsFromOtf($otfToken)
    {
        $this->response = Http::withHeaders([
            'Xco-Api-Key' => $this->api_key
        ])
            ->acceptJson()
            ->get('https://api.ontheflyconfigurator.com/api/' . $this->subdomain . '/quotes/' . $otfToken, [
                'token' => 'true'
            ]);
    }

    public function canGetItems()
    {
        if(!$this->response->successful() || !array_key_exists('data', $this->response->json())) {
            return false;
        }

        return true;
    }

    public function getItemsFromResponse()
    {
        return $this->response->json()['data']['items'];
    }
}
