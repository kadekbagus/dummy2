<?php namespace Orbit\Controller\API\v1\Pub\Purchase\DigitalProduct;

use Log;
use Illuminate\Support\Facades\App;
use Orbit\Helper\DigitalProduct\Providers\PurchaseProviderInterface;
use Orbit\Helper\Exception\OrbitCustomException;

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
        $responseData = json_decode($purchaseResponse->getData());

        if (null !== $responseData
            && (isset($responseData->status)
            && 100 === (int) $responseData->status)
        ) {
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
            $customData = new \stdclass();
            $customData->source = 'upoint';

            if (isset($responseData->status_msg)) {
                $customData->status = $responseData->status;
                $customData->message = $responseData->status_msg;
                Log::info("DTU Purchase failed: " . $responseData->status_msg);

                throw new OrbitCustomException(sprintf('Purchase request failed! %s', $responseData->status_msg), 1, $customData);
            } else {
                $customData->status = $responseData->status;
                $customData->message = $responseData->status_msg;

                throw new OrbitCustomException('Purchase request failed!', 1, $customData);
            }
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
            'item' => $providerProduct->code,
            'user_info' => $this->getUPointUserInfo($providerProduct, $request),
            'timestamp' => time(),
        ];

        $purchaseResponse = App::make(PurchaseProviderInterface::class, [
                'providerId' => $providerProduct->provider_name,
            ])->purchase($purchaseParams);

        // Info should contain user information associated with game user_id,
        // e.g. game nickname and the server name.
        $responseData = json_decode($purchaseResponse->getData());

        if (null !== $responseData
            && (isset($responseData->status)
            && 1 === (int) $responseData->status)
        ) {
            if (empty($purchase->notes)) {
                $purchase->notes = serialize([
                    'inquiry' => $purchaseResponse->getData(),
                    'confirm' => '',
                ]);

                $purchase->save();
            }

            Log::info("Voucher Purchase created for trxID: {$purchase->payment_transaction_id}");
        }
        else {
            $customData = new \stdclass();
            $customData->source = 'upoint';
            throw new OrbitCustomException('Voucher Purchase request failed!', 1, $customData);
        }
    }

    /**
     * Incase we need custom logic.
     *
     * @return string
     */
    protected function getUPointUserInfo($providerProduct, $request)
    {
        $decodedUserInfo = json_decode($request->upoint_user_info, true);

        if (isset($decodedUserInfo['product_code'])) {
            unset($decodedUserInfo['product_code']);
        }

        return json_encode($decodedUserInfo);
    }

    /**
     * @return string
     */
    protected function getUPointProductCode($digitalProduct, $request)
    {
        $decodedUserInfo = json_decode($request->upoint_user_info);

        if (! isset($decodedUserInfo->product_code)) {
            $customData = new \stdclass();
            $customData->source = 'upoint';
            throw new OrbitCustomException('Missing Product Code.', 1, $customData);
        }

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

    protected function buildUPointParams($purchase)
    {
        $purchaseNotes = unserialize($purchase->notes);
        $inquiry = json_decode($purchaseNotes['inquiry']);

        if ($purchase->forUPoint('dtu')) {
            if (isset($inquiry->info) && isset($inquiry->info->details)) {
                return [
                    'payment_info' => json_encode($inquiry->info->details)
                ];
            }
        }
        else if ($purchase->forUPoint('voucher')) {
            return [
                'upoint_trx_id' => $inquiry->trx_id,
                'trx_id' => $purchase->payment_transaction_id,
                'request_status' => $inquiry->status,
            ];
        }

        return [];
    }
}
