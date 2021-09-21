<?php namespace Orbit\Helper\Midtrans\API;

use Config;
use Exception;
use Log;
use Midtrans\Config as MidtransConfig;
use Midtrans\Transaction;
use Orbit\Helper\Midtrans\API\Response\DirectRefundResponse;

/**
 * Midtrans Refund api wrapper for Orbit.
 *
 * @author Budi <budi@gotomalls.com>
 */
class Refund
{
    /**
     * HTTP Client
     */
    protected $client;

    /**
     * Configs
     */
    protected $config;

    public function __construct($config = [])
    {
        $this->config = ! empty($config) ? $config : Config::get('orbit.partners_api.midtrans');

        MidtransConfig::$serverKey = $this->config['server_key'];
        MidtransConfig::$isProduction = $this->config['is_production'];
    }

    public static function create($config = [])
    {
        return new static($config);
    }

    /**
     * Perform direct refund for given transaction.
     *
     * @see https://api-docs.midtrans.com/#direct-refund-transaction
     * @param  array $id trx id
     * @param  array $params refund params
     * @return DirectRefundResponse
     */
    public function direct($id, $params)
    {
        try {

            $response = Transaction::refundDirect($id, $params);

        } catch (Exception $e) {
            Log::info(sprintf('Midtrans refund Exception: %s:%s >> %s',
                $e->getFile(),
                $e->getLine(),
                $e->getMessage()
            ));

            Log::info('refund params: ' . serialize($params));

            $response = new \stdClass;
            $response->status_code = $e->getCode();
            $response->status_message = $e->getMessage();
        }

        return new DirectRefundResponse($response);
    }
}
