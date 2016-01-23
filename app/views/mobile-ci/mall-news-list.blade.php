@extends('mobile-ci.layout')

@section('content')
    <div class="container">
        <div class="mobile-ci list-item-container">
            <div class="row">
            @if($data->status === 1)
                @if(sizeof($data->records) > 0)
                    @foreach($data->records as $news)
                        <div class="col-xs-12 col-sm-12" id="item-{{$news->promotion_id}}">
                            <section class="list-item-single-tenant">
                                <a class="list-item-link" href="{{ url('customer/mallnewsdetail?id='.$news->news_id) }}">
                                    <div class="list-item-info">
                                        <header class="list-item-title">
                                            <div><strong>{{ $news->news_name }}</strong></div>
                                        </header>
                                        <header class="list-item-subtitle">
                                            <div>
                                                {{-- Limit description per two line and 45 total character --}}
                                                <?php
                                                    $desc = explode("\n", $news->description);
                                                ?>
                                                @if (mb_strlen($news->description) > 45)
                                                    @if (count($desc) > 1)
                                                        <?php
                                                            $two_row = array_slice($desc, 0, 1);
                                                        ?>
                                                        @foreach ($two_row as $key => $value)
                                                            @if ($key === 0)
                                                                {{{ $value }}} <br>
                                                            @else
                                                                {{{ $value }}} ...
                                                            @endif
                                                        @endforeach
                                                    @else
                                                        {{{ mb_substr($news->description, 0, 45, 'UTF-8') . '...' }}}
                                                    @endif
                                                @else
                                                    @if (count($desc) > 1)
                                                        <?php
                                                            $two_row = array_slice($desc, 0, 1);
                                                        ?>
                                                        @foreach ($two_row as $key => $value)
                                                            @if ($key === 0)
                                                                {{{ $value }}} <br>
                                                            @else
                                                                {{{ $value }}} ...
                                                            @endif
                                                        @endforeach
                                                    @else
                                                        {{{ mb_substr($news->description, 0, 45, 'UTF-8') }}}
                                                    @endif
                                                @endif
                                            </div>
                                        </header>
                                    </div>
                                    <div class="list-vignette-non-tenant"></div>
                                    @if(!empty($news->image))
                                    <img class="img-responsive img-fit-tenant" src="{{ asset($news->image) }}" />
                                    @else
                                    <img class="img-responsive img-fit-tenant" src="{{ asset('mobile-ci/images/default_news.png') }}"/>
                                    @endif
                                </a>
                            </section>
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
            </div>
        </div>
    </div>
@stop