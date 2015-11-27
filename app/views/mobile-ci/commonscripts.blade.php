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
                        {{ \Orbit\UrlGenerator::hiddenSessionIdField() }}
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" id="searchProductBtn">{{ Lang::get('mobileci.modals.search_button') }}</button>
            </div>
        </div>
    </div>
</div>
@if(Config::get('orbit.shop.membership'))
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
                    <h3><strong><i>{{ Lang::get('mobileci.modals.membership_notfound') }}</i></strong></h3>
                    <h4><strong>{{ Lang::get('mobileci.modals.membership_want_member') }}</strong></h4>
                    <p>{{ Lang::get('mobileci.modals.membership_great_deal') }}</p>
                    <p><i>{{ Lang::get('mobileci.modals.membership_contact_our') }}</i></p>
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
@endif
<!-- Language Modal -->
<div class="modal fade bs-example-modal-sm" id="multi-language-popup" tabindex="-1" role="dialog" aria-labelledby="multi-language" aria-hidden="true">
    <div class="modal-dialog modal-sm orbit-modal" style="width:320px; margin: 30px auto;">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title">{{ Lang::get('mobileci.modals.language_title') }}</h4>
            </div>
            <form method="POST" name="selecLang" action="{{ url('/customer/setlanguage') }}">
                <div class="modal-body">
                    <select class="form-control" name="lang" id="selected-lang">
                        @if (isset($languages))
                                @foreach ($languages as $lang)
                                    <option value="{{{ $lang->language->name }}}" @if (isset($_COOKIE['orbit_preferred_language'])) @if ($lang->language->name === $_COOKIE['orbit_preferred_language']) selected @endif @else @if($lang->language->name === $default_lang) selected @endif @endif>{{{ $lang->language->name_long }}} @if($lang->language->name === $default_lang) (Default) @endif</option>
                                @endforeach
                        @endif
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-info" value="{{ Lang::get('mobileci.modals.ok') }}">{{ Lang::get('mobileci.modals.ok') }}</button>
                </div>
            </form>
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

        @if (Config::get('orbit.shop.offline_check.enable', TRUE))
            run();
            setInterval(run, {{ Config::get('orbit.shop.offline_check.interval' }});
        @endif

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
        $('.backBtn404').click(function(){
            window.history.back()
        });
        $('#search-tool-btn').click(function(){
            $('#search-tool').toggle();
        });
        if($('#cart-number').attr('data-cart-number') == '0'){
            $('.cart-qty').css('display', 'none');
        }
        @if(Config::get('orbit.shop.membership'))
        $('#membership-card').click(function(){
            $('#membership-card-popup').modal();
        });
        @endif
        $('#multi-language').click(function(){
            $('#multi-language-popup').modal();
        });
    });

    // pinch zoom using hammerjs
    $(document).on('click', '.zoomer', function(){
        setTimeout(function(){
            $("body").addClass("modal-open");
            var el = $('.featherlight-content').get(0).getElementsByTagName("img")[0];
            el.addEventListener('touchstart', function (e) {
                e.preventDefault()
            });

            var mc = new Hammer.Manager(el);
            var pinch = new Hammer.Pinch();
            var pan = new Hammer.Pan();
            var tap = new Hammer.Tap();

            pinch.recognizeWith(pan);
            mc.add([pinch, pan, tap]);

            var initialScale = 1;
            var initialDeltaX = 0;
            var initialDeltaY = 0;

            var adjustScale = 1;
            var adjustDeltaX = 0;
            var adjustDeltaY = 0;

            var currentScale = null;
            var currentDeltaX = null;
            var currentDeltaY = null;

            mc.on("pinch pan tap", function(ev) {
                var transforms = [];

                // Adjusting the current pinch/pan event properties using the previous ones set when they finished touching
                currentScale = adjustScale * ev.scale;
                currentDeltaX = adjustDeltaX + (ev.deltaX / currentScale);
                currentDeltaY = adjustDeltaY + (ev.deltaY / currentScale);

                // Concatenating and applying parameters.
                transforms.push('scale('+currentScale+')');
                transforms.push('translate('+currentDeltaX+'px,'+currentDeltaY+'px)');
                $('.featherlight-content img').css("transform", transforms.join(' '));
            });

            mc.on("panend pinchend", function (ev) {
                var transforms = [];
                var afterScale = adjustScale * ev.scale;
                if(afterScale > initialScale) { // Saving the final transforms for adjustment next time the user interacts.
                    adjustScale = currentScale;
                    adjustDeltaX = currentDeltaX;
                    adjustDeltaY = currentDeltaY;
                } else { // reset image to initial state if zoomed out smaller than initial scale
                    adjustScale = initialScale;
                    adjustDeltaX = initialDeltaX;
                    adjustDeltaY = initialDeltaY;
                    transforms.push('scale('+initialScale+')');
                    transforms.push('translate('+initialDeltaX+'px,'+initialDeltaY+'px)');
                    $('.featherlight-content img').css("transform", transforms.join(' '));
                }
            });

        }, 300);
    });

    $(document).on('click', '.featherlight-close', function(){
        $("body").removeClass("modal-open");
    });
</script>
