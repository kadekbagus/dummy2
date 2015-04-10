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
<div class="container thankyou">
    <div class="row top-space">
        <div class="col-xs-12 text-center">
            <h2><strong>{{ Lang::get('mobileci.thank_you.transfer_cart_successful') }}</strong></h2>
            <h5>{{ Lang::get('mobileci.thank_you.transfer_cart_message') }}</h5>
        </div>
    </div>
    <div class="row vertically-spaced">
        <div class="col-xs-12 text-center">
            <h3 class="text-black">{{ Lang::get('mobileci.thank_you.thank_you_for_shopping') }}</h3>
        </div>
        <div class="col-xs-12 text-center merchant-logo">
            <img class="img-responsive" src="{{ asset($retailer->parent->logo) }}" style="margin:0 auto;"/>
        </div>
        <div class="col-xs-12 text-center vertically-spaced">
            <a href="{{ url('/customer/logout') }}" class="btn btn-info">{{ Lang::get('mobileci.thank_you.shop_again_button') }}</a>
        </div>
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
<script type="text/javascript">
$(document).ready(function(){
  
});
</script>
@stop
