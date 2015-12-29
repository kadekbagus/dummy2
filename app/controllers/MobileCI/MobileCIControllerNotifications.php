<?php namespace MobileCI;

/**
 * An API controller for managing Mobile CI.
 */
use Net\MacAddr;
use Orbit\Helper\Email\MXEmailChecker;
use Orbit\CloudMAC;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \View;
use \User;
use \Token;
use \UserDetail;
use \Role;
use \Lang;
use \Language;
use \MerchantLanguage;
use \Apikey;
use \Validator;
use \Config;
use \Retailer;
use \Product;
use \Widget;
use \EventModel;
use \Promotion;
use \Coupon;
use \CartCoupon;
use \IssuedCoupon;
use Carbon\Carbon as Carbon;
use \stdclass;
use \Category;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use \Cart;
use \CartDetail;
use \Exception;
use \DB;
use \Activity;
use \Transaction;
use \TransactionDetail;
use \TransactionDetailPromotion;
use \TransactionDetailCoupon;
use \TransactionDetailTax;
use \LuckyDraw;
use \LuckyDrawNumber;
use \LuckyDrawNumberReceipt;
use \LuckyDrawReceipt;
use \LuckyDrawWinner;
use \Setting;
use URL;
use PDO;
use Response;
use LuckyDrawAPIController;
use OrbitShop\API\v1\Helper\Generator;
use Event;
use \Mall;
use \Tenant;
use Orbit\Helper\Security\Encrypter;
use Redirect;
use Cookie;
use \Inbox;
use \News;
use \Object;
use \App;
use \Media;

class MobileCIControllerNotifications extends ControllerAPI
{
    const APPLICATION_ID = 1;
    protected $session = null;


    /**
     * GET - Get notification lists
     *
     * @return Illuminate\View\View
     *
     * @author Mirza Eka <mirza@dominopos.com>
     */
    public function getNotificationsView()
    {
        $user = null;
        $keyword = null;
        $activityPage = Activity::mobileci()
            ->setActivityType('view');

        try {
            $MobileCIAPIController = new MobileCIAPIController();
            $retailer = $MobileCIAPIController->getRetailerInfo();
            /*// Require authentication
            MobileCIAPIController::registerCustomValidation();
            $user = MobileCIAPIController::getLoggedInUser();
            $retailer = MobileCIAPIController::getRetailerInfo();

            $alternateLanguage = MobileCIAPIController::getAlternateMerchantLanguage($user, $retailer);

            $sort_by = OrbitInput::get('sort_by');
            $keyword = trim(OrbitInput::get('keyword'));
            $category_id = trim(OrbitInput::get('cid'));
            $floor = trim(OrbitInput::get('floor'));

            $pagetitle = Lang::get('mobileci.page_title.promotions');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:name',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.user_sortby'),
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
            }

            $retailer = MobileCIAPIController::getRetailerInfo();

            // $categories = Category::active()->where('category_level', 1)->where('merchant_id', $retailer->merchant_id)->get();

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 300;
            }

            $mallTime = Carbon::now($retailer->timezone->timezone_name);
            $promotions = \News::active()
                ->where('mall_id', $retailer->merchant_id)
                ->where('object_type', 'promotion')
                ->whereRaw("? between begin_date and end_date", [$mallTime])
                ->orderBy('sticky_order', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            if ( ! empty($alternateLanguage) && ! empty($promotions)) {
                foreach ($promotions as $key => $val) {

                    $promotionTranslation = \NewsTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->merchant_language_id)
                        ->where('news_id', $val->news_id)->first();

                    if ( ! empty($promotionTranslation)) {
                        foreach (['news_name', 'description'] as $field) {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($promotionTranslation->{$field}) && $promotionTranslation->{$field} !== '') {
                                $val->{$field} = $promotionTranslation->{$field};
                            }
                        }

                        $media = $promotionTranslation->find($promotionTranslation->news_translation_id)
                            ->media_orig()
                            ->first();

                        if (isset($media->path)) {
                            $val->image = $media->path;
                        } else {
                            // back to default image if in the content multilanguage not have image
                            // check the system language
                            $defaultLanguage = MobileCIAPIController::getDefaultLanguage($retailer);
                            if ($defaultLanguage !== NULL) {
                                $contentDefaultLanguage = \NewsTranslation::excludeDeleted()
                                    ->where('merchant_language_id', '=', $defaultLanguage->merchant_language_id)
                                    ->where('news_id', $val->news_id)->first();

                                // get default image
                                $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->news_translation_id)
                                    ->media_orig()
                                    ->first();

                                if (isset($mediaDefaultLanguage->path)) {
                                    $val->image = $mediaDefaultLanguage->path;
                                }
                            }
                        }
                    }
                }
            }

            if ($promotions->isEmpty()) {
                $data = new stdclass();
                $data->status = 0;
            } else {
                $data = new stdclass();
                $data->status = 1;
                $data->total_records = sizeof($promotions);
                $data->returned_records = sizeof($promotions);
                $data->records = $promotions;
            }

            $languages = MobileCIAPIController::getListLanguages($retailer);

            $activityPageNotes = sprintf('Page viewed: %s', 'Promotion List Page');
            $activityPage->setUser($user)
                ->setActivityName('view_promotion_list')
                ->setActivityNameLong('View Promotion List')
                ->setObject(null)
                ->setModuleName('Promotion')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            $view_data = array(
                'page_title' => $pagetitle,
                'retailer' => $retailer,
                //'data' => $data,
                'active_user' => ($user->status === 'active'),
                'languages' => $languages,
                'user_email' => $user->user_email,
                'user' => $user
            );
            return View::make('mobile-ci.mall-coupon-list', $view_data);*/
            $pagetitle = "My Messages";

            $view_data = array(
                'page_title' => $pagetitle,
                'active_user' => true,
                'retailer' => $retailer
            );

            return View::make('mobile-ci.mall-notifications-list', $view_data);

        } catch (Exception $e) {
            /*$activityPageNotes = sprintf('Failed to view Page: %s', 'Promotion List');
            $activityPage->setUser($user)
                ->setActivityName('view_promotion_list')
                ->setActivityNameLong('View Promotion List Failed')
                ->setObject(null)
                ->setModuleName('Promotion')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();*/
            $classObj = new MobileCIAPIController();
            //$classObj->getData();

            return $classObj->redirectIfNotLoggedIn($e);
        }
    }


    /**
     * GET - Get detail for notification
     *
     * @return Illuminate\View\View
     *
     * @author Mirza Eka <mirza@dominopos.com>
     */
    public function getNotificationDetailView()
    {
        $user = null;
        $keyword = null;
        $activityPage = Activity::mobileci()
            ->setActivityType('view');

        try {
            $MobileCIAPIController = new MobileCIAPIController();
            $retailer = $MobileCIAPIController->getRetailerInfo();

            $pagetitle = "Example Title Here";
            $view_data = array(
                'page_title' => $pagetitle,
                'active_user' => true,
                'retailer' => $retailer
            );

            return View::make('mobile-ci.mall-notification-detail', $view_data);

        } catch (Exception $e) {
            $classObj = new MobileCIAPIController();

            return $classObj->redirectIfNotLoggedIn($e);
        }
    }

}