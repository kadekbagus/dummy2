<?php namespace Orbit\Helper\Midtrans\API\Response;

/**
 * Midtrans' Response.
 *
 * @author Budi <budi@dominopos.com>
 */
class Response {
    /**
     * For list of status codes please refer to:
     * https://api-docs.midtrans.com/#status-code
     */
    const STATUS_SUCCESS    = '200';
    const STATUS_PENDING    = '201';
    const STATUS_DENIED     = '202';
    const STATUS_EXPIRED    = '407';
    const STATUS_NOT_FOUND  = '404';
}
