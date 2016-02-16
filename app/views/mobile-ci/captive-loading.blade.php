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
body.bg {
@if(!empty($bg) && !empty($bg->path))
    background: url('{{ asset($bg->path) }}');
@else
    background: url('{{ asset('mobile-ci/images/skelatal_weave.png') }}');
@endif
    background-size: cover;
    background-repeat: no-repeat;
    background-position: center;
    position: absolute;
    height: 478px;
    display: table;
    width: 100%;
}
</style>
@stop

@section('content')
<div class="spinner-backdrop" id="spinner-backdrop">
    <div class="spinner-container">
        <i class="fa fa-spin fa-spinner"></i>
    </div>
</div>
<div class="row top-space" id="signIn">
    <div class="col-xs-12">
        <header>
            <div class="row vertically-spaced">
                <div class="col-xs-12 text-center">
                    <img class="img-responsive" src="{{ asset($retailer->logo) }}" />
                </div>
            </div>
        </header>

        <div class="col-xs-12 text-center welcome-user">
            <h3>{{ Lang::get('mobileci.greetings.welcome') }},<br><span class="userName">{{{ $display_name or '' }}}</span>!</h3>
        </div>
        <div class="col-xs-12 text-center">
            <button type="button" class="btn btn-info btn-block">{{ Lang::get('mobileci.signin.loading_orbit') }}</button>
        </div>
    </div>
</div>

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

<script>
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
 * This code 'hopefully' trigger the Captive Window to open a new browser.
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @return void
 */
window.onload = function() {
    var session_id = get('loadsession');
    var create_session_url = '{{ URL::Route("captive-portal") }}';

    // Form object
    var frm = document.createElement('form');
    frm.setAttribute('method', 'get');
    frm.setAttribute('action', create_session_url);
    frm.setAttribute('id', 'frm_session');
    frm.setAttribute('style', 'display:none;');

    // Hidden element which contains session id
    var session_element = document.createElement('input');
    session_element.setAttribute('type', 'hidden');
    session_element.setAttribute('name', 'createsession');
    session_element.setAttribute('value', session_id);

    // Submit button
    var submit = document.createElement('input');
    submit.setAttribute('type', 'submit');
    submit.setAttribute('value', 'Submit');

    frm.appendChild(session_element);
    frm.appendChild(submit);

    // Add the form to the body
    document.getElementsByTagName('body')[0].appendChild(frm);
    console.log(frm);

    // Submit the form, but wait for few seconds to make sure the Captive
    // portal knows that the internet connection ready
    var delay = 2;  // seconds
    setTimeout(function() {
        frm.submit();
    }, delay * 1000);
}
</script>
