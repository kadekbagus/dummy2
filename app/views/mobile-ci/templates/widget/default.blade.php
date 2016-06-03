@extends('mobile-ci.home')

@section('widget-template')
<div class="container">
    <div class="mobile-ci home-widget widget-container">
        <div class="row">
            @foreach($widgets as $i => $widget)
                @if($i % 3 === 0)
                <div class="col-xs-12 col-sm-12">
                    @include('mobile-ci.templates.widget.default-widget', array('widget'=>$widget))
                </div>
                @elseif($i % 3 === 1)
                    @if($i === count($widgets) - 1)
                        <div class="col-xs-12 col-sm-12">
                            @include('mobile-ci.templates.widget.default-widget', array('widget'=>$widget))                            
                        </div>
                    @else
                        <div class="col-xs-6 col-sm-6">
                            @include('mobile-ci.templates.widget.default-widget', array('widget'=>$widget))                            
                        </div>
                    @endif
                @else
                    <div class="col-xs-6 col-sm-6">
                        @include('mobile-ci.templates.widget.default-widget', array('widget'=>$widget))                            
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</div>
@stop