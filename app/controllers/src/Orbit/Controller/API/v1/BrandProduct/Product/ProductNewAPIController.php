<?php

namespace Orbit\Controller\API\v1\BrandProduct\Product;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Validator;

use Lang;
use Config;
use Event;
use BrandProduct;
use DB;
use Exception;
use App;
use BrandProductVideo;
use BrandProductCategory;

class ProductNewAPIController extends ControllerAPI
{

    /**
     * Create new product on brand product portal.
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function postNewProduct()
    {
        try {
            $httpCode = 200;

            $user = App::make('currentUser');
            $userId = $user->bpp_user_id;
            $brandId = $user->base_merchant_id;

            $productName = OrbitInput::post('product_name');
            $productDescription = OrbitInput::post('product_description');
            $tnc = OrbitInput::post('tnc');
            $status = OrbitInput::post('status', 'inactive');
            $maxReservationTime = OrbitInput::post('max_reservation_time', 48);
            $youtubeIds = OrbitInput::post('youtube_ids');
            $youtubeIds = (array) $youtubeIds;
            $categoryId = OrbitInput::post('category_id');

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'product_name'        => $productName,
                    'status'              => $status,
                ),
                array(
                    'product_name'        => 'required',
                    'status'              => 'in:active,inactive',
                ),
                array(
                    'product_name.required' => 'Product Name field is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $newBrandProduct = new BrandProduct();
            $newBrandProduct->brand_id = $brandId;
            $newBrandProduct->product_name = $productName;
            $newBrandProduct->product_description = $productDescription;
            $newBrandProduct->tnc = $tnc;
            $newBrandProduct->status = $status;
            $newBrandProduct->max_reservation_time = $maxReservationTime;
            $newBrandProduct->created_by = $userId;
            $newBrandProduct->save();

            // save brand_product_categories
            $newBrandProductCategories = new BrandProductCategory();
            $newBrandProductCategories->brand_product_id = $newBrandProduct->brand_product_id;
            $newBrandProductCategories->cetegory_id = $categoryId;
            $newBrandProductCategories->save();

            // save brand_product_videos
            $brandProductVideos = array();
            foreach ($youtubeIds as $youtube_id) {
                $newBrandProductVideo = new BrandProductVideo();
                $newBrandProductVideo->brand_product_id = $newBrandProduct->brand_product_id;
                $newBrandProductVideo->youtube_id = $youtube_id;
                $newBrandProductVideo->save();
                $brandProductVideos[] = $newBrandProductVideo;
            }
            $newBrandProduct->brand_product_video = $brandProductVideos;


            Event::fire('orbit.brandproduct.postnewbrandproduct.after.save', array($this, $newBrandProduct));

            $this->response->data = $newBrandProduct;

            // Commit the changes
            $this->commit();
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;

            // Rollback the changes
            $this->rollBack();
        } catch (\Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

}
