<?php namespace Orbit\Controller\API\v1\Merchant\Merchant;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Validator;
use Lang;
use BaseMerchant;
use BaseMerchantCategory;
use BaseMerchantKeyword;
use ObjectSupportedLanguage;
use BaseObjectPartner;
use Config;
use Language;
use Keyword;
use Event;
use Category;
use ObjectBank;
use ObjectFinancialDetail;
use MerchantStorePaymentProvider;
use ProductTag;
use ProductTagObject;
use BaseMerchantProductTag;
use App;
use BaseStore;
use Orbit\Controller\API\v1\Merchant\Merchant\MerchantHelper;
use Orbit\Database\ObjectID;
use DB;
use BaseStoreTranslation;
use BaseStoreProductTag;

class MerchantCopyToStoreAPIController extends ControllerAPI
{
    protected $merchantViewRoles = ['super admin', 'merchant database admin'];

    /**
     * copy base merchant enhanced fields to base store
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function postMerchantCopyToStore()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.basemerchant.postmerchantcopytostore.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.basemerchant.postmerchantcopytostore.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.basemerchant.postmerchantcopytostore.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->merchantViewRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.basemerchant.postmerchantcopytostore.after.authz', array($this, $user));

            $merchantHelper = MerchantHelper::create();
            $merchantHelper->merchantCustomValidator();

            $baseMerchantId = OrbitInput::post('base_merchant_id');

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'base_merchant_id'   => $baseMerchantId,
                ),
                array(
                    'base_merchant_id'   => 'required|orbit.empty.base_merchant',
                ),
                array(
                    'orbit.empty.base_merchant' => 'Base Merchant Not Found',
               )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $chunk = Config::get('orbit.mdm.synchronization.chunk', 50);
            $baseMerchant = App::make('orbit.empty.base_merchant');
            $baseStores = BaseStore::where('base_merchant_id', '=', $baseMerchantId);
            $_baseStores = clone $baseStores;
            $returnBaseStores = [];

            $baseStores->chunk($chunk, function($_baseStores) use ($baseMerchant, &$returnBaseStores)
            {
                foreach ($_baseStores as $store) {
                    $store->url = $baseMerchant->url;
                    $store->line_url = $baseMerchant->line_url;
                    $store->youtube_url = $baseMerchant->youtube_url;
                    $store->twitter_url = $baseMerchant->twitter_url;
                    $store->instagram_url = $baseMerchant->instagram_url;
                    $store->facebook_url = $baseMerchant->facebook_url;
                    $store->video_id_1 = $baseMerchant->video_id_1;
                    $store->video_id_2 = $baseMerchant->video_id_2;
                    $store->video_id_3 = $baseMerchant->video_id_3;
                    $store->video_id_4 = $baseMerchant->video_id_4;
                    $store->video_id_5 = $baseMerchant->video_id_5;
                    $store->video_id_6 = $baseMerchant->video_id_6;
                    $store->description = $baseMerchant->description;
                    $store->custom_title = $baseMerchant->custom_title;
                    $store->reservation_commission = $baseMerchant->reservation_commission;
                    $store->purchase_commission = $baseMerchant->purchase_commission;
                    $store->save();

                    // delete previous translation
                    $deleteTranslation = BaseStoreTranslation::where('base_store_id', '=', $store->base_store_id)->delete();

                    $translations = [];
                    foreach ($baseMerchant->baseMerchantTranslation as $base_translation) {
                        $translations[] = [ 'base_store_translation_id' => ObjectID::make(),
                                            'base_store_id' => $store->base_store_id,
                                            'language_id' => $base_translation->language_id,
                                            'description' => $base_translation->description,
                                            'custom_title' => $base_translation->custom_title,
                                            'meta_description' => $base_translation->meta_description,
                                           "created_at" => date("Y-m-d H:i:s"),
                                           "updated_at" => date("Y-m-d H:i:s") ];
                    }
                    if (! empty($translations)) {
                        DB::table('base_store_translations')->insert($translations);
                    }

                    // delete previous product tags
                    $deleteProductTag = BaseStoreProductTag::where('base_store_id', '=', $store->base_store_id)->delete();

                    // copy product tags
                    $productTags = [];
                    foreach($baseMerchant->productTags as $product_tag) {
                        $productTags[] = ["base_store_product_tag_id" => ObjectID::make(),
                                          "base_store_id" => $store->base_store_id,
                                          "product_tag_id" => $product_tag->product_tag_id,
                                          "created_at" => date("Y-m-d H:i:s"),
                                          "updated_at" => date("Y-m-d H:i:s")
                                        ];
                    }
                    if (! empty($productTags)) {
                        DB::table('base_store_product_tag')->insert($productTags);
                    }

                    $store->translation = $translations;
                    $store->product_tags = $productTags;
                    $returnBaseStores[] = $store;
                }
            });

            $this->response->data = $returnBaseStores;

            // Commit the changes
            $this->commit();

          Event::fire('orbit.basemerchant.postmerchantcopytostore.after.commit', array($this, $returnBaseStores));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.basemerchant.postmerchantcopytostore.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.basemerchant.postmerchantcopytostore.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.basemerchant.postmerchantcopytostore.query.error', array($this, $e));

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
        } catch (Exception $e) {
            Event::fire('orbit.basemerchant.postmerchantcopytostore.general.exception', array($this, $e));

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
