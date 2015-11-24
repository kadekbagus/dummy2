@extends('mobile-ci.layout-headless')

@section('ext_style')
<style>
.modal-backdrop{
  z-index:0;
}
#signup{
  display: none;
}
.img-responsive{
  margin:0 auto;
}
#signedIn{
  display: none;
}
@if(!empty($bg))
  @if(!empty($bg[0]))
  body.bg{
    background: url('{{ asset($bg[0]) }}');
    background-size: cover;
    background-repeat: no-repeat;
  }
  @endif
@endif
</style>
@stop

@section('content')
<div class="row" id="signIn">
    <div class="col-xs-12">
        <header>
            <div class="row vertically-spaced">
                <div class="col-xs-12 text-center">
                    <span class="greetings">{{ Lang::get('mobileci.greetings.welcome') }}</span>
                </div>
            </div>
            <div class="row vertically-spaced">
                <div class="col-xs-12 text-center">
                    <img class="img-responsive" src="{{ asset($retailer->bigLogo) }}" />
                </div>
            </div>
        </header>

        <div id="social-wrapper">
            <form class="row" name="fbLoginForm" id="fbLoginForm" action="{{ URL::route('mobile-ci.social_login') }}" method="post">
                <div class="form-group">
                    <input type="hidden" class="form-control" name="time" value="{{{ $orbitTime }}}" />
                    <input type="hidden" class="form-control" name="{{{ $orbitOriginName }}}" value="{{{ $orbitToFacebookOriginValue }}}" />
                </div>
                <div class="form-group">
                    <button style="background-color:#3B5998;" type="submit" class="btn btn-info btn-block submit-btn" id="btn-login-form-fb"><i class="fa fa-facebook"></i> {{{ trans('mobileci.signin.login_via_facebook') }}}</button>
                </div>
                <input class="agree_to_terms" type="hidden" name="agree_to_terms" value="no" />
            </form>

            <div class="row vertically-spaced">
                <div class="col-xs-12 text-center">
                    <p>{{{ trans('mobileci.signin.or_between_email_and_fb') }}}</p>
                </div>
            </div>
        </div>

        <form name="loginForm" id="loginForm" action="{{ url('customer/login') }}" method="post">
            <div class="form-group">
                <input type="text" value="{{{ $user_email }}}" class="form-control orbit-auto-login" name="email" id="email" placeholder="{{ Lang::get('mobileci.signin.email_placeholder') }}" />
            </div>
            <div class="form-group orbit-auto-login">
                <button type="submit" class="btn btn-info btn-block">{{ $start_button_login }}</button>
            </div>

            <input class="agree_to_terms" type="hidden" name="agree_to_terms" value="no" />
        </form>

        <div class="checkbox">
            <label><input type="checkbox" id="agree_to_terms" value="yes" /> {{ $agreeToTermsLabel  }}</label>
        </div>
    </div>
</div>
<div class="row top-space" id="signedIn">
    <div class="col-xs-12">
        <header>
            <div class="row vertically-spaced">
                <div class="col-xs-12 text-center">
                    <img class="img-responsive" src="{{ asset($retailer->bigLogo) }}" />
                </div>
            </div>
        </header>
        <div class="col-xs-12 text-center welcome-user">
            <h3>{{ Lang::get('mobileci.greetings.welcome') }}, <br><span class="signedUser">{{{ $user_email or '' }}}</span><span class="userName">{{{ $display_name or '' }}}</span>!</h3>
        </div>
        <div class="col-xs-12 text-center">
            <form name="loginForm" id="loginSignedForm" action="{{ url('customer/login') }}" method="post">
                <div class="form-group">
                    <input type="hidden" class="form-control orbit-auto-login" name="email" id="emailSigned" value="{{{ $user_email }}}" />
                </div>
                <div class="form-group orbit-auto-login">
                    <button type="submit" class="btn btn-info btn-block">{{ $start_button_login }}</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-xs-12 text-center vertically-spaced orbit-auto-login">
        <a id="notMe">{{ Lang::get('mobileci.signin.not') }} <span class="signedUser">{{{ $user_email or '' }}}</span><span class="userName">{{{ $display_name or '' }}}</span>, {{ Lang::get('mobileci.signin.click_here') }}.</a>
    </div>
