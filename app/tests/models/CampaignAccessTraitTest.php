<?php
use Laracasts\TestDummy\Factory;

/**
 * Unit testing for specify pmp portal access.
 *
 * @author Irianto Pratama <irianto@dominopos.com>
 */
class CampaignAccessTraitTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        // role
        $campaign_admin_role = Factory::create('Role', ['role_name' => 'campaign admin']);
        $campaign_owner_role = Factory::create('Role', ['role_name' => 'campaign owner']);

        // user
        $this->campaign_admin = $campaign_admin = Factory::create('User', ['user_role_id' => $campaign_admin_role->role_id]);
        $this->campaign_owner = $campaign_owner = Factory::create('User', ['user_role_id' => $campaign_owner_role->role_id]);
        $this->campaign_owner_b = $campaign_owner_b = Factory::create('User', ['user_role_id' => $campaign_owner_role->role_id]);

        // mall
        $mall = Factory::create('Mall');
        $mall_b = Factory::create('Mall');

        // news
        $newsMall = Factory::create('News', [
            'mall_id' => $mall->merchant_id,
            'object_type' => 'news']);

        $newsMall_2 = Factory::create('News', [
            'mall_id' => $mall->merchant_id,
            'object_type' => 'news']);

        $newsMall_b = Factory::create('News', [
            'mall_id' => $mall_b->merchant_id,
            'object_type' => 'news']);

        // promotions
        $promotionMall = Factory::create('News', [
            'mall_id' => $mall->merchant_id,
            'object_type' => 'promotion']);

        $promotionMall_b = Factory::create('News', [
            'mall_id' => $mall_b->merchant_id,
            'object_type' => 'promotion']);

        // coupons
        $couponsMall = Factory::create('Coupon', [
            'merchant_id' => $mall->merchant_id]);

        $couponsMall_b = Factory::create('Coupon', [
            'merchant_id' => $mall_b->merchant_id]);

        // user_campaign
        $user_campaign_news = Factory::create('user_campaign_news', [
                            'user_id' => $campaign_owner->user_id,
                            'campaign_id' =>  $newsMall->news_id
                        ]);

        $user_campaign_news_2 = Factory::create('user_campaign_news', [
                            'user_id' => $campaign_owner->user_id,
                            'campaign_id' =>  $newsMall_2->news_id
                        ]);

        $user_campaign_news_b = Factory::create('user_campaign_news', [
                            'user_id' => $campaign_owner_b->user_id,
                            'campaign_id' =>  $newsMall_b->news_id
                        ]);

        $user_campaign_promotion = Factory::create('user_campaign_promotion', [
                            'user_id' => $campaign_owner->user_id,
                            'campaign_id' =>  $promotionMall->news_id
                        ]);

        $user_campaign_promotion_b = Factory::create('user_campaign_promotion', [
                            'user_id' => $campaign_owner_b->user_id,
                            'campaign_id' =>  $promotionMall_b->news_id
                        ]);

        $user_campaign_coupon = Factory::create('user_campaign_coupon', [
                            'user_id' => $campaign_owner->user_id,
                            'campaign_id' =>  $couponsMall->promotion_id
                        ]);

        $user_campaign_coupon_b = Factory::create('user_campaign_coupon', [
                            'user_id' => $campaign_owner_b->user_id,
                            'campaign_id' =>  $couponsMall_b->promotion_id
                        ]);

    }

    public function testCampaignAdminAccess( )
    {
        $news = News::excludeDeleted()
                    ->where('object_type', 'news')
                    ->allowedForPMPUser($this->campaign_admin, 'news')
                    ->get();

        $this->assertEquals(3, $news->count());

        $promotion = News::excludeDeleted()
                    ->where('object_type', 'promotion')
                    ->allowedForPMPUser($this->campaign_admin, 'promotion')
                    ->get();

        $this->assertEquals(2, $promotion->count());

        $coupon = Coupon::excludeDeleted()
                    ->allowedForPMPUser($this->campaign_admin, 'coupon')
                    ->get();

        $this->assertEquals(2, $coupon->count());
    }

    public function testCampaignOwnerAccess( )
    {
        $news = News::excludeDeleted()
                    ->where('object_type', 'news')
                    ->allowedForPMPUser($this->campaign_owner, 'news')
                    ->get();

        $this->assertEquals(2, $news->count());

        $promotion = News::excludeDeleted()
                    ->where('object_type', 'promotion')
                    ->allowedForPMPUser($this->campaign_owner, 'promotion')
                    ->get();

        $this->assertEquals(1, $promotion->count());

        $coupon = Coupon::excludeDeleted()
                    ->allowedForPMPUser($this->campaign_owner, 'coupon')
                    ->get();

        $this->assertEquals(1, $coupon->count());
    }

    public function testCampaignOwnerBAccess( )
    {
        $news = News::excludeDeleted()
                    ->where('object_type', 'news')
                    ->allowedForPMPUser($this->campaign_owner_b, 'news')
                    ->get();

        $this->assertEquals(1, $news->count());

        $promotion = News::excludeDeleted()
                    ->where('object_type', 'promotion')
                    ->allowedForPMPUser($this->campaign_owner_b, 'promotion')
                    ->get();

        $this->assertEquals(1, $promotion->count());

        $coupon = Coupon::excludeDeleted()
                    ->allowedForPMPUser($this->campaign_owner_b, 'coupon')
                    ->get();

        $this->assertEquals(1, $coupon->count());
    }
}
