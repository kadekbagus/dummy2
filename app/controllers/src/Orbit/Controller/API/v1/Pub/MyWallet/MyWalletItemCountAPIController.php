<?php namespace Orbit\Controller\API\v1\Pub\MyWallet;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;
use Activity;
use Lang;
use \Exception;
use Orbit\Helper\Util\CdnUrlGenerator;

class MyWalletItemCountAPIController extends PubControllerAPI
{
    private function getMyCouponSql($prefix, $skip = false)
    {
        return $skip ? "SELECT 0" : "
            SELECT
                COUNT({$prefix}promotions.promotion_id)
            FROM {$prefix}promotions
            INNER JOIN {$prefix}campaign_status
                ON {$prefix}promotions.campaign_status_id = {$prefix}campaign_status.campaign_status_id
            INNER JOIN {$prefix}campaign_account
                ON {$prefix}campaign_account.user_id = {$prefix}promotions.created_by
            INNER JOIN {$prefix}issued_coupons
                ON {$prefix}issued_coupons.promotion_id = {$prefix}promotions.promotion_id
                AND {$prefix}issued_coupons.status IN ('issued' , 'redeemed')
            WHERE
                {$prefix}promotions.is_coupon = 'Y' AND
                {$prefix}issued_coupons.user_id = :couponUserId AND
                {$prefix}campaign_status.campaign_status_name IN ('ongoing' , 'expired')
        ";
    }

    private function getMyRewardSql($prefix, $skip = false)
    {
        return $skip ? "SELECT 0" : "
           SELECT
               COUNT({$prefix}user_rewards.user_reward_id)
           FROM {$prefix}user_rewards
           INNER JOIN {$prefix}reward_details
               ON {$prefix}reward_details.reward_detail_id = {$prefix}user_rewards.reward_detail_id
           INNER JOIN {$prefix}news
               ON {$prefix}reward_details.object_id = {$prefix}news.news_id
           INNER JOIN {$prefix}campaign_account
               ON {$prefix}campaign_account.user_id = {$prefix}news.created_by
           INNER JOIN {$prefix}campaign_status
               ON {$prefix}campaign_status.campaign_status_id = {$prefix}news.campaign_status_id
           WHERE {$prefix}user_rewards.user_id = :rewardUserId AND
               {$prefix}user_rewards.status IN ('redeemed', 'pending') AND
               {$prefix}campaign_status.campaign_status_name IN ('ongoing', 'expired')
        ";
    }

    private function getMyPurchaseSql($prefix, $skip = false)
    {
        return $skip ? "SELECT 0" : "
            SELECT
                COUNT(DISTINCT {$prefix}payment_transactions.payment_transaction_id)
            FROM {$prefix}payment_transactions
            LEFT JOIN {$prefix}payment_transaction_details
               ON {$prefix}payment_transaction_details.payment_transaction_id = {$prefix}payment_transactions.payment_transaction_id
            INNER JOIN {$prefix}promotions
               ON {$prefix}promotions.promotion_id = {$prefix}payment_transaction_details.object_id
            INNER JOIN {$prefix}campaign_account
               ON {$prefix}campaign_account.user_id = {$prefix}promotions.created_by
            INNER JOIN {$prefix}issued_coupons
               ON {$prefix}issued_coupons.promotion_id = {$prefix}promotions.promotion_id
            WHERE
                {$prefix}payment_transactions.user_id = :trxUserId AND
                {$prefix}issued_coupons.user_id = :trxUserId2 AND
                {$prefix}issued_coupons.status != 'deleted' AND
                {$prefix}payment_transaction_details.`object_type` = 'coupon' AND
                {$prefix}payment_transactions.`payment_method` != 'normal' AND
                {$prefix}payment_transactions.`status` != 'starting'
        ";
    }

    /**
     * GET - get my wallet item counter
     * (my reward, my coupon, and purchased item counter)
     *
     * @author  Zamroni <zamroni@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getItemCount()
    {
        $httpCode = 200;
        $user = NULL;

        try {
            $user = $this->getUser();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }
            $skipMyCoupon = (OrbitInput::get('skip_my_coupon', 'N') === 'Y');
            $skipMyReward = (OrbitInput::get('skip_my_reward', 'N') === 'Y');
            $skipMyPurchase = (OrbitInput::get('skip_my_purchase', 'N') === 'Y');

            $prefix = DB::getTablePrefix();

            $myCouponSQL  = $this->getMyCouponSql($prefix, $skipMyCoupon);
            $myRewardSQL  = $this->getMyRewardSql($prefix, $skipMyReward);
            $myPurchaseListSQL  = $this->getMyPurchaseSql($prefix, $skipMyPurchase);

            $myCouponParams = $skipMyCoupon ? array() : array('couponUserId' => $user->user_id);
            $myRewardParams = $skipMyReward ? array() : array('rewardUserId' => $user->user_id);
            $myPurchaseParams = $skipMyPurchase ? array() : array('trxUserId' => $user->user_id, 'trxUserId2' => $user->user_id);

            $itemCount = DB::select(
                DB::raw("
                    SELECT
                        ({$myCouponSQL}) AS my_coupon_count,
                        ({$myRewardSQL}) AS my_reward_count,
                        ({$myPurchaseListSQL}) AS my_purchase_count
                "),
                array_merge($myCouponParams, $myRewardParams, $myPurchaseParams)
            );

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';
            $this->response->data = $itemCount;
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
            $httpCode = 500;
        }

        $output = $this->render($httpCode);

        return $output;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
