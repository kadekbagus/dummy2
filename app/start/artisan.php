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
Artisan::add(new CampaignSpendingCalculation);

// Campaign spending migrate
Artisan::add(new CampaignSpendingMigrate);

// Campaign daily spending mmigration
Artisan::add(new CampaignDailySpendingMigration);

// Insert or update data on settings table
// @Todo investigate why its error
// Artisan::add(new MerchantSetting);