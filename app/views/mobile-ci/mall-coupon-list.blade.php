@extends('mobile-ci.layout')

@section('content')
    <div class="container">
        <div class="mobile-ci list-item-container">
            <div class="row">
            @if($data->status === 1)
                <div class="catalogue-wrapper">
                @foreach($data->records as $coupon)
                    <div class="col-xs-12 col-sm-12 item-x" data-ids="{{$coupon->promotion_id}}"  id="item-{{$coupon->promotion_id}}">
                        <section class="list-item-single-tenant">
                            <a class="list-item-link" href="{{ url('customer/mallcoupon?id='.$coupon->promotion_id) }}">
                                <div class="coupon-new-badge">
                                    <div class="new-number">{{$coupon->quantity}}</div>
                                </div>
                                <div class="list-item-info">
                                    <header class="list-item-title">
                                        <div><strong>{{{ $coupon->promotion_name }}}</strong></div>
                                    </header>
                                    <header class="list-item-subtitle">
                                        <div>
                                            {{-- Limit description per two line and 45 total character --}}
                                            <?php
                                                $desc = explode("\n", $coupon->description);
                                            ?>
                                            @if (mb_strlen($coupon->description) > 45)
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
                                                    {{{ mb_substr($coupon->description, 0, 45, 'UTF-8') . '...' }}}
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
                                                    {{{ mb_substr($coupon->description, 0, 45, 'UTF-8') }}}
                                                @endif
                                            @endif
                                        </div>
                                    </header>
                                </div>
                                <div class="list-vignette-non-tenant"></div>
                                @if(!empty($coupon->image))
                                <img class="img-responsive img-fit-tenant" src="{{ asset($coupon->image) }}" />
                                @else
                                <img class="img-responsive img-fit-tenant" src="{{ asset('mobile-ci/images/default_coupon.png') }}"/>
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
                        <h4>{{ Lang::get('mobileci.greetings.how_to_get_coupons') }}</h4>
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

@section('ext_script_bot')
<script type="text/javascript">
    $(document).ready(function(){
        $('body').on('click', '#load-more-x', function(){
            var listOfIDs = [];
            $('.catalogue-wrapper .item-x').each(function(id){
                listOfIDs.push($(this).data('ids'));
            });
            loadMoreX('my-coupon', listOfIDs);
        });
    }); 
</script>
@stop