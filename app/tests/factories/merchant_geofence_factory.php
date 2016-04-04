<?php
/**
 * @author Rio Astamal <rio@dominopos.com>
 */
$factory('MerchantGeofence', [
    // It should be Mall or Merchant?
    'merchant_id' => 'factory:Mall',

    // Antartica
    'position' => DB::raw("POINT(-76.336863, 25.120362)"),

    // Polygon in Antartica
    'area' => DB::raw("GeomFromText(\"POLYGON((-71.910888 -4.921875, -83.539970 -4.570313, -83.500295 59.589844, -67.474922 58.710938, -71.552741 26.718750, -71.910888 -4.921875))\")")
]);

$factory('MerchantGeofence', 'MerchantGeofence_Antartica2', [
    // It should be Mall or Merchant?
    'merchant_id' => 'factory:Mall',

    // Antartica
    'position' => DB::raw("POINT(-72.270103, 14.146998)"),

    // Polygon in Antartica
    'area' => DB::raw("GeomFromText(\"POLYGON((-71.910888 -4.921875, -83.539970 -4.570313, -83.500295 59.589844, -67.474922 58.710938, -71.552741 26.718750, -71.910888 -4.921875))\")")
]);