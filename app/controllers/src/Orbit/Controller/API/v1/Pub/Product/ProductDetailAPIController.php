<?php namespace Orbit\Controller\API\v1\Pub\Product;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use OrbitShop\API\v1\PubControllerAPI;

use Product;
use Lang;
use DB;
use Validator;

class ProductDetailAPIController extends PubControllerAPI
{
    protected $allowedRoles = ['product manager'];

    /**
     * GET Detail Product
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function getDetailProduct()
    {
        $httpCode = 200;
        $user = NULL;

        try {
            $user = $this->getUser();

            $productId = OrbitInput::get('product_id');

            $prefix = DB::getTablePrefix();

            $validator = Validator::make(
                array(
                    'product_id' => $productId,
                ),
                array(
                    'product_id' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $product = Product::with('media', 'categories', 'marketplaces.media', 'country')
                                ->where('product_id', $productId)
                                ->firstOrFail();

            $this->response->data = $product;
        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
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
        } catch (Exception $e) {

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);

        return $output;
    }
}