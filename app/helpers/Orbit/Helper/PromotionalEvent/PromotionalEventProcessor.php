<?php namespace Orbit\Helper\PromotionalEvent;
/**
 * If the Job driver support bury() command then try to run it, otherwise
 * use the provided callback.
 *
 * @author Shelgi Prasetyo <shelgi@dominopos.com>
 */

use User;
use RewardDetailTranslation;
use RewardDetail;
use RewardDetailCode;
use UserReward;
use DB;
use Config;
use Lang;
use App;
use Language;
use News;
use Queue;

class PromotionalEventProcessor
{
    /**
     * user id.
     *
     * @var string
     */
    protected $userId = '';

    /**
     * promotional event id.
     *
     * @var string
     */
    protected $peId = '';

    /**
     * promotional event type.
     *
     * @var string
     */
    protected $peType = '';

    /**
     * language string (en, id, etc).
     *
     * @var string
     */
    protected $peLang = 'en';

    /**
     * user is already exist flag.
     *
     * @var boolean
     */
    protected $isExistingUser = FALSE;

    public function __construct($userId='', $peId='', $peType='', $language = 'en', $existingUser = '')
    {
        $this->userId = $userId;
        $this->peId = $peId;
        $this->peType = $peType;
        $this->isExistingUser = (! empty($existingUser)) ? TRUE : FALSE;
        $this->peLang = $language;
    }

    public static function create($userId='', $peId='', $peType='', $language = 'en', $existingUser = '') {

        return new Static($userId, $peId, $peType, $language, $existingUser);
    }

    /**
     * get Reward detail by news id and object type
     *
     * @param string peId
     * @param string peType
     */
    public function getRewardDetail($peId='', $peType='') {
        $rewardDetail = RewardDetail::where('object_type', $peType)
                                    ->where('object_id', $peId)
                                    ->first();

        return $rewardDetail;
    }

    public function setUser($userId) {
        $this->userId = $userId;

        return $this;
    }

    public function setPEId($peId) {
        $this->peId = $peId;

        return $this;
    }

    public function setPEType($peType) {
        $this->peType = $peType;

        return $this;
    }

    /**
     * check user reward status
     *
     * @param string userId
     * @param string peId
     * @param string peType
     */
    public function checkUserReward($userId='', $peId='', $peType='') {
        $rewardDetail = $this->getRewardDetail($peId, $peType);
        $userReward = UserReward::where('user_id', $userId)
                                ->where('reward_detail_id', $rewardDetail->reward_detail_id)
                                ->where('status', '!=', 'expired')
                                ->first();

        return $userReward;
    }

    /**
     * get available code in promotional event
     *
     * @param string userId
     * @param string peId
     * @param string peType
     */
    public function getAvailableCode($userId='', $peId='', $peType='') {
        $code = RewardDetail::leftJoin('reward_detail_codes', 'reward_detail_codes.reward_detail_id', '=', 'reward_details.reward_detail_id')
                        ->where('reward_details.object_type', $peType)
                        ->where('reward_details.object_id', $peId)
                        ->where('reward_detail_codes.status', 'available')
                        ->first();

        if (is_object($code)) {
            return [
                'status' => 'reward_ok',
                'code' => $code->reward_code
            ];
        }

        return [
            'status' => 'empty_code',
            'code' => ''
        ];
    }

