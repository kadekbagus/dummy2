<?php

namespace Orbit\Controller\API\v1\BrandProduct\Product\DataBuilder;

use Variant;
use VariantOption;

/**
 * Brand Product Update data builder.
 *
 * @author Budi <budi@gotomalls.com>
 */
class UpdateBrandProductBuilder
{
    protected $request;

    function __construct($request)
    {
        $this->request = $request;
    }

    public function build()
    {
        $data = [
            'main' => [],
            'categories' => [],
            'videos' => [],
            'variants' => [],
            'brand_product_variants' => [],
            'main_photos' => '',
            'photos' => '',
        ];

        $this->request->has('product_name', function($name) use (&$data)
        {
            $data['main']['product_name'] = $name;
        });

        $this->request->has('product_description', function($desc) use (&$data)
        {
            $data['main']['product_description'] = $desc;
        });

        $this->request->has('max_reservation_time', function($mrt) use (&$data)
        {
            $data['main']['max_reservation_time'] = $mrt;
        });

        $this->request->has('tnc', function($tnc) use (&$data)
        {
            $data['main']['tnc'] = $tnc;
        });

        $this->request->has('status', function($status) use (&$data)
        {
            $data['main']['status'] = $status;
        });

        $this->request->has('category_id', function($catId) use (&$data)
        {
            $data['categories'] = [$catId];
        });

        $this->request->has('youtube_ids', function($videoIds) use (&$data)
        {
            $data['videos'] = $videoIds;
        });

        $this->request->has('deleted_images', function($images) use (&$data)
        {
            $data['deleted_images'] = $images;
        });

        $data['variants'] = @json_decode(
            $this->request->variants
        );

        $data['brand_product_variants'] = @json_decode(
            $this->request->brand_product_variants
        );

        return $data;
    }
}