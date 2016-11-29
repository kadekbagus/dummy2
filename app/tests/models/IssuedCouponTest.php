<?php

use Laracasts\TestDummy\Factory;
use \IssuedCoupon;
use Illuminate\Database\QueryException;

class IssuedCouponTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->numberOfCoupon = 9000;
    }

    public function tearDown()
    {
        $this->useTruncate = false;
        parent::tearDown();
    }

    public function testBulkIssue() {
        $promotionId = 'XXXXXXXX';
        $couponValidityDate = date('Y-m-d H:i:s', strtotime('+7 days'));
        $startingNumber = 10000;

        $couponCodes = $this->generateCouponCode($startingNumber, $this->numberOfCoupon);

        DB::beginTransaction();
        IssuedCoupon::bulkIssue($couponCodes, $promotionId, $couponValidityDate);
        DB::commit();

        $issuedCoupons = IssuedCoupon::where('promotion_id', $promotionId)->get();
        $this->assertSame((int) $this->numberOfCoupon, count($issuedCoupons));
    }

    public function testBulkIssueWithDupe() {
        $promotionId = 'XXXXXXXX';
        $couponValidityDate = date('Y-m-d H:i:s', strtotime('+7 days'));
        // make dupe staring from 10100
        $startingNumber = 10100;
        $numberOfCoupon = 100;
        $queryError = FALSE;

        $couponCodes = $this->generateCouponCode($startingNumber, $numberOfCoupon);

        DB::beginTransaction();
        try {
            IssuedCoupon::bulkIssue($couponCodes, $promotionId, $couponValidityDate);
            DB::commit();
        } catch (Illuminate\Database\QueryException $e) {
            DB::rollBack();
            $queryError = TRUE;
        }

        $issuedCoupons = IssuedCoupon::where('promotion_id', $promotionId)->get();

        $this->assertTrue($queryError);
        // make sure the count is the same as the first test
        $this->assertSame((int) $this->numberOfCoupon, count($issuedCoupons));
        $this->useTruncate = true;
        parent::tearDown();
    }

    protected function generateCouponCode($startingNumber, $numberOfCoupon) {
        $couponCodes = [];
        for ($i = 0; $i < $numberOfCoupon; $i++) {
            $couponCodes[] = $startingNumber + $i;
        }

        return $couponCodes;
    }
}
