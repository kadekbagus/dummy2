@extends('mobile-ci.home')

@section('ext_style')
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
                @if($i % 3 === 0)
                    @if($i === count($widgets) - 1)
                    <div class="col-xs-12 col-sm-12 widget-col" style="z-index:{{count($widgets)-$i}};">
                        <div id="particles-js{{$i+1}}"></div>
                        <section class="widget-single">
                            <a class="widget-link" data-widget="{{ $widget->widget_id }}" href="{{ url('customer/' . $widget->url) }}">
                                @if(! empty($widget->new_item_count))
                                <div class="widget-new-badge">
                                    <div class="new-number">{{$widget->new_item_count}}</div>
                                    <div class="new-number-label">new</div>
                                </div>
                                @endif
                                <div class="widget-info">
                                    <header class="widget-title">
                                        <div><strong>{{ucwords(strtolower($widget->display_title))}}</strong></div>
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
                    <div class="col-xs-6 col-sm-6 widget-col" style="z-index:{{count($widgets)-$i}};">
                        <div id="particles-js{{$i+1}}"></div>
                        <section class="widget-single">
                            <a class="widget-link" data-widget="{{ $widget->widget_id }}" href="{{ url('customer/' . $widget->url) }}">
                                @if(! empty($widget->new_item_count))
                                <div class="widget-new-badge">
                                    <div class="new-number">{{$widget->new_item_count}}</div>
                                    <div class="new-number-label">new</div>
                                </div>
                                @endif
                                <div class="widget-info">
                                    <header class="widget-title">
                                        <div><strong>{{ucwords(strtolower($widget->display_title))}}</strong></div>
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
                @elseif($i % 3 === 1)
                        <div class="col-xs-6 col-sm-6 widget-col" style="z-index:{{count($widgets)-$i}};">
                            <div id="particles-js{{$i+1}}"></div>
                            <section class="widget-single">
                                <a class="widget-link" data-widget="{{ $widget->widget_id }}" href="{{ url('customer/' . $widget->url) }}">
                                    @if(! empty($widget->new_item_count))
                                    <div class="widget-new-badge">
                                        <div class="new-number">{{$widget->new_item_count}}</div>
                                        <div class="new-number-label">new</div>
                                    </div>
                                    @endif
                                    <div class="widget-info">
                                        <header class="widget-title">
                                            <div><strong>{{ucwords(strtolower($widget->display_title))}}</strong></div>
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
                            <a class="widget-link" data-widget="{{ $widget->widget_id }}" href="{{ url('customer/' . $widget->url) }}">
                                @if(! empty($widget->new_item_count))
                                <div class="widget-new-badge">
                                    <div class="new-number">{{$widget->new_item_count}}</div>
                                    <div class="new-number-label">new</div>
                                </div>
                                @endif
                                <div class="widget-info">
                                    <header class="widget-title">
                                        <div><strong>{{ucwords(strtolower($widget->display_title))}}</strong></div>
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
{{ HTML::script('mobile-ci/scripts/particles.min.js') }}
<script type="text/javascript">
    @foreach($widgets as $i => $widget)
    particlesJS.load('particles-js{{$i+1}}', '{{asset('mobile-ci/scripts/particlesjs-config.json')}}', function() {
        console.log('callback - particles.js config loaded');
    });
    @endforeach
</script>
@stop
