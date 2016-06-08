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
                                        @if($widget->widget_type != 'free_wifi')
                                        <div><strong>{{{ucwords(strtolower($widget->display_title))}}}</strong></div>
                                        @else
                                        <div><strong>{{{$widget->display_title}}}</strong></div>
                                        @endif
                                    </header>
                                    <header class="widget-subtitle">
                                        @if($widget->item_count > 0)
                                        <div>{{$widget->item_count}} {{$widget->display_sub_title}}</div>
                                        @else
                                            @if($widget->always_show_subtitle)
                                            <div>{{ $widget->display_sub_title }}</div>
                                            @else
                                            <div>&nbsp;</div>
                                            @endif
                                        @endif
                                    </header>
                                </div>
                                <div class="list-vignette"></div>
                                <img class="img-responsive img-fit" src="{{ asset($widget->image) }}" />
                            </a>
                        </section>
