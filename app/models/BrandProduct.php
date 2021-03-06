<?php

use Orbit\Controller\API\v1\Pub\BrandProduct\DataBuilder\SearchParamBuilder;
use Orbit\Helper\Searchable\Searchable;

/**
 * Brand Product Model with Searchable feature.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProduct extends Eloquent
{
    use ModelStatusTrait;

    // Enable Searchable feature.
    use Searchable;

    protected $primaryKey = 'brand_product_id';

    protected $table = 'brand_products';

    protected $searchableCache = 'brand-product-list';

    /**
     * Get search query builder instance, which helps building
     * final search query based on $request param.
     *
     * @see Orbit\Helper\Searchable\Searchable
     *
     * @return null|DataBuilder $builder builder instance or null if we don't
     *                                   need one.
     */
    public function getSearchQueryBuilder($request)
    {
        return new SearchParamBuilder($request);
    }

    public function brand()
    {
        return $this->belongsTo(
            BaseMerchant::class, 'brand_id', 'base_merchant_id'
        );
    }

    public function videos()
    {
        return $this->hasMany(BrandProductVideo::class);
    }

    public function categories()
    {
        return $this->belongsToMany(
            Category::class,
            'brand_product_categories',
            'brand_product_id',
            'category_id',
            null,
            'brand_product_category_id'
        );
    }

    public function brand_product_variants()
    {
        return $this->hasMany(BrandProductVariant::class);
    }

    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'brand_product_id')
                    ->where('object_name', 'brand_product');
    }

    public function brand_product_main_photo()
    {
        return $this->media()->where('media_name_id', 'brand_product_main_photo');
    }

    public function brand_product_photos()
    {
    	return $this->media()->where('media_name_id', 'brand_product_photos');
    }

    public function creator()
    {
        return $this->belongsTo(BppUser::class, 'created_by', 'bpp_user_id');
    }

    public function marketplaces()
    {
        return $this->belongsToMany('Marketplace', 'brand_product_link_to_object', 'brand_product_id', 'object_id')
            ->select('marketplace_id', 'name', 'brand_product_link_to_object.product_url', 'selling_price', 'original_price', 'sku')
            ->where('brand_product_link_to_object.object_type', '=', 'marketplace');
    }

    /**
     * Determine if product created by given creator.
     *
     * @param string|BppUser $creator the creator instance/signature.
     * @return bool
     */
    public function createdBy($creator)
    {
        if ($creator instanceof BppUser) {
            return $this->created_by === $creator->bpp_user_id;
        }

        if ($creator === 'me') {
            return $this->created_by === App::make('currentUser')->bpp_user_id;
        }

        if ($creator === 'admin') {
            return $this->creator->user_type === 'brand';
        }

        if ($creator === 'store') {
            return $this->creator->user_type === 'store';
        }

        return false;
    }

    public function reservation_details()
    {
        return $this->hasMany(BrandProductReservationDetail::class);
    }

    public function order_details()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function reservation_details_count()
    {
        $dbPrefix = \DB::getTablePrefix();
        $query = $this->reservation_details();

        return $query->selectRaw($dbPrefix . $query->getForeignKey() . ', sum(quantity) as total_reservation')
            ->join('brand_product_reservations', 'brand_product_reservations.brand_product_reservation_id', '=', 'brand_product_reservation_details.brand_product_reservation_id')
            ->whereIn('brand_product_reservations.status', [
                BrandProductReservation::STATUS_ACCEPTED,
                BrandProductReservation::STATUS_DONE
            ])
            ->groupBy($query->getForeignKey());
    }

    public function order_details_count()
    {
        $dbPrefix = \DB::getTablePrefix();
        $query = $this->order_details();

        return $query->selectRaw($dbPrefix . $query->getForeignKey() . ', sum(quantity) as total_order')
            ->join('orders', 'orders.order_id', '=', 'order_details.order_id')
            ->whereIn('orders.status', [
                Order::STATUS_PAID,
                Order::STATUS_READY_FOR_PICKUP,
                Order::STATUS_DONE,
                Order::STATUS_PICKED_UP,
            ])
            ->groupBy($query->getForeignKey());
    }
}
