@extends('mobile-ci.layout')

@section('content')
    <div class="container">
        <div class="mobile-ci list-item-container">
            <div class="row">
            @if($data->status === 1)
                @if(sizeof($data->records) > 0)
                    @foreach($data->records as $promo)
                        <div class="col-xs-12 col-sm-12" id="item-{{$promo->promotion_id}}">
                            <section class="list-item-single-tenant">
                                <a class="list-item-link" href="{{ url('customer/mallpromotion?id='.$promo->news_id) }}">
                                    <div class="list-item-info">
                                        <header class="list-item-title">
                                            <div><strong>{{ $promo->news_name }}</strong></div>
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
                @else
                    <div class="row padded">
                        <div class="col-xs-12">
                            <h4>{{ Lang::get('mobileci.greetings.new_promotions_coming_soon') }}</h4>
                        </div>
                    </div>
                @endif
            @else
                <div class="row padded">
                    <div class="col-xs-12">
                        <h4>{{ Lang::get('mobileci.greetings.new_promotions_coming_soon') }}</h4>
                    </div>
                </div>
            @endif
            </div>
        </div>
    </div>
@stop

@section('modals')
<!-- Modal -->
<div class="modal fade" id="hasCouponModal" tabindex="-1" role="dialog" aria-labelledby="hasCouponLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title" id="hasCouponLabel">{{ Lang::get('mobileci.modals.coupon_title') }}</h4>
            </div>
            <div class="modal-body">
                <div class="row ">
                    <div class="col-xs-12 vertically-spaced">
                        <p></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="row">
                    <input type="hidden" name="detail" id="detail" value="">
                    <div class="col-xs-6">
                        <button type="button" id="applyCoupon" class="btn btn-success btn-block">{{ Lang::get('mobileci.modals.coupon_use') }}</button>
                    </div>
                    <div class="col-xs-6">
                        <button type="button" id="denyCoupon" class="btn btn-danger btn-block">{{ Lang::get('mobileci.modals.coupon_ignore') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@stop