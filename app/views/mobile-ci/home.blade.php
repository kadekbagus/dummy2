@extends('mobile-ci.layout')

@section('ext_style')
<style type="text/css">
.img-responsive{
    margin:0 auto;
}
</style>
@stop

@section('fb_scripts')
@if(! empty($facebookInfo))
@if(! empty($facebookInfo['version']) && ! empty($facebookInfo['app_id']))
<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/en_US/sdk.js#xfbml=1&version={{$facebookInfo['version']}}&appId={{$facebookInfo['app_id']}}";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>
@endif
@endif
@stop

@section('content')
    @yield('widget-template')
@stop

@section('modals')
<!-- Modal -->
<div class="modal fade" id="noModal" tabindex="-1" role="dialog" aria-labelledby="noModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title" id="noModalLabel"></h4>
            </div>
            <div class="modal-body">
                <p id="noModalText"></p>
            </div>
            <div class="modal-footer">
                <div class="pull-right"><button type="button" class="btn btn-default" data-dismiss="modal">{{ Lang::get('mobileci.modals.close') }}</button></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="tour-confirmation" data-keyboard="false" data-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <h4 class="modal-title"><i class="fa fa-lightbulb-o"></i> {{ Lang::get('mobileci.page_title.orbit_tour') }}</h4>
            </div>
            <div class="modal-body">
                {{ Lang::get('mobileci.tour.modal.content') }}
            </div>
            <div class="modal-footer">
                <button type="button" id="modal-end-tour" class="btn btn-danger">{{ Lang::get('mobileci.tour.modal.end_button') }}</button>
                <button type="button" id="modal-start-tour" class="btn btn-info">{{ Lang::get('mobileci.tour.modal.start_button') }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('mall-fb-footer')
    @if ($urlblock->isLoggedIn())
    <div class="text-center" style="padding-bottom: 20px;">
        @if(! empty($retailer->facebook_like_url))
        <div class="fb-like" data-href="{{{$retailer->facebook_like_url}}}" data-layout="button_count" data-action="like" data-show-faces="false" data-share="false" style="margin-right:25px;"></div>
        @endif
        @if(! empty($retailer->facebook_share_url))
        <div class="fb-share-button" data-href="{{{$retailer->facebook_share_url}}}" data-layout="button"></div>
        @endif
    </div>
    @endif
@stop

@section('ext_script_bot')
<script type="text/javascript">
    $(document).ready(function() {
        // Check if browser supports LocalStorage
        if(typeof(Storage) !== 'undefined') {
            localStorage.setItem('fromSource', 'home');
        }

        $('a.widget-link').click(function(event){
          event.preventDefault();

          if ($(this).attr('href') !== '#') {
              var link = $(this).attr('href');
              var widgetdata = $(this).data('widget');
              $.ajax({
                url: '{{ route('click-widget-activity') }}',
                data: {
                  widgetdata: widgetdata
                },
                method: 'POST'
              }).always(function(){
                window.location.assign(link);
              });
              return false; //for good measure
          }
        });
    });
</script>
@stop
