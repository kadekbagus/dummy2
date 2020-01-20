<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Repository;

use App;
use Carbon\Carbon;
use Config;
use DB;
use DigitalProduct;
use Log;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Controller\API\v1\Product\DigitalProduct\Resource\DigitalProductCollection;
use Orbit\Controller\API\v1\Product\DigitalProduct\Resource\DigitalProductResource;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Helper\MediaQuery;
use User;

/**
 * Digital Product repository.
 *
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductRepository
{
    use MediaQuery;

    private $digitalProduct = null;

    function __construct()
    {
        $this->setupImageUrlQuery();
    }

    /**
     * Find a collection of products.
     *
     * @return [type] [description]
     */
    public function findProducts()
    {
        $skip = OrbitInput::get('skip', 0);
        $take = OrbitInput::get('take', 10);
        $sortBy = OrbitInput::get('sortby', 'status');
        $sortMode = OrbitInput::get('sortmode', 'asc');

        $digitalProducts = DigitalProduct::select(
            'digital_product_id',
            'product_name',
            'selling_price',
            'product_type',
            'status'
        );

        OrbitInput::get('product_type', function($productType) use ($digitalProducts) {
            if (! empty($productType)) {
                $digitalProducts->where('product_type', $productType);
            }
        });

        OrbitInput::get('keyword', function($keyword) use ($digitalProducts) {
            if (! empty($keyword)) {
                $digitalProducts->where('product_name', 'like', "%{$keyword}%");
            }
        });

        OrbitInput::get('status', function($status) use ($digitalProducts) {
            if (! empty($status)) {
                $digitalProducts->where('status', $status);
            }
        });

        $total = clone $digitalProducts;
        $total = $total->count();

        $digitalProducts->orderBy($sortBy, $sortMode);

        $digitalProducts = $digitalProducts->skip($skip)->take($take)->get();

        return new DigitalProductCollection($digitalProducts, $total);
    }

    /**
     * Find single product.
     *
     * @param  [type] $digitalProductId [description]
     * @return [type]                   [description]
     */
    public function findProduct($digitalProductId = null)
    {
        $digitalProductId = $digitalProductId ?: OrbitInput::get('id');

        $this->digitalProduct = DigitalProduct::with([
            'games' => function($query) {
                $query->select('games.game_id', 'game_name');
            },
            'provider_product' => function($query) {
                $query->select('provider_product_id', 'provider_name');
            }
        ])->findOrFail($digitalProductId);

        return new DigitalProductResource($this->digitalProduct);
    }

    /**
     * Save new digital product.
     *
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function save($request)
    {
        DB::transaction(function() use ($request)
        {
            $this->digitalProduct = new DigitalProduct;

            $this->createModelFromRequest($request);

            $this->digitalProduct->save();

            // Attach relationship between Digital Product and Game.
            $this->digitalProduct->games()->attach($request->games);

            // Add provider_name into digital product object
            $this->digitalProduct->provider_name = App::make('providerProduct')->provider_name;
        });

        return new DigitalProductResource($this->digitalProduct);
    }

    /**
     * Update specific digital product.
     *
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function update($digitalProductId, $request)
    {
        DB::transaction(function() use ($digitalProductId, $request)
        {
            $this->digitalProduct = DigitalProduct::findOrFail($digitalProductId);

            $this->createModelFromRequest($request);

            $this->digitalProduct->save();

            // Sync (detach-attach) relationship between Digital Product and Game.
            $this->digitalProduct->games()->sync($request->games);

            // Add provider name into digital product response.
            $this->digitalProduct->provider_name = App::make('providerProduct')->provider_name;
        });

        return new DigitalProductResource($this->digitalProduct);
    }

    /**
     * Fill digital product properties from request params
     *
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    private function createModelFromRequest($request)
    {
        $this->digitalProduct->product_type = $request->type;
        $this->digitalProduct->product_name = $request->name;
        $this->digitalProduct->code = $request->code;
        $this->digitalProduct->selected_provider_product_id = $request->provider_id;
        $this->digitalProduct->selling_price = $request->price;
        $this->digitalProduct->is_displayed = $request->displayed;
        $this->digitalProduct->is_promo = $request->promo;
        $this->digitalProduct->status = $request->status;

        if (! empty($request->description)) {
            $this->digitalProduct->description = $request->description;
        }

        if (! empty($request->notes)) {
            $this->digitalProduct->notes = $request->notes;
        }

        if (! empty($request->extra_field_metadata)) {
            $this->digitalProduct->extra_field_metadata = $request->extra_field_metadata;
        }
    }

    private function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}
