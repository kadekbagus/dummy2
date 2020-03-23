<?php namespace Orbit\Helper\Midtrans\API;

use Orbit\Helper\Midtrans\Client as MidtransClient;
use Orbit\Helper\Midtrans\ConfigSelector;
use Config;
use Cache;
use Log;
use Exception;

use Orbit\Helper\Midtrans\API\Response\TransactionCancelResponse;

use \Veritrans_Config;
use \Veritrans_Transaction;

/**
 * Get Campaign List from Sepulsa
 */
class TransactionCancel
{
    /**
     * HTTP Client
     */
    protected $client;

    /**
     * Configs
     */
    protected $config;

    public function __construct($config=[])
    {
        $this->config = ! empty($config) ? $config : Config::get('orbit.partners_api.midtrans');

        Veritrans_Config::$serverKey = $this->config['server_key'];
        Veritrans_Config::$isProduction = $this->config['is_production'];
    }

    public static function create($config=[])
    {
        return new static($config);
    }

    /**
     * Cancel transaction.
     *
     * @param  [type] $transactionId [description]
     * @return [type]                [description]
     */
    public function cancel($transactionId)
    {
        try {

            if (empty($transactionId)) {
                throw new Exception('TransactionId is empty.', 404);
            }

            $response = new \stdClass;
            $response->status_code = Veritrans_Transaction::cancel($transactionId);
            $response->status_message = "Transaction Canceled.";

        } catch (Exception $e) {
            Log::info('Midtrans::getStatus(): Exception, File: ' . $e->getFile() . ':' . $e->getLine() . ' >> ' . $e->getMessage());

            $response = new \stdClass;
            $response->status_code = $e->getCode();
            $response->status_message = $e->getMessage();
        }

        return new TransactionCancelResponse($response);
    }
}
