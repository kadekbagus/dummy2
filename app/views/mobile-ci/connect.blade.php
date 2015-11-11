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
  /*body.bg{
    background: url('{{ asset($bg[0]) }}');
    background-size: cover;
    background-repeat: no-repeat;
  }*/
  @endif
@endif
</style>
@stop

@section('content')
<div class="row" id="signIn">
    <div class="col-xs-12">
        <header>
            <div class="row vertically-spaced">
                <div class="col-xs-12 text-center vertically-spaced">
                    <img class="img-responsive" src="{{ asset($this_mall->bigLogo) }}" />
                </div>
            </div>
        </header>
        <div class="row vertically-spaced">
            <div class="col-xs-12 text-center">
                <span class="greetings">{{{ trans('mobile-ci.connect.header') }}} <img src="{{ asset('mobile-ci/images/default-logo.png') }}" style="width:60px"></span>
            </div>
        </div>
        <div class="row vertically-spaced">
            <div class="col-xs-12 text-center">
                <strong>{{{ trans('mobile-ci.connect.message') }}}</strong>
            </div>
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
</footer>
@stop

@section('modals')

@stop

@section('ext_script_bot')
  
@stop
