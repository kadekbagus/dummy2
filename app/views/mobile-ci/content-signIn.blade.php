<div class="row header-signin">
    <div class="col-xs-12 text-center">
        <img class="img-responsive header-logo" src="{{asset($retailer->logo)}}" />
    </div>
</div>

<div class="content-signin">
    <div class="slogan-container" id="slogan-container">
        <div class="logged-in-user hide">
            {{ Lang::get('mobileci.greetings.welcome') }} {{$display_name}}
        </div>
        <div class="slogan">
            {{ Config::get('shop.start_button_label') }}
        </div>
    </div>
    <div class="social-media-wraper" id="social-media-wraper">
        <div class="logged-in-container hide">
            <div class="row">
                <div class="col-xs-12 sign-in-button">
                    <button id="logged-in-signin-button" type="button" class="btn btn-block btn-primary">{{ Lang::get('mobileci.signin.sign_in') }}</button>
                </div>
            </div>
            <br/>
            <div class="row">
                <div class="col-xs-12 text-center">
                    <em>{{ Lang::get('mobileci.signin.not') }} {{$display_name}}?</em>
                    <a id='not-me'>{{ Lang::get('mobileci.signin.click_here') }}</a>
                </div>
            </div>
        </div>
        <div class="social-media-container">
            <div class="row">
                <div class="col-xs-12 text-center label">
                    {{ Lang::get('mobileci.signin.connecting_with') }}:
            </div>
            <div class="row">
                <div class="col-xs-4 text-center">
                    <form name="fbLoginForm" id="fbLoginForm" action="{{ URL::route('mobile-ci.social_login') }}" method="post">
                        <div class="form-group">
                            <input type="hidden" class="form-control" name="time" value="{{{ $orbitTime }}}"/>
                            <input type="hidden" class="form-control" name="from_captive" value="{{{ Input::get('from_captive', '') }}}"/>
                            <input type="hidden" class="form-control" name="mac_address"
                                   value="{{{ Input::get('mac_address', '') }}}"/>
                            <input type="hidden" class="form-control" name="{{{ $orbitOriginName }}}"
                                   value="{{{ $orbitToFacebookOriginValue }}}"/>
                        </div>
                        <div class="form-group">
                            <button id="fbLoginButton" type="submit" class="btn btn-primary icon-button facebook text-center">
                                    <i class="fa fa-facebook fa-4x"></i>
                            </button>
                        </div>
                        <input class="agree_to_terms" type="hidden" name="agree_to_terms" value="yes"/>
                    </form>
                </div>
                <div class="col-xs-4 text-center">
                    <form name="googleLoginForm" id="googleLoginForm" action="{{ $googlePlusUrl }}" method="get">
                        <div class="form-group">
                            <input type="hidden" class="form-control" name="time" value="{{{ $orbitTime }}}"/>
                            <input type="hidden" class="form-control" name="from_captive" value="{{{ Input::get('from_captive', '') }}}"/>
                            <input type="hidden" class="form-control" name="mac_address" value="{{{ Input::get('mac_address', '') }}}"/>
                        </div>
                        <div class="form-group">
                            <button id="googleLoginButton" type="submit" class="btn btn-danger icon-button google text-center">
                                <i class="fa fa-google fa-4x"></i>
                            </button>
                        </div>
                        <input class="agree_to_terms" type="hidden" name="agree_to_terms" value="no"/>
                    </form>
                </div>
                <div class="col-xs-4 text-center">
                    <button type="button" class="btn btn-info icon-button form text-center" data-toggle="modal" data-target="#formModal"><i class="fa fa-pencil fa-3x"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="formModal" tabindex="-1" role="dialog" aria-labelledby="formModalLabel">
    <div class="modal-dialog">
        <div class="modal-content" id="signin-form-wrapper">
            <form  name="signinForm" id="signinForm" method="post">
                <div class="modal-body">
                    <button type="button" class="close close-form" data-dismiss="modal" aria-label="Close">
                        <i class="fa fa-times"></i>
                    </button>

                    <div class="form-group">
                        <input type="email" value="{{{ $user_email }}}" class="form-control" name="email" id="email" placeholder="{{ Lang::get('mobileci.signin.email_placeholder') }}">
                    </div>
                </div>
                <div class="modal-footer footer-form-modal">
                    <div class="row">
                        <div class="col-xs-8 text-left">
                            <span>{{ Lang::get('mobileci.signin.doesnt_have_account') }}? <a href="#" id="sign-up-link">{{ Lang::get('mobileci.signin.sign_up') }}</a></span>
                        </div>
                        <div class="col-xs-4 text-right">
                            <input type="submit" name="submit" id="btn-signin-form" class="btn btn-info icon-button form text-center" disabled value="{{ Lang::get('mobileci.signin.sign_in') }}">
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-content hide" id="signup-form-wrapper">
            <form  name="signupForm" id="signupForm" method="post">
                <div class="modal-body">
                    <button type="button" class="close close-form" data-dismiss="modal" aria-label="Close">
                        <i class="fa fa-times"></i>
                    </button>
                    
                    <span class="mandatory-label">{{ Lang::get('mobileci.signup.fields_are_mandatory') }}</span>
                    <div class="form-group">
                        <input type="email" value="{{{ $user_email }}}" class="form-control orbit-auto-login" name="email" id="email" placeholder="{{ Lang::get('mobileci.signin.email_placeholder') }}">
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control userName" value="" placeholder="{{ Lang::get('mobileci.signup.first_name') }}" name="firstname" id="firstName">
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control" placeholder="{{ Lang::get('mobileci.signup.last_name') }}" name="lastname" id="lastName">
                    </div>
                    <div class="form-group">
                        <select class="form-control" name="gender" id="gender">
                            <option value="">{{ Lang::get('mobileci.signup.gender') }}</option>
                            <option value="m">{{ Lang::get('mobileci.signup.male') }}</option>
                            <option value="f">{{ Lang::get('mobileci.signup.female') }}</option>
                        </select>
                    </div>
                    <div class="form-group date-of-birth">
                        <div class="row">
                            <div class="col-xs-4">
                                <select class="form-control" name="day">
                                    <option value="">{{ Lang::get('mobileci.signup.day') }}</option>
                                @for ($i = 1; $i <= 31; $i++)
                                    <option value="{{$i}}">{{$i}}</option>
                                @endfor
                                </select>
                            </div>
                            <div class="col-xs-4">
                                <select class="form-control" name="month">
                                    <option value="">{{ Lang::get('mobileci.signup.month') }}</option>
                                @for ($i = 1; $i <= 12; $i++)
                                    <option value="{{$i}}">{{$i}}</option>
                                @endfor
                                </select>
                            </div>
                            <div class="col-xs-4">
                                <select class="form-control" name="year">
                                    <option value="">{{ Lang::get('mobileci.signup.year') }}</option>
                                @for ($i = date('Y'); $i >= date('Y') - 150; $i--)
                                    <option value="{{$i}}">{{$i}}</option>
                                @endfor
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        {{ sprintf(Lang::get('mobileci.signup.policy_terms_message'), Config::get('orbit.contact_information.privacy_policy_url'), Config::get('orbit.contact_information.terms_of_service_url')) }}
                    </div>
                </div>
                <div class="modal-footer footer-form-modal">
                    <div class="row">
                        <div class="col-xs-8 text-left orbit-auto-login">
                            <span>{{{ Lang::get('mobileci.signup.already_have_an_account') }}}? <a href="#" id="sign-in-link">{{{ Lang::get('mobileci.signin.sign_in') }}}</a></span>
                        </div>
                        <div class="col-xs-4 text-right orbit-auto-login">
                            <input type="submit" name="submit" id="btn-signup-form" class="btn btn-info icon-button form text-center orbit-auto-login" disabled value="{{ Lang::get('mobileci.signin.sign_up') }}">
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="text/javascript">
    toastr.options.closeButton = true;
    toastr.options.closeDuration = 300;
    
    $('.content-signin').height('100%');
    
    $('#formModal').on('show.bs.modal', function () {
        $('#slogan-container, #social-media-wraper').addClass('hide');
    });

    $('#formModal').on('shown.bs.modal', function () {
        $('#signinForm #email').focus();
        $('#signupForm #firstName').focus();
    });

    $('#formModal').on('hide.bs.modal', function () {
        $('#slogan-container, #social-media-wraper').removeClass('hide');
    });

    function isValidEmailAddress(emailAddress) {
        var pattern = new RegExp(/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i);

        return pattern.test(emailAddress);
    };

    var orbitSignUpForm = {
        'userActive': false,
        'dataCompleted': false,
        'activeForm': 'signin',
        'formElementsInput': [
            '#firstName',
            '#lastName'
        ],
        'formElementsSelect': [
            '#gender',
            '#signupForm [name=day]',
            '#signupForm [name=month]',
            '#signupForm [name=year]'
        ]
    };

    /**
     * Log in the user.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @return void
     */
    orbitSignUpForm.doLogin = function() {
        var custEmail = $('#signinForm #email').val().trim();

        // Flag the processing
        if (orbitSignUpForm.isProcessing) {
            return;
        }
        orbitSignUpForm.isProcessing = true;
        orbitSignUpForm.disableEnableAllButton();

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
                    mac_address: {{ json_encode(Input::get('mac_address', '')) }},
                    auto_login: "{{{ Input::get('auto_login', 'no') }}}",
                    from_captive: "{{{ Input::get('from_captive', 'no') }}}"
                }
            }).done(function (response, status, xhr) {
                if (response.code !== 0 && response.code !== 302) {
                    toastr.error(response.message);
                    return;
                }
                var shiftHostName = window.location.hostname.split('.');
                    shiftHostName.shift();
                var baseDomain = shiftHostName.join('.');
                $.cookie('login_from', 'Form', {
                    path: '/',
                    expires: 3650,
                    domain: baseDomain
                });
                // Cloud redirection?
                if (response.data.redirect_to) {
                    document.location = response.data.redirect_to;
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
            }).fail(function (data) {
                orbitSignUpForm.isProcessing = false;

                // Something bad happens
                // @todo isplay this the error
                orbitSignUpForm.disableEnableAllButton();
            });
        };

        orbitSignUpForm.checkCustomerEmail(custEmail,
            // Send back to sign up form for unknown email
            function() {
                $('#signupForm #email').val(custEmail);
                orbitSignUpForm.isProcessing = false;
                orbitSignUpForm.disableEnableAllButton();

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

        // Flag the processing
        if (orbitSignUpForm.isProcessing) {
            return;
        }
        orbitSignUpForm.isProcessing = true;
        orbitSignUpForm.disableEnableAllButton();

        // Check if this email already registered or not
        // We suppose to not let user login when they are not registered yet
        // which is different from the old Orbit behavior
        var saveUser = function() {
            var birthdate = {
                'day': $('#signupForm [name=day]').val(),
                'month': $('#signupForm [name=month]').val(),
                'year': $('#signupForm [name=year]').val()
            };

            $.ajax({
                method: 'post',
                url: apiPath + 'customer/login',
                data: {
                    email: custEmail,
                    payload: "{{{ Input::get('payload', '') }}}",
                    mac_address: {{ json_encode(Input::get('mac_address', '')) }},
                    mode: 'registration',
                    first_name: $('#firstName').val(),
                    last_name: $('#lastName').val(),
                    gender: $('#gender').val(),
                    birth_date: birthdate.day + '-' + birthdate.month + '-' + birthdate.year
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
            }).fail(function (data) {
                orbitSignUpForm.isProcessing = false;

                // Something bad happens
                // @todo isplay this the error
                orbitSignUpForm.disableEnableAllButton();
            });
        }

        orbitSignUpForm.checkCustomerEmail(custEmail,
            saveUser,

            // Send back to sign in form if it is known user
            function() {
                $('#signinForm #email').val(custEmail);
                orbitSignUpForm.isProcessing = false;
                orbitSignUpForm.disableEnableAllButton();

                orbitSignUpForm.switchForm('signin');
            }
        );
    }

   /**
     * Disable or enable the sign up and sign in button.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @return void
     */
    orbitSignUpForm.disableEnableAllButton = function () {
        if (!orbitSignUpForm.isProcessing) {
            $('#spinner-backdrop').addClass('hide');
            return;
        }

        $('#spinner-backdrop').removeClass('hide');
    }

    /**
     * Switch the form between sign up and sign in or toggle in between.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @param string formName
     * @return void
     */
    orbitSignUpForm.switchForm = function(formName) {
        theForm = formName || 'signin';

        if (theForm === 'signin') {
            $('#signin-form-wrapper').removeClass('hide');
            $('#signup-form-wrapper').addClass('hide');
            $('#signinForm #email').focus();
        } else {
            $('#signin-form-wrapper').addClass('hide');
            $('#signup-form-wrapper').removeClass('hide');
            $('#signupForm #firstName').focus();
        }
    };

    /**
     * Get the basic data to determine the way we show the form to the user.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @param string custEmail - Customer email
     * @param callback emptyCallback - Calback called when empty data returned
     * @param callback dataCallback - Callback called when user data is found
     * @return void|object
     */
    orbitSignUpForm.checkCustomerEmail = function(custEmail, emptyCallback, dataCallback) {
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
    orbitSignUpForm.showFullForm = function(callback, cssClass) {
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
    orbitSignUpForm.enableDisableSignup = function() {
        orbitSignUpForm.dataCompleted = $('#firstName').val() &&
            $('#lastName').val() &&
            $('#gender').val() &&
            $('#signupForm [name=day]').val() &&
            $('#signupForm [name=month]').val() &&
            $('#signupForm [name=year]').val();

        if (orbitSignUpForm.dataCompleted) {
            $('#btn-signup-form').removeAttr('disabled');
        } else {
            $('#btn-signup-form').attr('disabled', 'disabled');
        }
    }

    var errorValidationFn = function () {
        var errorMessage = '{{isset($error) ? $error : 'No Error'}}';
        if (errorMessage !== 'No Error') {
            toastr(errorMessage);
            $('#spinner-backdrop').addClass('hide');
        }
    },
    inProgressFn = function () {
        var progressStatus = {{isset($isInProgress) ? $isInProgress : 'false'}};
        if (progressStatus === true) {
            $('#spinner-backdrop').removeClass('hide');
            return;
        }
        $('#spinner-backdrop').addClass('hide');
    },
    isSignedInFn = function () {
        var displayName = '{{isset($display_name) ? $display_name : ''}}',
            userEmail = '{{isset($user_email) ? $user_email : ''}}';

        if (displayName === '' && userEmail === '') {
            $('.logged-in-user').addClass('hide');
            $('.logged-in-container').addClass('hide');

            $('.social-media-container').removeClass('hide');
            return;
        }

        $('.logged-in-user').removeClass('hide');
        $('.logged-in-container').removeClass('hide');

        $('.social-media-container').addClass('hide');
    },
    isFromCaptiveFn = function () {
        if ('{{{ Input::get('from_captive', 'no') }}}' === 'yes') {
            $('#social-media-wraper').addClass('hide');
        }
    };

    orbitSignUpForm.boot = function() {
        isSignedInFn();
        inProgressFn();
        isFromCaptiveFn();
        errorValidationFn();

        for (var i=0; i<orbitSignUpForm.formElementsInput.length; i++) {
            $(orbitSignUpForm.formElementsInput[i]).keyup(function(e) {
                orbitSignUpForm.enableDisableSignup();
            });
        }

        for (var i=0; i<orbitSignUpForm.formElementsSelect.length; i++) {
            $(orbitSignUpForm.formElementsSelect[i]).change(function(e) {
                orbitSignUpForm.enableDisableSignup();
            });
        }

        $('#signupForm #email').keyup(function(e) {
            var value = $(this).val();

            if (isValidEmailAddress(value)) {

            }
        });

        $('#signinForm #email').on('input', function(e) {
            var value = $(this).val();

            if (isValidEmailAddress(value)) {
                $('#btn-signin-form').removeAttr('disabled');
            } else {
                $('#btn-signin-form').attr('disabled', 'disabled');
            }
        });

        $('#logged-in-signin-button').click(function() {
            var loginFrom = '{{$login_from}}';

            switch (loginFrom) {
                case 'Form':
                    orbitSignUpForm.doLogin();
                    break;
                case 'Facebook':
                    $('#fbLoginButton').click();
                    break;
                case 'Google':
                    $('#googleLoginButton').click();
                    break;
            }
        });

        $('#not-me').click(function () {
            var currentDomain = orbitGetDomainName();
            $.removeCookie('orbit_email', {path: '/', domain: currentDomain});
            $.removeCookie('orbit_firstname', {path: '/', domain: currentDomain});
            window.location.replace('/customer/logout?not_me=true');
        });

        $('#btn-signin-form').click(function(e) {
            orbitSignUpForm.doLogin();
            return false;
        });

        $('#btn-signup-form').click(function(e) {
            orbitSignUpForm.doRegister();
            return false;
        });

        $('#signinForm, #signupForm').submit(function(e) {
            e.preventDefault();
        });

        $('#sign-up-link').click(function(e) {
            orbitSignUpForm.switchForm('signup');
        });

        $('#sign-in-link').click(function(e) {
            orbitSignUpForm.switchForm('signin');
        });

        if (isValidEmailAddress( $('#signinForm #email').val() )) {
            $('#btn-signin-form').removeAttr('disabled');
        }
    }

    orbitSignUpForm.boot();
</script>
