<?php
use Laracasts\TestDummy\Factory;

/**
 * Unit testing for campaign:set-to-expired artisan command.
 *
 * @author Irianto Pratama <irianto@dominopos.com>
 */
class CampaignSetToExpiredCommandTest extends TestCase {

    public function setUp()
    {
        parent::setUp();

        // server Time
        $this->serverTime = $serverTime = DB::select('select UTC_TIMESTAMP() as now');
        // var_dump($serverTime);

        // test Time
        $this->test_time = $test_time = date('Y-m-d H:i:s', strtotime('+7 hour +25 minutes'));
        // var_dump($test_time);

        // campaign status
        $this->campaign_paused = $campaign_paused = Factory::create('campaign_paused');
        $this->campaign_expired = $campaign_expired = Factory::create('campaign_expired');

        // jakarta mall
        $this->timezone_jakarta = $timezone_jakarta = Factory::create('timezone_jakarta');
        $this->jakartaMall = $jakartaMall = Factory::create('Mall', ['timezone_id' => $timezone_jakarta->timezone_id]);
        $this->jakartaMallTime = $jakartaMallTime = DB::select("select CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '{$jakartaMall->timezone->timezone_name}') as jakartaMallTime");
        // var_dump($jakartaMallTime);

        // makassar mall
        $this->timezone_makassar = $timezone_makassar = Factory::create('timezone_makassar');
        $this->makassarMall = $makassarMall = Factory::create('Mall', ['timezone_id' => $timezone_makassar->timezone_id]);
        $this->makassarMallTime = $makassarMallTime = DB::select("select CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '{$makassarMall->timezone->timezone_name}') as makassarMallTime");
        // var_dump($makassarMallTime);

        $this->command = new CampaignSetToExpired();
    }

    // test the news query is run
    public function testNewsQueryRun() {
        $newsMallJM = Factory::create('News', [
            'mall_id' => $this->jakartaMall->merchant_id,
            'campaign_status_id' => $this->campaign_paused->campaign_status_id,
            'end_date' => $this->test_time,
            'object_type' => 'news']);

        $newsMallMM = Factory::create('News', [
            'mall_id' => $this->makassarMall->merchant_id,
            'campaign_status_id' => $this->campaign_paused->campaign_status_id,
            'end_date' => $this->test_time,
            'object_type' => 'news']);

        $getNews = News::with('campaign_status')
                    ->where('object_type', 'news')
                    ->get();

        foreach ($getNews as $news) {
            $this->assertSame('paused', $news->campaign_status->campaign_status_name);
        }

        $newsQuery = $this->command->getExpiredCampaignQuery('news');
        $dbUpdate  = DB::statement($newsQuery);

        $this->assertTrue($dbUpdate);

        $getUpdateNewsJM = News::with('campaign_status')
                            ->where('object_type', 'news')
                            ->where('news_id', $newsMallJM->news_id)
                            ->first();
        $getUpdateNewsMM = News::with('campaign_status')
                            ->where('object_type', 'news')
                            ->where('news_id', $newsMallMM->news_id)
                            ->first();

        $this->assertSame('paused', $getUpdateNewsJM->campaign_status->campaign_status_name);
        $this->assertSame('expired', $getUpdateNewsMM->campaign_status->campaign_status_name);

    }

