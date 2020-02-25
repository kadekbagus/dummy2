<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Repository;

use App;
use DB;
use DigitalProduct;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Controller\API\v1\Product\DigitalProduct\Resource\DigitalProductCollection;
use Orbit\Controller\API\v1\Product\DigitalProduct\Resource\DigitalProductResource;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Helper\MediaQuery;

/**
 * Digital Product repository.
 *
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductRepository
{
    use MediaQuery;

    protected $imagePrefix = 'game_image_';

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
     * @return Illuminate\Database\Eloquent\Builder
     */
    public function findProducts()
    {
        $sortBy = OrbitInput::get('sortby', 'updated_at');
        $sortMode = OrbitInput::get('sortmode', 'desc');

        $digitalProducts = DigitalProduct::select(
            'digital_product_id', 'product_name',
            'selling_price', 'product_type', 'status'
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

        return $digitalProducts;
    }

    /**
     * Find a single Digital Product.
     *
     * @todo  reduce query call (from request class to this function)
     * @param  string $digitalProductId
     * @param  string|null $gameSlugOrId game slug or id that linked to the product.
     *
     * @return Illuminate\Database\Eloquent\Model digital product instance.
     */
    public function findProduct($digitalProductId, $gameSlugOrId = null)
    {
        $this->digitalProduct = DigitalProduct::with([
            'games' => function($query) use ($gameSlugOrId) {
                $query->select(
                    'games.game_id', 'game_name', 'games.slug',
                    'games.description', 'games.seo_text'
                );

                // If request has game slug/id,
                // then we need to load the game images.
                if (! empty($gameSlugOrId)) {

                    $query->where(function($query) use ($gameSlugOrId) {
                        $query->where('games.slug', $gameSlugOrId)
                            ->orWhere('games.game_id', $gameSlugOrId);
                    });

                    $query->with($this->buildMediaRelation());
                }
            },
            'provider_product' => function($query) {
                $query->select(
                    'provider_product_id', 'provider_name',
                    'provider_product_name'
                );
            }
        ])->where('digital_products.digital_product_id', $digitalProductId);

        $this->digitalProduct = $this->digitalProduct->firstOrFail();

        return $this->digitalProduct;
    }

    /**
     * Save new digital product.
     *
     * @param  Orbit\Controller\API\v1\Product\DigitalProduct\Request\DigitalProductNewRequest $request
     *
     * @return Orbit\Controller\API\v1\Product\DigitalProduct\Resource\DigitalProductResource newly created digital product
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
     * @param  string $digitalProductId the digital product id that will be updated.
     * @param  Orbit\Controller\API\v1\Product\DigitalProduct\Request\DigitalProductUpdateRequest $request
     *
     * @return Orbit\Controller\API\v1\Product\DigitalProduct\Resource\DigitalProductResource updated digital product
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
     * @param  Orbit\Helper\Request\ValidateRequest $request current request instance
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
