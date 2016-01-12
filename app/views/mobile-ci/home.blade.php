@extends('mobile-ci.layout')

@section('ext_style')
{{ HTML::style('mobile-ci/stylesheet/jquery-ui.min.css') }}
<style type="text/css">
.img-responsive{
    margin:0 auto;
}
</style>
@stop

@section('content')
<div class="container">
    <div class="mobile-ci home-widget widget-container">
        <div class="row">
            @foreach($widgets as $i => $widget)
                @if($i % 3 === 0)
                <div class="col-xs-12 col-sm-12">
                    <section class="widget-single">
                        <a class="widget-link" data-widget="{{ $widget->widget_id }}" href="{{ url('customer/' . $widget->url) }}">
                            @if(! empty($widget->new_item_count))
                            <div class="widget-new-badge">
                                <div class="new-number">{{$widget->new_item_count}}</div>
                                <div class="new-number-label">new</div>
                            </div>
                            @endif
                            <div class="widget-info">
                                <header class="widget-title">
                                    <div><strong>{{ucwords(strtolower($widget->display_title))}}</strong></div>
                                </header>
                                <header class="widget-subtitle">
                                    @if($widget->item_count > 0)
                                    <div>{{$widget->item_count}} {{$widget->display_sub_title}}</div>
                                    @else
                                    <div>&nbsp;</div>
                                    @endif
                                </header>
                            </div>
                            <div class="list-vignette"></div>
                            <img class="img-responsive img-fit" src="{{ asset($widget->image) }}" />
                        </a>
                    </section>
                </div>
                @elseif($i % 3 === 1)
                    @if($i === count($widgets) - 1)
                        <div class="col-xs-12 col-sm-12">
                            <section class="widget-single">
                                <a class="widget-link" data-widget="{{ $widget->widget_id }}" href="{{ url('customer/' . $widget->url) }}">
                                    @if(! empty($widget->new_item_count))
                                    <div class="widget-new-badge">
                                        <div class="new-number">{{$widget->new_item_count}}</div>
                                        <div class="new-number-label">new</div>
                                    </div>
                                    @endif
                                    <div class="widget-info">
                                        <header class="widget-title">
                                            <div><strong>{{ucwords(strtolower($widget->display_title))}}</strong></div>
                                        </header>
                                        <header class="widget-subtitle">
                                            @if($widget->item_count > 0)
                                            <div>{{$widget->item_count}} {{$widget->display_sub_title}}</div>
                                            @else
                                            <div>&nbsp;</div>
                                            @endif
                                        </header>
                                    </div>
                                    <div class="list-vignette"></div>
                                    <img class="img-responsive img-fit" src="{{ asset($widget->image) }}" />
                                </a>
                            </section>
                        </div>
                    @else
                        <div class="col-xs-6 col-sm-6">
                            <section class="widget-single">
                                <a class="widget-link" data-widget="{{ $widget->widget_id }}" href="{{ url('customer/' . $widget->url) }}">
                                    @if(! empty($widget->new_item_count))
                                    <div class="widget-new-badge">
                                        <div class="new-number">{{$widget->new_item_count}}</div>
                                        <div class="new-number-label">new</div>
                                    </div>
                                    @endif
                                    <div class="widget-info">
                                        <header class="widget-title">
                                            <div><strong>{{ucwords(strtolower($widget->display_title))}}</strong></div>
                                        </header>
                                        <header class="widget-subtitle">
                                            @if($widget->item_count > 0)
                                            <div>{{$widget->item_count}} {{$widget->display_sub_title}}</div>
                                            @else
                                            <div>&nbsp;</div>
                                            @endif
                                        </header>
                                    </div>
                                    <div class="list-vignette"></div>
                                    <img class="img-responsive img-fit" src="{{ asset($widget->image) }}" />
                                </a>
                            </section>
                        </div>
                    @endif
                @else
                    <div class="col-xs-6 col-sm-6">
                        <section class="widget-single">
                            <a class="widget-link" data-widget="{{ $widget->widget_id }}" href="{{ url('customer/' . $widget->url) }}">
                                @if(! empty($widget->new_item_count))
                                <div class="widget-new-badge">
                                    <div class="new-number">{{$widget->new_item_count}}</div>
                                    <div class="new-number-label">new</div>
                                </div>
                                @endif
                                <div class="widget-info">
                                    <header class="widget-title">
                                        <div><strong>{{ucwords(strtolower($widget->display_title))}}</strong></div>
                                    </header>
                                    <header class="widget-subtitle">
                                        @if($widget->item_count > 0)
                                        <div>{{$widget->item_count}} {{$widget->display_sub_title}}</div>
                                        @else
                                        <div>&nbsp;</div>
                                        @endif
                                    </header>
                                </div>
                                <div class="list-vignette"></div>
                                <img class="img-responsive img-fit" src="{{ asset($widget->image) }}" />
                            </a>
                        </section>
                    </div>
                @endif
            @endforeach
        </div>  
    </div>
