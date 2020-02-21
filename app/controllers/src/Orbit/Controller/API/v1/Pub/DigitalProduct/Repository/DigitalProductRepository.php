<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Repository;

use App;
use DB;
use DigitalProduct;
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
     * Get the digital product object.
     *
     * @return [type] [description]
     */
    public function getDigitalProduct()
    {
        return $this->digitalProduct;
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
        $sortBy = OrbitInput::get('sortby', 'updated_at');
        $sortMode = OrbitInput::get('sortmode', 'desc');

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
     * @todo  reduce query call (from request class to this function)
     * @param  [type] $digitalProductId [description]
     * @return [type]                   [description]
     */
    public function findProduct($digitalProductId = null, $gameSlugOrId = null)
    {
        $digitalProductId = $digitalProductId ?: OrbitInput::get('product_id');
        $gameSlugOrId = $gameSlugOrId ?: OrbitInput::get('game_id');

        $this->digitalProduct = DigitalProduct::with([
            'games' => function($query) use ($gameSlugOrId) {
                $query->select('games.game_id', 'game_name', 'games.slug', 'games.description', 'games.seo_text');

                // If request has gameSlug, then we need to load the game images.
                if (! empty($gameSlugOrId)) {

                    $query->where(function($query) use ($gameSlugOrId) {
                        $query->where('games.slug', $gameSlugOrId)->orWhere('games.game_id', $gameSlugOrId);
                    });

                    $query->with(['media' => function($query) {
                        $query->select('object_id', 'media_name_long', DB::raw($this->imageQuery));

                        $imageVariants = $this->resolveImageVariants('game_image_', 'mobile_medium');
                        if (! empty($imageVariants)) {
                            $query->whereIn('media_name_long', $imageVariants);
                        }
                    }]);
                }
            },
            'provider_product' => function($query) {
                $query->select('provider_product_id', 'provider_name', 'provider_product_name');
            }
        ])->where('digital_products.digital_product_id', $digitalProductId);

        $this->digitalProduct = $this->digitalProduct->firstOrFail();

        return $this->digitalProduct;
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
            if ($request->type === 'game_voucher') {
                $this->digitalProduct->games()->attach($request->games);
            }

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
            if ($request->type === 'game_voucher') {
                $this->digitalProduct->games()->sync($request->games);
            }

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
        if ($request->type) {
            $this->digitalProduct->product_type = $request->type;
        }

        $this->digitalProduct->product_name = $request->name;
        $this->digitalProduct->code = $request->code;
        $this->digitalProduct->selected_provider_product_id = $request->provider_id;
        $this->digitalProduct->selling_price = $request->price;
        $this->digitalProduct->is_displayed = $request->displayed;
        $this->digitalProduct->is_promo = $request->promo;
        $this->digitalProduct->status = $request->status;
        $this->digitalProduct->description = $request->description;
        $this->digitalProduct->notes = $request->notes;
        $this->digitalProduct->extra_field_metadata = $request->extra_field_metadata;
    }

    /**
     * Update status specific digital product.
     *
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function updateStatus($digitalProductId, $request)
    {
        DB::transaction(function() use ($digitalProductId, $request)
        {
            $this->digitalProduct = DigitalProduct::findOrFail($digitalProductId);

            if ($this->digitalProduct->status == 'active') {
                $this->digitalProduct->status = 'inactive';
            } else {
                $this->digitalProduct->status = 'active';
            }

            $this->digitalProduct->save();

        });

        return new DigitalProductResource($this->digitalProduct);
    }
}
