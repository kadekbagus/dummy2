<?php
/**
 * Merchant_Geofences Model. Contains relaration and some helpful methods.
 *
 * @author shelgi <shelgi@dominopos.com>
 * @author Rio Astamal <rio@dominopos.com>
 */

class MerchantGeofence extends Eloquent
{
    protected $table = 'merchant_geofences';

    protected $primaryKey = 'merchant_geofence_id';

	public function mall()
    {
        return $this->belongsTo('Mall', 'merchant_id', 'merchant_id');
    }

    /**
     * Add select query so it return latitude and longitude parseable column.
     *
     * @param $query QueryBuilder
     * @return QueryBuilder
     */
    public function scopeLatLong($query)
    {
        $prefix = DB::getTablePrefix();
        return $query->addSelect(DB::raw(
                        "X(${prefix}merchant_geofences.position) as latidude,
                        Y(${prefix}merchant_geofences.position) as longitude"
        ));
    }

    /**
     * Add select query so it return the area as text.
     *
     * @param $query QueryBuilder
     * @return QueryBuilder
     */
    public function scopeAreaAsText($query)
    {
        $prefix = DB::getTablePrefix();
        return $query->addSelect(DB::raw("asText(${prefix}merchant_geofences.area) as area"));
    }

    /**
     * Transform geolocation data (Polygon) of MySQL to Elasticsearch format GeoShape.
     * MySQL uses Lat1 Long1, LatN LongN, .... while
     * ES uses Long1 Lat1, LongN LatN, ....
     *
     * Example data from MySQL:
     *    GeomFromText("POLYGON((-71.910888 -4.921875, -83.539970 -4.570313, -83.500295 59.589844, -67.474922 58.710938, -71.552741 26.718750, -71.910888 -4.921875))")
     *
     * Expected output:
     *    [[-4.921875, -71.910888], [-4.570313, -83.539970], [59.589844, -83.500295], [58.710938, -67.474922], [26.718750, -71.552741], [-4.921875, -71.910888]]
     *
     * @param string $geodata
     * @return string
     */
    public static function transformPolygonToElasticsearch($geodata)
    {
        $transform = function($latlong)
        {
            list($lat, $long) = explode(' ', trim($latlong));

            return sprintf('%s %s', $long, $lat);
        };

        $geodata = str_ireplace('GeomFromText("POLYGON((', '', substr($geodata, 0, -4));
        $area = explode(',', $geodata);
        $esGeo = array_map($transform, $area);

        return $esGeo;
    }

    /**
     * Transform geolocation data (Point) of MySQL to Elasticsearch format GeoPoint.
     *
     * Example data from MySQL:
     *    POINT(1.0, 1.2)
     *
     * @param string $geodata
     * @return string
     */
    public static function transformPointToElasticSearch($geodata)
    {
        return trim(str_ireplace('point(', '', substr($geodata, 0, -1)));
    }

    /**
     * Fill the default value of area and position with NULL if there
     * is something wrong.
     *
     * @param $string $merchantId
     * @return object
     */
    public static function getDefaultValueForAreaAndPosition($merchantId)
    {
        $_geofence = new \stdClass();
        $_geofence->area = NULL;
        $_geofence->latitude = NULL;
        $_geofence->longitude = NULL;

        $geofence = MerchantGeofence::latLong()->areaAsText()
                                    ->where('merchant_id', $merchantId)
                                    ->first();

        if (! is_object($geofence)) {
            return $_geofence;
        }

        if (empty($geofence->area)) {
            $geofence->area = $_geofence->area;
        } else {
            // Make sure the data return POLYGON((...))
            if (preg_match('/^polygon\(\((.*)\)\)$/i', $geofence->area)) {
                // Everything is fine
                $geofence->area = [ static::transformPolygonToElasticsearch($geofence->area) ];
            } else {
                $geofence->area = $_geofence->area;

            }
        }

        if (empty($geofence->latidude)) {
            $geofence->latidue = $_geofence->latitude;
        }

        if (empty($geofence)) {
            $geofence->longitude = $_geofence->longitude;
        }

        return $geofence;
    }
}