<?php namespace Orbit\Controller\API\v1\Pub\Purchase\DigitalProduct;

use Log;
use Exception;
use Illuminate\Support\Facades\App;
use Orbit\Helper\DigitalProduct\Providers\PurchaseProviderInterface;

trait UPointHelper
{
    /**
     * Create a UPoint purchase request, then add its response (user detail) into
     * purchase object.
     */
    protected function createUPointPurchase(&$purchase, $digitalProduct, $providerProduct, $request)
    {
        if ($providerProduct->provider_name === 'upoint-dtu') {
            $this->createUPointDTUPurchase($purchase, $digitalProduct, $providerProduct, $request);
        }
        else if ($providerProduct->provider_name === 'upoint-voucher') {
            $this->createUPointVoucherPurchase($purchase, $digitalProduct, $providerProduct, $request);
        }
    }

    /**
     * Create DTU purchase.
     */
    protected function createUPointDTUPurchase(&$purchase, $digitalProduct, $providerProduct, $request)
    {
        Log::info("Initiating DTU purchase request to UPoint...");

        $purchaseParams = [
            'trx_id' => $purchase->payment_transaction_id,
            'product' => $this->getUPointProductCode($digitalProduct, $request),
            'item' => $providerProduct->code,
            'user_info' => $this->getUPointUserInfo($providerProduct, $request),
        ];

        $purchaseResponse = App::make(PurchaseProviderInterface::class, [
                'providerId' => $providerProduct->provider_name
            ])->purchase($purchaseParams);

        // Info should contain user information associated with game user_id,
        // e.g. game nickname and the server name.
        if ($purchaseResponse->isSuccess()) {

            if (empty($purchase->notes)) {
                $purchase->notes = serialize([
                    'inquiry' => $purchaseResponse->getData(),
                    'confirm' => '',
                ]);

                $purchase->save();
            }

            $purchase->upoint_info = $this->transformUPointPurchaseInfo(
                $purchaseResponse
            );

            Log::info("DTU Purchase created for trxID: {$purchase->payment_transaction_id}");
        }
        else {
            throw new Exception("DTU Purchase request failed!");
        }
    }

    /**
     * Create voucher purchase.
     */
    protected function createUPointVoucherPurchase(&$purchase, $digitalProduct, $providerProduct, $request)
    {
        Log::info("Initiating Voucher purchase request to UPoint...");

        $purchaseParams = [
            'trx_id' => $purchase->payment_transaction_id,
            'product' => $this->getUPointProductCode($digitalProduct, $request),
            'item' => $digitalProduct->code,
            'user_info' => $this->getUPointUserInfo($providerProduct, $request),
        ];

        $purchaseResponse = app(PurchaseProviderInterface::class)->purchase($purchaseParams);

        // Info should contain user information associated with game user_id,
        // e.g. game nickname and the server name.
        if ($purchaseResponse->isSuccess()) {
            $purchase->upoint_info = $this->transformUPointPurchaseInfo(
                $purchaseResponse->info
            );
        }
        else {
            throw new Exception("Voucher Purchase request failed!");
        }
    }

    /**
     * Incase we need custom logic.
     *
     * @return string
     */
    protected function getUPointUserInfo($providerProduct, $request)
    {
        $decodedUserInfo = json_decode($request->upoint_user_info);
        $userInfo = [];

        unset($decodedUserInfo->product_code);

        foreach($decodedUserInfo as $key => $value) {
            $userInfo[$key] = $value;
        }

        return json_encode($userInfo);
    }

    /**
     * @return string
     */
    protected function getUPointProductCode($digitalProduct, $request)
    {
        $decodedUserInfo = json_decode($request->upoint_user_info);

        return $decodedUserInfo->product_code ?: '';
    }

    /**
     * In-case we need custom logic once we get the real response
     * from 3rd party server.
     *
     * @return object
     */
    protected function transformUPointPurchaseInfo($response)
    {
        return $response->info;
    }

    /**
     * @return string json encoded payment info.
     */
    protected function getUPointPaymentInfo($purchase, $request)
    {
        return $request->payment_info;
    }
}
