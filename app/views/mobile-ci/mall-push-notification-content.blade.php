<div class="row vertically-spaced">
	<div class="col-xs-12">
		@if($inbox->inbox_type == 'lucky_draw_issuance')
		<img src="{{ asset('mobile-ci/images/default_lucky_number.png') }}" class="img-responsive side-margin-center">
		@elseif($inbox->inbox_type == 'lucky_draw_announcement')
		<img src="{{ asset('mobile-ci/images/default_lucky_number.png') }}" class="img-responsive side-margin-center">
		@elseif($inbox->inbox_type == 'activation')
		<img src="{{ asset('mobile-ci/images/default_lucky_number.png') }}" class="img-responsive side-margin-center">
		@endif
	</div>	
</div>

@if($inbox->inbox_type == 'activation')
<div class="row vertically-spaced">
	<div class="col-xs-12">
		<p>
			An email has been sent to <b>{{ $fullName }}</b>. Please follow the instruction to activate your account.
		</p>
		<p>
			<small>
				If for some reason you don't see an email from us in a couple of hours, please check your Spam or Junk folder.
			</small>
		</p>
	</div>
</div>
@elseif($inbox->inbox_type == 'lucky_draw_issuance')
<div class="row vertically-spaced">
	<div class="col-xs-12">
		<a href="{{ url('customer/luckydraws') }}" style="color:#000;">
			<span class="link-spanner" style="height:100%;"></span>
			<h4>Hello {{$fullName}},</h4>
	        <p>{{Lang::get('mobileci.lucky_draw.congratulation')}} {{count($listItem)}} {{Lang::get('mobileci.lucky_draw.no_lucky_draw')}} <strong>{{$item->records[0]->lucky_draw_name}}</strong>.
	        {{ Lang::get('mobileci.lucky_draw.lucky_draw_info_1') }} {{ $dateIssued }}.
	        </p>

	        <ol>
	        @foreach ($listItem as $number)
	            <li>{{$number->lucky_draw_number_code}}</li>
	        @endforeach
	        </ol>

	        <p style="margin-top:1em">
	            {{Lang::get('mobileci.lucky_draw.goodluck')}}</br>
	            <strong>{{$mallName}}</strong>
	        </p>
        </a>
	</div>
</div>
@endif