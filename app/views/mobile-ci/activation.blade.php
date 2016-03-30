@extends('mobile-ci.layout-headless')
@section('ext_style')
<style>
.modal-backdrop{
    z-index:0;
}
#loader .modal-backdrop{
    z-index:-1;
}
.img-responsive{
    margin:0 auto;
}
</style>
@stop
@section('content')
<div class="row top-space">
    <div class="col-xs-12">
        <header>
            <div class="row vertically-spaced">
                <div class="col-xs-12 text-center">
                    <span class="greetings">{{ Lang::get('mobileci.greetings.welcome') }}</span>
                </div>
            </div>
            <div class="row vertically-spaced">
                <div class="col-xs-12 text-center">
                    <img class="img-responsive" src="{{ asset($retailer->bigLogo) }}" />
                </div>
            </div>
        </header>
        <form name="loginForm" id="loginForm">
            <input type="hidden" name="token" id="token" value="{{{ Input::get('token') }}}">
            <div class="form-group">
                <input type="password" class="form-control" name="password" id="password" placeholder="{{ Lang::get('mobileci.activation.new_password') }}" pattern=".{5,}" required title="Harap isi password (5-20 karakter)"/>
            </div>
            <div class="form-group">
                <input type="password" class="form-control" name="password_confirmation" id="password_confirmation" placeholder="{{ Lang::get('mobileci.activation.confirm_password') }}" required title="{{ Lang::get('mobileci.activation.fill_password') }}"/>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-success btn-block">{{ Lang::get('mobileci.activation.activate') }}</button>
            </div>
        </form>
    </div>
</div>
@stop

@section('modals')
<!-- Modal -->
<div class="modal fade" id="errorModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.activation.close') }}</span></button>
                <h4 class="modal-title" id="myModalLabel">{{ Lang::get('mobileci.activation.error') }}</h4>
            </div>
            <div class="modal-body">
                <p id="errorModalText"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ Lang::get('mobileci.activation.close') }}</button>
            </div>
        </div>
    </div>
</div>
<div class="loader">
    <div class="loader-wrapper">
        <img class="img-responsive" src="{{ asset('mobile-ci/images/loading.gif') }}">
    </div>
</div>
@stop
@section('ext_script_bot')
<script type="text/javascript">
$(document).ready(function() {

    $('#loginForm').submit(function(event) {
        $('#myModalLabel').text('Error');
        $('#errorModalText').text('');
        $('.loader').css('display', 'block');
        if ($('#password').val() !== $('#password_confirmation').val()) {
            $('#errorModalText').text('Konfirmasi password tidak cocok.');
            $('.loader').css('display', 'none');
            $('#errorModal').modal();
        } else {
            $.ajax({
                method: 'POST',
                url: apiPath + 'user/activate',
                data: {
                    token: $('#token').val(),
                    password: $('#password').val(),
                    password_confirmation: $('#password_confirmation').val()
                }
            }).done(function(data) {
                if (data.status === 'error') {
                    $('#errorModalText').html(data.message);
                    $('.loader').css('display', 'none');
                    $('#errorModal').modal();
                }
                if (data.data) {
                    console.log(data.data);
                    $.ajax({
                        method: 'POST',
                        url: apiPath + 'customer/login',
                        data: {
                            email: data.data.user_email
                        }
                    }).done(function(data) {
                        if (data.status === 'error') {
                            $('#errorModalText').html(data.message);
                            $('.loader').css('display', 'none');
                            $('#errorModal').modal();
                        }
                        if (data.data) {
                            // console.log(data.data);
                            window.location.replace(homePath);
                        }
                    }).fail(function(data) {
                        $('#errorModalText').text(data.responseJSON.message);
                        $('#errorModal').modal();
                    });
                }
            }).fail(function(data) {
                console.log(data.responseJSON);
                $('#errorModalText').text(data.responseJSON.message);
                $('.loader').css('display', 'none');
                $('#errorModal').modal();
            });
        }
        event.preventDefault();
    });
});
</script>
@stop