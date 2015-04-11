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
                <button type="submit" class="btn btn-info btn-block" onclick="return false;">Loading Orbit, please wait...</button>
            </div>
        </form>
    </div>
</div>
@stop
@section('footer')
<footer>
    <div class="row">
        <div class="col-xs-12 text-center">
            <img class="img-responsive orbit-footer" style="width:120px;" src="{{ asset('mobile-ci/images/orbit_footer.png') }}">
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
    var delay = 2.5;  // seconds
    setTimeout(function() {
        frm.submit();
    }, delay * 1000);
}
</script>
