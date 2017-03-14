<?php

/*
|--------------------------------------------------------------------------
| Register The Artisan Commands
|--------------------------------------------------------------------------
|
| Each available Artisan command must be registered with the console so
| that it is available to be called. We'll register every command so
| the console gets access to each of the command object instances.
|
*/

// Merchant or Retailer activation
Artisan::add(new merchantActivation);

// Compile Orbit Routes into single file
Artisan::add(new CompileOrbitRoutes);

// Delete User based on email or ID
Artisan::add(new DeleteUser);

// Delete User based on email or ID
Artisan::add(new ClearBeanstalkdQueueCommand);

// diff configs vs sample and report differences.
Artisan::add(new configDiffFromSample);

// Insert or delete agreement on settings table
Artisan::add(new ConfigAgreement);

// Delete Inactive CI sessions
Artisan::add(new DeleteInactiveCISessions);

// Insert campaign base price for merchant
Artisan::add(new BasePrice);

//Create/install function and stored procedure
Artisan::add(new MysqlStoredProcedure);

// Delete Inactive CI sessions
Artisan::add(new MerchantLogoCommand);

// Set campaign status to expired when past the date and time
Artisan::add(new CampaignSetToExpired);

// Campaign spending counting
Artisan::add(new CampaignDailySpendingCalculation);

// Campaign daily spending mmigration
Artisan::add(new CampaignDailySpendingMigration);

// Merchant geolocation
Artisan::add(new MerchantGeolocation);

// Tenant Import
Artisan::add(new TenantImport);

// Elasticsearch Migration
Artisan::add(new ElasticsearchMigrationCommand);

// Category Migration
Artisan::add(new CategoryMigration);

// DeleteGuestViewItemUser
Artisan::add(new DeleteGuestViewItemUser);

// DeleteGuestUser
Artisan::add(new DeleteGuestUser);

// DestroyMall
Artisan::add(new DestroyMall);

// Send newsletter email
Artisan::add(new NewsletterSenderCommand);

// Insert or delete pokestop map
Artisan::add(new PokestopMap);

// Import DB IP
Artisan::add(new ImportDBIP);

// Create table for DB IP
Artisan::add(new CreateTableDBIP);

// Update es mall index
Artisan::add(new ElasticsearchUpdateMallIndex);

// Update ES mall logo
Artisan::add(new ElasticsearchUpdateMallLogoCommand);

// Send Email about campaign expired
Artisan::add(new SendEmailCampaignExpired);

// Update ES mall is_subscribed
Artisan::add(new ElasticsearchUpdateMallIsSubscribed);

// Sync to elasticsearch from activity table
Artisan::add(new ElasticsearchResyncActivityCommand);

// Shorten Coupon Url
Artisan::add(new UrlShortenerCommand);

// Create User
Artisan::add(new CreateUserCommand);

// Update category image
Artisan::add(new CategoryImageCommand);

// Create Partner Link
Artisan::add(new CreatePartnerLinkCommand);

// Create Partner Competitor
Artisan::add(new CreatePartnerCompetitorCommand);

// Sync to elasticsearch from merchants table (mall)
Artisan::add(new ElasticsearchResyncMallCommand);

// Initial coupon data migration
Artisan::add(new ElasticsearchResyncCouponCommand);

// Initial news/event data migration
Artisan::add(new ElasticsearchResyncNewsCommand);

// Initial Promotion data migration
Artisan::add(new ElasticsearchResyncPromotionCommand);

// Initial Promotion data migration
Artisan::add(new ElasticsearchResyncStoreCommand);

// Clear Promotion data in ES
Artisan::add(new ElasticsearchClearPromotionCommand);

// Clear News data in ES
Artisan::add(new ElasticsearchClearNewsCommand);

// Clear Coupon data in ES
Artisan::add(new ElasticsearchClearCouponCommand);

// Generate Sitemap
Artisan::add(new GenerateSitemapCommand);

// Upload images to S3
Artisan::add(new CdnS3UploadCommand);

// List all active promotions
Artisan::add(new GetListActivePromotionCommand);

// List all active news
Artisan::add(new GetListActiveNewsCommand);

// List all active coupon
Artisan::add(new GetListActiveCouponCommand);

// List all active stores
Artisan::add(new GetListActiveStoreCommand);

// List all active malls
Artisan::add(new GetListActiveMallCommand);

// Fill table mall_countries
Artisan::add(new FillTableMallCountriesCommand);

// Fill table mall_cities
Artisan::add(new FillTableMallCitiesCommand);

// Fill table db_ip_countries
Artisan::add(new FillTableDBIPCountriesCommand);

// Fill table db_ip_cities
Artisan::add(new FillTableDBIPCitiesCommand);

// Map Country Vendor to GTM
Artisan::add(new MapVendorCoutriesCommand);

// Map Country Vendor to GTM
Artisan::add(new MapVendorCitiesCommand);

// Create or update page multilanguage
Artisan::add(new CreatePageCommand);

// Sync to elasticsearch from merchants table (mall) to mall suggestion index
Artisan::add(new ElasticsearchResyncMallSuggestionCommand);

// Sync to elasticsearch from news table to news suggestion index
Artisan::add(new ElasticsearchResyncNewsSuggestionCommand);

// Sync to elasticsearch from promotion table to promotion suggestion index
Artisan::add(new ElasticsearchResyncPromotionSuggestionCommand);

// Sync to elasticsearch from coupon table to coupon suggestion index
Artisan::add(new ElasticsearchResyncCouponSuggestionCommand);

// Sync to elasticsearch from merchant(store) table to store suggestion index
Artisan::add(new ElasticsearchResyncStoreSuggestionCommand);

// Delete not active campaign in suggest index elasticsearch
Artisan::add(new CampaignDeleteInactiveEsCommand);

// Sync to elasticsearch from merchant(store) table to store detail index
Artisan::add(new ElasticsearchResyncStoreDetailCommand);

// Resend Registration Email
Artisan::add(new UserResendRegistrationEmailCommand);

// Import IP2Location data to mysql database
Artisan::add(new ImportIP2Location);

// Import IP2Location city data to mysql database
Artisan::add(new FillTableIp2LocationCitiesCommand);

// Insert or update data on settings table
// @Todo investigate why its error
// Artisan::add(new MerchantSetting);