    /**
     * check user role in promotional event
     *
     * @param string userId
     * @param string peId
     * @param string peType
     */
    public function format($userId='', $peId='', $peType='', $language='en', $firstTime='false') {
        $this->userId = (empty($userId)) ? $this->userId : $userId;
        $this->peId = (empty($peId)) ? $this->peId : $peId;
        $this->peType = (empty($peType)) ? $this->peType : $peType;
        $user = User::where('user_id', $this->userId)->first();
        $rewardDetail = $this->getRewardDetail($this->peId, $this->peType);
        App::setLocale($language);

        $codeMessage = Lang::get('label.promotional_event.code_message.promotion');
        $rewardType = 'promotion code';
        if ($rewardDetail->reward_type === 'lucky_draw') {
            $codeMessage = Lang::get('label.promotional_event.code_message.lucky_draw');
            $rewardType = 'lucky number';
        }

        $prefix = DB::getTablePrefix();

        $validLanguage = Language::where('name', $language)->first();
        $messageContent = News::select(DB::raw("CASE WHEN ({$prefix}reward_detail_translations.after_participation_content = ''
                                            or {$prefix}reward_detail_translations.after_participation_content is null)
                                        THEN default_translation.after_participation_content
                                        ELSE {$prefix}reward_detail_translations.after_participation_content
                                        END as after_participation_content"))
                                ->join('reward_details', 'reward_details.object_id', '=', 'news.news_id')
                                ->join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                                ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                                ->leftJoin('reward_detail_translations', function ($q) use ($validLanguage) {
                                    $q->on('reward_detail_translations.reward_detail_id', '=', 'reward_details.reward_detail_id')
                                      ->on('reward_detail_translations.language_id', '=', DB::raw("'{$validLanguage->language_id}'"));
                                })
                                ->leftJoin('reward_detail_translations as default_translation', function ($q) use ($validLanguage) {
                                    $q->on(DB::raw("default_translation.reward_detail_id"), '=', 'reward_details.reward_detail_id')
                                      ->on(DB::raw("default_translation.language_id"), '=', 'languages.language_id');
                                })
                                ->where('news.news_id', $this->peId)
                                ->where('news.is_having_reward', '=', 'Y')
                                ->first();

        // check user reward
        $userReward = $this->checkUserReward($this->userId, $this->peId, $this->peType);
        if (is_object($userReward)) {
            switch ($userReward->status) {
                case 'redeemed':
                    if (strtolower($firstTime) != 'false') {
                        return [
                            'status' => 'reward_ok',
                            'message_title' => Lang::get('label.promotional_event.information_message.reward_ok.title'),
                            'message_content' => $messageContent->after_participation_content,
                            'code_message' => $codeMessage,
                            'code' => $userReward->reward_code
                        ];
                    }

                    return [
                        'status' => 'already_got',
                        'message_title' => Lang::get('label.promotional_event.information_message.already_got.title'),
                        'message_content' => Lang::get('label.promotional_event.information_message.already_got.content'),
                        'code_message' => $codeMessage,
                        'code' => $userReward->reward_code
                    ];
                    break;

                case 'pending':
                    if ($user->status != 'active') {
                        return [
                            'status' => 'inactive_user',
                            'message_title' => Lang::get('label.promotional_event.information_message.inactive_user.title'),
                            'message_content' => Lang::get('label.promotional_event.information_message.inactive_user.content', array('type' => $rewardType)),
                            'code_message' => '',
                            'code' => ''
                        ];
                    } else {
                        return [
                            'status' => 'reward_ok',
                            'message_title' => Lang::get('label.promotional_event.information_message.reward_ok.title'),
                            'message_content' => $messageContent->after_participation_content,
                            'code_message' => $codeMessage,
                            'code' => $userReward->reward_code
                        ];
                    }
                    break;
            }
        }

        if ($rewardDetail->is_new_user_only === 'Y') {
            return [
                'status' => 'new_user_only',
                'message_title' => Lang::get('label.promotional_event.information_message.new_user_only.title'),
                'message_content' => Lang::get('label.promotional_event.information_message.new_user_only.content'),
                'code_message' => '',
                'code' => ''
            ];
        } else {
          $reward = $this->getAvailableCode($this->userId, $this->peId, $this->peType);
          switch ($reward['status']) {
            case 'reward_ok':
              return [
                  'status' => 'play_button',
                  'message_title' => '',
                  'message_content' => '',
                  'code_message' => '',
                  'code' => ''
              ];
              break;

            case 'empty_code':
              return [
                  'status' => 'empty_code',
                  'message_title' => Lang::get('label.promotional_event.information_message.empty_code.title'),
                  'message_content' => Lang::get('label.promotional_event.information_message.empty_code.content'),
                  'code_message' => '',
                  'code' => ''
              ];
              break;
          }

        }
    }

    /**
     * insert reward to database, if user get code
     *
     * @param string userId
     * @param string peId
     * @param string peType
     */
    public function insertRewardCode($userId='', $peId='', $peType='', $language='en') {
        $this->userId = (empty($userId)) ? $this->userId : $userId;
        $this->peId = (empty($peId)) ? $this->peId : $peId;
        $this->peType = (empty($peType)) ? $this->peType : $peType;
        $this->peLang = (empty($language)) ? $this->peLang : $language;
        $user = User::where('user_id', $this->userId)->first();
        App::setLocale($this->peLang);

        $rewardDetail = $this->getRewardDetail($this->peId, $this->peType);
        $userReward = $this->checkUserReward($this->userId, $this->peId, $this->peType);
        $reward = $this->getAvailableCode($this->userId, $this->peId, $this->peType);

        if (! is_object($rewardDetail)) {
            return;
        }

        // prevent existing user if reward detail only apply to new user only
        if (strtolower($rewardDetail->is_new_user_only) === 'y' && $this->isExistingUser) {
            return;
        }

        if (! is_object($userReward)) {
            if ($reward['status'] === 'empty_code') {
                return;
            }
            $code = $reward['code'];
        } else {
            $code = $userReward->reward_code;
        }

        $updateField = array('status' => 'pending',
                          'user_id' => $user->user_id,
                          'user_email' => $user->user_email);

        $status = 'pending';

        if ($user->status === 'active') {
            $updateField = array('status' => 'redeemed',
                              'user_id' => $user->user_id,
                              'user_email' => $user->user_email);
            $status = 'redeemed';

            // send the email via queue
            Queue::push('Orbit\\Queue\\PromotionalEventMail', [
                'campaignId'         => $this->peId,
                'userId'             => $user->user_id,
                'languageId'         => $language
            ]);
        }

        $updateRewardDetailCode = RewardDetailCode::where('reward_detail_id', $rewardDetail->reward_detail_id)
                                                ->where('reward_code', $code)
                                                ->update($updateField);

        if (is_object($userReward)) {
            $updateUserReward = UserReward::where('reward_detail_id', $rewardDetail->reward_detail_id)
                                          ->where('user_id', $user->user_id)
                                          ->where('reward_code', $code);

            $updateUserRewardField = array('status' => $status);
            if ($status === 'redeemed') {
                $updateUserRewardField = array('status' => $status, 'redeemed_date' => date("Y-m-d H:i:s"));
            }

            $updateUserReward->update($updateUserRewardField);

            return;
        }

        // insert to user reward
        $newUserReward = new UserReward();
        $newUserReward->reward_detail_id = $rewardDetail->reward_detail_id;
        $newUserReward->user_id = $user->user_id;
        $newUserReward->user_email = $user->user_email;
        $newUserReward->reward_code = $code;
        $newUserReward->issued_date = date("Y-m-d H:i:s");
        $newUserReward->status = $status;
        if ($status === 'redeemed') {
            $newUserReward->redeemed_date = date("Y-m-d H:i:s");
        }
        $newUserReward->save();

        return;
    }

    /**
     * get promotional event object based on type
     * (should be called after create())
     *
     * @return News or Coupon
     */
    public function getPromotionalEvent()
    {
        $reward = NULL;
        switch (strtolower($this->peType)) {
            case 'news':
                $promotionalEvent = News::where('news.news_id', $this->peId)
                    ->where('status', 'active')
                    ->where('news.is_having_reward', '=', 'Y')
                    ->first();

                if (is_object($promotionalEvent)) {
                    $reward = new \stdclass();
                    $reward->reward_id = $promotionalEvent->news_id;
                    $reward->reward_name = $promotionalEvent->news_name;
                }

                break;

            default:
                # code...
                break;
        }

        return $reward;
    }
}