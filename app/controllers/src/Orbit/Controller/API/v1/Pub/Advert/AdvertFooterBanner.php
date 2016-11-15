<?php namespace Orbit\Controller\API\v1\Pub\Advert;

use \Carbon\Carbon as Carbon;
use DB;
use Advert;
use AdvertLinkType;
use AdvertLocation;
use AdvertPlacement;

class AdvertFooterBanner
{

    public function __construct($session = NULL)
    {
        $this->session = $session;
    }

    /**
     * Static method to instantiate the class.
     */
    public static function create($session = NULL)
    {
        return new static($session);
    }

    public function getAdvertFooterBanner($location_type = 'gtm', $location_id = 0)
    {
        $now = Carbon::now('Asia/Jakarta'); // now with jakarta timezone
        $prefix = DB::getTablePrefix();

        $footer_banner = DB::table('adverts')
                        ->select(
                            'adverts.advert_id',
                            'adverts.advert_name as title',
                            'adverts.link_url',
                            'adverts.link_object_id as object_id',
                            DB::raw('alt.advert_link_name as advert_type'),
                            DB::raw('img.path as img_url')
                        )
                        ->join('advert_link_types as alt', function ($q) {
                            $q->on(DB::raw('alt.advert_link_type_id'), '=', 'adverts.advert_link_type_id')
                                ->on(DB::raw('alt.status'), '=', DB::raw("'active'"));
                        })
                        ->join('advert_locations as al', function ($q) use ($location_type, $location_id) {
                            $q->on(DB::raw('al.advert_id'), '=', 'adverts.advert_id')
                                ->on(DB::raw('al.location_type'), '=', DB::raw("{$this->quote($location_type)}"))
                                ->on(DB::raw('al.location_id'), '=', DB::raw("{$this->quote($location_id)}"));
                        })
                        ->join('advert_placements as ap', function ($q) {
                            $q->on(DB::raw('ap.advert_placement_id'), '=', 'adverts.advert_placement_id')
                                ->on(DB::raw('ap.placement_type'), '=', DB::raw("'footer_banner'"));
                        })
                        ->leftJoin('media as img', function ($q) {
                            $q->on(DB::raw('img.object_id'), '=', 'adverts.advert_id')
                                ->on(DB::raw("img.media_name_long"), '=', DB::raw("'advert_image_orig'"));
                        })
                        ->where('adverts.status', 'active')
                        ->whereRaw("{$this->quote($now)} between {$prefix}adverts.start_date and {$prefix}adverts.end_date")
                        ->orderBy(DB::raw('RAND()'))
                        ->first();

        return $footer_banner;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}