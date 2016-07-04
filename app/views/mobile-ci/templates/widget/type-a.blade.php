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
                        @include('mobile-ci.templates.widget.type-a-widget', array('widget'=>$widget))
                    </div>
                @elseif($i === 1 || $i === count($widgets) - 1)
                    <div class="col-xs-6 col-sm-6 widget-col" style="z-index:{{count($widgets)-$i}};">
                        @if($i === 1)
                            <div id="particles-js2"></div>
                        @else
                            <div id="particles-js5"></div>
                        @endif
                        @include('mobile-ci.templates.widget.type-a-widget', array('widget'=>$widget))
                    </div>
                @else
                    <div class="col-xs-12 col-sm-12 widget-col" style="z-index:{{count($widgets)-$i}};">
                        <!-- <div id="particles-js{{$i+1}}"></div> -->
                        @include('mobile-ci.templates.widget.type-a-widget', array('widget'=>$widget))
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
