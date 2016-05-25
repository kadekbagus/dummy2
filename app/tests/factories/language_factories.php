<?php
$factory('Language', [
    'name'    => $faker->languageCode,
    'name_native'    => $faker->locale,
    'name_long'    => $faker->country,
    'language_order'    => 0,
    'status'    => 'active',
]);