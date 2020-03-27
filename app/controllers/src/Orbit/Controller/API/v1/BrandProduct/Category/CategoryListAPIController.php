<?php

namespace Orbit\Controller\API\v1\BrandProduct\Category;

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
use Category;
use Exception;
use App;

class CategoryListAPIController extends ControllerAPI
{

    /**
     * Category list on brand product portal.
     *
     * @author ahmad <ahmad@dominopos.com>
     */
    public function getSearchCategory()
    {
        try {
            $httpCode = 200;

            $user = App::make('currentUser');
            $userId = $user->bpp_user_id;
            $brandId = $user->base_merchant_id;
            $merchantId = $user->merchant_id;

            $categories = Category::select('categories.category_id','category_name')
                ->where('merchant_id', 0)
                ->excludeDeleted('categories');

            $_categories = clone $categories;

            $take = 200;
            $categories->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $categories->skip($skip);

            $categories->orderBy('category_name', 'asc');

            $totalItems = RecordCounter::create($_categories)->count();
            $listOfItems = $categories->get();

            $data = new stdclass();
            $data->total_records = $totalItems;
            $data->returned_records = count($listOfItems);
            $data->records = $listOfItems;

            if ($totalItems === 0) {
                $data->records = NULL;
                $this->response->message = "There is no categories that matched your search criteria";
            }

            $this->response->data = $data;

        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }

}
