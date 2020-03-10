<?php

namespace Orbit\Controller\API\v1\Product\Repository;

use App;
use DB;
use DigitalProduct;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
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
        return DigitalProduct::select(
                'digital_product_id', 'product_name',
                'selling_price', 'product_type', 'status'
            )
            ->whenHas('product_type', function($query, $productType) {
                $query->where('product_type', $productType);
            })
            ->whenHas('keyword', function($query, $keyword) {
                $query->where('product_name', 'like', "%{$keyword}%");
            })
            ->whenHas('status', function($query, $status) {
                $query->where('status', $status);
            })
            ->orderBy(
                OrbitInput::get('sortby', 'updated_at'),
                OrbitInput::get('sortmode', 'desc')
            );
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
                    $query->when(
                        $gameSlugOrId,
                        function($gameQuery, $gameSlugOrId) use ($query) {
                            $query->with($this->buildMediaQuery());

                            $gameQuery->where('games.slug', $gameSlugOrId)
                                ->orWhere('games.game_id', $gameSlugOrId);
                        }
                    );
                },
                'provider_product' => function($query) {
                    $query->select(
                        'provider_product_id', 'provider_name',
                        'provider_product_name'
                    );
                }
            ])
            ->where('digital_products.digital_product_id', $digitalProductId);

        $this->digitalProduct = $this->digitalProduct->firstOrFail();

        return $this->digitalProduct;
    }

    /**
     * Save new digital product.
     *
     * @param  ..\Request\CreateRequest $request
     *
     * @return ..\Resource\DigitalProductResource newly created digital product
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
            $this->digitalProduct->provider_product = App::make('providerProduct');
        });

        return new DigitalProductResource($this->digitalProduct);
    }

    /**
     * Update specific digital product.
     *
     * @param  string $id the digital product id
     * @param  ..\Request\UpdateRequest $request
     *
     * @return ..\Resource\DigitalProductResource updated digital product
     */
    public function update($id, $request)
    {
        DB::transaction(function() use ($id, $request)
        {
            $this->digitalProduct = DigitalProduct::findOrFail($id);

            $this->createModelFromRequest($request);

            $this->digitalProduct->save();

            // Sync relationship between Digital Product and Game.
            $request->has('games', function($games) {
                if ($this->digitalProduct->product_type === 'game_voucher') {
                    $this->digitalProduct->games()->sync($games);
                }
            });

            // Add provider name into digital product response.
            $this->digitalProduct->provider_product = App::make('providerProduct');
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
        $request->has('type', function($type) {
            $this->digitalProduct->product_type = $type;
        });

        $request->has('name', function($name) {
            $this->digitalProduct->product_name = $name;
        });

        $request->has('code', function($code) {
            $this->digitalProduct->code = $code;
        });

        $request->has('provider_id', function($providerId) {
            $this->digitalProduct->selected_provider_product_id = $providerId;
        });

        $request->has('price', function($price) {
            $this->digitalProduct->selling_price = $price;
        });

        $request->has('displayed', function($displayed) {
            $this->digitalProduct->is_displayed = $displayed;
        });

        $request->has('promo', function($promo) {
            $this->digitalProduct->is_promo = $promo;
        });

        $request->has('status', function($status) {
            $this->digitalProduct->status = $status;
        });

        $request->has('description', function($description) {
            $this->digitalProduct->description = $description;
        });

        $request->has('notes', function($notes) {
            $this->digitalProduct->notes = $notes;
        });

        $request->has('extra_field_metadata', function($extraFieldMetadata) {
            $this->digitalProduct->extra_field_metadata = $extraFieldMetadata;
        });
    }

    /**
     * Update status specific digital product.
     *
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function updateStatus($digitalProductId)
    {
        DB::transaction(function() use ($digitalProductId)
        {
            $this->digitalProduct = DigitalProduct::findOrFail($digitalProductId);

            if ($this->digitalProduct->status == 'active') {
                $this->digitalProduct->status = 'inactive';
            } else {
                $this->digitalProduct->status = 'active';
            }

            $this->digitalProduct->save();

        });

        return $this->digitalProduct;
    }
}
