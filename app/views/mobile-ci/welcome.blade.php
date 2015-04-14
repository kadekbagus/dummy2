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
                    <img class="img-responsive" src="{{ asset($retailer->parent->logo) }}" />
                </div>
            </div>
        </header>
        <form name="loginForm" id="loginForm" action="{{ url('customer/login') }}" method="post">
            <div class="form-group">
                <input type="text" class="form-control" name="email" id="email" placeholder="{{ Lang::get('mobileci.signin.email_placeholder') }}" />
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-info btn-block">{{ Lang::get('mobileci.signin.login_button') }}</button>
            </div>
        </form>
    </div>
</div>
<div class="row top-space" id="signedIn">
    <div class="col-xs-12">
        <header>
            <div class="row vertically-spaced">
                <div class="col-xs-12 text-center">
                    <img class="img-responsive" src="{{ asset($retailer->parent->logo) }}" />
                </div>
            </div>
        </header>
        <div class="col-xs-12 text-center welcome-user">
            <h3>{{ Lang::get('mobileci.greetings.welcome') }}, <br><span class="signedUser"></span><span class="userName"></span> !</h3>
        </div>
        <div class="col-xs-12 text-center">
            <form name="loginForm" id="loginSignedForm" action="{{ url('customer/login') }}" method="post">
                <div class="form-group">
                    <input type="hidden" class="form-control" name="email" id="emailSigned" />
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-info btn-block">{{ Lang::get('mobileci.signin.start_button') }}</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-xs-12 text-center vertically-spaced">
        <a id="notMe">{{ Lang::get('mobileci.signin.not') }} <span class="signedUser"></span><span class="userName"></span>, {{ Lang::get('mobileci.signin.click_here') }}.</a>
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

@section('ext_script_bot')
  {{ HTML::script('mobile-ci/scripts/jquery.cookie.js') }}
  <script type="text/javascript">
    /**
     * Get Query String from the URL
     *
     * @author Rio Astamal <me@rioastamal.net> 
     * @param string n - Name of the parameter
     */
    function get(n) {
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
    function afterLogin(xhr) {
        // Get Session which returned by the Orbit backend named
        // 'Set-X-Orbit-Session: SESSION_ID
        // To do: replace this hardcode session name
        var session_id = xhr.getResponseHeader('Set-X-Orbit-Mobile-Session');
        console.log('Session ID: ' + session_id);

        // We will pass this session id to the application inside real browser
        // so the it can recreate the session information and able to recognize
        // the user.
        var create_session_url = '{{ URL::Route("captive-portal") }}';
        console.log('Create session URL: ' + create_session_url);

        window.location = create_session_url + '?loadsession=' + session_id;

        return;
    }

    $(document).ready(function(){
      var em;
      var user_em = '{{ strtolower($user_email) }}';
      function isValidEmailAddress(emailAddress) {
        var pattern = new RegExp(/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i);
        return pattern.test(emailAddress);
      };

      if(get('email')){
        if(isValidEmailAddress(get('email').trim())){
          //console.log('asd');
          $.ajax({
            method:'POST',
            url:apiPath+'customer/login',
            data:{
              email: get('email').trim()
            }
          }).done(function(data, status, xhr){
            if(data.status==='error'){
              console.log(data);
            }
            if(data.data){
              // console.log(data.data);
              $.cookie('orbit_email', data.data.user_email, { expires: 5 * 365, path: '/' });
              if(data.data.user_firstname) {
                $.cookie('orbit_firstname', data.data.user_firstname, { expires: 5 * 365, path: '/' });
              }

              // Check if we are redirected from captive portal
              // The query string 'from_captive' are from apache configuration
              if (get('from_captive') == 'yes') {
                  afterLogin(xhr);
              } else {
                  window.location.replace(homePath);
              }
            }
          }).fail(function(data){
            $('#errorModalText').text(data.responseJSON.message);
            $('#errorModal').modal();
          });
        }
      }
      
      if(user_em != ''){
        $('#signedIn').show();
        $('#signIn').hide();
        em = user_em;
        $('.signedUser').text(em.toLowerCase());
        $('.emailSigned').val(em.toLowerCase());
        $('#email').val(em.toLowerCase());
        // console.log(user_em);
      } else {
        if(typeof $.cookie('orbit_email') === 'undefined') {
          // $.cookie('orbit_email', '-', { expires: 5 * 365, path: '/' });
          $('#signedIn').hide();
          $('#signIn').show();

        } else {
          if($.cookie('orbit_email')){
            $('#signedIn').show();
            $('#signIn').hide();
            em = $.cookie('orbit_email');
            $('.emailSigned').val(em.toLowerCase());
            $('.signedUser').text(em.toLowerCase());
            $('#email').val(em.toLowerCase());
          } else {
            $('#signedIn').hide();
            $('#signIn').show();

          }
        }
      }
      if($.cookie('orbit_firstname')){
        $('.signedUser').hide();
        $('.userName').text($.cookie('orbit_firstname'));
        $('.userName').show();
      }
      $('#notMe').click(function(){
        $.removeCookie('orbit_email', { path: '/' });
        $.removeCookie('orbit_firstname', { path: '/' });
        window.location.replace('/customer/logout');
      });
      $('form[name="loginForm"]').submit(function(event){
        event.preventDefault();
        // $('.signedUser').text(em);

        $('#signup').css('display','none');
        $('#errorModalText').text('');
        $('#emailSignUp').val('');

        if(!$('#email').val().trim()) {
          $('#errorModalText').text('{{ Lang::get('mobileci.modals.email_error') }}');
          $('#errorModal').modal();
        }else{
          
          if(isValidEmailAddress($('#email').val().trim())){
            $.ajax({
              method:'POST',
              url:apiPath+'customer/login',
              data:{
                email: $('#email').val().trim()
              }
            }).done(function(data, status, xhr){
              if(data.status==='error'){
                console.log(data);
              }
              if(data.data){
                // console.log(data.data);
                $.cookie('orbit_email', data.data.user_email, { expires: 5 * 365, path: '/' });
                if(data.data.user_firstname) {
                  $.cookie('orbit_firstname', data.data.user_firstname, { expires: 5 * 365, path: '/' });
                }

                // Check if we are redirected from captive portal
                // The query string 'from_captive' are from apache configuration
                if (get('from_captive') == 'yes') {
                    afterLogin(xhr);
                } else {
                    window.location.replace(homePath);
                }
              }
            }).fail(function(data){
              $('#errorModalText').text(data.responseJSON.message);
              $('#errorModal').modal();
            });
          } else {
            $('#errorModalText').text('{{ Lang::get('mobileci.signin.email_not_valid') }}');
            $('#errorModal').modal();
          }
        }
      });
    });
  </script>
@stop
