<?php

namespace Orbit\Controller\API\v1\BrandProduct;

use App;
use BrandProduct;
use DB;
use Exception;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Helper\MediaQuery;

/**
 * Brand Product Repository. An abstraction which unify various Brand Product
 * functionalities (single source of truth).
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductRepository
{
    use MediaQuery;

    protected $imagePrefix = 'brand_product_image_';

    public function __construct()
    {
        $this->setupImageUrlQuery();
    }

    public function getList($request)
    {
        return BrandProduct::search($request);
    }

    public function get($brandProductId)
    {
        return BrandProduct::with([
                'media' => $this->buildMediaQuery(),
                'categories' => function($query) {
                    $query->select('category_id', 'category_name');
                },
                'videos' => function($query) {
                    $query->select('brand_product_video_id', 'video_url');
                },
                'brand_product_variants',
            ])
            ->findOrFail($brandProductId);
    }

    /**
     * Reserve a product.
     *
     * @param  [type] $brandProductVariantId [description]
     * @param  [type] $request               [description]
     * @return [type]                        [description]
     */
    public function reserve($data)
    {
        $reservation = null;

        DB::transaction(function() use ($reservation, $data)
        {
            $reservation = new ProductReservation;
            $reservation->brand_product_variant_id = $data['variant_id'];
            $reservation->option_type = $data['option_type'];
            $reservation->option_id = $data['option_id'];

            $reservation->save();
        });

        return $reservation;
    }

    /**
     * Cancel product reservation.
     *
     * @param  [type] $brandProductId [description]
     * @return [type]                 [description]
     */
    public function cancelReservation($reservationId)
    {
        $reservation = null;

        DB::transaction(function() use ($reservation, $reservationId)
        {
            $reservation = ProductReservation::findOrFail($reservationId);
            $reservation->status = ProductReservation::STATUS_CANCELLED;
            $reservation->cancelled_by = App::make('currentUser')->user_id;
            $reservation->save();
        });

        return $reservation;
    }

    /**
     * Accept a Reservation.
     *
     * @param  [type] $reservationId [description]
     * @return [type]                [description]
     */
    public function acceptReservation($reservationId)
    {
        $reservation = null;

        DB::transaction(function() use ($reservationId, $reservation) {
            $reservation = ProductReservation::findOrFail($reservationId);
            $reservation->status = ProductReservation::STATUS_ACCEPTED;
            $reservation->save();
        });

        return $reservation;
    }

    /**
     * Decline a Reservation.
     *
     * @param  [type] $reservationId [description]
     * @return [type]                [description]
     */
    public function declineReservation($reservationId)
    {
        $reservation = null;

        DB::transaction(function() use ($reservationId, $reservation) {
            $reservation = ProductReservation::findOrFail($reservationId);
            $reservation->status = ProductReservation::STATUS_DECLINED;
            $reservation->save();
        });

        return $reservation;
    }
}
