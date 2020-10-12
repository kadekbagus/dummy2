<?php

namespace Orbit\Controller\API\v1\BrandProduct\Product\DataBuilder;

use Variant;
use VariantOption;
use OrbitShop\API\v1\Helper\Input as OrbitInput;

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
            'deleted_images' => [],
        ];

        $this->request->has('product_name', function($name) use (&$data)
        {
            $data['main']['product_name'] = strip_tags($name);
        });

        $this->request->has('max_reservation_time', function($mrt) use (&$data)
        {
            $data['main']['max_reservation_time'] = $mrt;
        });

        // Specifically use OrbitInput because we only check for the
        // existance of given param, not the value (has value or not).
        OrbitInput::post('product_description', function($desc) use (&$data)
        {
            $data['main']['product_description'] = strip_tags($desc);
        });

        OrbitInput::post('tnc', function($tnc) use (&$data)
        {
            $data['main']['tnc'] = strip_tags($tnc);
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
            $data['videos'] = array_filter($videoIds, function($video) {
                return ! empty(trim($video));
            });
        });

        $this->request->has('deleted_images', function($images) use (&$data)
        {
            if (is_string($images)) {
                $data['deleted_images'] = @json_decode($images, true);
            }
            else {
                $data['deleted_images'] = $images;
            }
        });

        $this->request->has('variants', function($variants) use (&$data)
        {
            $data['variants'] = @json_decode($variants);
        });

        $this->request->has('brand_product_variants', function($bpv) use (
            &$data
        ) {
            $data['brand_product_variants'] = @json_decode($bpv);
        });

        return $data;
    }
}