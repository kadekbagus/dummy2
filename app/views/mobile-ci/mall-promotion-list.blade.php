@extends('mobile-ci.layout')

@section('content')
    <div class="container">
        <div class="mobile-ci list-item-container">
            <div class="row">
            @if($data->status === 1)
                <div class="catalogue-wrapper">
                @foreach($data->records as $promo)
                    <div class="col-xs-12 col-sm-12 item-x" data-ids="{{$promo->news_id}}" id="item-{{$promo->news_id}}">
                        <section class="list-item-single-tenant">
                            <a class="list-item-link" data-href="{{ route('ci-promotion-detail', ['id' => $promo->news_id, 'name' => Str::slug($promo->news_name)]) }}" href="{{ \Orbit\Helper\Net\UrlChecker::blockedRoute('ci-promotion-detail', ['id' => $promo->news_id, 'name' => Str::slug($promo->news_name)], $session) }}">
                                <div class="list-item-info">
                                    <header class="list-item-title">
                                        <div><strong>{{{ $promo->news_name }}}</strong></div>
                                    </header>
                                    <header class="list-item-subtitle">
                                        <div>
                                            {{-- Limit description per two line and 45 total character --}}
                                            <?php
                                                $desc = explode("\n", $promo->description);
                                            ?>
                                            @if (mb_strlen($promo->description) > 45)
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
                                                    {{{ mb_substr($promo->description, 0, 45, 'UTF-8') . '...' }}}
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
                                                    {{{ mb_substr($promo->description, 0, 45, 'UTF-8') }}}
                                                @endif
                                            @endif
                                        </div>
                                    </header>
                                </div>
                                <div class="list-vignette-non-tenant"></div>
                                @if(!empty($promo->image))
                                <img class="img-responsive img-fit-tenant" src="{{ asset($promo->image) }}" />
                                @else
                                <img class="img-responsive img-fit-tenant" src="{{ asset('mobile-ci/images/default_promotion.png') }}"/>
                                @endif
                            </a>
                        </section>
                    </div>
                @endforeach
                </div>
                @if($data->returned_records < $data->total_records)
                    <div class="row">
                        <div class="col-xs-12 padded">
                            <button class="btn btn-info btn-block" id="load-more-x">{{Lang::get('mobileci.notification.load_more_btn')}}</button>
                        </div>
                    </div>
                @endif
            @else
                @if(Input::get('keyword') === null)
                <div class="row padded">
                        <div class="col-xs-12">
                            <h4>{{ Lang::get('mobileci.greetings.no_promotions_listing') }}</h4>
                        </div>
                    </div>
                @else
                <div class="row padded">
                    <div class="col-xs-12">
                        <h4>{{ Lang::get('mobileci.search.no_result') }}</h4>
                    </div>
                </div>
                @endif
            @endif
            </div>
        </div>
    </div>
@stop

@section('ext_script_bot')
<script type="text/javascript">
    $(document).ready(function(){
        $('body').on('click', '#load-more-x', function(){
            var listOfIDs = [];
            $('.catalogue-wrapper .item-x').each(function(id){
                listOfIDs.push($(this).data('ids'));
            });
            loadMoreX('promotion', listOfIDs);
        });
    });
</script>
@stop