</div>
@stop

@section('footer')
<footer>
    <div class="row">
        <div class="col-xs-12 text-center">
            <img class="img-responsive orbit-footer"  src="{{ asset('mobile-ci/images/orbit_footer.png') }}">
        </div>
        <div class="text-center">
            {{ 'Orbit v' . ORBIT_APP_VERSION }}
        </div>
    </div>
    <div class="row">
        <div class="col-xs-12 text-center">
            <span class="fa-stack fa-lg offline">
              <i class="fa fa-globe fa-stack-1x globe"></i>
              <i id="offlinemark"></i>
            </span>
        </div>
    </div>
</footer>
@stop

@section('modals')
<!-- Modal -->
<div class="modal fade" id="errorModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title" id="myModalLabel">{{ Lang::get('mobileci.activation.error') }}</h4>
            </div>
            <div class="modal-body">
                <p id="errorModalText"></p>
            </div>
        </div>
    </div>
</div>

<!-- Privacy Policy -->
<div class="modal fade" id="privacyModal" tabindex="-1" role="dialog" aria-labelledby="privacyModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span><span class="sr-only">{{{ $closeModalText or 'OK' }}}</span>
                </button>
                <h4 class="modal-title" id="myModalLabel">Privacy Policy</h4>
            </div>
            <div class="modal-body">
                <iframe src="{{{ Config::get('orbit.contact_information.privacy_policy_url') }}}" style="zoom:0.60" frameborder="0" height="55%" width="99.6%"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Term and Condition -->
<div class="modal fade" id="tosModal" tabindex="-1" role="dialog" aria-labelledby="tosModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span><span class="sr-only">{{{ $closeModalText or 'OK' }}}</span>
                </button>
                <h4 class="modal-title" id="myModalLabel">Terms and Conditions</h4>
            </div>
            <div class="modal-body">
                <iframe src="{{{ Config::get('orbit.contact_information.terms_of_service_url') }}}" style="zoom:0.60" frameborder="0" height="55%" width="99.6%"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Ask to Accept Privacy Policy -->
<div class="modal fade" id="acceptTnCModal" tabindex="-1" role="dialog" aria-labelledby="acceptTnCModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span><span class="sr-only">{{{ $closeModalText or 'Close' }}}</span>
                </button>
                <h4 class="modal-title" id="acceptTnCModalTitle">Info</h4>
            </div>
            <div class="modal-body">
                <p id="emailModalText">{{ trans('mobileci.signin.must_accept_terms') }}.</p>
            </div>
        </div>
    </div>
</div>


@stop