    // test the promotions query is run
    public function testPromotionsQueryRun() {
        $promotionsMallJM = Factory::create('News', [
            'mall_id' => $this->jakartaMall->merchant_id,
            'campaign_status_id' => $this->campaign_paused->campaign_status_id,
            'end_date' => $this->test_time,
            'object_type' => 'promotion']);

        $promotionsMallMM = Factory::create('News', [
            'mall_id' => $this->makassarMall->merchant_id,
            'campaign_status_id' => $this->campaign_paused->campaign_status_id,
            'end_date' => $this->test_time,
            'object_type' => 'promotion']);

        $getPromotions = News::with('campaign_status')
                    ->where('object_type', 'promotion')
                    ->get();

        foreach ($getPromotions as $promotion) {
            $this->assertSame('paused', $promotion->campaign_status->campaign_status_name);
        }

        $promotionsQuery = $this->command->getExpiredCampaignQuery('promotions');
        $dbUpdate  = DB::statement($promotionsQuery);

        $this->assertTrue($dbUpdate);

        $getUpdatePromotionsJM = News::with('campaign_status')
                            ->where('object_type', 'promotion')
                            ->where('news_id', $promotionsMallJM->news_id)
                            ->first();
        $getUpdatePromotionsMM = News::with('campaign_status')
                            ->where('object_type', 'promotion')
                            ->where('news_id', $promotionsMallMM->news_id)
                            ->first();

        $this->assertSame('paused', $getUpdatePromotionsJM->campaign_status->campaign_status_name);
        $this->assertSame('expired', $getUpdatePromotionsMM->campaign_status->campaign_status_name);

    }

    // test the coupons query is run
    public function testCouponsQueryRun() {
        $couponsMallJM = Factory::create('Coupon', [
            'merchant_id' => $this->jakartaMall->merchant_id,
            'campaign_status_id' => $this->campaign_paused->campaign_status_id,
            'end_date' => $this->test_time]);
        $couponsMallMM = Factory::create('Coupon', [
            'merchant_id' => $this->makassarMall->merchant_id,
            'campaign_status_id' => $this->campaign_paused->campaign_status_id,
            'end_date' => $this->test_time]);

        $getCoupons = Coupon::with('campaign_status')
                    ->get();

        foreach ($getCoupons as $coupon) {
            $this->assertSame('paused', $coupon->campaign_status->campaign_status_name);
        }
        $couponsQuery = $this->command->getExpiredCampaignQuery('coupons');
        $dbUpdate  = DB::statement($couponsQuery);

        $this->assertTrue($dbUpdate);

        $getUpdateCouponsJM = Coupon::with('campaign_status')
                            ->where('promotion_id', $couponsMallJM->promotion_id)
                            ->first();
        $getUpdateCouponsMM = Coupon::with('campaign_status')
                            ->where('promotion_id', $couponsMallMM->promotion_id)
                            ->first();

        $this->assertSame('paused', $getUpdateCouponsJM->campaign_status->campaign_status_name);
        $this->assertSame('expired', $getUpdateCouponsMM->campaign_status->campaign_status_name);

    }

    // test the lucky draws query is run
    public function testLuckyDrawsQueryRun() {
        $luckyDrawsMallJM = Factory::create('LuckyDraw', [
            'mall_id' => $this->jakartaMall->merchant_id,
            'campaign_status_id' => $this->campaign_paused->campaign_status_id,
            'end_date' => $this->test_time]);

        $luckyDrawsMallMM = Factory::create('LuckyDraw', [
            'mall_id' => $this->makassarMall->merchant_id,
            'campaign_status_id' => $this->campaign_paused->campaign_status_id,
            'end_date' => $this->test_time]);

        $getLuckyDraws = LuckyDraw::with('campaign_status')
                                    ->get();

        foreach ($getLuckyDraws as $luckydraw) {
            $this->assertSame('paused', $luckydraw->campaign_status->campaign_status_name);
        }

        $luckydrawsQuery = $this->command->getExpiredCampaignQuery('lucky_draws');
        $dbUpdate  = DB::statement($luckydrawsQuery);

        $this->assertTrue($dbUpdate);

        $getUpdateLuckyDrawsJM = LuckyDraw::with('campaign_status')
                            ->where('lucky_draw_id', $luckyDrawsMallJM->lucky_draw_id)
                            ->first();
        $getUpdateLuckyDrawsMM = LuckyDraw::with('campaign_status')
                            ->where('lucky_draw_id', $luckyDrawsMallMM->lucky_draw_id)
                            ->first();

        $this->assertSame('paused', $getUpdateLuckyDrawsJM->campaign_status->campaign_status_name);
        $this->assertSame('expired', $getUpdateLuckyDrawsMM->campaign_status->campaign_status_name);

    }
}