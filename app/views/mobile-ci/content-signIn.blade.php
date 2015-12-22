<div class="row header-signin">
    <div class="col-xs-12 text-center">
        <img class="img-responsive header-logo" src="{{asset($retailer->logo)}}" />
    </div>
</div>

<div class="content-signin">
    <div class="social-media-wraper">
        <div class="social-media-container">
            <div class="row">
                <div class="col-xs-4 text-center">
                    <!-- <button type="button" class="btn btn-primary icon-button facebook text-center"><i class="fa fa-facebook fa-4x"></i></button> -->
                    <form class="row" name="fbLoginForm" id="fbLoginForm" action="{{ URL::route('mobile-ci.social_login') }}" method="post">
                        <div class="form-group">
                            <input type="hidden" class="form-control" name="time" value="{{{ $orbitTime }}}"/>
                            <input type="hidden" class="form-control" name="mac_address"
                                   value="{{{ Input::get('mac_address', '') }}}"/>
                            <input type="hidden" class="form-control" name="{{{ $orbitOriginName }}}"
                                   value="{{{ $orbitToFacebookOriginValue }}}"/>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary icon-button facebook text-center">
                                    <i class="fa fa-facebook fa-4x"></i>
                            </button>
                        </div>
                        <input class="agree_to_terms" type="hidden" name="agree_to_terms" value="yes"/>
                    </form>
                </div>
                <div class="col-xs-4 text-center">
                    <!-- <button type="button" class="btn btn-danger icon-button google text-center"><i class="fa fa-google-plus fa-4x"></i></button> -->
                    <form class="row" name="googleLoginForm" id="googleLoginForm" action="{{ $googlePlusUrl }}" method="get">
                        <div class="form-group">
                            <input type="hidden" class="form-control" name="time" value="{{{ $orbitTime }}}"/>
                            <input type="hidden" class="form-control" name="mac_address" value="{{{ Input::get('mac_address', '') }}}"/>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-danger icon-button google text-center">
                                <i class="fa fa-google fa-4x"></i>
                            </button>
                        </div>
                        <input class="agree_to_terms" type="hidden" name="agree_to_terms" value="no"/>
                    </form>
                </div>
                <div class="col-xs-4 text-center">
                    <button type="button" class="btn btn-info icon-button form text-center" data-toggle="modal" data-target="#formModal"><i class="fa fa-envelope fa-3x"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="formModal" tabindex="-1" role="dialog" aria-labelledby="formModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="close close-form" data-dismiss="modal" aria-label="Close">
                    <i class="fa fa-times"></i>
                </button>

                <div id="signin-form-wrapper">
                    <form  name="signinForm" id="signinForm" action="{{ url('customer/login') }}" method="post">
                        <div class="form-group">
                            <input type="text" value="{{{ $user_email }}}" class="form-control orbit-auto-login" name="email" id="email" placeholder="{{ Lang::get('mobileci.signin.email_placeholder') }}">
                        </div>
                        <div class="modal-footer footer-form-modal">
                            <div class="row social-media-container">
                                <div class="col-xs-8 text-left orbit-auto-login">
                                    <span>Does not have account? <a href="#" id="sign-up-link">Sign up</a></span>
                                </div>
                                <div class="col-xs-4 text-right orbit-auto-login">
                                    <input type="submit" name="submit" id="btn-signin-form" class="btn btn-info icon-button form text-center orbit-auto-login" disabled value="Sign in">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div id="signup-form-wrapper" class="hide">
                    <form  name="signupForm" id="signupForm" action="{{ url('customer/login') }}" method="post">
                        <div class="form-group">
                            <input type="text" value="{{{ $user_email }}}" class="form-control orbit-auto-login" name="email" id="email" placeholder="{{ Lang::get('mobileci.signin.email_placeholder') }}">
                        </div>
                        <div class="form-group">
                            <input type="text" class="form-control userName" value="" placeholder="First Name" name="firstname">
                        </div>
                        <div class="form-group">
                            <input type="text" class="form-control" placeholder="Last Name" name="lastname">
                        </div>
                        <div class="form-group">
                            <input type="text" class="form-control" placeholder="Gender" name="gender">
                        </div>
                        <div class="form-group">
                            <input type="text" class="form-control" placeholder="Date of Birth (dd/mm/yyyy)" name="birthdate">
                        </div>
                        <div class="form-group">
                                By clicking <strong>Sign up</strong> you confirm that you accept
                                <a target="_blank" href="{{ Config::get('orbit.contact_information.privacy_policy_url') }}">Privacy Policy</a> and
                                <a target="_blank" href="{{ Config::get('orbit.contact_information.terms_of_service_url') }}">Terms and Conditions</a>
                        </div>
                        <div class="modal-footer footer-form-modal">
                            <div class="row social-media-container">
                                <div class="col-xs-8 text-left orbit-auto-login">
                                    <span>Already have an account? <a href="#" id="sign-in-link">Sign in</a></span>
                                </div>
                                <div class="col-xs-4 text-right orbit-auto-login">
                                    <input type="submit" name="submit" id="btn-signup-form" class="btn btn-info icon-button form text-center orbit-auto-login" disabled value="Sign up">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    var contentHeight = $(window).height() - 90;
    $('.content-signin').height(contentHeight);

    function isValidEmailAddress(emailAddress) {
        var pattern = new RegExp(/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i);

        return pattern.test(emailAddress);
    };

    var orbitSignUpForm = {};
    orbitSignUpForm.userActive = false;
    orbitSignUpForm.dataCompleted = false;
    orbitSignUpForm.activeForm = 'signin';
    orbitSignUpForm.formElements = ['#signupForm [name=firstname]', '#signupForm [name=lastname]', '#signupForm [name=gender]', '#signupForm [name=birthdate]'];

    /**
     * Log in the user.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @return void
     */
    orbitSignUpForm.doLogin = function()
    {
        var custEmail = $('#signinForm #email').val().trim();

        // Check if this email already registered or not
        // We suppose to not let user login when they are not registered yet
        // which is different from the old Orbit behavior
        var userIdentified = function() {
            $.ajax({
                method: 'post',
                url: apiPath + 'customer/login',
                data: {
                    email: custEmail,
                    payload: "{{{ Input::get('payload', '') }}}",
                    mac_address: {{ json_encode(Input::get('mac_address', '')) }}
                }
            }).done(function (resp, status, xhr) {
                if (resp.status === 'error') {
                    // do something
                    return;
                }

                // Cloud redirection?
                if (resp.data.redirect_to) {
                    document.location = resp.data.redirect_to;
                    return;
                }

                // Todo check the login from captive
                // the '?from_captive=yes'

                // @Todo: Replace the hardcoded name
                session_id = xhr.getResponseHeader('Set-X-Orbit-Session');
                var landing_url = '{{ $landing_url }}';

                if (session_id) {
                    if (landing_url.indexOf('orbit_session=') < 0) {
                        // orbit_session= is not exists, append manually
                        landing_url += '&orbit_session=' + session_id;
                    } else {
                        landing_url = landing_url.replace(/orbit_session=(.*)$/, 'orbit_session=' + session_id);
                    }
                }

                window.location.replace(landing_url);
            });
        }

        orbitSignUpForm.checkCustomerEmail(custEmail,
            // Send back to sign up form for unknown email
            function() {
                $('#signupForm #email').val(custEmail);
                orbitSignUpForm.switchForm('signup');
            },
            // Proceed the login for identified user
            userIdentified
        );
    }

   /**
     * Register new user.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @return void
     */
    orbitSignUpForm.doRegister = function()
    {
        var custEmail = $('#signupForm #email').val().trim();

        // Check if this email already registered or not
        // We suppose to not let user login when they are not registered yet
        // which is different from the old Orbit behavior
        var saveUser = function() {
            $.ajax({
                method: 'post',
                url: apiPath + 'customer/login',
                data: {
                    email: custEmail,
                    payload: "{{{ Input::get('payload', '') }}}",
                    mac_address: {{ json_encode(Input::get('mac_address', '')) }},
                    mode: 'registration',
                    firstname: $('#signupForm #firstname').val(),
                    lastname: $('#signupForm #lastname').val(),
                    gender: $('#signupForm #gender').val(),
                    birthdate: $('#signupForm #lastname').val()
                }
            }).done(function (resp, status, xhr) {
                if (resp.status === 'error') {
                    // do something
                    return;
                }

                console.log(resp);

                // Cloud redirection?
                if (resp.data.redirect_to) {
                    document.location = resp.data.redirect_to;
                    return;
                }

                // Todo check the login from captive
                // the '?from_captive=yes'

                // @Todo: Replace the hardcoded name
                session_id = xhr.getResponseHeader('Set-X-Orbit-Session');
                var landing_url = '{{ $landing_url }}';

                if (session_id) {
                    if (landing_url.indexOf('orbit_session=') < 0) {
                        // orbit_session= is not exists, append manually
                        landing_url += '&orbit_session=' + session_id;
                    } else {
                        landing_url = landing_url.replace(/orbit_session=(.*)$/, 'orbit_session=' + session_id);
                    }
                }

                window.location.replace(landing_url);
            });
        }

        orbitSignUpForm.checkCustomerEmail(custEmail,
            saveUser,

            // Send back to sign up form for unknown email
            function() {
                $('#signinForm #email').val(custEmail);
                orbitSignUpForm.switchForm('sign');
            }
        );
    }

    /**
     * Switch the form between sign up and sign in or toggle in between.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @param string formName
     * @return void
     */
    orbitSignUpForm.switchForm = function(formName)
    {
        theForm = formName || 'signin';

        if (theForm === 'signin') {
            $('#signin-form-wrapper').removeClass('hide');
            $('#signup-form-wrapper').addClass('hide');
        } else {
            $('#signin-form-wrapper').addClass('hide');
            $('#signup-form-wrapper').removeClass('hide');
        }
    }

    /**
     * Get the basic data to determine the way we show the form to the user.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @param string custEmail - Customer email
     * @param callback emptyCallback - Calback called when empty data returned
     * @param callback dataCallback - Callback called when user data is found
     * @return void|object
     */
    orbitSignUpForm.checkCustomerEmail = function(custEmail, emptyCallback, dataCallback)
    {
        $.ajax({
            method: 'POST',
            url: apiPath + 'customer/basic-data',
            data: { email: custEmail }
        }).done(function (data, status, xhr) {
            if (data.length === 0) {

                return emptyCallback();
            }

            return dataCallback(data[0]);
        });
    };

    /**
     * Show the sign up form since the user is either not active or the profile is not complete.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @param callback callback - Callback to run after the method finish
     * @param string cssClass - Valid value: 'hide' or 'show'
     * @return void
     */
    orbitSignUpForm.showFullForm = function(callback, cssClass)
    {
        theClass = cssClass || 'hide';

        if (cssClass !== 'hide') {
            // default value
            theClass = 'show';
        }

        for (var i=0; i<orbitSignUpForm.formElements.length; i++) {
            $(orbitSignUpForm.formElements[i]).removeClass(theClass);
        }

        // run the callback
        callback();
    }

    /**
     * Enable or disable the Sign up button depend on the completeness of the form.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @return void
     */
    orbitSignUpForm.enableDisableSignup = function()
    {
        orbitSignUpForm.dataCompleted = $('#signupForm [name=firstname]').val() &&
            $('#signupForm [name=lastname]').val() &&
            $('#signupForm [name=gender]').val() &&
            $('#signupForm [name=birthdate]').val();

        if (orbitSignUpForm.dataCompleted) {
            $('#btn-signup-form').removeAttr('disabled');
        } else {
            $('#btn-signup-form').attr('disabled', 'disabled');
        }

        console.log('orbitSignUpForm.dataCompleted: ' + orbitSignUpForm.dataCompleted);
    }

    orbitSignUpForm.boot = function()
    {
        for (var i=0; i<orbitSignUpForm.formElements.length; i++) {
            $(orbitSignUpForm.formElements[i]).keyup(function(e) {
                orbitSignUpForm.enableDisableSignup();
            });
        }

        $('#signupForm #email').keyup(function(e) {
            var value = $(this).val();

            if (isValidEmailAddress(value)) {

            }
        });

        $('#signinForm #email').keyup(function(e) {
            var value = $(this).val();

            if (isValidEmailAddress(value)) {
                $('#btn-signin-form').removeAttr('disabled');
            } else {
                $('#btn-signin-form').attr('disabled', 'disabled');
            }
        });

        $('#btn-signin-form').click(function(e)
        {
            orbitSignUpForm.doLogin();
            return false;
        });

        $('#btn-signup-form').click(function(e)
        {
            orbitSignUpForm.doRegister();
            return false;
        });

        $('#signinForm, #signupForm').submit(function(e)
        {
            e.preventDefault();
        });

        $('#sign-up-link').click(function(e)
        {
            orbitSignUpForm.switchForm('signup');
        });
        $('#sign-in-link').click(function(e)
        {
            orbitSignUpForm.switchForm('signin');
        });

        if (isValidEmailAddress( $('#signinForm #email').val() )) {
            $('#btn-signin-form').removeAttr('disabled');
        } else {
            $('#btn-signin-form').attr('disabled', 'disabled');
        }
    }

    orbitSignUpForm.boot();
</script>