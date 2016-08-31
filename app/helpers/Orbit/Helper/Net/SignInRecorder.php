<?php namespace Orbit\Helper\Net;
/**
 * Helper for recording sign up and sign in activity
 *
 * @author ahmad <ahmad@dominopos.com>
 */
use Activity;
use UserSignin;

class SignInRecorder {

    /**
     * Helper for recording sign in activity
     * @param User $user
     * @param string $from ('facebook', 'google', 'form')
     * @param Mall $mall - optional
     * @return void
     */
    public static function setSignInActivity($user, $from, $mall = NULL, $activity = NULL, $saveUserSignIn = FALSE)
    {
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

    /**
     * Helper for recording sign up activity
     * @param User $user
     * @param string $from ('facebook', 'google', 'form')
     * @param Mall $mall - optional
     * @return void
     */
    public static function setSignUpActivity($user, $from, $mall)
    {
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
