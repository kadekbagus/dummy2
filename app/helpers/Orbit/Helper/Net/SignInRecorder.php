<?php namespace Orbit\Helper\Net;
/**
 * Helper for recording sign up and sign in activity
 *
 * @author ahmad <ahmad@dominopos.com>
 */
use Activity;
use UserSignin;
use Orbit\Helper\PromotionalEvent\PromotionalEventProcessor;

class SignInRecorder {

    /**
     * Helper for recording sign in activity
     * @param User $user
     * @param string $from ('facebook', 'google', 'form')
     * @param Mall $mall - optional
     * @return void
     */
    public static function setSignInActivity($user, $from, $mall = NULL, $activity = NULL, $saveUserSignIn = FALSE, $rewardId = null, $rewardType = null, $language = 'en')
    {
        if (! empty($rewardId) && ! empty($rewardType)) {
            // registration activity that comes from promotional event page
            $reward = PromotionalEventProcessor::create($user->user_id, $rewardId, $rewardType, $language)->getPromotionalEvent();

            if (is_object($reward)) {
                if (is_object($user)) {
                    if (is_null($activity)) {
                        $activity = Activity::mobileci()
                            ->setActivityType('login_with_reward')
                            ->setLocation($mall)
                            ->setUser($user)
                            ->setActivityName('login_ok')
                            ->setActivityNameLong('Sign In')
                            ->setObject($user)
                            ->setObjectDisplayName($reward->reward_name)
                            ->setNotes(sprintf('Sign In via Mobile (%s) OK', ucfirst($from)))
                            ->setModuleName('Application')
                            ->responseOK();

                        $activity->save();
                    }

                    if ($saveUserSignIn) {
                        static::saveUserSignIn($user, $from, $mall, $activity);
                    }
                }
            }
        } else {
            if (is_object($user)) {
                if (is_null($activity)) {
                    $activity = Activity::mobileci()
                        ->setActivityType('login')
                        ->setLocation($mall)
                        ->setUser($user)
                        ->setActivityName('login_ok')
                        ->setActivityNameLong('Sign In')
                        ->setObject($user)
                        ->setNotes(sprintf('Sign In via Mobile (%s) OK', ucfirst($from)))
                        ->setModuleName('Application')
                        ->responseOK();

                    $activity->save();
                }

                if ($saveUserSignIn) {
                    static::saveUserSignIn($user, $from, $mall, $activity);
                }
            }
        }
    }

    /**
     * Helper for recording sign up activity
     * @param User $user
     * @param string $from ('facebook', 'google', 'form')
     * @param Mall $mall - optional
     * @return void
     */
    public static function setSignUpActivity($user, $from, $mall, $rewardId = null, $rewardType = null, $language = 'en')
    {
        if (! empty($rewardId) && ! empty($rewardType)) {
            // registration activity that comes from promotional event page
            $reward = PromotionalEventProcessor::create($user->user_id, $rewardId, $rewardType, $language)->getPromotionalEvent();

            if (is_object($reward)) {
                $activity = Activity::mobileci()
                    ->setLocation($mall)
                    ->setActivityType('registration_with_reward')
                    ->setUser($user)
                    ->setActivityName('registration_ok')
                    ->setObject($user)
                    ->setObjectDisplayName($reward->reward_name)
                    ->setModuleName('User')
                    ->setNotes($reward->reward_id)
                    ->responseOK();

                if ($from === 'facebook') {
                    $activity->setActivityNameLong('Sign Up via Mobile (Facebook)');
                } else if ($from === 'google') {
                    $activity->setActivityNameLong('Sign Up via Mobile (Google+)');
                } else if ($from === 'form') {
                    $activity->setActivityNameLong('Sign Up via Mobile (Email Address)');
                }

                $activity->save();
            }
        } else {
            $activity = Activity::mobileci()
                ->setLocation($mall)
                ->setActivityType('registration')
                ->setUser($user)
                ->setActivityName('registration_ok')
                ->setObject($user)
                ->setModuleName('User')
                ->responseOK();

            if ($from === 'facebook') {
                $activity->setActivityNameLong('Sign Up via Mobile (Facebook)')
                        ->setNotes('Sign Up via Mobile (Facebook) OK');
            } else if ($from === 'google') {
                $activity->setActivityNameLong('Sign Up via Mobile (Google+)')
                        ->setNotes('Sign Up via Mobile (Google+) OK');
            } else if ($from === 'form') {
                $activity->setActivityNameLong('Sign Up via Mobile (Email Address)')
                        ->setNotes('Sign Up via Mobile (Email Address) OK');
            }

            $activity->save();
        }
    }

    /**
     * Helper for recording user sign in
     * @param User $user
     * @param string $from ('facebook', 'google', 'form', 'guest')
     * @param Mall $mall - optional
     * @param Activity $activity - optional
     * @return void
     */
    public static function saveUserSignIn($user, $from, $mall = NULL, $activity = NULL)
    {
        if (is_object($activity)) {
            $mall_id = is_object($mall) ? $mall->merchant_id : NULL;
            $newUserSignin = new UserSignin();
            $newUserSignin->user_id = $user->user_id;
            $newUserSignin->signin_via = $from;
            $newUserSignin->location_id = $mall_id;
            $newUserSignin->activity_id = $activity->activity_id;
            $newUserSignin->save();
        }
    }
}
