<div class="modal fade" id="tour-confirmation" data-keyboard="false" data-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">{{ Lang::get('mobileci.tour.modal.title') }}</h4>
            </div>
            <div class="modal-body">
                {{ Lang::get('mobileci.tour.modal.content') }}
            </div>
            <div class="modal-footer">
                <button type="button" id="modal-end-tour" class="btn btn-danger">{{ Lang::get('mobileci.tour.modal.end_button') }}</button>
                <button type="button" id="modal-start-tour" class="btn btn-primary">{{ Lang::get('mobileci.tour.modal.start_button') }}</button>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript">
$(function() {
    @if (Request::is('customer/home'))

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
                                    '<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6">' +
                                    '</div>' +
                                    '<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6">' +
                                        '<button class="btn btn-primary btn-block main-end" data-role="end">OK</button>' +
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

                $.cookie("orbit-tour", true, { expires : 60 });
            },
            steps: [{
                element: "#orbit-tour-profile",
                placement: "left",
                animation: true,
                backdrop: true,
                backdropContainer: 'body',
                title: "{{ Lang::get('mobileci.tour.end.title') }}",
                content: "{{ Lang::get('mobileci.tour.end.content') }}",
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
                                        '<button class="btn btn-primary btn-block" data-role="prev"><i class="fa fa-chevron-left"></i></button>' +
                                    '</div>' +
                                    '<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6">' +
                                        '<button class="btn btn-primary btn-block" data-role="next"><i class="fa fa-chevron-right"></i></button>' +
                                    '</div>' +
                                    '<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6">' +
                                        '<button class="btn btn-primary btn-block main-end" data-role="end">END</button>' +
                                    '</div>' +
                                '</div>' +
                            '</div>' +
                        '</div>',
            onEnd: function (tour) {
                // Start the tour
                if (endTour.ended()) {
                    endTour.restart();
                } else{
                    endTour.start();
                }
            },
            steps: [{
                element: "#orbit-tour-home",
                placement: "bottom",
                animation: true,
                backdrop: true,
                backdropContainer: 'body',
                title: "{{ Lang::get('mobileci.tour.home.title') }}",
                content: "{{ Lang::get('mobileci.tour.home.content') }}",
                arrowClass: 'top-left'
            }, {
                element: "#orbit-tour-back",
                placement: "right",
                animation: true,
                backdrop: true,
                title: "{{ Lang::get('mobileci.tour.back.title') }}",
                content: "{{ Lang::get('mobileci.tour.back.content') }}",
                arrowClass: 'top-left'
            }, {
                element: "#orbit-tour-search",
                placement: "bottom",
                animation: true,
                backdrop: true,
                title: "{{ Lang::get('mobileci.tour.search.title') }}",
                content: "{{ Lang::get('mobileci.tour.search.content') }}",
                arrowClass: 'top-right'
            }, {
                element: "#orbit-tour-tenant",
                placement: "bottom",
                animation: true,
                backdrop: true,
                classToFocus: ['.single-widget-container:eq(0)'],
                title: "{{ Lang::get('mobileci.tour.directory.title') }}",
                content: "{{ Lang::get('mobileci.tour.directory.content') }}",
                arrowClass: 'top-right'
            }, {
                element: "#orbit-tour-profile",
                placement: "left",
                animation: true,
                backdrop: true,
                title: "{{ Lang::get('mobileci.tour.setting.title') }}",
                content: "{{ Lang::get('mobileci.tour.setting.content') }}",
                arrowClass: 'top-right'
            }, {
            //     element: "#orbit-tour-connection",
            //     placement: "left",
            //     animation: true,
            //     backdrop: true,
            //     title: "{{ Lang::get('mobileci.tour.home.title') }}",
            //     content: "{{ Lang::get('mobileci.tour.home.content') }}",
            //     arrowClass: 'top-right'
            // }, {
            //     element: ".single-widget-container:eq(0)",
            //     placement: "bottom",
            //     animation: true,
            //     backdrop: true,
            //     backdropContainer: 'body',
            //     title: {{ Lang::get('mobileci.tour.home.title') }}",
            //     content: {{ Lang::get('mobileci.tour.home.content') }}",
            //     arrowClass: 'top-left'
            // }, {
                element: ".single-widget-container:eq(1)",
                placement: "bottom",
                animation: true,
                backdrop: true,
                backdropContainer: 'body',
                title: "{{ Lang::get('mobileci.tour.promotion.title') }}",
                content: "{{ Lang::get('mobileci.tour.promotion.content') }}",
                arrowClass: 'top-right'
            }, {
                element: ".single-widget-container:eq(2)",
                placement: "top",
                animation: true,
                backdrop: true,
                backdropContainer: 'body',
                title: "{{ Lang::get('mobileci.tour.news.title') }}",
                content: "{{ Lang::get('mobileci.tour.news.content') }}",
                arrowClass: 'bottom-left'
            }, {
            //     element: ".single-widget-container:eq(5)",
            //     placement: "top",
            //     animation: true,
            //     backdrop: true,
            //     backdropContainer: 'body',
            //     title: "{{ Lang::get('mobileci.tour.home.title') }}",
            //     content: "{{ Lang::get('mobileci.tour.home.content') }}",
            //     arrowClass: 'bottom-right'
            // }, {
                element: ".single-widget-container:eq(3)",
                placement: "top",
                animation: true,
                backdrop: true,
                backdropContainer: 'body',
                title: "{{ Lang::get('mobileci.tour.coupon.title') }}",
                content: "{{ Lang::get('mobileci.tour.coupon.content') }}",
                arrowClass: 'bottom-right'
            }]
        });

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

        // Event click for the tour from the settings
        $('#orbit-tour-setting').on('click', function(event) {
            event.preventDefault();
            prepareHeader();

            // Start the tour
            if (homeTour.ended()) {
                homeTour.restart();
            } else{
                homeTour.start();
            }
        });
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
        }

    @endif

});
</script>
