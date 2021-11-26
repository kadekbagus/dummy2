<?php

namespace Orbit\Helper\MCash\API\ElectricityBill;

use Orbit\Helper\MCash\API\ElectricityBill\Response\PayResponse;

/**
 * Pay implementation for electricity bill.
 *
 * @author Budi <budi@gotomalls.com>
 */
trait Pay
{
    protected function payResponse($response)
    {
        return new PayResponse($response);
    }

    public function pay($params = [])
    {
        try {
            if (empty($params['product'])) {
                throw new \Exception("Product code is required", 1);
            }
            if (empty($params['customer'])) {
                throw new \Exception("Customer phone number is required", 1);
            }

            $requestParams = [
                'command' => $this->payCommand,
                'product' => $params['product'],
                'customer' => $params['customer'],
                'partner_trxid' => $params['partnerTrxId'],
            ];

            $this->initMockResponse('pay');
            if (! empty($this->mockData)) {
                return $this->payResponse($this->mockData);
            }

            $response = $this->client
                ->setJsonBody($requestParams)
                ->setEndpoint($this->endpoint)
                ->request('POST');

        } catch (OrbitCustomException $e) {
            $response = (object) [
                'status' => $e->getCode(),
                'message' => $e->getMessage(),
                'data' => null,
            ];
        } catch (\Exception $e) {
            $response = $e->getMessage();
        }

        return $this->payResponse($response);
    }

    protected function mockPaySuccessResponse()
    {
        return $this->mockResponse([
            "status" => 0,
            "message" => "Payment success. PLN-551000490568 SUGITO BA. Amount: Rp.73819, admin: Rp.1000, total: Rp.74819",
            "created_at" => "0001-01-01T00:00:00Z",
            "inquiry_id" => 20210027,
            "amount" => 73819,
            "total" => 74819,
            "pending" => 0,
            "data" => (object) [
                "customer_name" => "SUGITO BA",
                "admin_fee" => 1000,
                "amount" => 73819,
                "period" => 1,
                "billing_id" => "551000490568",
                "receipt" => (object) [
                    "header" => "",
                    "footer" => "<br>",
                    "info" => "IDPEL: 551000490568|NAMA: SUGITO BA|JML BLN TAG: 01|BL/TH: Nov21|JML TAG PLN: 76.319|"
                ],
            ],
            "balance" => 36077242,
        ]);
    }

    protected function mockPayFailedResponse()
    {
        return $this->mockResponse([
            "status" => 611,
            "message" => "[ TAGIHAN SUDAH TERBAYAR ]",
            "created_at" => "0001-01-01T00:00:00Z",
            "inquiry_id" => 20303407,
            "pending" => 0
        ]);
    }
}
