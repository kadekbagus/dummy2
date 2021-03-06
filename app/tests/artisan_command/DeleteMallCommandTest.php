<?php
use Laracasts\TestDummy\Factory;
use \DestroyMall;

/**
 * Unit testing for delete mall artisan command.
 *
 */
class DeleteMallCommandTest extends TestCase {

    public function setUp()
    {
        parent::setUp();

        Config::set('orbit.activity.force.save', TRUE);        
    }

    public function prepareData($index)
    {
        // main tables
        $this->mall[$index] = $mall[$index] = Factory::create('Mall');
        // activities
        $this->activity[$index] = Factory::create('Activity', [
            'location_id' => $mall[$index]->merchant_id
        ]);
        // age_ranges
        $this->age_range[$index] = Factory::create('AgeRange', [
            'merchant_id' => $mall[$index]->merchant_id
        ]);
        // campaign_base_prices
        $this->campaign_base_price[$index] = Factory::create('CampaignBasePrice', [
            'merchant_id' => $mall[$index]->merchant_id
        ]);
        // campaign_base_prices
        $this->campaign_click[$index] = Factory::create('CampaignClicks', [
            'location_id' => $mall[$index]->merchant_id,
            'activity_id' => $this->activity[$index]->activity_id,
        ]);
        // campaign_daily_spendings
        $this->campaign_daily_spending[$index] = Factory::create('CampaignDailySpending', [
            'mall_id' => $mall[$index]->merchant_id
        ]);
        // campaign_page_views
        $this->campaign_page_view[$index] = Factory::create('CampaignPageView', [
            'location_id' => $mall[$index]->merchant_id
        ]);
        // campaign_popup_views
        $this->campaign_popup_view[$index] = Factory::create('CampaignPopupView', [
            'location_id' => $mall[$index]->merchant_id
        ]);
        // campaign_spendings
        $this->campaign_spending[$index] = Factory::create('CampaignSpendingCount', [
            'mall_id' => $mall[$index]->merchant_id
        ]);
        // connected_now
        $this->connected_now[$index] = Factory::create('ConnectedNow', [
            'merchant_id' => $mall[$index]->merchant_id
        ]);
        // connection_times
        $this->connection_time[$index] = Factory::create('ConnectionTime', [
            'location_id' => $mall[$index]->merchant_id
        ]);
        // inboxes
        $this->inbox[$index] = Factory::create('Inbox', [
            'merchant_id' => $mall[$index]->merchant_id
        ]);
        // keywords
        $this->keyword[$index] = Factory::create('Keyword', [
            'merchant_id' => $mall[$index]->merchant_id
        ]);
        // keyword_object
        $this->keyword_object[$index] = Factory::create('KeywordObject', [
            'keyword_id' => $this->keyword[$index]->keyword_id,
            'object_type' => 'tenant',
            'object_id' => $mall[$index]->merchant_id
        ]);
        // lucky_draws
        $this->lucky_draw[$index] = Factory::create('LuckyDraw', [
            'mall_id' => $mall[$index]->merchant_id
        ]);
        // lucky_draw_receipts
        $this->lucky_draw_receipt[$index] = Factory::create('LuckyDrawReceipt', [
            'mall_id' => $mall[$index]->merchant_id
        ]);
        // lucky_draw_announcements
        $this->lucky_draw_announcement[$index] = Factory::create('LuckyDrawAnnouncement', [
            'lucky_draw_id' => $this->lucky_draw[$index]->lucky_draw_id
        ]);
        // lucky_draw_numbers
        $this->lucky_draw_number[$index] = Factory::create('LuckyDrawNumber', [
            'lucky_draw_id' => $this->lucky_draw[$index]->lucky_draw_id
        ]);
        // lucky_draw_prize
        $this->lucky_draw_prize[$index] = Factory::create('LuckyDrawPrize', [
            'lucky_draw_id' => $this->lucky_draw[$index]->lucky_draw_id
        ]);
        // lucky_draw_translation
        $this->lucky_draw_translations[$index] = Factory::create('LuckyDrawTranslation', [
            'lucky_draw_id' => $this->lucky_draw[$index]->lucky_draw_id
        ]);
        // lucky_draw_winners
        $this->lucky_draw_winner[$index] = Factory::create('LuckyDrawWinner', [
            'lucky_draw_id' => $this->lucky_draw[$index]->lucky_draw_id,
            'lucky_draw_number_id' => $this->lucky_draw_number[$index]->lucky_draw_numbers_id,
            'lucky_draw_prize_id' => $this->lucky_draw_prize[$index]->lucky_draw_prize_id
        ]);
        // lucky_draw_announcement_translation
        $this->lucky_draw_announcement_translations[$index] = Factory::create('LuckyDrawAnnouncementTranslation', [
            'lucky_draw_announcement_id' => $this->lucky_draw_announcement[$index]->lucky_draw_announcement_id
        ]);
        // membership
        $this->membership[$index] = Factory::create('Membership', [
            'merchant_id' => $mall[$index]->merchant_id
        ]);
        // membership_number
        $this->membership_number[$index] = Factory::create('MembershipNumber', [
            'membership_id' => $this->membership[$index]->membership_id,
        ]);
        // merchant_geofences
        $this->merchant_geofence[$index] = Factory::create('MerchantGeofence', [
            'merchant_id' => $mall[$index]->merchant_id
        ]);
        // merchant_languages
        $this->merchant_language[$index] = Factory::create('MerchantLanguage', [
            'merchant_id' => $mall[$index]->merchant_id
        ]);
        // merchant_translation
        $this->merchant_translations[$index] = Factory::create('MerchantTranslation', [
            'merchant_language_id' => $this->merchant_language[$index]->merchant_language_id
        ]);
        // setting_translation
        $this->setting_translations[$index] = Factory::create('SettingTranslation', [
            'merchant_language_id' => $this->merchant_language[$index]->merchant_language_id
        ]);
        // widget_translations
        $this->widget_translations[$index] = Factory::create('WidgetTranslation', [
            'merchant_language_id' => $this->merchant_language[$index]->merchant_language_id
        ]);
        // merchant_page_views
        $this->merchant_page_views[$index] = Factory::create('MerchantPageView', [
            'location_id' => $mall[$index]->merchant_id
        ]);
        // merchant_social_media
        $this->merchant_social_media[$index] = Factory::create('MerchantSocialMedia', [
            'merchant_id' => $mall[$index]->merchant_id
        ]);
        // news_merchants
        $this->news_merchant[$index] = Factory::create('NewsMerchant', [
            'merchant_id' => $mall[$index]->merchant_id,
            'object_type' => 'mall'
        ]);
        // objects
        $this->objects[$index] = Factory::create('Object', [
            'merchant_id' => $mall[$index]->merchant_id
        ]);
        // promotion_retailer
        $this->promotion_retailer[$index] = Factory::create('coupon_link_tenant', [
            'retailer_id' => $mall[$index]->merchant_id,
            'object_type' => 'mall'
        ]);
        // promotion_retailer_redeem
        $this->promotion_retailer_redeem[$index] = Factory::create('coupon_link_redeem_tenant', [
            'retailer_id' => $mall[$index]->merchant_id,
            'object_type' => 'mall'
        ]);
        // user_merchant
        $this->user_merchant[$index] = Factory::create('UserMerchant', [
            'merchant_id' => $mall[$index]->merchant_id,
            'object_type' => 'mall'
        ]);
        // user_signin
        $this->user_signin[$index] = Factory::create('UserSignin', [
            'location_id' => $mall[$index]->merchant_id
        ]);
        // user_verification_number
        $this->user_verification_number[$index] = Factory::create('UserVerificationNumber', [
            'merchant_id' => $mall[$index]->merchant_id
        ]);
        // viewed_item_user
        $this->viewed_item_user[$index] = Factory::create('ViewItemUser', [
            'mall_id' => $mall[$index]->merchant_id
        ]);
        // widget_clicks
        $this->widget_click[$index] = Factory::create('WidgetClick', [
            'location_id' => $mall[$index]->merchant_id
        ]);
        // widgets
        $this->widget[$index] = Factory::create('Widget', [
            'merchant_id' => $mall[$index]->merchant_id
        ]);
        // widget_retailer
        $this->widget_retailer[$index] = Factory::create('WidgetRetailer', [
            'widget_id' => $this->widget[$index]->widget_id,
            'retailer_id' => $mall[$index]->merchant_id
        ]);
        // setting
        $this->setting[$index] = Factory::create('Setting', [
            'setting_name' => 'enable_coupon_widget',
            'object_id' => $mall[$index]->merchant_id,
        ]);

        //tenant 
        $this->retailer[$index] = Factory::create('Retailer', [
            'parent_id' => $mall[$index]->merchant_id
        ]);

        //category_merchant
        $this->category_merchant[$index] = Factory::create('CategoryMerchant', [
            'merchant_id' => $this->retailer[$index]->merchant_id
        ]);

        // merchant_social_media (tenant)
        $this->merchant_social_media_tenant[$index] = Factory::create('MerchantSocialMedia', [
            'merchant_id' => $this->retailer[$index]->merchant_id
        ]);

        // merchant_translation
        $this->merchant_translations_tenant[$index] = Factory::create('MerchantTranslation', [
            'merchant_id' => $this->retailer[$index]->merchant_id
        ]);

        // news_merchants (tenant)
        $this->news_merchant_tenant[$index] = Factory::create('NewsMerchant', [
            'merchant_id' => $this->retailer[$index]->merchant_id,
            'object_type' => 'retailer'
        ]);

        // promotion_retailer
        $this->promotion_retailer_tenant[$index] = Factory::create('coupon_link_tenant', [
            'retailer_id' => $this->retailer[$index]->merchant_id,
            'object_type' => 'tenant'
        ]);

        // promotion_retailer_redeem
        $this->promotion_retailer_redeem_tenant[$index] = Factory::create('coupon_link_redeem_tenant', [
            'retailer_id' => $this->retailer[$index]->merchant_id,
            'object_type' => 'tenant'
        ]);

        // retailer_tenant
        $this->retailer_tenant[$index] = Factory::create('RetailerTenant', [
            'retailer_id' => $this->retailer[$index]->merchant_id
        ]);

        // user_merchant_tenant
        $this->user_merchant_tenant[$index] = Factory::create('UserMerchant', [
            'merchant_id' => $this->retailer[$index]->merchant_id,
            'object_type' => 'tenant'
        ]);
    }

