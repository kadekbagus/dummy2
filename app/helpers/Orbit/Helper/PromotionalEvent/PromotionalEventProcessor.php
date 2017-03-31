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

    public function __construct($userId='', $peId='', $peType='')
    {
        $this->userId = $userId;
        $this->peId = $peId;
        $this->peType = $peType;
    }

    public function create($userId='', $peId='', $peType='') {
        return new Static($userId, $peId, $peType);
    }

    public function getRewardDetail($peId='', $peType='') {
        $rewardDetail = RewardDetail::where('object_type', $peType)
                                    ->where('object_id', $peId);
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

    public function checkUserReward($userId='', $peId='', $peType='') {
        $rewardDetail = $this->getRewardDetail($peId, $peType);
        $userReward = UserReward::where('user_id', $userId)
                                ->('reward_detail_id', $rewardDetail->reward_detail_id)
                                ->('status', '!=', 'expired')
                                ->first();

        return $userReward;
    }

    public function getAvailableCode($userId='', $peId='', $peType='') {
        $code = RewardDetail::leftJoin('reward_detail_code', 'reward_detail_code.reward_detail_id', 'reward_detail.reward_detail_id')
                        ->where('reward_detail.object_type', $peType)
                        ->where('reward_detail.object_id', $peId)
                        ->where('reward_detail_code.status', 'available')
                        ->first();

        if (is_object($code)) {
            return [
                'status' => 'reward_ok',
                'codes' => $code->reward_code
            ];
        }

        return [
            'status' => 'empty_code',
            'codes' => ''
        ];
    }

    public function format($userId='', $peId='', $peType='', $language='en') {
        $this->userId = (empty($userId)) ? $this->userId : $userId;
        $this->peId = (empty($peId)) ? $this->peId : $peId;
        $this->peType = (empty($peType)) ? $this->peType : $peType;
        $user = User::where('user_id', $this->userId)->first();
        $rewardDetail = $this->getRewardDetail($this->peId, $this->peType);
        App::setLocale($language);

        // check user reward
        $userReward = $this->checkUserReward($userId, $peId, $peType);
        if (is_object($userReward)) {
            switch ($userReward->status) {
                case 'redeemed':
                    return [
                        'status' => 'already_got',
                        'message' => Lang::get('label.promotional_event.information_message.already_got'),
                        'codes' => $userReward->reward_code
                    ];
                    break;

                case 'pending':
                    if ($user->status != 'active') {
                        return [
                            'status' => 'inactive_user',
                            'message' => Lang::get('label.promotional_event.information_message.inactive_user'),
                            'codes' => $userReward->reward_code
                        ];
                    } else {
                        return [
                            'status' => 'reward_ok',
                            'message' => Lang::get('label.promotional_event.information_message.reward_ok'),
                            'codes' => $reward['codes']
                        ];
                    }
                    break;
            }
        }

        if ($rewardDetail->is_new_user_only === 'Y') {
            return [
                'status' => 'new_user_only',
                'message' => Lang::get('label.promotional_event.information_message.new_user_only'),
                'codes' => ''
            ];
        }

        // check reward - code is still available or not
        $reward = $this->getAvailableCode($this->userId, $this->peId, $this->peType);
        switch ($reward['status']) {
            case 'empty_code':
                return [
                    'status' => 'empty_code',
                    'message' => Lang::get('label.promotional_event.information_message.empty_code'),
                    'codes' => $reward['codes']
                ];
                break;

            case 'reward_ok':
                return [
                    'status' => 'reward_ok',
                    'message' => Lang::get('label.promotional_event.information_message.reward_ok'),
                    'codes' => $reward['codes']
                ];
                break;
        }
    }

    public function insertRewardCode($userId='', $peId='', $peType='', $language='en') {
        $this->userId = (empty($userId)) ? $this->userId : $userId;
        $this->peId = (empty($peId)) ? $this->peId : $peId;
        $this->peType = (empty($peType)) ? $this->peType : $peType;
        $user = User::where('user_id', $this->userId)->first();
        App::setLocale($language);

        $rewardDetail = $this->getRewardDetail($this->peId, $this->peType);
        $userReward = $this->checkUserReward($this->userId, $this->peId, $this->peType);
        $reward = $this->getAvailableCode($this->userId, $this->peId, $this->peType);

        if (! is_object($userReward)) {
            if ($reward['status'] === 'empty_code') {
                return;
            }
            $code = $reward['codes'];
        } else {
            $code = $userReward->reward_code;
        }

        $updateField = array('status' => 'redeemed',
                              'user_id' => $user->user_id,
                              'user_email' => $user->user_email);
        $status = 'redeemed';

        if ($user->status != 'active') {
            $updateField = array('status' => 'pending',
                              'user_id' => $user->user_id,
                              'user_email' => $user->user_email);

            $status = 'pending';
        }

        $updateRewardDetailCode = RewardDetailCode::where('reward_detail_id', $rewardDetail->reward_detail_id)
                                                ->where('reward_code', $code)
                                                ->update($updateField);

        if (is_object($userReward)) {
            $updateUserReward = UserReward::where('reward_detail_id', $rewardDetail->reward_detail_id)
                                          ->where('user_id', $user->user_id)
                                          ->where('reward_code', $code)
                                          ->update(array('status' => $status));

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
        $newUserReward->save();

        return;
    }
}