@extends('mobile-ci.layout')

@section('content')
    <div class="container">
        <div class="mobile-ci list-item-container">
            <div class="row">
            @if($data->status === 1)
                @if(sizeof($data->records) > 0)
                    @foreach($data->records as $luckydraw)
                        <div class="col-xs-12 col-sm-12" id="item-{{$luckydraw->lucky_draw_id}}">
                            <section class="list-item-single-tenant">
                                <a class="list-item-link" href="{{ url('customer/luckydraw?id='.$luckydraw->lucky_draw_id) }}">
                                    <div class="list-item-info">
                                        <header class="list-item-title">
                                            <div><strong>{{ $luckydraw->lucky_draw_name }}</strong></div>
                                        </header>
                                        <header class="list-item-subtitle">
                                            <div>
                                                {{-- Limit description per two line and 45 total character --}}
                                                <?php
                                                    $desc = explode("\n", $luckydraw->description);
                                                ?>
                                                @if (mb_strlen($luckydraw->description) > 45)
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
                                                        {{{ mb_substr($luckydraw->description, 0, 45, 'UTF-8') . '...' }}}
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
                                                        {{{ mb_substr($luckydraw->description, 0, 45, 'UTF-8') }}}
                                                    @endif
                                                @endif
                                            </div>
                                        </header>
                                    </div>
                                    <div class="list-vignette-non-tenant"></div>
                                    @if(!empty($luckydraw->image))
                                    <img class="img-responsive img-fit-tenant" src="{{ asset($luckydraw->image) }}" />
                                    @else
                                    <img class="img-responsive img-fit-tenant" src="{{ asset('mobile-ci/images/default_lucky_number.png') }}"/>
                                    @endif
                                </a>
                            </section>
                        </div>
                    @endforeach
                @else
                    <div class="row padded">
                        <div class="col-xs-12">
                            <h4>{{ Lang::get('mobileci.greetings.latest_luckydraw_coming_soon') }}</h4>
                        </div>
                    </div>
                @endif
            @else
                <div class="row padded">
                    <div class="col-xs-12">
                        <h4>{{ Lang::get('mobileci.greetings.latest_luckydraw_coming_soon') }}</h4>
                    </div>
                </div>
            @endif
            </div>
        </div>
    </div>
@stop

@section('modals')
<div class="modal fade" id="userActivationModal" tabindex="-1" role="dialog" aria-labelledby="userActivationModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title" id="userActivationModalLabel"><i class="fa fa-envelope-o"></i> {{ Lang::get('mobileci.promotion.info') }}</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-xs-12 text-center">
                        <p style="font-size:15px;">
                            {{{ sprintf(Lang::get('mobileci.modals.message_user_activation'), $user_email) }}}
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="row">
                    <div class="col-xs-12 text-center">
                        <button type="button" class="btn btn-info btn-block" data-dismiss="modal">{{ Lang::get('mobileci.modals.okay') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@stop