    public function testDeleteMallOK()
    {
        $indexmall = 'mall1';
        $indexmall_2 = 'mall2';
        $this->prepareData($indexmall);
        $this->prepareData($indexmall_2);
        $cmd = new DestroyMall($this->mall[$indexmall]->merchant_id, TRUE, 'yes');
        $cmd->fire();

        // rage assertion
        $this->assertNull(\Mall::where('merchant_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\Activity::where('location_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\AgeRange::where('merchant_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\CampaignBasePrice::where('merchant_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\CampaignClicks::where('location_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\CampaignDailySpending::where('mall_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\CampaignPageView::where('location_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\CampaignPopupView::where('location_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\CampaignSpendingCount::where('mall_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\ConnectedNow::where('merchant_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\ConnectionTime::where('location_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\Inbox::where('merchant_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\Keyword::where('merchant_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\KeywordObject::where('object_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\LuckyDrawReceipt::where('mall_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\LuckyDraw::where('mall_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\LuckyDrawAnnouncement::where('lucky_draw_id', $this->lucky_draw[$indexmall]->lucky_draw_id)->first());
        $this->assertNull(\LuckyDrawNumber::where('lucky_draw_id', $this->lucky_draw[$indexmall]->lucky_draw_id)->first());
        $this->assertNull(\LuckyDrawPrize::where('lucky_draw_id', $this->lucky_draw[$indexmall]->lucky_draw_id)->first());
        $this->assertNull(\LuckyDrawTranslation::where('lucky_draw_id', $this->lucky_draw[$indexmall]->lucky_draw_id)->first());
        $this->assertNull(\LuckyDrawWinner::where('lucky_draw_id', $this->lucky_draw[$indexmall]->lucky_draw_id)->first());
        $this->assertNull(\LuckyDrawAnnouncementTranslation::where('lucky_draw_announcement_id', $this->lucky_draw_announcement[$indexmall]->lucky_draw_announcement_id)->first());
        $this->assertNull(\Membership::where('merchant_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\MembershipNumber::where('membership_id', $this->membership[$indexmall]->membership_id)->first());
        $this->assertNull(\MerchantGeofence::where('merchant_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\MerchantLanguage::where('merchant_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\MerchantTranslation::where('merchant_language_id', $this->merchant_language[$indexmall]->merchant_language_id)->first());
        $this->assertNull(\SettingTranslation::where('merchant_language_id', $this->merchant_language[$indexmall]->merchant_language_id)->first());
        $this->assertNull(\WidgetTranslation::where('merchant_language_id', $this->merchant_language[$indexmall]->merchant_language_id)->first());
        $this->assertNull(\MerchantPageView::where('location_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\MerchantSocialMedia::where('merchant_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\NewsMerchant::where('merchant_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\Object::where('merchant_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\CouponRetailer::where('retailer_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\CouponRetailerRedeem::where('retailer_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\UserMerchant::where('merchant_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\UserSignin::where('location_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\UserVerificationNumber::where('merchant_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\ViewItemUser::where('mall_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\WidgetClick::where('location_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\Widget::where('merchant_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\WidgetRetailer::where('widget_id', $this->widget[$indexmall]->widget_id)->first());
        $this->assertNull(\Setting::where('object_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\Retailer::where('parent_id', $this->mall[$indexmall]->merchant_id)->first());
        $this->assertNull(\CategoryMerchant::where('merchant_id', $this->retailer[$indexmall]->merchant_id)->first());
        $this->assertNull(\MerchantSocialMedia::where('merchant_id', $this->retailer[$indexmall]->merchant_id)->first());
        $this->assertNull(\MerchantTranslation::where('merchant_id', $this->retailer[$indexmall]->merchant_id)->first());
        $this->assertNull(\NewsMerchant::where('merchant_id', $this->retailer[$indexmall]->merchant_id)->first());
        $this->assertNull(\CouponRetailer::where('retailer_id', $this->retailer[$indexmall]->merchant_id)->first());
        $this->assertNull(\CouponRetailerRedeem::where('retailer_id', $this->retailer[$indexmall]->merchant_id)->first());
        $this->assertNull(\RetailerTenant::where('retailer_id', $this->retailer[$indexmall]->merchant_id)->first());
        $this->assertNull(\UserMerchant::where('merchant_id', $this->retailer[$indexmall]->merchant_id)->first());

        // make sure not delete the other mall
        $this->AssertNotNull(\Mall::where('merchant_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\Activity::where('location_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\AgeRange::where('merchant_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\CampaignBasePrice::where('merchant_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\CampaignClicks::where('location_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\CampaignDailySpending::where('mall_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\CampaignPageView::where('location_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\CampaignPopupView::where('location_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\CampaignSpendingCount::where('mall_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\ConnectedNow::where('merchant_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\ConnectionTime::where('location_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\Inbox::where('merchant_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\Keyword::where('merchant_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\KeywordObject::where('object_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\LuckyDrawReceipt::where('mall_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\LuckyDraw::where('mall_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\LuckyDrawAnnouncement::where('lucky_draw_id', $this->lucky_draw[$indexmall_2]->lucky_draw_id)->first());
        $this->AssertNotNull(\LuckyDrawNumber::where('lucky_draw_id', $this->lucky_draw[$indexmall_2]->lucky_draw_id)->first());
        $this->AssertNotNull(\LuckyDrawPrize::where('lucky_draw_id', $this->lucky_draw[$indexmall_2]->lucky_draw_id)->first());
        $this->AssertNotNull(\LuckyDrawTranslation::where('lucky_draw_id', $this->lucky_draw[$indexmall_2]->lucky_draw_id)->first());
        $this->AssertNotNull(\LuckyDrawWinner::where('lucky_draw_id', $this->lucky_draw[$indexmall_2]->lucky_draw_id)->first());
        $this->AssertNotNull(\LuckyDrawAnnouncementTranslation::where('lucky_draw_announcement_id', $this->lucky_draw_announcement[$indexmall_2]->lucky_draw_announcement_id)->first());
        $this->AssertNotNull(\Membership::where('merchant_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\MembershipNumber::where('membership_id', $this->membership[$indexmall_2]->membership_id)->first());
        $this->AssertNotNull(\MerchantGeofence::where('merchant_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\MerchantLanguage::where('merchant_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\MerchantTranslation::where('merchant_language_id', $this->merchant_language[$indexmall_2]->merchant_language_id)->first());
        $this->AssertNotNull(\SettingTranslation::where('merchant_language_id', $this->merchant_language[$indexmall_2]->merchant_language_id)->first());
        $this->AssertNotNull(\WidgetTranslation::where('merchant_language_id', $this->merchant_language[$indexmall_2]->merchant_language_id)->first());
        $this->AssertNotNull(\MerchantPageView::where('location_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\MerchantSocialMedia::where('merchant_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\NewsMerchant::where('merchant_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\Object::where('merchant_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\CouponRetailer::where('retailer_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\CouponRetailerRedeem::where('retailer_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\UserMerchant::where('merchant_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\UserSignin::where('location_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\UserVerificationNumber::where('merchant_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\ViewItemUser::where('mall_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\WidgetClick::where('location_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\Widget::where('merchant_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\WidgetRetailer::where('widget_id', $this->widget[$indexmall_2]->widget_id)->first());
        $this->AssertNotNull(\Setting::where('object_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\Retailer::where('parent_id', $this->mall[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\CategoryMerchant::where('merchant_id', $this->retailer[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\MerchantSocialMedia::where('merchant_id', $this->retailer[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\MerchantTranslation::where('merchant_id', $this->retailer[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\NewsMerchant::where('merchant_id', $this->retailer[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\CouponRetailer::where('retailer_id', $this->retailer[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\CouponRetailerRedeem::where('retailer_id', $this->retailer[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\RetailerTenant::where('retailer_id', $this->retailer[$indexmall_2]->merchant_id)->first());
        $this->AssertNotNull(\UserMerchant::where('merchant_id', $this->retailer[$indexmall_2]->merchant_id)->first());
    }
}
