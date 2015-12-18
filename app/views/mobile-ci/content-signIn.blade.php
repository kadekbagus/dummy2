<div class="row header-signin">
    <div class="col-xs-12 text-center">
        <img class="img-responsive header-logo" src="{{asset($retailer->logo)}}" />
    </div>
</div>

<div class="content-signin">
    <div class="social-media-wraper">
        <div class="social-media-container">
            <div class="row">
                <div class="col-xs-4 text-center">
                    <button type="button" class="btn btn-primary icon-button facebook text-center"><i class="fa fa-facebook fa-4x"></i></button>
                </div>
                <div class="col-xs-4 text-center">
                    <button type="button" class="btn btn-danger icon-button google text-center"><i class="fa fa-google-plus fa-4x"></i></button>
                </div>
                <div class="col-xs-4 text-center">
                    <button type="button" class="btn btn-info icon-button form text-center" data-toggle="modal" data-target="#formModal"><i class="fa fa-file-text fa-4x"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="formModal" tabindex="-1" role="dialog" aria-labelledby="formModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="close close-form" data-dismiss="modal" aria-label="Close">
                    <i class="fa fa-times"></i>
                </button>
                <form>
                    <div class="form-group">
                        <input type="text" value="{{{ $user_email }}}" class="form-control" placeholder="{{ Lang::get('mobileci.signin.email_placeholder') }}">
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control" placeholder="First Name">
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control" placeholder="Last Name">
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control" placeholder="Gender">
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control" placeholder="Date of Birth (dd/mm/yyyy)">
                    </div>
                </form>
                <div class="form-group">
                    <mute>
                        By clicking SignUp you confirm that you accept
                        <a target="_blank" href="{{ Config::get('orbit.contact_information.privacy_policy_url') }}">Privacy Policy</a>.
                        <a target="_blank" href="{{ Config::get('orbit.contact_information.terms_of_service_url') }}">Terms and Conditions</a>
                    </mute>
                </div>
            </div>
            <div class="modal-footer footer-form-modal">
                <div class="row social-media-container">
                    <div class="col-xs-2 text-left">
                        <button type="button" class="btn btn-primary icon-button facebook text-center">
                            <i class="fa fa-facebook"></i>
                        </button>
                    </div>
                    <div class="col-xs-2 text-left">
                        <button type="button" class="btn btn-danger icon-button google text-center">
                            <i class="fa fa-google-plus"></i>
                        </button>
                    </div>
                    <div class="col-xs-8 text-right">
                        <button type="button" class="btn btn-info icon-button form text-center" disabled>Signup</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    var contentHeight = $(window).height() - 90;
    $('.content-signin').height(contentHeight);
</script>