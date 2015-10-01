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
<div class="row top-space" id="signIn">
    <div class="col-xs-12">
        <header>
            <div class="row vertically-spaced">
                <div class="col-xs-12 text-center">
                    <span class="greetings">{{ Lang::get('mobileci.greetings.welcome') }}</span>
                </div>
            </div>
            <div class="row vertically-spaced">
                <div class="col-xs-12 text-center">
                    <img class="img-responsive" src="{{ asset($retailer->parent->biglogo) }}" />
                </div>
            </div>
        </header>
        <form name="loginForm" id="loginForm" action="{{ url('customer/login') }}" method="post">
            <div class="form-group">
                <input type="text" value="{{{ $user_email }}}" class="form-control orbit-auto-login" name="email" id="email" placeholder="{{ Lang::get('mobileci.signin.email_placeholder') }}" />
            </div>
            <div class="form-group orbit-auto-login">
                <button type="submit" class="btn btn-info btn-block">{{ Config::get('shop.start_button_label') }}</button>
            </div>
        </form>
    </div>
</div>
<div class="row top-space" id="signedIn">
    <div class="col-xs-12">
        <header>
            <div class="row vertically-spaced">
                <div class="col-xs-12 text-center">
                    <img class="img-responsive" src="{{ asset($retailer->parent->biglogo) }}" />
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
                    <button type="submit" class="btn btn-info btn-block">{{ Config::get('shop.start_button_label') }}</button>
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
                <h4 class="modal-title" id="myModalLabel">Error</h4>
            </div>
            <div class="modal-body">
                <p id="errorModalText"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ Lang::get('mobileci.modals.close') }}</button>
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
        console.log('Session ID: ' + session_id);

        // We will pass this session id to the application inside real browser
        // so the it can recreate the session information and able to recognize
        // the user.
        var create_session_url = '{{ URL::Route("captive-portal") }}';
        console.log('Create session URL: ' + create_session_url);

        var fname = $('.userName')[0].innerHTML;
        var email = $('#email').val();
        window.location = create_session_url + '?loadsession=' + session_id + '&fname=' + fname + '&email=' + email;

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

      $('#notMe').click(function() {
        var currentDomain = window.location.hostname;

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
