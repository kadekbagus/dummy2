@extends('mobile-ci.layout')

@section('content')
    <div class="row">
        <div class="col-xs-12 text-center">
            <div class="profile-img-wrapper">
                @if(count($media) > 0)
                <img src="{{ asset($media[0]->path) }}">
                @else
                <img src="{{ asset('mobile-ci/images/default_my_profile_alternate.png') }}">
                @endif
                <div class="list-vignette"></div>
                <div class="profile-img-title">
                    <div class="col-xs-12 pad-left text-left">
                        <h4><strong>{{{$user_full_name}}}</strong></h4>
                        <p>{{{$user->user_email}}}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop