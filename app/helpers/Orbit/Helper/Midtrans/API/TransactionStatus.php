<?php namespace Orbit\Helper\Midtrans\API;

use Log;
use Config;
use Exception;
use Midtrans\Config as MidtransConfig;
use Midtrans\Transaction;
use Orbit\Helper\Midtrans\API\Response\TransactionStatusResponse;

/**
 * Get Campaign List from Sepulsa
 */
class TransactionStatus
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

        MidtransConfig::$serverKey = $this->config['server_key'];
        MidtransConfig::$isProduction = $this->config['is_production'];
    }

    public static function create($config=[])
    {
        return new static($config);
    }

    /**
     * Get transaction status based on given transactionId.
     *
     * @param  [type] $transactionId [description]
     * @return [type]                [description]
     */
    public function getStatus($transactionId)
    {
        try {

            if (empty($transactionId)) {
                throw new Exception('Midtrans::getStatus(): TransactionId is empty.', 404);
            }

            $response = Transaction::status($transactionId);

        } catch (Exception $e) {
            Log::info('Midtrans::getStatus(): Exception, File: ' . $e->getFile() . ':' . $e->getLine() . ' >> ' . $e->getMessage());

            $response = new \stdClass;
            $response->status_code = $e->getCode();
            $response->status_message = $e->getMessage();
        }

        return new TransactionStatusResponse($response);
    }
}
