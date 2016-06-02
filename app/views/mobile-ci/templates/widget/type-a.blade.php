@extends('mobile-ci.home')

@section('ext_style')
@parent
<style type="text/css">
#particles-js1{
    pointer-events: none;
    background:rgba(0,0,0,.3);
    position: absolute;
    top:0px;
    left:-50%;
    width: 100%;
    height:100%;
    z-index: 1;
    -webkit-transform: skew(-45deg, 0deg);
    -moz-transform: skew(-45deg, 0deg);
    -ms-transform: skew(-45deg, 0deg);
    -o-transform: skew(-45deg, 0deg);
     transform: skew(-45deg, 0deg);
}
#particles-js2{
    pointer-events: none;
    background:rgba(0,0,0,.3);
    position: absolute;
    top:0px;
    left:50%;
    width: 100%;
    height:100%;
    z-index: 1;
    -webkit-transform: skew(45deg, 0deg);
    -moz-transform: skew(45deg, 0deg);
    -ms-transform: skew(45deg, 0deg);
    -o-transform: skew(45deg, 0deg);
     transform: skew(45deg, 0deg);
}
#particles-js3{
    pointer-events: none;
    background:rgba(0,0,0,.3);
    position: absolute;
    top:0px;
    left:-50%;
    width: 100%;
    height:100%;
    z-index: 1;
    -webkit-transform: skew(-45deg, 0deg);
    -moz-transform: skew(-45deg, 0deg);
    -ms-transform: skew(-45deg, 0deg);
    -o-transform: skew(-45deg, 0deg);
     transform: skew(-45deg, 0deg);
}
#particles-js4{
    pointer-events: none;
    background:rgba(0,0,0,.3);
    position: absolute;
    top:0px;
    left:-50%;
    width: 100%;
    height:100%;
    z-index: 1;
    -webkit-transform: skew(45deg, 0deg);
    -moz-transform: skew(45deg, 0deg);
    -ms-transform: skew(45deg, 0deg);
    -o-transform: skew(45deg, 0deg);
     transform: skew(45deg, 0deg);
}
#particles-js5{
    pointer-events: none;
    background:rgba(0,0,0,.3);
    position: absolute;
    top:0px;
    left:50%;
    width: 100%;
    height:100%;
    z-index: 1;
    -webkit-transform: skew(-45deg, 0deg);
    -moz-transform: skew(-45deg, 0deg);
    -ms-transform: skew(-45deg, 0deg);
    -o-transform: skew(-45deg, 0deg);
     transform: skew(-45deg, 0deg);
}
.widget-col{
    overflow: hidden;
}
</style>
@stop

@section('widget-template')
<div class="container">
    <div class="mobile-ci home-widget widget-container">
        <div class="row">
            @foreach($widgets as $i => $widget)
                @if($i === 0 || $i === count($widgets) - 2)
                    <div class="col-xs-6 col-sm-6 widget-col" style="z-index:{{count($widgets)-$i}};">
                        @if($i === 0)
                            <div id="particles-js1"></div>
                        @else
                            <div id="particles-js4"></div>
                        @endif
                        <section class="widget-single">
                            <a class="widget-link" data-widget="{{ $widget->widget_id }}" data-href="{{$widget->redirect_url}}" href="{{ $widget->url }}">
                                @if(! empty($widget->new_item_count))
                                <div class="widget-new-badge">
                                    <div class="new-number">{{$widget->new_item_count}}</div>
                                    <div class="new-number-label">new</div>
                                </div>
                                @endif
                                <div class="widget-info">
                                    <header class="widget-title">
                                        <div><strong>{{{ucwords(strtolower($widget->display_title))}}}</strong></div>
                                    </header>
                                    <header class="widget-subtitle">
                                        @if($widget->item_count > 0)
                                        <div>{{$widget->item_count}} {{$widget->display_sub_title}}</div>
                                        @else
                                        <div>&nbsp;</div>
                                        @endif
                                    </header>
                                </div>
                                <div class="list-vignette"></div>
                                <img class="img-responsive img-fit" src="{{ asset($widget->image) }}" />
                            </a>
                        </section>
                    </div>
                @elseif($i === 1 || $i === count($widgets) - 1)
                    <div class="col-xs-6 col-sm-6 widget-col" style="z-index:{{count($widgets)-$i}};">
                        @if($i === 1)
                            <div id="particles-js2"></div>
                        @else
                            <div id="particles-js5"></div>
                        @endif
                        <section class="widget-single">
                            <a class="widget-link" data-widget="{{ $widget->widget_id }}" data-href="{{$widget->redirect_url}}" href="{{ $widget->url }}">
                                @if(! empty($widget->new_item_count))
                                <div class="widget-new-badge">
                                    <div class="new-number">{{$widget->new_item_count}}</div>
                                    <div class="new-number-label">new</div>
                                </div>
                                @endif
                                <div class="widget-info">
                                    <header class="widget-title">
                                        <div><strong>{{{ucwords(strtolower($widget->display_title))}}}</strong></div>
                                    </header>
                                    <header class="widget-subtitle">
                                        @if($widget->item_count > 0)
                                        <div>{{$widget->item_count}} {{$widget->display_sub_title}}</div>
                                        @else
                                        <div>&nbsp;</div>
                                        @endif
                                    </header>
                                </div>
                                <div class="list-vignette"></div>
                                <img class="img-responsive img-fit" src="{{ asset($widget->image) }}" />
                            </a>
                        </section>
                    </div>
                @else
                    <div class="col-xs-12 col-sm-12 widget-col" style="z-index:{{count($widgets)-$i}};">
                        <!-- <div id="particles-js{{$i+1}}"></div> -->
                        <section class="widget-single">
                            <a class="widget-link" data-widget="{{ $widget->widget_id }}" data-href="{{$widget->redirect_url}}" href="{{ $widget->url }}">
                                @if(! empty($widget->new_item_count))
                                <div class="widget-new-badge">
                                    <div class="new-number">{{$widget->new_item_count}}</div>
                                    <div class="new-number-label">new</div>
                                </div>
                                @endif
                                <div class="widget-info">
                                    <header class="widget-title">
                                        <div><strong>{{{ucwords(strtolower($widget->display_title))}}}</strong></div>
                                    </header>
                                    <header class="widget-subtitle">
                                        @if($widget->item_count > 0)
                                        <div>{{$widget->item_count}} {{$widget->display_sub_title}}</div>
                                        @else
                                        <div>&nbsp;</div>
                                        @endif
                                    </header>
                                </div>
                                <div class="list-vignette"></div>
                                <img class="img-responsive img-fit" src="{{ asset($widget->image) }}" />
                            </a>
                        </section>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</div>
@stop

@section('ext_script_bot')
@parent
{{ HTML::script('mobile-ci/scripts/particles.min.js') }}
<script type="text/javascript">
    $(document).ready(function(){
        @foreach($widgets as $i => $widget)
        if($('#particles-js{{$i+1}}').length) {
            particlesJS.load('particles-js{{$i+1}}', '{{asset('mobile-ci/scripts/particlesjs-config.json')}}', function() {

            });
        }
        @endforeach
    });
</script>
@stop