</div>
@stop

@section('modals')
<!-- Modal -->
<div class="modal fade" id="noModal" tabindex="-1" role="dialog" aria-labelledby="noModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title" id="noModalLabel"></h4>
            </div>
            <div class="modal-body">
                <p id="noModalText"></p>
            </div>
            <div class="modal-footer">
                <div class="pull-right"><button type="button" class="btn btn-default" data-dismiss="modal">{{ Lang::get('mobileci.modals.close') }}</button></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="verifyModal" tabindex="-1" role="dialog" aria-labelledby="verifyModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title" id="verifyModalLabel"><i class="fa fa-envelope-o"></i> Info</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-xs-12 text-center">
                        <p style="font-size:15px;">
                            <b>{{ Lang::get('mobileci.modals.enjoy_free') }}</b>
                            <br>
                            @if ($active_user)
                            <span style="color:#0aa5d5; font-size:22px; font-weight: bold;">{{ Lang::get('mobileci.modals.unlimited') }}</span>
                            @else
                            <span style="color:#0aa5d5; font-size:22px; font-weight: bold;">30 {{ Lang::get('mobileci.modals.minutes') }}</span>
                            @endif
                            <br>
                            <b>{{ Lang::get('mobileci.modals.internet') }}</b>
                            <br><br>
                            <b>{{ Lang::get('mobileci.modals.check_out_our') }}</b>
                            <br>
                            <b><span style="color:#0aa5d5;">{{ Lang::get('mobileci.page_title.promotion') }}</span> {{ Lang::get('mobileci.modals.and') }} <span style="color:#0aa5d5;">{{ Lang::get('mobileci.page_title.coupon_single') }}</span></b>
                        </p>
                    </div>
                </div>
                <div class="row" style="margin-left: -30px; margin-right: -30px; margin-bottom: -15px;">
                    <div class="col-xs-12">
                        <img class="img-responsive" src="{{ asset('mobile-ci/images/pop-up-banner.png') }}">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="row">
                    <div class="col-xs-12 text-center">
                        <button type="button" class="btn btn-info btn-block" data-dismiss="modal">{{ Lang::get('mobileci.modals.okay') }}</button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-12 text-left">
                            <input type="checkbox" name="verifyModalCheck" id="verifyModalCheck" style="top:2px;position:relative;">
                            <label for="verifyModalCheck">{{ Lang::get('mobileci.modals.do_not_display') }}</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="tour-confirmation" data-keyboard="false" data-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <h4 class="modal-title"><i class="fa fa-lightbulb-o"></i> {{ Lang::get('mobileci.page_title.orbit_tour') }}</h4>
            </div>
            <div class="modal-body">
                {{ Lang::get('mobileci.tour.modal.content') }}
            </div>
            <div class="modal-footer">
                <button type="button" id="modal-end-tour" class="btn btn-danger">{{ Lang::get('mobileci.tour.modal.end_button') }}</button>
                <button type="button" id="modal-start-tour" class="btn btn-info">{{ Lang::get('mobileci.tour.modal.start_button') }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('ext_script_bot')
{{ HTML::script('mobile-ci/scripts/jquery-ui.min.js') }}
{{ HTML::script('mobile-ci/scripts/responsiveslides.min.js') }}
<script type="text/javascript">
    var cookie_dismiss_name = 'dismiss_verification_popup';
    var cookie_dismiss_name_2 = 'dismiss_activation_popup';

    @if ($active_user)
    cookie_dismiss_name = 'dismiss_verification_popup_unlimited';
    @endif

    /**
     * Get Query String from the URL
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string n - Name of the parameter
     */
    function get(n)
    {
        var half = location.search.split(n + '=')[1];
        return half !== undefined ? decodeURIComponent(half.split('&')[0]) : null;
    }

    navigator.getBrowser= (function(){
        var ua = navigator.userAgent, tem,
            M = ua.match(/(opera|chrome|safari|firefox|msie|trident(?=\/))\/?\s*(\d+)/i) || [];
        if(/trident/i.test(M[1])){
            tem=  /\brv[ :]+(\d+)/g.exec(ua) || [];
            return 'IE '+(tem[1] || '');
        }
        if(M[1]=== 'Chrome'){
            tem= ua.match(/\b(OPR|Edge)\/(\d+)/);
            if(tem!= null) return tem.slice(1).join(' ').replace('OPR', 'Opera');
        }
        M= M[2]? [M[1], M[2]]: [navigator.appName, navigator.appVersion, '-?'];
        if((tem= ua.match(/version\/(\d+)/i))!= null) M.splice(1, 1, tem[1]);
        return M;
    })();

    function getMobileOperatingSystem() {
        var userAgent = navigator.userAgent || navigator.vendor || window.opera;

        if( userAgent.match( /iPad/i ) || userAgent.match( /iPhone/i ) || userAgent.match( /iPod/i ) ) {
            return 'ios';
        } else if( userAgent.match( /Android/i ) ) {
            return 'android';
        } else {
            return 'unknown';
        }
    }

    $(document).ready(function() {
        var homescreenPopover = {};
        function homescreenPopup() {
            // get the os first
            if(getMobileOperatingSystem() === 'android' || getMobileOperatingSystem() === 'ios') {
                $('.ci-header').append('<div class="fake-homescreen"></div>')
                // android chrome
                if(navigator.getBrowser[0] === 'Chrome') {
                    $('.fake-homescreen').css({
                        position: 'absolute',
                        right: '22px',
                        top: '4px',
                        width: '1px',
                        height: '1px'
                    });
                    homescreenPopover = {
                        element: '.fake-homescreen',
                        placement: 'bottom',
                        animation: true,
                        backdrop: true,
                        backdropContainer: 'body',
                        title: '{{ Lang::get('mobileci.homescreen.title') }}',
                        content: '{{ Lang::get('mobileci.homescreen.message') }} {{ Lang::get('mobileci.homescreen.add_to') }} {{ Lang::get('mobileci.homescreen.message_after') }}',
                        arrowClass: 'top-right'
                    };
                } else if(navigator.getBrowser[0] === 'Safari') { // ios safari
                    if(! navigator.userAgent.match('CriOS') && ! navigator.userAgent.match('MiuiBrowser')) { // check if it's not chrome in ios, chrome in ios doesn't have add to homescreen menu
                        if(window.orientation == 90 || window.orientation == -90) { // detect safari in landscape
                            $('.fake-homescreen').css({
                                position: 'fixed',
                                top: '4px',
                                width: '1px',
                                height: '1px',
                                right: '120px'
                            });
                            homescreenPopover = {
                                element: '.fake-homescreen',
                                placement: 'bottom',
                                animation: true,
                                backdrop: true,
                                backdropContainer: 'body',
                                title: '{{ Lang::get('mobileci.homescreen.title') }}',
                                content: '{{ Lang::get('mobileci.homescreen.message') }} {{ Lang::get('mobileci.homescreen.add_to') }} {{ Lang::get('mobileci.homescreen.message_after') }}',
                                arrowClass: 'top-right'
                            };
                        } else if(window.orientation == 0 || window.orientation == 180) { // detect safari in portrait
                            $('.fake-homescreen').css({
                                position: 'fixed',
                                bottom: '4px',
                                width: '1px',
                                height: '1px',
                                left: '50%'
                            });
                            homescreenPopover = {
                                element: '.fake-homescreen',
                                placement: 'top',
                                animation: true,
                                backdrop: true,
                                backdropContainer: 'body',
                                title: '{{ Lang::get('mobileci.homescreen.title') }}',
                                content: '{{ Lang::get('mobileci.homescreen.message') }} {{ Lang::get('mobileci.homescreen.add_to') }} {{ Lang::get('mobileci.homescreen.message_after') }}',
                                arrowClass: 'bottom'
                            };
                        }
                    }
                } else if(navigator.getBrowser[0] === 'Firefox') { // android firefox
                    $('.fake-homescreen').css({
                        position: 'absolute',
                        right: '22px',
                        top: '4px',
                        width: '1px',
                        height: '1px'
                    });
                    homescreenPopover = {
                        element: '.fake-homescreen',
                        placement: 'bottom',
                        animation: true,
                        backdrop: true,
                        backdropContainer: 'body',
                        title: '{{ Lang::get('mobileci.homescreen.title') }}',
                        content: '{{ Lang::get('mobileci.homescreen.message') }} {{ Lang::get('mobileci.homescreen.add_to') }} {{ Lang::get('mobileci.homescreen.message_after') }}',
                        arrowClass: 'top-right'
                    };
                } else if(navigator.getBrowser[0] === 'Opera' || navigator.getBrowser[0] === 'O') { // android opera
                    $('.fake-homescreen').css({
                        position: 'absolute',
                        left: '18px',
                        top: '4px',
                        width: '1px',
                        height: '1px'
                    });
                    homescreenPopover = {
                        element: '.fake-homescreen',
                        placement: 'bottom',
                        animation: true,
                        backdrop: true,
                        backdropContainer: 'body',
                        title: '{{ Lang::get('mobileci.homescreen.title') }}',
                        content: '{{ Lang::get('mobileci.homescreen.message') }} {{ Lang::get('mobileci.homescreen.add_to') }} {{ Lang::get('mobileci.homescreen.message_after') }}',
                        arrowClass: 'top-right'
                    };
                }
            }
        }
        homescreenPopup();

        var displayTutorial = false;
        orbitIsViewing = true; {{-- declared in layout --}}
        // Override the content of displayTutorial
        if (get('show_tour') === 'yes') {
            displayTutorial = true;
        }

        var loadModal = function () {
            var onlyEvent = false;

            if (get('from_login') !== 'yes') {
                onlyEvent = true;
            }
            orbitIsViewing = false;
            $('#verifyModal').on('hidden.bs.modal', function () {
                if ($('#verifyModalCheck')[0].checked) {
                    $.cookie(cookie_dismiss_name, 't', {expires: 3650});
                }
            });

            var modals = [
            {
                selector: '#verifyModal',
                display: get('internet_info') === 'yes' && !$.cookie(cookie_dismiss_name)
            }
            ];
            var modalIndex;

            for (modalIndex = 0; modalIndex < modals.length; modalIndex++) {
                {{-- for each displayable modal, after it is hidden try and display the next displayable modal --}}
                if (modals[modalIndex].display) {
                    $(modals[modalIndex].selector).on('hidden.bs.modal', (function(myIndex) {
                        return function() {
                            for (var i = myIndex + 1; i < modals.length; i++) {
                                if (modals[i].display) {
                                    $(modals[i].selector).modal();
                                    return;
                                }
                            }
                        }
                    })(modalIndex));
                }
            }

            {{-- display the first displayable modal --}}
            for (modalIndex = 0; modalIndex < modals.length; modalIndex++) {
                if (modals[modalIndex].display) {
                    $(modals[modalIndex].selector).modal();
                    break;
                }
            }

        };

        // Instance the tour
        var endTour = new Tour({
            name: 'end',
            storage: false,
            template:   '<div class="popover" role="tooltip">' +
                            '<div class="arrow"></div>' +
                            '<h3 class="popover-title"></h3>' +
                            '<div class="popover-content"></div>' +
                            '<div class="popover-navigation">' +
                                '<div class="row">' +
                                    '<div class="col-xs-8 col-sm-8 col-md-8 col-lg-8">' +
                                        '<div class="checkbox">'+
                                            '<label>'+
                                                '<input id="hide_tour" type="checkbox"> <small>{{ Lang::get('mobileci.tour.end.check') }}</small>'+
                                            '</label>'+
                                        '</div>'+
                                    '</div>' +
                                    '<div class="col-xs-4 col-sm-4 col-md-4 col-lg-4">' +
                                        '<button class="btn btn-info btn-block main-end" data-role="end">{{ Lang::get('mobileci.modals.ok') }}</button>' +
                                    '</div>' +
                                '</div>' +
                            '</div>' +
                        '</div>',
            onEnd: function (tour) {
                $('.mobile-ci.ci-header.header-container').css({
                    'position': 'fixed'
                });

                $('.headed-layout.content-container').css({
                    'padding-top': '4.8em'
                });

                if ($('#hide_tour').is(':checked')) {
                    $.cookie("hide-orbit-tour", true, { expires : 60 });
                }

                $.cookie("orbit-tour", true, { expires : 60 });
                loadModal();
            },
            steps: [{
                element: '#orbit-tour-profile',
                placement: 'bottom',
                animation: true,
                backdrop: true,
                backdropContainer: 'body',
                title: '{{ Lang::get('mobileci.tour.end.title') }}',
                content: '{{ Lang::get('mobileci.tour.end.content') }}',
                arrowClass: 'top-right'
            }]
        });

        // Initialize the tour configuration
        endTour.init();


        // Instance the tour
        var homeTour = new Tour({
            name: 'start',
            storage: false,
            template:   '<div class="popover" role="tooltip">' +
                            '<div class="arrow"></div>' +
                            '<a href="#" class="fa fa-times close-orbit" data-role="end"></a>' +
                            '<h3 class="popover-title"></h3>' +
                            '<div class="popover-content"></div>' +
                            '<div class="popover-navigation">' +
                                '<div class="row">' +
                                    '<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6">' +
                                        '<button class="btn btn-info btn-block" data-role="prev"><i class="fa fa-chevron-left"></i></button>' +
                                    '</div>' +
                                    '<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6">' +
                                        '<button class="btn btn-info btn-block" data-role="next"><i class="fa fa-chevron-right"></i></button>' +
                                    '</div>' +
                                    '<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6">' +
                                        '<button class="btn btn-info btn-block main-end" data-role="end">{{ Lang::get('mobileci.tour.end.button') }}</button>' +
                                    '</div>' +
                                '</div>' +
                            '</div>' +
                        '</div>',
            onEnd: function (tour) {
                // Start the tour
                if (!$.cookie('hide-orbit-tour')) {
                    if (endTour.ended()) {
                        endTour.restart();
                    } else{
                        endTour.start();
                    }
                } else {
                    $('.mobile-ci.ci-header.header-container').css({
                        'position': 'fixed'
                    });

                    $('.headed-layout.content-container').css({
                        'padding-top': '4.8em'
                    });

                    if (displayTutorial) {
                        loadModal();
                    }
                }
            },
            steps: [{
                element: '#orbit-tour-home',
                placement: 'bottom',
                animation: true,
                backdrop: true,
                backdropContainer: 'body',
                title: '{{ Lang::get('mobileci.tour.home.title') }}',
                content: '{{ Lang::get('mobileci.tour.home.content') }}',
                arrowClass: 'top-left'
            }, {
                element: '#orbit-tour-back',
                placement: 'bottom',
                animation: true,
                backdrop: true,
                title: '{{ Lang::get('mobileci.tour.back.title') }}',
                content: '{{ Lang::get('mobileci.tour.back.content') }}',
                arrowClass: 'top-left'
            }, {
                element: '#orbit-tour-search',
                placement: 'bottom',
                animation: true,
                backdrop: true,
                title: '{{ Lang::get('mobileci.tour.search.title') }}',
                content: '{{ Lang::get('mobileci.tour.search.content') }}',
                arrowClass: 'top-right'
            }, {
                element: '.single-widget-container:eq(0)',
                placement: 'bottom',
                animation: true,
                backdrop: true,
                classToFocus: ['#orbit-tour-tenant'],
                title: '{{ Lang::get('mobileci.tour.directory.title') }}',
                content: '{{ Lang::get('mobileci.tour.directory.content') }}',
                arrowClass: 'top-right'
            }, {
                element: '#orbit-tour-profile',
                placement: 'bottom',
                animation: true,
                backdrop: true,
                title: '{{ Lang::get('mobileci.tour.setting.title') }}',
                content: '{{ Lang::get('mobileci.tour.setting.content') }}',
                arrowClass: 'top-right'
            }, {
            //     element: '#orbit-tour-connection',
            //     placement: 'left',
            //     animation: true,
            //     backdrop: true,
            //     title: '{{ Lang::get('mobileci.tour.home.title') }}',
            //     content: '{{ Lang::get('mobileci.tour.home.content') }}',
            //     arrowClass: 'top-right'
            // }, {
            //     element: '.single-widget-container:eq(0)',
            //     placement: 'bottom',
            //     animation: true,
            //     backdrop: true,
            //     backdropContainer: 'body',
            //     title: {{ Lang::get('mobileci.tour.home.title') }}',
            //     content: {{ Lang::get('mobileci.tour.home.content') }}',
            //     arrowClass: 'top-left'
            // }, {
                element: '.single-widget-container:eq(1)',
                placement: 'bottom',
                animation: true,
                backdrop: true,
                backdropContainer: 'body',
                title: '{{ Lang::get('mobileci.tour.promotion.title') }}',
                content: '{{ Lang::get('mobileci.tour.promotion.content') }}',
                arrowClass: 'top-right'
            }, {
                element: '.single-widget-container:eq(2)',
                placement: 'top',
                animation: true,
                backdrop: true,
                backdropContainer: 'body',
                title: '{{ Lang::get('mobileci.tour.news.title') }}',
                content: '{{ Lang::get('mobileci.tour.news.content') }}',
                arrowClass: 'bottom-left'
            }, {
            //     element: '.single-widget-container:eq(5)',
            //     placement: 'top',
            //     animation: true,
            //     backdrop: true,
            //     backdropContainer: 'body',
            //     title: '{{ Lang::get('mobileci.tour.home.title') }}',
            //     content: '{{ Lang::get('mobileci.tour.home.content') }}',
            //     arrowClass: 'bottom-right'
            // }, {
                element: '.single-widget-container:eq(3)',
                placement: 'top',
                animation: true,
                backdrop: true,
                backdropContainer: 'body',
                title: '{{ Lang::get('mobileci.tour.coupon.title') }}',
                content: '{{ Lang::get('mobileci.tour.coupon.content') }}',
                arrowClass: 'bottom-right'
            }]
        });
        if(! jQuery.isEmptyObject(homescreenPopover)) {
            homeTour._options.steps.push(homescreenPopover);
        }

        // function to prepare the header for the tour
        var prepareHeader = function () {
            $('.mobile-ci.ci-header.header-container').css({
                'position': 'static'
            });

            $('.headed-layout.content-container').css({
                'padding-top': '0'
            });
        }

        // Initialize the tour configuration
        homeTour.init();

        var loadTutorial = function () {
            orbitIsViewing = true;
            if (!$.cookie('orbit-tour')) {

                $('#tour-confirmation').modal('show');

                $('#modal-end-tour').on('click', function(event) {
                    event.preventDefault();
                    $('#tour-confirmation').modal('hide');
                    prepareHeader();

                    // Start the tour
                    if (endTour.ended()) {
                        endTour.restart();
                    } else{
                        endTour.start();
                    }
                });
                $('#modal-start-tour').on('click', function(event) {
                    event.preventDefault();
                    $('#tour-confirmation').modal('hide');
                    prepareHeader();

                    // Start the tour
                    if (homeTour.ended()) {
                        homeTour.restart();
                    } else{
                        homeTour.start();
                    }
                });
            } else {
                prepareHeader();

                // Start the tour
                if (homeTour.ended()) {
                    homeTour.restart();
                } else{
                    homeTour.start();
                }
            }
        }

        // Event click for the tour from the settings
        $('#orbit-tour-setting').on('click', function(event) {
            event.preventDefault();
            loadTutorial();
        });

        if (displayTutorial || !$.cookie('orbit-tour')) {
            loadTutorial();
        } else {
            loadModal();
        }

        $('#emptyCoupon').click(function(){
          $('#noModalLabel').text('{{ Lang::get('mobileci.modals.info_title') }}');
          $('#noModalText').text('{{ Lang::get('mobileci.modals.message_no_coupon') }}');
          $('#noModal').modal();
        });
        $('#emptyNew').click(function(){
          $('#noModalLabel').text('{{ Lang::get('mobileci.modals.info_title') }}');
          $('#noModalText').text('{{ Lang::get('mobileci.modals.message_no_new_product') }}');
          $('#noModal').modal();
        });
        $('#emptyPromo').click(function(){
          $('#noModalLabel').text('{{ Lang::get('mobileci.modals.info_title') }}');
          $('#noModalText').text('{{ Lang::get('mobileci.modals.message_no_promotion') }}');
          $('#noModal').modal();
        });
        $('#emptyLuck').click(function(){
          $('#noModalLabel').text('{{ Lang::get('mobileci.modals.info_title') }}');
          $('#noModalText').html('{{ Lang::get('mobileci.modals.message_no_lucky_draw') }}');
          $('#noModal').modal();
        });
        $('a.widget-link').click(function(event){
          var link = $(this).attr('href');
          var widgetdata = $(this).data('widget');
          event.preventDefault();

          $.ajax({
            url: '{{ route('click-widget-activity') }}',
            data: {
              widgetdata: widgetdata
            },
            method: 'POST'
          }).always(function(){
            window.location.assign(link);
          });
          return false; //for good measure
        });
        $('#promoModal a').click(function (event){
            var link = $(this).attr('href');
            var eventdata = $(this).data('event');

            event.preventDefault();
            $.ajax({
              data: {
                eventdata: eventdata
              },
              method: 'POST',
              url:apiPath+'customer/eventpopupactivity'
            }).always(function(data){
              window.location.assign(link);
            });
            return false; //for good measure
        });
        $('#slider1').responsiveSlides({
            auto: true,
            pager: false,
            nav: true,
            prevText: '<i class="fa fa-chevron-left"></i>',
            nextText: '<i class="fa fa-chevron-right"></i>',
            speed: 500
        });
        $('#slider2').responsiveSlides({
            auto: true,
            pager: false,
            nav: true,
            prevText: '<i class="fa fa-chevron-left"></i>',
            nextText: '<i class="fa fa-chevron-right"></i>',
            speed: 500
        });
        $('#slider3').responsiveSlides({
            auto: true,
            pager: false,
            nav: true,
            prevText: '<i class="fa fa-chevron-left"></i>',
            nextText: '<i class="fa fa-chevron-right"></i>',
            speed: 500
        });
        $('#slider4').responsiveSlides({
            auto: true,
            pager: false,
            nav: true,
            prevText: '<i class="fa fa-chevron-left"></i>',
            nextText: '<i class="fa fa-chevron-right"></i>',
            speed: 500
        });

        $.each($('.rslides li'), function(i, v){
            $(this).css('height', $(this).width());
        });
    });
    $(window).resize(function(){
        $.each($('.rslides li'), function(i, v){
            $(this).css('height', $(this).width());
        });
    });
    $(window).ready(function(){
        $.each($('.rslides li'), function(i, v){
            $(this).css('height', $(this).width());
        });
    });
</script>
@stop
