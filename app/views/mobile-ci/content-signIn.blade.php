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
                    <!-- <button type="button" class="btn btn-primary icon-button facebook text-center"><i class="fa fa-facebook fa-4x"></i></button> -->
                    <form class="row" name="fbLoginForm" id="fbLoginForm" action="{{ URL::route('mobile-ci.social_login') }}" method="post">
                        <div class="form-group">
                            <input type="hidden" class="form-control" name="time" value="{{{ $orbitTime }}}"/>
                            <input type="hidden" class="form-control" name="mac_address"
                                   value="{{{ Input::get('mac_address', '') }}}"/>
                            <input type="hidden" class="form-control" name="{{{ $orbitOriginName }}}"
                                   value="{{{ $orbitToFacebookOriginValue }}}"/>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary icon-button facebook text-center" id="btn-login-form-fb">
                                    <i class="fa fa-facebook fa-4x"></i>
                            </button>
                        </div>
                        <input class="agree_to_terms" type="hidden" name="agree_to_terms" value="yes"/>
                    </form>
                </div>
                <div class="col-xs-4 text-center">
                    <!-- <button type="button" class="btn btn-danger icon-button google text-center"><i class="fa fa-google-plus fa-4x"></i></button> -->
                    <form class="row" name="googleLoginForm" id="googleLoginForm" action="{{ $googlePlusUrl }}" method="get">
                        <div class="form-group">
                            <input type="hidden" class="form-control" name="time" value="{{{ $orbitTime }}}"/>
                            <input type="hidden" class="form-control" name="mac_address" value="{{{ Input::get('mac_address', '') }}}"/>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-danger icon-button google text-center">
                                <i class="fa fa-google fa-4x"></i>
                            </button>
                        </div>
                        <input class="agree_to_terms" type="hidden" name="agree_to_terms" value="no"/>
                    </form>
                </div>
                <div class="col-xs-4 text-center">
                    <button type="button" class="btn btn-info icon-button form text-center" data-toggle="modal" data-target="#formModal"><i class="fa fa-envelope fa-4x"></i></button>
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
                <form  name="loginForm" id="loginForm" action="{{ url('customer/login') }}" method="post">
                    <div class="form-group">
                        <input type="text" value="{{{ $user_email }}}" class="form-control orbit-auto-login" name="email" id="email" placeholder="{{ Lang::get('mobileci.signin.email_placeholder') }}">
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control" class="userName" value="{{{ $display_name or '' }}}" placeholder="First Name">
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
                    <div class="col-xs-8 text-right orbit-auto-login">
                        <button type="submit" class="btn btn-info icon-button form text-center orbit-auto-login" disabled>Signup</button>
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