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
        $response = $this->getItemsFromOtf($token);

        if($response->successful() && array_key_exists('data', $response->json())) {

            $items = $this->getItemsFromResponse($response);

            $otfOrders = [];

            foreach($items as $item) {

                $item['price'] = $this->setPriceForCustomiseModel($item);

                $product = (new Product)->getItemBySku($item['external_id']);

                if(!$product) {
                    $this->handleError($request, $item['external_id']);
                    return false;
                }
                $data = $this->getVariants($item);

                if(!is_array($data)) {

                    $this->handleError($request, $data);

                    return false;
                }

                $otfOrders[] = [
                    'product_id' => $product->id,
                    'price'      => $data['price'],
                    'variants'   => $data['variants']
                ];
            }

            $request->session()->put('otf', $otfOrders);
        }
    }


    public function getSettings(){
        $this->subdomain = config('ontheflyconfigurator.subdomain');
        $this->api_key = config('ontheflyconfigurator.api_key');
    }


    public function getItemsFromOtf($otfToken)
    {
        dd($this->subdomain);
        return Http::withHeaders([
            'Xco-Api-Key' => $this->settings->api_key
        ])
            ->acceptJson()
            ->get('https://api.ontheflyconfigurator.com/api/demo/quotes/' . $otfToken, [
                'token' => 'true'
            ]);
    }

    public function handleError($request, $data)
    {

        $request->session()->put('otf', []);
        $message = $data . ' could not be found. The otf order has been voided.';

        $request->session()->flash('unavailable-items', $message);

        Log::warning($message);
    }

    public function getOrdersFromSession($request)
    {
        $data['otf_orders'] = $request->session()->get('otf') ?? [];
        $data['orders'] = $request->session()->get('order') ?? [];

        return $data;
    }


    public function setPriceForCustomiseModel($item)
    {
        if($item['model'] == 'App\\Models\\Customise') {
            $item['price'] = 0.00;
        }

        return $item['price'];
    }

    public function getItemsFromResponse($response)
    {
        return $response->json()['data']['items'];
    }

    public function getVariants($item)
    {
        $data['variants'] = [];

        foreach($item['variants'] as $variant) {

            $model = (new Variant)->whereSku($variant['external_id'])->first();

            if(!$model) {
                return $variant['external_id'];
            }

            $data['variants'][] = [
                'variant_id' => $model->id,
                'price'      => $this->getVariantPrice($variant)
            ];

            $data['price'] = $item['price'] = $this->getTotalProductPrice($item, $variant);
        }

        return $data;
    }

    public function getVariantPrice($variant)
    {
        return $variant['overwrite_price'] != "0.00" ? $variant['overwrite_price'] : $variant['price'];
    }

    public function getTotalProductPrice($item, $variant)
    {
        if($item['model'] != 'App\\Models\\Customise') {
            return $item['price'] + $variant['overwrite_price'];
        }

        return $item['price'] + ($variant['overwrite_price'] != "0.00" ? $variant['overwrite_price'] : $variant['price']);
    }

    public function getCartItemsByOrder($order, $otfOrder, $productIds, $variantIds)
    {
        $data = [
            'products' => [],
            'variants' => []
        ];

        if($order || $otfOrder) {
            $data['products'] = Product::whereIn('id', $productIds)->get();
            $data['variants'] = Variant::whereIn('id', $variantIds)->get();
        }

        return $data;
    }

    public function getProductAndVariantIds($order, $otfOrders)
    {

        $data = [
            'products' => [],
            'variants' => []
        ];

        foreach($otfOrders as $otfOrder) {
            $data['products'][] = $otfOrder['product_id'];
            foreach($otfOrder['variants'] as $variant) {
                $data['variants'][] = $variant['variant_id'];
            }
        }

        foreach($order as $item) {
            $data['products'][] = $item['product_id'];
            foreach($item['variants'] as $variant) {
                $data['variants'][] = $variant;
            }
        }

        return $data;
    }
}