@section('ext_script_bot')
  {{ HTML::script('mobile-ci/scripts/jquery.cookie.js') }}
  <script type="text/javascript">
    /**
     * Flag for AJAX call to login API
     *
     * @var boolean
     */
    var orbit_login_processing = false;
    var no_ajax = false;
    var term_accepted = false;

    // Default state is always no (back button?)
    $('.agree_to_terms').value = 'no';

    $('#agree_to_terms').click(function (e) {
        term_accepted = this.checked;
        $('.agree_to_terms').val('yes');
    }).removeAttr('checked'); {{-- in case user got here via back button, always clear the checkbox --}}

    $('#orbit-privacy-policy-anchor').click(function (e) {
        e.preventDefault();
        $('#privacyModal').modal();
    });

    $('#orbit-tos-anchor').click(function (e) {
        e.preventDefault();
        $('#tosModal').modal();
    });

    $('#btn-login-form-fb').click(function(e) {
        if (! term_accepted) {
            e.preventDefault();
            $('#acceptTnCModal').modal();
            return false;
        }
        this.innerHTML = '<i class="fa fa-facebook"></i> ' + {{  json_encode(trans('mobileci.signin.connecting_to_facebook')); }};
    });


    /**
     * Get Query String from the URL
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string n - Name of the parameter
     */
    function get(n)
    {
        var half = location.search.split(n + '=')[1];
        return half !== undefined ? decodeURIComponent(half.split('&')[0]) : null;
    }

    /**
     * Function to handle after loction process which used to trick the
     * CaptivePortal Login Window such as in iPhone/iPad to open real browser.
     *
     * @author Rio Astamal <me@rioastamal>
     * @param XMLHttpRequest|jqXHR xhr
     * @return void
     */
    function afterLogin(xhr)
    {
        // Get Session which returned by the Orbit backend named
        // 'Set-X-Orbit-Session: SESSION_ID
        // To do: replace this hardcode session name
        var session_id = xhr.getResponseHeader('Set-X-Orbit-Session');
        var prefix = '?';
        console.log('Session ID: ' + session_id);

        // We will pass this session id to the application inside real browser
        // so the it can recreate the session information and able to recognize
        // the user.
        var create_session_url = '{{ URL::Route("captive-portal") }}';
        console.log('Create session URL: ' + create_session_url);

        var fname = $('.userName')[0].innerHTML;
        var email = $('#email').val();

        // Check for the '?' mark
        if (create_session_url.indexOf('?') > 0) {
            // There is already query string
            prefix = '&';
        }
        window.location = create_session_url + prefix + 'loadsession=' + session_id + '&fname=' + fname + '&email=' + email;

        return;
    }

    /**
     * Call the Login API
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     */
    function callLoginAPI()
    {
        if (term_accepted == false && no_ajax == false) {
            $('#acceptTnCModal').modal();

            return false;
        }

        if (orbit_login_processing) {
            return;
        }
        orbit_login_processing = true;

        $.ajax({
            method:'POST',
            url:apiPath+'customer/login',
            data:{
                email: $('#email').val().trim(),
                payload: "{{{ Input::get('payload', '') }}}"
            }
        }).done(function(data, status, xhr) {
            orbit_login_processing = false;

            if (data.status==='error') {
                $('#errorModalText').text(data.message);
                $('#errorModal').modal();
            }

            if (data.data) {
                if (data.data.redirect_to) {
                    document.location = data.data.redirect_to;
                    return;
                }
                // Check if we are redirected from captive portal
                // The query string 'from_captive' are from apache configuration
                if (get('from_captive') == 'yes') {
                    afterLogin(xhr);
                } else {
                    window.location.replace('{{ $landing_url }}');
                }
            }
        }).fail(function(data) {
            orbit_login_processing = false;

            $('#errorModalText').text(data.responseJSON.message);
            $('#errorModal').modal();
        });
    }

    $(document).ready(function() {
      var em;
      var user_em = '{{ strtolower($user_email) }}';
      function isValidEmailAddress(emailAddress) {
        var pattern = new RegExp(/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i);
        return pattern.test(emailAddress);
      };

      if (user_em != '') {
        $('#signedIn').show();
        $('#signIn').hide();
        term_accepted = true; {{-- assume user have accepted since user is known --}}

        em = user_em.toLowerCase();

        $('.signedUser').text(em.toLowerCase());
        $('.emailSigned').val(em.toLowerCase());
        $('#email').val(em.toLowerCase());

        if (get('auto_login') == 'yes') {
            $('.orbit-auto-login').hide();
            callLoginAPI();
        }
      }

      if ($('.userName')[0].innerHTML.length > 0) {
        $('.signedUser').hide();
        $('.userName').show();
      }

      $.removeCookie('dismiss_activation_popup', { path: '/', domain: window.location.hostname });

      $('#notMe').click(function() {
        var currentDomain = orbitGetDomainName();

        $.removeCookie('orbit_email', { path: '/', domain: currentDomain });
        $.removeCookie('orbit_firstname', { path: '/', domain: currentDomain });

        window.location.replace('/customer/logout?not_me=true');
      });

      $('form[name="loginForm"]').submit(function(event) {
        event.preventDefault();
        // $('.signedUser').text(em);

        $('#signup').css('display','none');
        $('#errorModalText').text('');
        $('#emailSignUp').val('');

        if (! $('#email').val().trim()) {
            $('#errorModalText').text('{{ Lang::get('mobileci.modals.email_error') }}');
            $('#errorModal').modal();
        } else {
          if(isValidEmailAddress($('#email').val().trim())) {
            callLoginAPI();
          } else {
            $('#errorModalText').text('{{ Lang::get('mobileci.signin.email_not_valid') }}');
            $('#errorModal').modal();
          }
        }
      });
    });
  </script>
@stop