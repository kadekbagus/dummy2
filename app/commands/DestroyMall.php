<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class DestroyMall extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'mall:destroy';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Delete all data in mall';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
		$mallid = $this->option('merchant_id');
        $confirm = $this->option('yes');
        $campaign = $this->option('with_campaign');
        $prefix = DB::getTablePrefix();

        $mall = Mall::where('merchant_id', $mallid)->first();

        if (empty($mall)) {
        	$this->info("Cannot find mall");
        	return;
        }

        $campaignText = "without Campaign data";
        if ($campaign === "yes") {
            $campaignText = "with Campaign data";
        }

        if (! $confirm) {
            $question = "Are you sure want to delete all data in " . $mall->name . " (" . $campaignText . ")? [y|n]";
            if (! $this->confirm($question, false)) {
                $confirm = false;
                return;
            }
        }

        $this->info("Delete all " . $mall->name . " data in {$prefix}connected_now table ... ");
		DB::unprepared("
			DELETE {$prefix}connected_now, {$prefix}list_connected_user
			FROM {$prefix}connected_now
			LEFT JOIN {$prefix}list_connected_user ON {$prefix}connected_now.connected_now_id = {$prefix}list_connected_user.connected_now_id
			WHERE {$prefix}connected_now.merchant_id = '{$mallid}'
        ");

        $this->info("Delete all " . $mall->name . " data in {$prefix}connection_times table ... ");
		DB::unprepared("
			DELETE
			FROM {$prefix}connection_times WHERE location_id = '{$mallid}'
        ");

        $this->info("Delete all " . $mall->name . " data in {$prefix}events table ... ");
		DB::unprepared("
			DELETE {$prefix}events, {$prefix}activities, {$prefix}event_product, {$prefix}event_retailer, {$prefix}event_translations
			FROM {$prefix}events
			LEFT JOIN {$prefix}activities ON {$prefix}activities.event_id = {$prefix}events.event_id
			LEFT JOIN {$prefix}event_product ON {$prefix}event_product.event_id = {$prefix}events.event_id
			LEFT JOIN {$prefix}event_retailer ON {$prefix}event_retailer.event_id = {$prefix}events.event_id
			LEFT JOIN {$prefix}event_translations ON {$prefix}event_translations.event_id = {$prefix}events.event_id
			WHERE {$prefix}events.merchant_id = '{$mallid}'
        ");

        $this->info("Delete all " . $mall->name . " data in {$prefix}inboxes table ... ");
		DB::unprepared("
			DELETE 
			FROM {$prefix}inboxes WHERE merchant_id = '{$mallid}'
        ");

        $this->info("Delete all " . $mall->name . " data in {$prefix}lucky_draws table ... ");
		DB::unprepared("
			DELETE  {$prefix}lucky_draws, {$prefix}lucky_draw_announcements, {$prefix}lucky_draw_numbers, {$prefix}lucky_draw_prizes, {$prefix}lucky_draw_translations, {$prefix}lucky_draw_winners
			FROM  {$prefix}lucky_draws 
			LEFT JOIN {$prefix}lucky_draw_announcements ON {$prefix}lucky_draw_announcements.lucky_draw_id = {$prefix}lucky_draws.lucky_draw_id
			LEFT JOIN {$prefix}lucky_draw_numbers ON {$prefix}lucky_draw_numbers.lucky_draw_id = {$prefix}lucky_draws.lucky_draw_id
			LEFT JOIN {$prefix}lucky_draw_prizes ON {$prefix}lucky_draw_prizes.lucky_draw_id = {$prefix}lucky_draws.lucky_draw_id
			LEFT JOIN {$prefix}lucky_draw_translations ON {$prefix}lucky_draw_translations.lucky_draw_id = {$prefix}lucky_draws.lucky_draw_id
			LEFT JOIN {$prefix}lucky_draw_winners ON {$prefix}lucky_draw_winners.lucky_draw_id = {$prefix}lucky_draws.lucky_draw_id
			WHERE {$prefix}lucky_draws.mall_id = '{$mallid}'
        ");

        $this->info("Delete all " . $mall->name . " data in {$prefix}memberships table ... ");
		DB::unprepared("
			DELETE {$prefix}memberships, {$prefix}membership_numbers
			FROM {$prefix}memberships 
			LEFT JOIN {$prefix}membership_numbers ON {$prefix}membership_numbers.membership_id = {$prefix}memberships.membership_id
			WHERE {$prefix}memberships.merchant_id = '{$mallid}'
        ");

        $this->info("Delete all " . $mall->name . " data in {$prefix}merchant_geofences table ... ");
		DB::unprepared("
			DELETE 
			FROM {$prefix}merchant_geofences WHERE merchant_id = '{$mallid}'
        ");

        $this->info("Delete all " . $mall->name . " data in {$prefix}merchant_languages table ... ");
		DB::unprepared("
			DELETE 
			FROM {$prefix}merchant_languages WHERE merchant_id = '{$mallid}'
        ");

        $this->info("Delete all " . $mall->name . " data in {$prefix}merchant_page_views table ... ");
		DB::unprepared("
			DELETE 
			FROM {$prefix}merchant_page_views WHERE location_id = '{$mallid}'
        ");

        $this->info("Delete all " . $mall->name . " data in {$prefix}merchant_social_media table ... ");
		DB::unprepared("
			DELETE 
			FROM {$prefix}merchant_social_media WHERE merchant_id = '{$mallid}'
        ");

        $campaign_1 = '';
        $campaign_2 = '';
        $campaignActivity1 = '';
        $campaignActivity2 = '';

        if ($campaign === "yes") { 
            $campaign_1 = "{$prefix}news_merchant,
                {$prefix}news_translations,
                {$prefix}promotion_retailer,
                {$prefix}promotion_retailer_redeem,
                {$prefix}promotions,
                {$prefix}campaign_base_prices,";

            $campaign_2 = "LEFT JOIN {$prefix}news_merchant ON {$prefix}news_merchant.merchant_id = {$prefix}merchants.merchant_id
                LEFT JOIN {$prefix}news_translations ON {$prefix}news_translations.merchant_id = {$prefix}merchants.merchant_id
                LEFT JOIN {$prefix}promotion_retailer ON {$prefix}promotion_retailer.retailer_id = {$prefix}merchants.merchant_id
                LEFT JOIN {$prefix}promotion_retailer_redeem ON {$prefix}promotion_retailer_redeem.retailer_id = {$prefix}merchants.merchant_id
                LEFT JOIN {$prefix}promotions ON {$prefix}promotions.merchant_id = {$prefix}merchants.merchant_id
                LEFT JOIN {$prefix}campaign_base_prices ON {$prefix}campaign_base_prices.merchant_id = {$prefix}merchants.merchant_id";

            $campaignActivity1 = "{$prefix}campaign_clicks, 
                {$prefix}campaign_page_views, 
                {$prefix}campaign_popup_views,";

            $campaignActivity2 = "LEFT JOIN {$prefix}campaign_clicks ON {$prefix}activities.activity_id = {$prefix}campaign_clicks.activity_id 
                LEFT JOIN {$prefix}campaign_page_views ON {$prefix}activities.activity_id = {$prefix}campaign_page_views.activity_id
                LEFT JOIN {$prefix}campaign_popup_views ON {$prefix}activities.activity_id = {$prefix}campaign_popup_views.activity_id";

            $this->info("Delete all " . $mall->name . " data in {$prefix}age_ranges table ... ");
            DB::unprepared("
                DELETE {$prefix}age_ranges, {$prefix}campaign_age
                FROM {$prefix}age_ranges 
                LEFT JOIN {$prefix}campaign_age ON {$prefix}age_ranges.age_range_id = {$prefix}campaign_age.age_range_id
                WHERE {$prefix}age_ranges.merchant_id = '{$mallid}'
            ");

            $this->info("Delete all " . $mall->name . " data in {$prefix}keywords table ... ");
            DB::unprepared("
                DELETE {$prefix}keywords, {$prefix}keyword_object
                FROM {$prefix}keywords 
                LEFT JOIN {$prefix}keyword_object ON {$prefix}keyword_object.keyword_id = {$prefix}keywords.keyword_id
                WHERE {$prefix}keywords.merchant_id = '{$mallid}'
            ");

            $this->info("Delete all " . $mall->name . " data in {$prefix}campaign_base_prices table ... ");
            DB::unprepared("
                DELETE
                FROM {$prefix}campaign_base_prices where merchant_id = '{$mallid}'
            ");

            $this->info("Delete all " . $mall->name . " data in {$prefix}campaign_clicks table ... ");
            DB::unprepared("
                DELETE 
                FROM {$prefix}campaign_clicks WHERE location_id = '{$mallid}'
            ");

            $this->info("Delete all " . $mall->name . " data in {$prefix}campaign_daily_spendings table ... ");
            DB::unprepared("
                DELETE 
                FROM {$prefix}campaign_daily_spendings WHERE mall_id = '{$mallid}'
            ");

            $this->info("Delete all " . $mall->name . " data in {$prefix}campaign_page_views table ... ");
            DB::unprepared("
                DELETE
                FROM {$prefix}campaign_page_views WHERE location_id = '{$mallid}'
            ");

            $this->info("Delete all " . $mall->name . " data in {$prefix}campaign_popup_views table ... ");
            DB::unprepared("
                DELETE
                FROM {$prefix}campaign_popup_views WHERE location_id = '{$mallid}'
            ");

            $this->info("Delete all " . $mall->name . " data in {$prefix}campaign_spendings table ... ");
            DB::unprepared("
                DELETE
                FROM {$prefix}campaign_spendings WHERE mall_id = '{$mallid}'
            ");

            $this->info("Delete all " . $mall->name . " data in {$prefix}news table ... ");
            DB::unprepared("
                DELETE
                {$prefix}news,
                {$prefix}activities,
                {$prefix}campaign_age,
                {$prefix}campaign_billings,
                {$prefix}campaign_clicks,
                {$prefix}campaign_daily_spendings,
                {$prefix}campaign_gender,
                {$prefix}campaign_histories,
                {$prefix}campaign_page_views,
                {$prefix}campaign_popup_views,
                {$prefix}campaign_price,
                {$prefix}campaign_spendings,
                {$prefix}news_merchant,
                {$prefix}news_translations,
                {$prefix}user_campaign
                FROM {$prefix}news
                LEFT JOIN {$prefix}activities ON {$prefix}activities.news_id = {$prefix}news.news_id
                LEFT JOIN {$prefix}campaign_age ON {$prefix}campaign_age.campaign_id = {$prefix}news.news_id
                LEFT JOIN {$prefix}campaign_billings ON {$prefix}campaign_billings.campaign_id = {$prefix}news.news_id
                LEFT JOIN {$prefix}campaign_clicks ON {$prefix}campaign_clicks.campaign_id = {$prefix}news.news_id
                LEFT JOIN {$prefix}campaign_daily_spendings ON {$prefix}campaign_daily_spendings.campaign_id = {$prefix}news.news_id
                LEFT JOIN {$prefix}campaign_gender ON {$prefix}campaign_gender.campaign_id = {$prefix}news.news_id
                LEFT JOIN {$prefix}campaign_histories ON {$prefix}campaign_histories.campaign_id = {$prefix}news.news_id
                LEFT JOIN {$prefix}campaign_page_views ON {$prefix}campaign_page_views.campaign_id = {$prefix}news.news_id
                LEFT JOIN {$prefix}campaign_popup_views ON {$prefix}campaign_popup_views.campaign_id = {$prefix}news.news_id
                LEFT JOIN {$prefix}campaign_price ON {$prefix}campaign_price.campaign_id = {$prefix}news.news_id
                LEFT JOIN {$prefix}campaign_spendings ON {$prefix}campaign_spendings.campaign_id = {$prefix}news.news_id
                LEFT JOIN {$prefix}news_merchant ON {$prefix}news_merchant.news_id = {$prefix}news.news_id
                LEFT JOIN {$prefix}news_translations ON {$prefix}news_translations.news_id = {$prefix}news.news_id
                LEFT JOIN {$prefix}user_campaign ON {$prefix}user_campaign.campaign_id = {$prefix}news.news_id
                WHERE {$prefix}news.mall_id = '{$mallid}'
            ");

            $this->info("Delete all " . $mall->name . " data in {$prefix}news_merchant table ... ");
            DB::unprepared("
                DELETE 
                FROM {$prefix}news_merchant WHERE merchant_id = '{$mallid}'
            ");

            $this->info("Delete all " . $mall->name . " data in {$prefix}news_translations table ... ");
            DB::unprepared("
                DELETE 
                FROM {$prefix}news_translations WHERE merchant_id = '{$mallid}'
            ");

            $this->info("Delete all " . $mall->name . " data in {$prefix}promotions table ... ");
            DB::unprepared("
                DELETE
                {$prefix}promotions,
                {$prefix}activities,
                {$prefix}campaign_age,
                {$prefix}campaign_billings,
                {$prefix}campaign_clicks,
                {$prefix}campaign_daily_spendings,
                {$prefix}campaign_gender,
                {$prefix}campaign_histories,
                {$prefix}campaign_page_views,
                {$prefix}campaign_popup_views,
                {$prefix}campaign_price,
                {$prefix}campaign_spendings,
                {$prefix}coupon_translations,
                {$prefix}issued_coupons,
                {$prefix}promotion_employee,
                {$prefix}promotion_retailer,
                {$prefix}promotion_retailer_redeem,
                {$prefix}promotion_rules,
                {$prefix}promotion_translations,
                {$prefix}transaction_detail_coupons,
                {$prefix}transaction_detail_promotions,
                {$prefix}user_campaign
                FROM {$prefix}promotions
                LEFT JOIN {$prefix}activities ON {$prefix}activities.promotion_id = {$prefix}promotions.promotion_id
                LEFT JOIN {$prefix}campaign_age ON {$prefix}campaign_age.campaign_id = {$prefix}promotions.promotion_id
                LEFT JOIN {$prefix}campaign_billings ON {$prefix}campaign_billings.campaign_id = {$prefix}promotions.promotion_id
                LEFT JOIN {$prefix}campaign_clicks ON {$prefix}campaign_clicks.campaign_id = {$prefix}promotions.promotion_id
                LEFT JOIN {$prefix}campaign_daily_spendings ON {$prefix}campaign_daily_spendings.campaign_id = {$prefix}promotions.promotion_id
                LEFT JOIN {$prefix}campaign_gender ON {$prefix}campaign_gender.campaign_id = {$prefix}promotions.promotion_id
                LEFT JOIN {$prefix}campaign_histories ON {$prefix}campaign_histories.campaign_id = {$prefix}promotions.promotion_id
                LEFT JOIN {$prefix}campaign_page_views ON {$prefix}campaign_page_views.campaign_id = {$prefix}promotions.promotion_id
                LEFT JOIN {$prefix}campaign_popup_views ON {$prefix}campaign_popup_views.campaign_id = {$prefix}promotions.promotion_id
                LEFT JOIN {$prefix}campaign_price ON {$prefix}campaign_price.campaign_id = {$prefix}promotions.promotion_id
                LEFT JOIN {$prefix}campaign_spendings ON {$prefix}campaign_spendings.campaign_id = {$prefix}promotions.promotion_id
                LEFT JOIN {$prefix}coupon_translations ON {$prefix}coupon_translations.promotion_id = {$prefix}promotions.promotion_id
                LEFT JOIN {$prefix}issued_coupons ON {$prefix}issued_coupons.promotion_id = {$prefix}promotions.promotion_id
                LEFT JOIN {$prefix}promotion_employee ON {$prefix}promotion_employee.promotion_id = {$prefix}promotions.promotion_id
                LEFT JOIN {$prefix}promotion_retailer ON {$prefix}promotion_retailer.promotion_id = {$prefix}promotions.promotion_id
                LEFT JOIN {$prefix}promotion_retailer_redeem ON {$prefix}promotion_retailer_redeem.promotion_id = {$prefix}promotions.promotion_id
                LEFT JOIN {$prefix}promotion_rules ON {$prefix}promotion_rules.promotion_id = {$prefix}promotions.promotion_id
                LEFT JOIN {$prefix}promotion_translations ON {$prefix}promotion_translations.promotion_id = {$prefix}promotions.promotion_id
                LEFT JOIN {$prefix}transaction_detail_coupons ON {$prefix}transaction_detail_coupons.promotion_id = {$prefix}promotions.promotion_id
                LEFT JOIN {$prefix}transaction_detail_promotions ON {$prefix}transaction_detail_promotions.promotion_id = {$prefix}promotions.promotion_id
                LEFT JOIN {$prefix}user_campaign ON {$prefix}user_campaign.campaign_id = {$prefix}promotions.promotion_id
                WHERE {$prefix}promotions.merchant_id = '{$mallid}'
            ");
        }

        $this->info("Delete all " . $mall->name . " data in {$prefix}activities table ... ");
        $sql_activity = "
            DELETE {$prefix}activities, 
            " . $campaignActivity1 . "
            {$prefix}merchant_page_views,
            {$prefix}user_signin,
            {$prefix}widget_clicks
            FROM {$prefix}activities
            " . $campaignActivity2 . "
            LEFT JOIN {$prefix}merchant_page_views ON {$prefix}activities.activity_id = {$prefix}merchant_page_views.activity_id
            LEFT JOIN {$prefix}user_signin ON {$prefix}activities.activity_id = {$prefix}user_signin.activity_id
            LEFT JOIN {$prefix}widget_clicks ON {$prefix}activities.activity_id = {$prefix}widget_clicks.activity_id
            WHERE {$prefix}activities.location_id = '{$mallid}'
        ";
        DB::unprepared($sql_activity);

        $this->info("Delete all " . $mall->name . " data in {$prefix}merchants (tenant) table ... ");
        $sql_merchant = "DELETE 
            {$prefix}merchants,
            {$prefix}age_ranges,
            {$prefix}carts,
            {$prefix}categories,
            {$prefix}category_merchant,
            {$prefix}connected_now,
            {$prefix}employee_retailer,
            {$prefix}event_retailer,
            {$prefix}events,
            {$prefix}inboxes,
            {$prefix}indoormap_elements,
            {$prefix}keywords,
            {$prefix}memberships,
            {$prefix}merchant_geofences,
            {$prefix}merchant_languages,
            {$prefix}merchant_page_views,
            {$prefix}merchant_social_media,
            {$prefix}merchant_taxes,
            {$prefix}merchant_translations,
            {$prefix}objects,
            {$prefix}pos_quick_products,
            {$prefix}product_attributes,
            {$prefix}product_retailer,
            {$prefix}product_variants,
            {$prefix}products,
            {$prefix}retailer_tenant,
            {$prefix}transactions,
            {$prefix}user_details,
            {$prefix}user_merchant,
            {$prefix}user_verification_numbers,
            {$prefix}widget_retailer,
            " . $campaign_1 . "
            {$prefix}widgets
            FROM {$prefix}merchants 
            " . $campaign_2 . "
            LEFT JOIN {$prefix}age_ranges ON {$prefix}age_ranges.merchant_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}carts ON {$prefix}carts.merchant_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}categories ON {$prefix}categories.merchant_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}category_merchant ON {$prefix}category_merchant.merchant_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}connected_now ON {$prefix}connected_now.merchant_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}employee_retailer ON {$prefix}employee_retailer.retailer_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}event_retailer ON {$prefix}event_retailer.retailer_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}events ON {$prefix}events.merchant_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}inboxes ON {$prefix}inboxes.merchant_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}indoormap_elements ON {$prefix}indoormap_elements.merchant_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}keywords ON {$prefix}keywords.merchant_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}memberships ON {$prefix}memberships.merchant_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}merchant_geofences ON {$prefix}merchant_geofences.merchant_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}merchant_languages ON {$prefix}merchant_languages.merchant_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}merchant_page_views ON {$prefix}merchant_page_views.merchant_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}merchant_social_media ON {$prefix}merchant_social_media.merchant_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}merchant_taxes ON {$prefix}merchant_taxes.merchant_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}merchant_translations ON {$prefix}merchant_translations.merchant_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}objects ON {$prefix}objects.merchant_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}pos_quick_products ON {$prefix}pos_quick_products.retailer_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}product_attributes ON {$prefix}product_attributes.merchant_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}product_retailer ON {$prefix}product_retailer.retailer_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}product_variants ON {$prefix}product_variants.retailer_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}products ON {$prefix}products.merchant_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}retailer_tenant ON {$prefix}retailer_tenant.retailer_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}transactions ON {$prefix}transactions.retailer_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}user_details ON {$prefix}user_details.retailer_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}user_merchant ON {$prefix}user_merchant.merchant_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}user_verification_numbers ON {$prefix}user_verification_numbers.merchant_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}widget_retailer ON {$prefix}widget_retailer.retailer_id = {$prefix}merchants.merchant_id
            LEFT JOIN {$prefix}widgets ON {$prefix}widgets.merchant_id = {$prefix}merchants.merchant_id
            WHERE {$prefix}merchants.parent_id= '{$mallid}'";
		DB::unprepared($sql_merchant);

        $this->info("Delete all " . $mall->name . " data in {$prefix}objects table ... ");
		DB::unprepared("
			DELETE 
			{$prefix}objects,
			{$prefix}activities,
			{$prefix}cart_coupons,
			{$prefix}indoormap_elements,
			{$prefix}keyword_object,
			{$prefix}media,
			{$prefix}settings,
			{$prefix}spending_rules,
			{$prefix}tokens
			FROM {$prefix}objects
			LEFT JOIN {$prefix}activities ON {$prefix}activities.object_id = {$prefix}objects.object_id
			LEFT JOIN {$prefix}cart_coupons ON {$prefix}cart_coupons.object_id = {$prefix}objects.object_id
			LEFT JOIN {$prefix}indoormap_elements ON {$prefix}indoormap_elements.object_id = {$prefix}objects.object_id
			LEFT JOIN {$prefix}keyword_object ON {$prefix}keyword_object.object_id = {$prefix}objects.object_id
			LEFT JOIN {$prefix}media ON {$prefix}media.object_id = {$prefix}objects.object_id
			LEFT JOIN {$prefix}settings ON {$prefix}settings.object_id = {$prefix}objects.object_id
			LEFT JOIN {$prefix}spending_rules ON {$prefix}spending_rules.object_id = {$prefix}objects.object_id
			LEFT JOIN {$prefix}tokens ON {$prefix}tokens.object_id = {$prefix}objects.object_id
			WHERE {$prefix}objects.merchant_id = '{$mallid}'
        ");

		$this->info("Delete all " . $mall->name . " data in {$prefix}user_merchant table ... ");
		DB::unprepared("
			DELETE 
			FROM {$prefix}user_merchant WHERE merchant_id = '{$mallid}'
        ");

        $this->info("Delete all " . $mall->name . " data in {$prefix}user_signin table ... ");
		DB::unprepared("
			DELETE 
			FROM {$prefix}user_signin WHERE location_id = '{$mallid}'
        ");

        $this->info("Delete all " . $mall->name . " data in {$prefix}user_verification_numbers table ... ");
		DB::unprepared("
			DELETE 
			FROM {$prefix}user_verification_numbers WHERE merchant_id = '{$mallid}'
        ");

        $this->info("Delete all " . $mall->name . " data in {$prefix}viewed_item_user table ... ");
		DB::unprepared("
			DELETE 
			FROM {$prefix}viewed_item_user WHERE mall_id = '{$mallid}'
        ");

        $this->info("Delete all " . $mall->name . " data in {$prefix}widget_clicks table ... ");
		DB::unprepared("
			DELETE 
			FROM {$prefix}widget_clicks WHERE location_id = '{$mallid}'
        ");

        $this->info("Delete all " . $mall->name . " data in {$prefix}widgets table ... ");
		DB::unprepared("
			DELETE 
			{$prefix}widgets,
			{$prefix}widget_clicks,
			{$prefix}widget_retailer,
			{$prefix}widget_translations
			FROM {$prefix}widgets
			LEFT JOIN {$prefix}widget_clicks ON {$prefix}widget_clicks.widget_id = {$prefix}widgets.widget_id
			LEFT JOIN {$prefix}widget_retailer ON {$prefix}widget_retailer.widget_id = {$prefix}widgets.widget_id
			LEFT JOIN {$prefix}widget_translations ON {$prefix}widget_translations.widget_id = {$prefix}widgets.widget_id
			WHERE location_id = '{$mallid}'
        ");

        $this->info("Delete all " . $mall->name . " data in {$prefix}settings table ... ");
		DB::unprepared("
			DELETE 
			FROM {$prefix}settings WHERE object_id = '{$mallid}' or setting_value = '{$mallid}'
        ");

        $this->info("Delete all " . $mall->name . " data in {$prefix}merchants (mall) table ... ");
		DB::unprepared("
			DELETE 
			FROM {$prefix}merchants WHERE merchant_id = '{$mallid}'
        ");

		$this->info("Delete data in mall " . $mall->name . " Success");
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
		);
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array(
            array('merchant_id', null, InputOption::VALUE_REQUIRED, 'Mall or Merchant ID.'),
			array('with_campaign', null, InputOption::VALUE_REQUIRED, 'Delete campaign data (news, promotion, coupon) [yes|no]'),
			array('yes', null, InputOption::VALUE_NONE, 'Confirmation to delete mall data'),
		);
	}

}
