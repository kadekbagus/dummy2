<?php

namespace Orbit\Helper\MCash\API\PBBTaxBill;

use Orbit\Helper\MCash\API\PBBTaxBill\Response\PayResponse;

/**
 * Pay implementation for pbb tax.
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
          "message" => "Payment success. PDAM013-031120022796 SLAMET RAHMAT HARSANTO,ST. Amount: Rp.39200, admin: Rp.1600, total: Rp.40800",
          "created_at" => "0001-01-01T00:00:00Z",
          "inquiry_id" => 20248409,
          "partner_trxid" => "test123",
          "amount" => 39200,
          "total" => 40800,
          "pending" => 0,
          "data" => (object) [
            "customer_number" => "031120022796",
            "customer_name" => "SLAMET RAHMAT HARSANTO,ST",
            "admin_fee" => 1600,
            "amount" => 39200,
            "period" => 1,
            "period_name" => "NOV21",
            "meter_start" => 25,
            "meter_end" => 20,
            "usage" => 5,
            "penalty" => 0,
            "receipt" => (object) [
              "header" => "TAGIHAN PDAM DENPASAR",
              "footer" => null,
              "fields" => (object) [
                "amount" => "Tot.Tagihan",
                "customer_name" => "Nama",
                "penalty" => "Denda",
                "period_name" => "Rek Bulan",
                "usage" => "Pemakaian"
              ],
              "info" => "No.Sambungan: 031120022796|Nama        : SLAMET RAHMAT HARSANTO,ST|Rek Bulan   : NOV21|Pemakaian   : 20-25=5|Denda       :Rp.           0|Tot Tagihan :Rp.      39.200|"
            ]
          ],
          "balance" => 34153493,
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
