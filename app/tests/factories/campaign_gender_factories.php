<?php

$factory('CampaignGender', [
    'campaign_type' => $faker->randomElement(['coupon', 'promotion', 'news']),
    'campaign_id'   => $faker->randomDigitNotNull,
    'gender_value'  => $faker->randomElement(['M', 'F', 'U'])
]);
