<?php

namespace Orbit\Controller\API\v1\BrandProduct\Dashboard;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Validator;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Util\PaginationNumber;
use stdclass;
use Activity;
use Exception;
use App;
use DB;
use Media;
use Carbon\Carbon;

class TopFiveProductAPIController extends ControllerAPI
{

    /**.
     * Get top 5 viewed brand products
     *
     * @author ahmad <ahmad@gotomalls.com>
     */
    public function get()
    {
        try {
            $httpCode = 200;

            $user = App::make('currentUser');
            $userId = $user->bpp_user_id;
            $brandId = $user->base_merchant_id;
            $userType = $user->user_type;

            // @todo: Cache the result based on the brand and/or merchant ids

            // minus 7 hour GMT+7
            $start = Carbon::now()->startOfMonth()->subHours(7);

            // @todo: add filter to select all brands if user_type is GTM Admin
            $data = Activity::select(
                    DB::raw('product_id, product_name, count(activity_id) as total_view')
                )
                ->where('object_name', 'BaseMerchant')
                ->where('activity_name', 'view_instore_bp_detail_page')
                ->where('created_at', '>=', $start)
                ->groupBy('product_id')
                ->orderBy(DB::raw('total_view'), 'desc')
                ->take(5)
                ->skip(0);

            ($userType === 'gtm_admin') ? null : $data->where('object_id', $brandId);

            $data = $data->get();

            foreach ($data as $product) {
                $product->cdn_url = null;
                $product->image_url = null;

                $img = Media::where('media_name_id', 'brand_product_main_photo')
                    ->where('object_name', 'brand_product')
                    ->where('object_id', $product->product_id)
                    ->first();

                if (is_object($img)) {
                    $product->cdn_url = $img->cdn_url;
                    $product->image_url = $img->path;
                }
            }

            $this->response->data = $data;
        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }

}
