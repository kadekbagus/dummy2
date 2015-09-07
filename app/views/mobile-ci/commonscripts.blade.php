<!-- Search Product Modal -->
<div class="modal fade" id="SearchProducts" tabindex="-1" role="dialog" aria-labelledby="SearchProduct" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title" id="SearchProduct">{{ Lang::get('mobileci.modals.search_title') }}</h4>
            </div>
            <div class="modal-body">
                <form method="GET" name="searchForm" id="searchForm" action="{{ url('/customer/tenants') }}">
                    <div class="form-group">
                        <label for="keyword">{{ Lang::get('mobileci.modals.search_label') }}</label>
                        <input type="text" class="form-control" name="keyword" id="keyword" placeholder="{{ Lang::get('mobileci.modals.search_placeholder') }}">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" id="searchProductBtn">{{ Lang::get('mobileci.modals.search_button') }}</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade bs-example-modal-sm" id="membership-card-popup" tabindex="-1" role="dialog" aria-labelledby="membership-card" aria-hidden="true">
    <div class="modal-dialog modal-sm orbit-modal" style="width:320px; margin: 30px auto;">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title">{{ Lang::get('mobileci.modals.membership_title') }}</h4>
            </div>
            <div class="modal-body">
                @if (! empty($user->membership_number))
                <div class="member-card">
                    <img class="img-responsive" src="{{ asset('mobile-ci/images/lmp-widgets/membership_card.png') }}">
                    <h2>
                        <span>
                            <strong>
                                {{ (strlen($user->user_firstname . ' ' . $user->user_lastname) >= 20) ? substr($user->user_firstname . ' ' . $user->user_lastname, 0, 20) : $user->user_firstname . ' ' . $user->user_lastname }}
                            </strong>
                            <span class='spacery'></span>
                            <br>
                            <span class='spacery'></span>
                            <strong>
                                {{ $user->membership_number }}
                            </strong>
                        </span>
                    </h2>
                </div>
                @else
                <div class="no-member-card text-center">
                    <h3><strong><i>Membership Not Found</i></strong></h3>
                    <h4><strong>Want to be a member?</strong></h4>
                    <p>To get special great deals from us</p>
                    <p><i>Please, contact our customer service to get your membership number.</i></p>
                    <br>
                    <p><small>Lippo Mall Management</small></p>
                </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" data-dismiss="modal">{{ Lang::get('mobileci.modals.close') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Language Modal -->
<div class="modal fade bs-example-modal-sm" id="multi-language-popup" tabindex="-1" role="dialog" aria-labelledby="multi-language" aria-hidden="true">
    <div class="modal-dialog modal-sm orbit-modal" style="width:320px; margin: 30px auto; height:1000px;">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title">{{ Lang::get('mobileci.modals.language_title') }}</h4>
            </div>
            <div class="dropdown">
                <button id="dLabel" type="button" class="btn btn-info btn-block" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <span class="buttonLabel">
                        @if (isset($_COOKIE['orbit_preferred_language']))
                            @if (isset($languages))
                                @foreach ($languages as $lang)
                                    @if ($lang->language->name === $_COOKIE['orbit_preferred_language']) 
                                        {{{ $lang->language->name_long }}}
                                    @endif
                                @endforeach
                            @endif
                        @else
                            {{{ 'Language' }}}
                        @endif
                    </span>
                    <span class="caret"></span>
                </button>
                <ul class="dropdown-menu" role="menu" aria-labelledby="dLabel" id="lang">
                    @if (isset($languages))
                        @foreach ($languages as $lang)
                            @if (isset($_COOKIE['orbit_preferred_language']))
                                @if ($lang->language->name !== $_COOKIE['orbit_preferred_language']) 
                                    <li data-lang="{{{ $lang->language->name }}}"><span>{{{ $lang->language->name_long }}}</span></li>
                                @endif
                            @else
                                <li data-lang="{{{ $lang->language->name }}}"><span>{{{ $lang->language->name_long }}}</span></li>
                            @endif
                        @endforeach
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>

{{ HTML::script('mobile-ci/scripts/offline.js') }}
<script type="text/javascript">
    $(document).ready(function(){
        var run = function () {
            if (Offline.state === 'up') {
              $('#offlinemark').attr('class', 'fa fa-check fa-stack-1x').css({
                'color': '#3c9',
                'left': '6px',
                'top': '0px',
                'font-size': '1em'
              });
              Offline.check();
            } else {
              $('#offlinemark').attr('class', 'fa fa-times fa-stack-1x').css({
                'color': 'red',
                'left': '6px',
                'top': '0px',
                'font-size': '1em'
              });
            }
        };
        run();
        setInterval(run, 5000);

        $('#barcodeBtn').click(function(){
            $('#get_camera').click();
        });
        $('#get_camera').change(function(){
            $('#qrform').submit();
        });
        $('#searchBtn').click(function(){
            $('#SearchProducts').modal();
            setTimeout(function(){
                $('#keyword').focus();
            }, 500);
        });
        $('#searchProductBtn').click(function(){
            $('#SearchProducts').modal('toggle');
            $('#searchForm').submit();
        });
        $('#backBtn').click(function(){
            window.history.back()
        });
        $('#search-tool-btn').click(function(){
            $('#search-tool').toggle();
        });
        if($('#cart-number').attr('data-cart-number') == '0'){
            $('.cart-qty').css('display', 'none');
        }
        $('#membership-card').click(function(){
            $('#membership-card-popup').modal();
        });
        $('#multi-language').click(function(){
            $('#multi-language-popup').modal();
        });
        var path = '{{{ url('/customer/setlanguage') }}}';
        $('#dLabel').dropdown();

        $('#lang>li').click(function(){
            $.post('/customer/setlanguage', {lang: $(this).data('lang')}, function() {
                console.log('/customer/home');
                window.location.replace('/customer/home');
            });
        });
    });
</script>
