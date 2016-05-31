<?php

$factory('NewsTranslation', [
    'news_id' => 'factory:News',
    'merchant_id'  => 'factory:Merchant',
    'merchant_language_id'  => 'factory:MerchantLanguage',
    'news_name'      => $faker->sentence(3),
    'description'  => $faker->sentence(5),
    'status'    => 'active',
]);