@extends('mobile-ci.home')

@section('widget-template')
<div class="container">
    <div class="mobile-ci home-widget widget-container">
        <div class="row">
            @foreach($widgets as $i => $widget)
                @if($i % 3 === 0)
                <div class="col-xs-12 col-sm-12">
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
                @elseif($i % 3 === 1)
                    @if($i === count($widgets) - 1)
                        <div class="col-xs-12 col-sm-12">
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
                        <div class="col-xs-6 col-sm-6">
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
                @else
                    <div class="col-xs-6 col-sm-6">
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