<h2>
    {!! trans('email-purchase-rewards.coupon.heading', [], '', $lang) !!}
</h2>

<p>
    {!! trans('email-purchase-rewards.coupon.section_1', ['rewardsCount' => count($rewards)], '', $lang ) !!}
</p>

<a href="{!! $redeemUrl !!}" class="btn btn-light" role="button">
    {!! trans('email-purchase-rewards.coupon.btn_my_wallet', [], '', $lang) !!}
</a>

<div>
    @foreach($rewards as $reward)
        <div>
            <img src="{!! $reward['image_url'] !!}" alt="{!! $reward['name'] !!}">
            <span>{!! $reward['name'] !!}</span>
        </div>
    @endforeach
</div>
