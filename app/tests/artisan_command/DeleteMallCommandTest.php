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

        // main tables
        $this->mall = $mall = Factory::create('Mall');
        // activities
        $this->activity = Factory::create('Activity', [
            'location_id' => $mall->merchant_id
        ]);
        // age_ranges
        $this->age_range = Factory::create('AgeRange', [
            'merchant_id' => $mall->merchant_id
        ]);
        // campaign_base_prices
        $this->campaign_base_price = Factory::create('CampaignBasePrice', [
            'merchant_id' => $mall->merchant_id
        ]);
        // campaign_base_prices
        $this->campaign_click = Factory::create('CampaignClicks', [
            'location_id' => $mall->merchant_id,
            'activity_id' => $this->activity->activity_id,
        ]);
        // campaign_daily_spendings
        $this->campaign_daily_spending = Factory::create('CampaignDailySpending', [
            'mall_id' => $mall->merchant_id
        ]);
        // campaign_page_views
        $this->campaign_page_view = Factory::create('CampaignPageView', [
            'location_id' => $mall->merchant_id
        ]);
        // campaign_popup_views
        $this->campaign_popup_view = Factory::create('CampaignPopupView', [
            'location_id' => $mall->merchant_id
        ]);
        // campaign_spendings
        $this->campaign_spending = Factory::create('CampaignSpendingCount', [
            'mall_id' => $mall->merchant_id
        ]);
        // connected_now
        $this->connected_now = Factory::create('ConnectedNow', [
            'merchant_id' => $mall->merchant_id
        ]);
        // connection_times
        $this->connection_time = Factory::create('ConnectionTime', [
            'location_id' => $mall->merchant_id
        ]);
        // inboxes
        $this->inbox = Factory::create('Inbox', [
            'merchant_id' => $mall->merchant_id
        ]);
        // keywords
        $this->keyword = Factory::create('Keyword', [
            'merchant_id' => $mall->merchant_id
        ]);
        // keyword_object
        $this->keyword_object = Factory::create('KeywordObject', [
            'keyword_id' => $this->keyword->keyword_id,
            'object_type' => 'tenant',
            'object_id' => $mall->merchant_id
        ]);
        // lucky_draws
        $this->lucky_draw = Factory::create('LuckyDraw', [
            'mall_id' => $mall->merchant_id
        ]);
        // lucky_draw_receipts
        $this->lucky_draw_receipt = Factory::create('LuckyDrawReceipt', [
            'mall_id' => $mall->merchant_id
        ]);
        // lucky_draw_announcements
        $this->lucky_draw_announcement = Factory::create('LuckyDrawAnnouncement', [
            'lucky_draw_id' => $this->lucky_draw->lucky_draw_id
        ]);
        // lucky_draw_numbers
        $this->lucky_draw_number = Factory::create('LuckyDrawNumber', [
            'lucky_draw_id' => $this->lucky_draw->lucky_draw_id
        ]);
        // lucky_draw_prize
        $this->lucky_draw_prize = Factory::create('LuckyDrawPrize', [
            'lucky_draw_id' => $this->lucky_draw->lucky_draw_id
        ]);
        // lucky_draw_translation
        $this->lucky_draw_translations = Factory::create('LuckyDrawTranslation', [
            'lucky_draw_id' => $this->lucky_draw->lucky_draw_id
        ]);
        // lucky_draw_winners
        $this->lucky_draw_winner = Factory::create('LuckyDrawWinner', [
            'lucky_draw_id' => $this->lucky_draw->lucky_draw_id,
            'lucky_draw_number_id' => $this->lucky_draw_number->lucky_draw_numbers_id,
            'lucky_draw_prize_id' => $this->lucky_draw_prize->lucky_draw_prize_id
        ]);
        // lucky_draw_announcement_translation
        $this->lucky_draw_announcement_translations = Factory::create('LuckyDrawAnnouncementTranslation', [
            'lucky_draw_announcement_id' => $this->lucky_draw_announcement->lucky_draw_announcement_id
        ]);
        // membership
        $this->membership = Factory::create('Membership', [
            'merchant_id' => $mall->merchant_id
        ]);
        // membership_number
        $this->membership_number = Factory::create('MembershipNumber', [
            'membership_id' => $this->membership->membership_id,
        ]);
        // merchant_geofences
        $this->merchant_geofence = Factory::create('MerchantGeofence', [
            'merchant_id' => $mall->merchant_id
        ]);
        // merchant_languages
        $this->merchant_language = Factory::create('MerchantLanguage', [
            'merchant_id' => $mall->merchant_id
        ]);
        // merchant_translation
        $this->merchant_translations = Factory::create('MerchantTranslation', [
            'merchant_language_id' => $this->merchant_language->merchant_language_id
        ]);
        // setting_translation
        $this->setting_translations = Factory::create('SettingTranslation', [
            'merchant_language_id' => $this->merchant_language->merchant_language_id
        ]);
        // widget_translations
        $this->widget_translations = Factory::create('WidgetTranslation', [
            'merchant_language_id' => $this->merchant_language->merchant_language_id
        ]);
        // merchant_page_views
        $this->merchant_page_views = Factory::create('MerchantPageView', [
            'location_id' => $mall->merchant_id
        ]);
        // merchant_social_media
        $this->merchant_social_media = Factory::create('MerchantSocialMedia', [
            'merchant_id' => $mall->merchant_id
        ]);
        // news_merchants
        $this->news_merchant = Factory::create('NewsMerchant', [
            'merchant_id' => $mall->merchant_id,
            'object_type' => 'mall'
        ]);
        // objects
        $this->objects = Factory::create('Object', [
            'merchant_id' => $mall->merchant_id
        ]);
        // promotion_retailer
        $this->promotion_retailer = Factory::create('coupon_link_tenant', [
            'retailer_id' => $mall->merchant_id,
            'object_type' => 'mall'
        ]);
        // promotion_retailer_redeem
        $this->promotion_retailer_redeem = Factory::create('coupon_link_redeem_tenant', [
            'retailer_id' => $mall->merchant_id,
            'object_type' => 'mall'
        ]);
        // user_merchant
        $this->user_merchant = Factory::create('UserMerchant', [
            'merchant_id' => $mall->merchant_id,
            'object_type' => 'mall'
        ]);
        // user_signin
        $this->user_signin = Factory::create('UserSignin', [
            'location_id' => $mall->merchant_id
        ]);
        // user_verification_number
        $this->user_verification_number = Factory::create('UserVerificationNumber', [
            'merchant_id' => $mall->merchant_id
        ]);
        // viewed_item_user
        $this->viewed_item_user = Factory::create('ViewItemUser', [
            'mall_id' => $mall->merchant_id
        ]);
        // widget_clicks
        $this->widget_click = Factory::create('WidgetClick', [
            'location_id' => $mall->merchant_id
        ]);
        // widgets
        $this->widget = Factory::create('Widget', [
            'merchant_id' => $mall->merchant_id
        ]);
        // widget_retailer
        $this->widget_retailer = Factory::create('WidgetRetailer', [
            'widget_id' => $this->widget->widget_id,
            'retailer_id' => $mall->merchant_id
        ]);
        // setting
        $this->setting = Factory::create('Setting', [
            'setting_name' => 'enable_coupon_widget',
            'object_id' => $mall->merchant_id,
        ]);
    }

    public function testDeleteMallOK()
    {
        $cmd = new DestroyMall($this->mall->merchant_id, TRUE, 'yes');
        $cmd->fire();

        // rage assertion
        $this->assertNull(\Mall::where('merchant_id', $this->mall->merchant_id)->first());
        $this->assertNull(\Activity::where('location_id', $this->mall->merchant_id)->first());
        $this->assertNull(\AgeRange::where('merchant_id', $this->mall->merchant_id)->first());
        $this->assertNull(\CampaignBasePrice::where('merchant_id', $this->mall->merchant_id)->first());
        $this->assertNull(\CampaignClicks::where('location_id', $this->mall->merchant_id)->first());
        $this->assertNull(\CampaignDailySpending::where('mall_id', $this->mall->merchant_id)->first());
        $this->assertNull(\CampaignPageView::where('location_id', $this->mall->merchant_id)->first());
        $this->assertNull(\CampaignPopupView::where('location_id', $this->mall->merchant_id)->first());
        $this->assertNull(\CampaignSpendingCount::where('mall_id', $this->mall->merchant_id)->first());
        $this->assertNull(\ConnectedNow::where('merchant_id', $this->mall->merchant_id)->first());
        $this->assertNull(\ConnectionTime::where('location_id', $this->mall->merchant_id)->first());
        $this->assertNull(\Inbox::where('merchant_id', $this->mall->merchant_id)->first());
        $this->assertNull(\Keyword::where('merchant_id', $this->mall->merchant_id)->first());
        $this->assertNull(\KeywordObject::where('object_id', $this->mall->merchant_id)->first());
        $this->assertNull(\LuckyDrawReceipt::where('mall_id', $this->mall->merchant_id)->first());
        $this->assertNull(\LuckyDraw::where('mall_id', $this->mall->merchant_id)->first());
        $this->assertNull(\LuckyDrawAnnouncement::where('lucky_draw_id', $this->lucky_draw->lucky_draw_id)->first());
        $this->assertNull(\LuckyDrawNumber::where('lucky_draw_id', $this->lucky_draw->lucky_draw_id)->first());
        $this->assertNull(\LuckyDrawPrize::where('lucky_draw_id', $this->lucky_draw->lucky_draw_id)->first());
        $this->assertNull(\LuckyDrawTranslation::where('lucky_draw_id', $this->lucky_draw->lucky_draw_id)->first());
        $this->assertNull(\LuckyDrawWinner::where('lucky_draw_id', $this->lucky_draw->lucky_draw_id)->first());
        $this->assertNull(\LuckyDrawAnnouncementTranslation::where('lucky_draw_announcement_id', $this->lucky_draw_announcement->lucky_draw_announcement_id)->first());
        $this->assertNull(\Membership::where('merchant_id', $this->mall->merchant_id)->first());
        $this->assertNull(\MembershipNumber::where('membership_id', $this->membership->membership_id)->first());
        $this->assertNull(\MerchantGeofence::where('merchant_id', $this->mall->merchant_id)->first());
        $this->assertNull(\MerchantLanguage::where('merchant_id', $this->mall->merchant_id)->first());
        $this->assertNull(\MerchantTranslation::where('merchant_language_id', $this->merchant_language->merchant_language_id)->first());
        $this->assertNull(\SettingTranslation::where('merchant_language_id', $this->merchant_language->merchant_language_id)->first());
        $this->assertNull(\WidgetTranslation::where('merchant_language_id', $this->merchant_language->merchant_language_id)->first());
        $this->assertNull(\MerchantPageView::where('location_id', $this->mall->merchant_id)->first());
        $this->assertNull(\MerchantSocialMedia::where('merchant_id', $this->mall->merchant_id)->first());
        $this->assertNull(\NewsMerchant::where('merchant_id', $this->mall->merchant_id)->first());
        $this->assertNull(\Object::where('merchant_id', $this->mall->merchant_id)->first());
        $this->assertNull(\CouponRetailer::where('retailer_id', $this->mall->merchant_id)->first());
        $this->assertNull(\CouponRetailerRedeem::where('retailer_id', $this->mall->merchant_id)->first());
        $this->assertNull(\UserMerchant::where('merchant_id', $this->mall->merchant_id)->first());
        $this->assertNull(\UserSignin::where('location_id', $this->mall->merchant_id)->first());
        $this->assertNull(\UserVerificationNumber::where('merchant_id', $this->mall->merchant_id)->first());
        $this->assertNull(\ViewItemUser::where('mall_id', $this->mall->merchant_id)->first());
        $this->assertNull(\WidgetClick::where('location_id', $this->mall->merchant_id)->first());
        $this->assertNull(\Widget::where('merchant_id', $this->mall->merchant_id)->first());
        $this->assertNull(\WidgetRetailer::where('widget_id', $this->widget->widget_id)->first());
        $this->assertNull(\Setting::where('object_id', $this->mall->merchant_id)->first());
    }
}
