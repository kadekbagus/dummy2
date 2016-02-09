<?php

// autoload_classmap.php @generated by Composer

$vendorDir = dirname(dirname(__FILE__));
$baseDir = dirname($vendorDir);

return array(
    'Activity' => $baseDir . '/app/models/Activity.php',
    'ActivityAPIController' => $baseDir . '/app/controllers/api/v1/ActivityAPIController.php',
    'AgeRange' => $baseDir . '/app/models/AgeRange.php',
    'AgeRangeAPIController' => $baseDir . '/app/controllers/api/v1/AgeRangeAPIController.php',
    'AlterActivitiesAddOsLoginTime' => $baseDir . '/app/database/migrations/2015_11_23_032746_alter_activities_add_os_login_time.php',
    'AlterSessionsAddApplicationId' => $baseDir . '/app/database/migrations/2015_10_30_112636_alter_sessions_add_application_id.php',
    'AlterSessionsAddIndexExpireAt' => $baseDir . '/app/database/migrations/2015_10_27_094038_alter_sessions_add_index_expire_at.php',
    'AlterTableActivitiesAddObjectDisplayNameColumn' => $baseDir . '/app/database/migrations/2015_12_11_062518_alter_table_activities_add_object_display_name_column.php',
    'AlterTableCampaignHistoriesAddField' => $baseDir . '/app/database/migrations/2016_01_18_081524_alter_table_campaign_histories_add_field.php',
    'AlterTableCampaignHistoriesRemoveAndAddField' => $baseDir . '/app/database/migrations/2016_01_18_020344_alter_table_campaign_histories_remove_and_add_field.php',
    'AlterTableInboxesAddColumnMerchantId' => $baseDir . '/app/database/migrations/2015_10_30_062500_alter_table_inboxes_add_column_merchant_id.php',
    'AlterTableIssuedCouponsAddColumnIssuerUserId' => $baseDir . '/app/database/migrations/2015_12_04_080436_alter_table_issued_coupons_add_column_issuer_user_id.php',
    'AlterTableIssuedCouponsChangeRedeemRetailerIdToUuid' => $baseDir . '/app/database/migrations/2015_10_29_114000_alter_table_issued_coupons_change_redeem_retailer_id_to_uuid.php',
    'AlterTableLuckyDrawAddDrawDateColumn' => $baseDir . '/app/database/migrations/2015_12_02_095519_alter_table_lucky_draw_add_draw_date_column.php',
    'AlterTableLuckyDrawAnnouncementsAddColumnBlastedAt' => $baseDir . '/app/database/migrations/2015_12_23_033025_alter_table_lucky_draw_announcements_add_column_blasted_at.php',
    'AlterTableLuckyDrawChangeDrawDateToNullable' => $baseDir . '/app/database/migrations/2015_12_17_041729_alter_table_lucky_draw_change_draw_date_to_nullable.php',
    'AlterTableLuckyDrawWinnerAddColumnLuckyDrawPrizeId' => $baseDir . '/app/database/migrations/2015_12_04_074334_alter_table_lucky_draw_winner_add_column_lucky_draw_prize_id.php',
    'AlterTableLuckyDrawsAddColumnsFreeNumbersAndGeneratedTo' => $baseDir . '/app/database/migrations/2015_11_23_083312_alter_table_lucky_draws_add_columns_free_numbers_and_generated_to.php',
    'AlterTableMerchantsAddColumnTimezoneId' => $baseDir . '/app/database/migrations/2015_10_18_043247_alter_table_merchants_add_column_timezone_id.php',
    'AlterTableNewsAddFieldIsAllGenderIsAllAgeIsPopup' => $baseDir . '/app/database/migrations/2015_12_29_042349_alter_table_news_add_field_is_all_gender_is_all_age_is_popup.php',
    'AlterTableOrbsPromotionsAddColumnIsAllEmployee' => $baseDir . '/app/database/migrations/2015_12_04_080452_alter_table_orbs_promotions_add_column_is_all_employee.php',
    'AlterTablePromotionAddColumnIsReedemedAtCs' => $baseDir . '/app/database/migrations/2015_11_24_055009_alter_table_promotion_add_column_is_reedemed_at_cs.php',
    'AlterTablePromotionRulesAddFieldRuleBeginDateRuleEndDate' => $baseDir . '/app/database/migrations/2015_12_29_042352_alter_table_promotion_rules_add_field_rule_begin_date_rule_end_date.php',
    'AlterTablePromotionsAddFieldIsAllGenderIsAllAgeIsPopup' => $baseDir . '/app/database/migrations/2015_12_29_042351_alter_table_promotions_add_field_is_all_gender_is_all_age_is_popup.php',
    'AlterTableUserAcquisitionAddColumnSignupviaSocialidSocialurl' => $baseDir . '/app/database/migrations/2015_12_21_045145_alter_table_user_acquisition_add_column_signupvia_socialid_socialurl.php',
    'AlterTableUseracquisitionRemoveSocialurlColumn' => $baseDir . '/app/database/migrations/2015_12_22_025018_alter_table_useracquisition_remove_socialurl_column.php',
    'Apikey' => $baseDir . '/app/models/Apikey.php',
    'Arrays\\Util\\DuplicateChecker' => $baseDir . '/app/helpers/Arrays/Util/DuplicateChecker.php',
    'BaseController' => $baseDir . '/app/controllers/BaseController.php',
    'BasePrice' => $baseDir . '/app/commands/BasePrice.php',
    'CampaignAge' => $baseDir . '/app/models/CampaignAge.php',
    'CampaignBasePrices' => $baseDir . '/app/models/CampaignBasePrices.php',
    'CampaignBilling' => $baseDir . '/app/models/CampaignBilling.php',
    'CampaignClicks' => $baseDir . '/app/models/CampaignClick.php',
    'CampaignDatabaseSeeder' => $baseDir . '/app/database/seeds/CampaignDatabaseSeeder.php',
    'CampaignGender' => $baseDir . '/app/models/CampaignGender.php',
    'CampaignGroupName' => $baseDir . '/app/models/CampaignGroupName.php',
    'CampaignHistory' => $baseDir . '/app/models/CampaignHistory.php',
    'CampaignHistoryActionTableSeeder' => $baseDir . '/app/database/seeds/CampaignHistoryActionTableSeeder.php',
    'CampaignHistoryActions' => $baseDir . '/app/models/CampaignHistoryActions.php',
    'CampaignPageView' => $baseDir . '/app/models/CampaignPageView.php',
    'CampaignPermissionRoleTableSeeder' => $baseDir . '/app/database/seeds/CampaignPermissionRoleTableSeeder.php',
    'CampaignPopupView' => $baseDir . '/app/models/CampaignPopupView.php',
    'CampaignPrice' => $baseDir . '/app/models/CampaignPrice.php',
    'CampaignReportAPIController' => $baseDir . '/app/controllers/api/v1/CampaignReportAPIController.php',
    'CampaignRoleTableSeeder' => $baseDir . '/app/database/seeds/CampaignRoleTableSeeder.php',
    'CaptiveIntegrationAPIController' => $baseDir . '/app/controllers/api/v1/CaptiveIntegrationAPIController.php',
    'Cart' => $baseDir . '/app/models/Cart.php',
    'CartCoupon' => $baseDir . '/app/models/CartCoupon.php',
    'CartDetail' => $baseDir . '/app/models/CartDetail.php',
    'Category' => $baseDir . '/app/models/Category.php',
    'CategoryAPIController' => $baseDir . '/app/controllers/api/v1/CategoryAPIController.php',
    'CategoryMerchant' => $baseDir . '/app/models/CategoryMerchant.php',
    'CategoryTranslation' => $baseDir . '/app/models/CategoryTranslation.php',
    'ClearBeanstalkdQueueCommand' => $baseDir . '/app/commands/ClearBeanstalkdQueueCommand.php',
    'CompileOrbitRoutes' => $baseDir . '/app/commands/CompileOrbitRoutes.php',
    'ConfigAgreement' => $baseDir . '/app/commands/ConfigAgreement.php',
    'ConnectedNow' => $baseDir . '/app/models/ConnectedNow.php',
    'ConnectionTime' => $baseDir . '/app/models/ConnectionTime.php',
    'Country' => $baseDir . '/app/models/Country.php',
    'CountryAPIController' => $baseDir . '/app/controllers/api/v1/CountryAPIController.php',
    'Coupon' => $baseDir . '/app/models/Coupon.php',
    'CouponAPIController' => $baseDir . '/app/controllers/api/v1/CouponAPIController.php',
    'CouponEmployee' => $baseDir . '/app/models/CouponEmployee.php',
    'CouponReportAPIController' => $baseDir . '/app/controllers/api/v1/CouponReportAPIController.php',
    'CouponRetailer' => $baseDir . '/app/models/CouponRetailer.php',
    'CouponRetailerRedeem' => $baseDir . '/app/models/CouponRetailerRedeem.php',
    'CouponRule' => $baseDir . '/app/models/CouponRule.php',
    'CouponTranslation' => $baseDir . '/app/models/CouponTranslation.php',
    'CreateFunctionCampaignCost' => $baseDir . '/app/database/migrations/2016_02_03_040022_create_function_campaign_cost.php',
    'CreateFunctionCampaignTotalSpending' => $baseDir . '/app/database/migrations/2016_02_01_032738_create_function_campaign_total_spending.php',
    'CreateTableAgeRanges' => $baseDir . '/app/database/migrations/2015_12_29_042343_create_table_age_ranges.php',
    'CreateTableCampaignAge' => $baseDir . '/app/database/migrations/2015_12_29_042344_create_table_campaign_age.php',
    'CreateTableCampaignBasePrices' => $baseDir . '/app/database/migrations/2015_12_29_042348_create_table_campaign_base_prices.php',
    'CreateTableCampaignBillings' => $baseDir . '/app/database/migrations/2016_01_13_024005_create_table_campaign_billings.php',
    'CreateTableCampaignGender' => $baseDir . '/app/database/migrations/2015_12_29_042345_create_table_campaign_gender.php',
    'CreateTableCampaignHistories' => $baseDir . '/app/database/migrations/2016_01_13_024441_create_table_campaign_histories.php',
    'CreateTableCampaignHistoryActions' => $baseDir . '/app/database/migrations/2016_01_18_022514_create_table_campaign_history_actions.php',
    'CreateTableCampaignPrice' => $baseDir . '/app/database/migrations/2015_12_29_042347_create_table_campaign_price.php',
    'CreateTableKeywordObject' => $baseDir . '/app/database/migrations/2016_01_12_034757_create_table_keyword_object.php',
    'CreateTableKeywords' => $baseDir . '/app/database/migrations/2016_01_12_034756_create_table_keywords.php',
    'CreateTableLuckyDrawAnnouncementTranslations' => $baseDir . '/app/database/migrations/2015_12_02_040307_create_table_lucky_draw_announcement_translations.php',
    'CreateTableLuckyDrawAnnouncements' => $baseDir . '/app/database/migrations/2015_12_02_033444_create_table_lucky_draw_announcements.php',
    'CreateTableLuckyDrawPrizes' => $baseDir . '/app/database/migrations/2015_12_03_021542_create_table_lucky_draw_prizes.php',
    'CreateTableLuckyDrawTranslations' => $baseDir . '/app/database/migrations/2015_11_24_065646_create_table_lucky_draw_translations.php',
    'CreateTableMembershipNumbers' => $baseDir . '/app/database/migrations/2015_10_19_094143_create_table_membership_numbers.php',
    'CreateTableMemberships' => $baseDir . '/app/database/migrations/2015_10_19_091502_create_table_memberships.php',
    'CreateTableSequence' => $baseDir . '/app/database/migrations/2016_01_25_094200_create_table_sequence.php',
    'CreateTableTimezones' => $baseDir . '/app/database/migrations/2015_10_18_085530_create_table_timezones.php',
    'CreateTableUserSignin' => $baseDir . '/app/database/migrations/2015_12_21_044542_create_table_user_signin.php',
    'CurlWrapper' => $vendorDir . '/svyatov/curlwrapper/CurlWrapper.php',
    'CurlWrapperCurlException' => $vendorDir . '/svyatov/curlwrapper/CurlWrapper.php',
    'CurlWrapperException' => $vendorDir . '/svyatov/curlwrapper/CurlWrapper.php',
    'CustomPermission' => $baseDir . '/app/models/CustomPermission.php',
    'DashboardAPIController' => $baseDir . '/app/controllers/api/v1/DashboardAPIController.php',
    'DeleteInactiveCISessions' => $baseDir . '/app/commands/DeleteInactiveCISessions.php',
    'DeleteUser' => $baseDir . '/app/commands/DeleteUser.php',
    'DummyAPIController' => $baseDir . '/app/controllers/api/v1/DummyAPIController.php',
    'Employee' => $baseDir . '/app/models/Employee.php',
    'EmployeeAPIController' => $baseDir . '/app/controllers/api/v1/EmployeeAPIController.php',
    'EventAPIController' => $baseDir . '/app/controllers/api/v1/EventAPIController.php',
    'EventModel' => $baseDir . '/app/models/EventModel.php',
    'EventRetailer' => $baseDir . '/app/models/EventRetailer.php',
    'EventTranslation' => $baseDir . '/app/models/EventTranslation.php',
    'GenericMallSeeder' => $baseDir . '/app/database/seeds/GenericMallSeeder.php',
    'GenericPMPUserSeeder' => $baseDir . '/app/database/seeds/GenericPMPUserSeeder.php',
    'Helper\\EloquentRecordCounter' => $baseDir . '/app/models/Helper/EloquentRecordCounter.php',
    'HomeController' => $baseDir . '/app/controllers/HomeController.php',
    'IlluminateQueueClosure' => $vendorDir . '/laravel/framework/src/Illuminate/Queue/IlluminateQueueClosure.php',
    'Inbox' => $baseDir . '/app/models/Inbox.php',
    'InboxAPIController' => $baseDir . '/app/controllers/api/v1/InboxAPIController.php',
    'IntermediateAuthBrowserController' => $baseDir . '/app/controllers/intermediate/v1/IntermediateAuthBrowserController.php',
    'IntermediateAuthController' => $baseDir . '/app/controllers/intermediate/v1/IntermediateAuthController.php',
    'IntermediateBaseController' => $baseDir . '/app/controllers/intermediate/v1/IntermediateBaseController.php',
    'IntermediateLoginController' => $baseDir . '/app/controllers/intermediate/v1/IntermediateLoginController.php',
    'InternalIntegrationAPIController' => $baseDir . '/app/controllers/api/v1/InternalIntegrationAPIController.php',
    'IssuedCoupon' => $baseDir . '/app/models/IssuedCoupon.php',
    'IssuedCouponAPIController' => $baseDir . '/app/controllers/api/v1/IssuedCouponAPIController.php',
    'Keyword' => $baseDir . '/app/models/Keyword.php',
    'KeywordObject' => $baseDir . '/app/models/KeywordObject.php',
    'Language' => $baseDir . '/app/models/Language.php',
    'LanguageAPIController' => $baseDir . '/app/controllers/api/v1/LanguageAPIController.php',
    'LanguageTableSeeder' => $baseDir . '/app/database/seeds/LanguageTableSeeder.php',
    'LippoPuriCategorySeeder' => $baseDir . '/app/database/seeds/LippoPuriCategorySeeder.php',
    'LippoPuriIconSeeder' => $baseDir . '/app/database/seeds/LippoPuriIconSeeder.php',
    'LippoPuriLanguageSeeder' => $baseDir . '/app/database/seeds/LippoPuriLanguageSeeder.php',
    'LippoPuriMallSeeder' => $baseDir . '/app/database/seeds/LippoPuriMallSeeder.php',
    'LippoPuriSettingSeeder' => $baseDir . '/app/database/seeds/LippoPuriSettingSeeder.php',
    'LippoPuriTenantCSSeeder' => $baseDir . '/app/database/seeds/LippoPuriTenantCSSeeder.php',
    'LippoPuriTenantCSSeeder2' => $baseDir . '/app/database/seeds/LippoPuriTenantCSSeeder2.php',
    'ListConnectedUser' => $baseDir . '/app/models/ListConnectedUser.php',
    'LoginAPIController' => $baseDir . '/app/controllers/api/v1/LoginAPIController.php',
    'LuckyDraw' => $baseDir . '/app/models/LuckyDraw.php',
    'LuckyDrawAPIController' => $baseDir . '/app/controllers/api/v1/LuckyDrawAPIController.php',
    'LuckyDrawAnnouncement' => $baseDir . '/app/models/LuckyDrawAnnouncement.php',
    'LuckyDrawAnnouncementTranslation' => $baseDir . '/app/models/LuckyDrawAnnouncementTranslation.php',
    'LuckyDrawCSAPIController' => $baseDir . '/app/controllers/api/v1/LuckyDrawCSAPIController.php',
    'LuckyDrawNumber' => $baseDir . '/app/models/LuckyDrawNumber.php',
    'LuckyDrawNumberAPIController' => $baseDir . '/app/controllers/api/v1/LuckyDrawNumberAPIController.php',
    'LuckyDrawNumberReceipt' => $baseDir . '/app/models/LuckyDrawNumberReceipt.php',
    'LuckyDrawNumberReceiptAPIController' => $baseDir . '/app/controllers/api/v1/LuckyDrawNumberReceiptAPIController.php',
    'LuckyDrawPrize' => $baseDir . '/app/models/LuckyDrawPrize.php',
    'LuckyDrawReceipt' => $baseDir . '/app/models/LuckyDrawReceipt.php',
    'LuckyDrawTranslation' => $baseDir . '/app/models/LuckyDrawTranslation.php',
    'LuckyDrawWinner' => $baseDir . '/app/models/LuckyDrawWinner.php',
    'MacAddress' => $baseDir . '/app/models/MacAddress.php',
    'Mall' => $baseDir . '/app/models/Mall.php',
    'MallAPIController' => $baseDir . '/app/controllers/api/v1/MallAPIController.php',
    'MallGroup' => $baseDir . '/app/models/MallGroup.php',
    'MallGroupAPIController' => $baseDir . '/app/controllers/api/v1/MallGroupAPIController.php',
    'MallTenantScope' => $baseDir . '/app/models/MallTenantScope.php',
    'MallTrait' => $baseDir . '/app/models/MallTrait.php',
    'MallTypeTrait' => $baseDir . '/app/models/MallTypeTrait.php',
    'Media' => $baseDir . '/app/models/Media.php',
    'Membership' => $baseDir . '/app/models/Membership.php',
    'MembershipAPIController' => $baseDir . '/app/controllers/api/v1/MembershipAPIController.php',
    'MembershipNumber' => $baseDir . '/app/models/MembershipNumber.php',
    'MembershipNumberAPIController' => $baseDir . '/app/controllers/api/v1/MembershipNumberAPIController.php',
    'Merchant' => $baseDir . '/app/models/Merchant.php',
    'MerchantAPIController' => $baseDir . '/app/controllers/api/v1/MerchantAPIController.php',
    'MerchantLanguage' => $baseDir . '/app/models/MerchantLanguage.php',
    'MerchantPageView' => $baseDir . '/app/models/MerchantPageView.php',
    'MerchantRetailerScope' => $baseDir . '/app/models/MerchantRetailerScope.php',
    'MerchantSetting' => $baseDir . '/app/commands/MerchantSetting.php',
    'MerchantTax' => $baseDir . '/app/models/MerchantTax.php',
    'MerchantTaxAPIController' => $baseDir . '/app/controllers/api/v1/MerchantTaxAPIController.php',
    'MerchantTranslation' => $baseDir . '/app/models/MerchantTranslation.php',
    'MerchantTypeTrait' => $baseDir . '/app/models/MerchantTypeTrait.php',
    'MigrationOrbitCloudInitStruct' => $baseDir . '/app/database/migrations/2015_10_17_142319_migration_orbit_cloud_init_struct.php',
    'MobileCI\\MobileCIAPIController' => $baseDir . '/app/controllers/MobileCI/MobileCIAPIController.php',
    'MobileCI\\MobileCIControllerNotifications' => $baseDir . '/app/controllers/MobileCI/MobileCIControllerNotifications.php',
    'ModelStatusTrait' => $baseDir . '/app/models/ModelStatusTrait.php',
    'MysqlStoredProcedure' => $baseDir . '/app/commands/MysqlStoredProcedure.php',
    'Net\\MacAddr' => $baseDir . '/app/helpers/Net/MacAddr.php',
    'Net\\Security\\Firewall' => $baseDir . '/app/helpers/Net/Security/Firewall.php',
    'Net\\Security\\RequestAccess' => $baseDir . '/app/helpers/Net/Security/RequestAccess.php',
    'News' => $baseDir . '/app/models/News.php',
    'NewsAPIController' => $baseDir . '/app/controllers/api/v1/NewsAPIController.php',
    'NewsMerchant' => $baseDir . '/app/models/NewsMerchant.php',
    'NewsTranslation' => $baseDir . '/app/models/NewsTranslation.php',
    'OAuth\\Common\\Storage\\OrbitSession' => $baseDir . '/app/helpers/OAuth/Common/Storage/OrbitSession.php',
    'Object' => $baseDir . '/app/models/Object.php',
    'ObjectAPIController' => $baseDir . '/app/controllers/api/v1/ObjectAPIController.php',
    'ObjectRelation' => $baseDir . '/app/models/ObjectRelation.php',
    'OrbitBlueprint' => $baseDir . '/app/models/OrbitBlueprint.php',
    'OrbitMySqlSchemaGrammar' => $baseDir . '/app/models/OrbitMySqlSchemaGrammar.php',
    'OrbitRelation\\BelongsTo' => $baseDir . '/app/models/OrbitRelation/BelongsTo.php',
    'OrbitRelation\\HasManyThrough' => $baseDir . '/app/models/OrbitRelation/HasManyThrough.php',
    'OrbitTestCase' => $baseDir . '/app/tests/OrbitTestCase.php',
    'OrbitVersionAPIController' => $baseDir . '/app/controllers/OrbitVersionAPIController.php',
    'Orbit\\CloudMAC' => $baseDir . '/app/helpers/Orbit/CloudMAC.php',
    'Orbit\\EncodedUUID' => $baseDir . '/app/helpers/Orbit/EncodedUUID.php',
    'Orbit\\FacebookSessionAdapter' => $baseDir . '/app/helpers/Orbit/FacebookSessionAdapter.php',
    'Orbit\\FakeJob' => $baseDir . '/app/helpers/Orbit/FakeJob.php',
    'Orbit\\Helper\\Asset\\Stylesheet' => $baseDir . '/app/helpers/Orbit/Helper/Asset/Stylesheet.php',
    'Orbit\\Helper\\Email\\MXEmailChecker' => $baseDir . '/app/helpers/Orbit/Helper/Email/MXEmailChecker.php',
    'Orbit\\Helper\\Net\\Domain' => $baseDir . '/app/helpers/Orbit/Helper/Net/Domain.php',
    'Orbit\\Helper\\Security\\Encrypter' => $baseDir . '/app/helpers/Orbit/Helper/Security/Encrypter.php',
    'Orbit\\OS\\Shutdown' => $baseDir . '/app/helpers/Orbit/OS/Shutdown.php',
    'Orbit\\RoutingServiceProvider' => $baseDir . '/app/helpers/Orbit/RoutingServiceProvider.php',
    'Orbit\\Setting' => $baseDir . '/app/helpers/Orbit/Setting.php',
    'Orbit\\Text' => $baseDir . '/app/helpers/Orbit/Text.php',
    'Orbit\\UrlGenerator' => $baseDir . '/app/helpers/Orbit/UrlGenerator.php',
    'POS\\Product' => $baseDir . '/app/models/POS/Product.php',
    'Permission' => $baseDir . '/app/models/Permission.php',
    'PermissionRole' => $baseDir . '/app/models/PermissionRole.php',
    'PersonalInterest' => $baseDir . '/app/models/PersonalInterest.php',
    'PersonalInterestAPIController' => $baseDir . '/app/controllers/api/v1/PersonalInterestAPIController.php',
    'PosQuickProduct' => $baseDir . '/app/models/PosQuickProduct.php',
    'PosQuickProductAPIController' => $baseDir . '/app/controllers/api/v1/PosQuickProductAPIController.php',
    'Product' => $baseDir . '/app/models/Product.php',
    'ProductAPIController' => $baseDir . '/app/controllers/api/v1/ProductAPIController.php',
    'ProductAttribute' => $baseDir . '/app/models/ProductAttribute.php',
    'ProductAttributeAPIController' => $baseDir . '/app/controllers/api/v1/ProductAttributeAPIController.php',
    'ProductAttributeValue' => $baseDir . '/app/models/ProductAttributeValue.php',
    'ProductRetailer' => $baseDir . '/app/models/ProductRetailer.php',
    'ProductVariant' => $baseDir . '/app/models/ProductVariant.php',
    'Promotion' => $baseDir . '/app/models/Promotion.php',
    'PromotionAPIController' => $baseDir . '/app/controllers/api/v1/PromotionAPIController.php',
    'PromotionCouponScope' => $baseDir . '/app/models/PromotionCouponScope.php',
    'PromotionCouponTrait' => $baseDir . '/app/models/PromotionCouponTrait.php',
    'PromotionRetailer' => $baseDir . '/app/models/PromotionRetailer.php',
    'PromotionRule' => $baseDir . '/app/models/PromotionRule.php',
    'PromotionTranslation' => $baseDir . '/app/models/PromotionTranslation.php',
    'Report\\CRMSummaryReportPrinterController' => $baseDir . '/app/controllers/Report/CRMSummaryReportPrinterController.php',
    'Report\\CampaignReportPrinterController' => $baseDir . '/app/controllers/Report/CampaignReportPrinterController.php',
    'Report\\CaptivePortalPrinterController' => $baseDir . '/app/controllers/Report/CaptivePortalPrinterController.php',
    'Report\\ConsumerPrinterController' => $baseDir . '/app/controllers/Report/ConsumerPrinterController.php',
    'Report\\CouponReportPrinterController' => $baseDir . '/app/controllers/Report/CouponReportPrinterController.php',
    'Report\\DataPrinterController' => $baseDir . '/app/controllers/Report/DataPrinterController.php',
    'Report\\DatabaseSimulationPrinterController' => $baseDir . '/app/controllers/Report/DatabaseSimulationPrinterController.php',
    'Retailer' => $baseDir . '/app/models/Retailer.php',
    'RetailerAPIController' => $baseDir . '/app/controllers/api/v1/RetailerAPIController.php',
    'RetailerTenant' => $baseDir . '/app/models/RetailerTenant.php',
    'Role' => $baseDir . '/app/models/Role.php',
    'RoleAPIController' => $baseDir . '/app/controllers/api/v1/RoleAPIController.php',
    'SeminyakVillageDatabaseSeeder' => $baseDir . '/app/database/seeds/SeminyakVillageDatabaseSeeder.php',
    'SessionAPIController' => $baseDir . '/app/controllers/api/v1/SessionAPIController.php',
    'SessionHandlerInterface' => $vendorDir . '/symfony/http-foundation/Symfony/Component/HttpFoundation/Resources/stubs/SessionHandlerInterface.php',
    'Setting' => $baseDir . '/app/models/Setting.php',
    'SettingAPIController' => $baseDir . '/app/controllers/api/v1/SettingAPIController.php',
    'SettingTranslation' => $baseDir . '/app/models/SettingTranslation.php',
    'ShutdownAPIController' => $baseDir . '/app/controllers/api/v1/ShutdownAPIController.php',
    'TablePromotionEmployee' => $baseDir . '/app/database/migrations/2015_12_04_080633_table_promotion_employee.php',
    'TableUserVerificationNumbers' => $baseDir . '/app/database/migrations/2015_11_23_054308_table_user_verification_numbers.php',
    'TakashimayaIconSeeder' => $baseDir . '/app/database/seeds/TakashimayaIconSeeder.php',
    'TakashimayaLogoSeeder' => $baseDir . '/app/database/seeds/TakashimayaLogoSeeder.php',
    'TakashimayaMallSeeder' => $baseDir . '/app/database/seeds/TakashimayaMallSeeder.php',
    'TakashimayaSettingSeeder' => $baseDir . '/app/database/seeds/TakashimayaSettingSeeder.php',
    'Tenant' => $baseDir . '/app/models/Tenant.php',
    'TenantAPIController' => $baseDir . '/app/controllers/api/v1/TenantAPIController.php',
    'TestCase' => $baseDir . '/app/tests/TestCase.php',
    'Text\\Util\\LineChecker' => $baseDir . '/app/helpers/Text/Util/LineChecker.php',
    'Timezone' => $baseDir . '/app/models/Timezone.php',
    'Token' => $baseDir . '/app/models/Token.php',
    'TokenAPIController' => $baseDir . '/app/controllers/api/v1/TokenAPIController.php',
    'Transaction' => $baseDir . '/app/models/Transaction.php',
    'TransactionDetail' => $baseDir . '/app/models/TransactionDetail.php',
    'TransactionDetailCoupon' => $baseDir . '/app/models/TransactionDetailCoupon.php',
    'TransactionDetailPromotion' => $baseDir . '/app/models/TransactionDetailPromotion.php',
    'TransactionDetailTax' => $baseDir . '/app/models/TransactionDetailTax.php',
    'TransactionHistoryAPIController' => $baseDir . '/app/controllers/api/v1/TransactionHistoryAPIController.php',
    'UploadAPIController' => $baseDir . '/app/controllers/api/v1/UploadAPIController.php',
    'User' => $baseDir . '/app/models/User.php',
    'UserAPIController' => $baseDir . '/app/controllers/api/v1/UserAPIController.php',
    'UserAcquisition' => $baseDir . '/app/models/UserAcquisition.php',
    'UserDetail' => $baseDir . '/app/models/UserDetail.php',
    'UserGuestSeeder' => $baseDir . '/app/database/seeds/UserGuestSeeder.php',
    'UserPersonalInterest' => $baseDir . '/app/models/UserPersonalInterest.php',
    'UserRoleTrait' => $baseDir . '/app/models/UserRoleTrait.php',
    'UserSignin' => $baseDir . '/app/models/UserSignin.php',
    'UserVerificationNumber' => $baseDir . '/app/models/UserVerificationNumber.php',
    'Whoops\\Module' => $vendorDir . '/filp/whoops/src/deprecated/Zend/Module.php',
    'Whoops\\Provider\\Zend\\ExceptionStrategy' => $vendorDir . '/filp/whoops/src/deprecated/Zend/ExceptionStrategy.php',
    'Whoops\\Provider\\Zend\\RouteNotFoundStrategy' => $vendorDir . '/filp/whoops/src/deprecated/Zend/RouteNotFoundStrategy.php',
    'Widget' => $baseDir . '/app/models/Widget.php',
    'WidgetAPIController' => $baseDir . '/app/controllers/api/v1/WidgetAPIController.php',
    'WidgetClick' => $baseDir . '/app/models/WidgetClick.php',
    'WidgetGroupName' => $baseDir . '/app/models/WidgetGroupName.php',
    'WidgetRetailer' => $baseDir . '/app/models/WidgetRetailer.php',
    'WidgetTranslation' => $baseDir . '/app/models/WidgetTranslation.php',
    'configDiffFromSample' => $baseDir . '/app/commands/configDiffFromSample.php',
    'lippopuriLogoSeeder' => $baseDir . '/app/database/seeds/LippoPuriLogoSeeder.php',
    'merchantActivation' => $baseDir . '/app/commands/merchantActivation.php',
);
