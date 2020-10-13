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

            $purchase->confirmation_info = $this->getConfirmationInfo($purchase);

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

    /**
     * Information to be used on confirmation window popup.
     *
     * @return object
     */
    protected function getConfirmationInfo($purchase)
    {
        $confirmationInfo = new \stdclass();
        $payload = unserialize($purchase->notes);

        if (isset($payload['inquiry'])) {
            $payloadObj = json_decode($payload['inquiry']);

            if (isset($payloadObj->info)) {
                if (isset($payloadObj->info->user_info)) {
                    // append user_id
                    if (isset($payloadObj->info->user_info->user_id)) {
                        $confirmationInfo->user_id = $payloadObj->info->user_info->user_id;
                    }
                    // append server_id
                    if (isset($payloadObj->info->user_info->server_id) && $payloadObj->info->user_info->server_id != '1') {
                        $confirmationInfo->server_id = $payloadObj->info->user_info->server_id;
                    }
                    // append user_code
                    if (isset($payloadObj->info->user_info->user_code)) {
                        $confirmationInfo->user_code = $payloadObj->info->user_info->user_code;
                    }
                }

                if (isset($payloadObj->info->details)) {
                    // if the details is an array of object
                    if (is_array($payloadObj->info->details) && isset($payloadObj->info->details[0])) {
                        if (isset($payloadObj->info->details[0])) {
                            // append server_name
                            if (isset($payloadObj->info->details[0]->server_name)) {
                                if (! empty($payloadObj->info->details[0]->server_name)) {
                                    $confirmationInfo->server_name = $payloadObj->info->details[0]->server_name;
                                }
                            }
                            // append user name
                            if (isset($payloadObj->info->details[0]->username)) {
                                if (! empty($payloadObj->info->details[0]->username)) {
                                    $confirmationInfo->username = $payloadObj->info->details[0]->username;
                                }
                            } elseif (isset($payloadObj->info->details[0]->role_name)) {
                                if (! empty($payloadObj->info->details[0]->role_name)) {
                                    $confirmationInfo->username = $payloadObj->info->details[0]->role_name;
                                }
                            }
                        }
                    }

                    // if the details is an object
                    if (is_object($payloadObj->info->details)) {
                        // append username
                        if (isset($payloadObj->info->details->username)) {
                            $confirmationInfo->username = $payloadObj->info->details->username;
                        }
                        // append user_name
                        if (isset($payloadObj->info->details->user_name)) {
                            $confirmationInfo->user_name = $payloadObj->info->details->user_name;
                        }
                    }
                }
            }
        }

        return $confirmationInfo;
    }

    protected function buildUPointParams($purchase)
    {
        $providerProduct = $purchase->getProviderProduct();

        $purchaseNotes = unserialize($purchase->notes);
        $inquiry = json_decode($purchaseNotes['inquiry']);

        if (isset($providerProduct->provider_name)) {
            if ($providerProduct->provider_name === 'upoint-dtu') {

                if (isset($inquiry->info) && isset($inquiry->info->details)) {
                    if (is_array($inquiry->info->details) && isset($inquiry->info->details[0])) {
                        return [
                            'payment_info' => json_encode($inquiry->info->details[0])
                        ];
                    } else {
                        return [
                            'payment_info' => json_encode($inquiry->info->details)
                        ];
                    }
                }
            }
            else if ($providerProduct->provider_name === 'upoint-voucher') {
                return [
                    'upoint_trx_id' => $inquiry->trx_id,
                    'trx_id' => $purchase->payment_transaction_id,
                    'request_status' => $inquiry->status,
                ];
            }
        }
        return [];
    }
}
