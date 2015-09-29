@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
    @if($data->status === 1)
        @if(sizeof($data->records) > 0)
            @foreach($data->records as $news)
                <div class="main-theme-mall catalogue" id="product-{{$news->promotion_id}}">
                    <div class="row catalogue-top">
                        <div class="col-xs-3 catalogue-img">
                            @if(!empty($news->image))
                            <a href="{{ asset($news->image) }}" data-featherlight="image" class="text-left"><img class="img-responsive" alt="" src="{{ asset($news->image) }}"></a>
                            @else
                            <a class="img-responsive" src="{{ asset('mobile-ci/images/default_product.png') }}"/><img class="img-responsive" alt="" src="{{ asset('mobile-ci/images/default_product.png') }}"></a>
                            @endif
                        </div>
                        <div class="col-xs-6">
                            <h4>{{ $news->news_name }}</h4>
                            @if (strlen($news->description) > 120)
                            <p>{{{ substr($news->description, 0, 120) }}} [<a href="{{ url('customer/mallnewsdetail?id='.$news->news_id) }}">...</a>] </p>
                            @else
                            <p>{{{ $news->description }}}</p>
                            @endif
                        </div>
                        <div class="col-xs-3" style="margin-top:20px">
                            <div class="circlet btn-blue detail-btn pull-right">
                                <a href="{{ url('customer/mallnewsdetail?id='.$news->news_id) }}"><span class="link-spanner"></span><i class="fa fa-ellipsis-h"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @else
            <div class="row padded">
                <div class="col-xs-12">
                    <h4>{{ Lang::get('mobileci.greetings.latest_news_coming_soon') }}</h4>
                </div>
            </div>
        @endif
    @else
        <div class="row padded">
            <div class="col-xs-12">
                <h4>{{ Lang::get('mobileci.greetings.latest_news_coming_soon') }}</h4>
            </div>
        </div>
    @endif
@stop

@section('modals')
<!-- Modal -->
<div class="modal fade" id="verifyModal" tabindex="-1" role="dialog" aria-labelledby="verifyModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title" id="verifyModalLabel"><i class="fa fa-envelope-o"></i> Info</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-xs-12">
                        <p>
                            {{ Lang::get('mobileci.promotion.want_unlimited_browsing') }}<br>
                            {{ Lang::get('mobileci.promotion.or_want_to_follow_the_lucky_draw') }}<br>
                            {{ Lang::get('mobileci.promotion.open_your_email_and_verify_now') }}<br>
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="pull-right"><button type="button" class="btn btn-default" data-dismiss="modal">{{ Lang::get('mobileci.modals.close') }}</button></div>
            </div>
        </div>
    </div>
</div>
@stop

@section('ext_script_bot')
{{ HTML::script('mobile-ci/scripts/jquery-ui.min.js') }}
{{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
{{ HTML::script('mobile-ci/scripts/jquery.cookie.js') }}
<script type="text/javascript">
    function updateQueryStringParameter(uri, key, value) {
        var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
        var separator = uri.indexOf('?') !== -1 ? "&" : "?";
        if (uri.match(re)) {
            return uri.replace(re, '$1' + key + "=" + value + '$2');
        } else {
            return uri + separator + key + "=" + value;
        }
    }
    $(document).ready(function(){
        $(document).on('show.bs.modal', '.modal', function (event) {
            var zIndex = 1040 + (10 * $('.modal:visible').length);
            $(this).css('z-index', zIndex);
            setTimeout(function() {
                $('.modal-backdrop').not('.modal-stack').css('z-index', 0).addClass('modal-stack');
            }, 0);
        });
        if(!$.cookie('dismiss_verification_popup')) {
            $.cookie('dismiss_verification_popup', 't', { expires: 1 });
            // $('#verifyModal').modal();
        }
        
        var path = '{{ url('/customer/tenants?keyword='.Input::get('keyword').'&sort_by=name&sort_mode=asc&cid='.Input::get('cid').'&fid='.Input::get('fid')) }}';
        $('#dLabel').dropdown();
        $('#dLabel2').dropdown();
        $('#category>li').click(function(){
            if(!$(this).data('category')) {
                $(this).data('category', '');
            }
            path = updateQueryStringParameter(path, 'cid', $(this).data('category'));
            console.log(path);
            window.location.replace(path);
        });
        $('#floor>li').click(function(){
            if(!$(this).data('floor')) {
                $(this).data('floor', '');
            }
            path = updateQueryStringParameter(path, 'fid', $(this).data('floor'));
            console.log(path);
            window.location.replace(path);
        });
    });
</script>
@stop
