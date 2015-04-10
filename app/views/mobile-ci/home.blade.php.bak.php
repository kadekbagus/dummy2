@extends('mobile-ci.layout')

@section('ext_style')
  <style type="text/css">
    .img-responsive{
      margin:0 auto;
    }
  </style>
@stop

@section('content')
  <div class="container">
      <div class="row">
        <div class="col-xs-12 text-center merchant-logo">
          <img class="img-responsive" src="{{ asset($retailer->parent->logo) }}" />
        </div>
      </div>
      <div class="mobile-ci home-widget widget-container">
        <div class="row">
          <div class="single-widget-container col-xs-6 col-sm-6">
            <header class="widget-title">
              <span>{{ Lang::get('mobileci.widgets.catalogue') }}</span>
            </header>
            <section class="widget-single">
              <a href="{{ url('customer/catalogue') }}"><img class="img-responsive text-center" src="{{ asset('mobile-ci/images/products/product1.png') }}" /></a>
            </section>
          </div>
          <div class="single-widget-container col-xs-6 col-sm-6">
            <header class="widget-title">
              <span>{{ Lang::get('mobileci.widgets.new_product') }}</span>
            </header>
            <section class="widget-single" style="height:120px;">
              <div id="slider1_container" style="display: none; position: relative; margin: 0 auto; width: 980px; height: 380px; overflow: hidden;">
                  <!-- Loading Screen -->
                  <div u="loading" style="position: absolute; top: 0px; left: 0px;">
                      <div style="filter: alpha(opacity=70); opacity:0.7; position: absolute; display: block;

                      background-color: #000; top: 0px; left: 0px;width: 100%; height:100%;">
                      </div>
                      <div style="position: absolute; display: block; background: url({{asset('mobile-ci/images/loading.gif')}}) no-repeat center center;

                      top: 0px; left: 0px;width: 100%;height:100%;">
                      </div>
                  </div>

                  <!-- Slides Container -->
                  <div u="slides" style="cursor: move; position: absolute; left: 0px; top: 10px; width: 960px; height: 360px;
                  overflow: hidden;">
                      @foreach($new_products as $new_product)
                        <div>
                          <a href="{{ url('customer/product?id='.$new_product->product_id) }}">
                          @if(!is_null($new_product->image))
                            <img u="image" class="img-responsive" src2="{{ asset($new_product->image) }}" style="width: 350px;"/>
                          @else
                            <img u="image" class="img-responsive" src2="{{ asset('mobile-ci/images/default-product.png') }}" style="width: 350px;"/>
                          @endif
                          </a>
                        </div>
                      @endforeach
                  </div>
                 
                  <!-- Arrow Navigator Skin Begin -->
                  <style>
                      /* jssor slider arrow navigator skin 11 css */
                      /*
                      .jssora11l              (normal)
                      .jssora11r              (normal)
                      .jssora11l:hover        (normal mouseover)
                      .jssora11r:hover        (normal mouseover)
                      .jssora11ldn            (mousedown)
                      .jssora11rdn            (mousedown)
                      */
                      .jssora11l, .jssora11r, .jssora11ldn, .jssora11rdn {
                          position: absolute;
                          cursor: pointer;
                          display: block;
                          background: url({{ asset('mobile-ci/images/a11.png') }}) no-repeat;
                          overflow: hidden;
                      }

                      .jssora11l {
                          background-position: -11px -41px;
                      }

                      .jssora11r {
                          background-position: -71px -41px;
                      }

                      .jssora11l:hover {
                          background-position: -131px -41px;
                      }

                      .jssora11r:hover {
                          background-position: -191px -41px;
                      }

                      .jssora11ldn {
                          background-position: -251px -41px;
                      }

                      .jssora11rdn {
                          background-position: -311px -41px;
                      }
                  </style>
                  <!-- Arrow Left -->
                  <span u="arrowleft" class="jssora11l" style="width: 37px; height: 37px; top: 123px; left: 8px;">
                  </span>
                  <!-- Arrow Right -->
                  <span u="arrowright" class="jssora11r" style="width: 37px; height: 37px; top: 123px; right: 8px">
                  </span>
                  <!-- Arrow Navigator Skin End -->
                  <a style="display: none" href="http://www.jssor.com">bootstrap carousel</a>
              </div>
            </section>
          </div>
        </div>
        <div class="row">
          <div class="single-widget-container col-xs-6 col-sm-6">
            <header class="widget-title">
              <span>{{ Lang::get('mobileci.widgets.promotion') }}</span>
            </header>
            <section class="widget-single">
              <div id="slider2_container" style="display: none; position: relative; margin: 0 auto; width: 980px; height: 380px; overflow: hidden;">
                  <!-- Loading Screen -->
                  <div u="loading" style="position: absolute; top: 0px; left: 0px;">
                      <div style="filter: alpha(opacity=70); opacity:0.7; position: absolute; display: block;

                      background-color: #000; top: 0px; left: 0px;width: 100%; height:100%;">
                      </div>
                      <div style="position: absolute; display: block; background: url({{asset('mobile-ci/images/loading.gif')}}) no-repeat center center;

                      top: 0px; left: 0px;width: 100%;height:100%;">
                      </div>
                  </div>

                  <!-- Slides Container -->
                  <div u="slides" style="cursor: move; position: absolute; left: 0px; top: 10px; width: 960px; height: 360px;
                  overflow: hidden;">
                      @foreach($promo_products as $promo_product)
                        <div>
                          <a href="{{ url('customer/product?id='.$promo_product->product_id) }}">
                          @if(!is_null($promo_product->image))
                            <img u="image" class="img-responsive" src2="{{ asset($promo_product->image) }}" style="width: 350px;"/>
                          @else
                            <img u="image" class="img-responsive" src2="{{ asset('mobile-ci/images/default-product.png') }}" style="width: 350px;"/>
                          @endif
                          </a>
                        </div>
                      @endforeach
                  </div>
                  <!-- Arrow Navigator Skin Begin -->
                  <style>
                      /* jssor slider arrow navigator skin 11 css */
                      /*
                      .jssora11l              (normal)
                      .jssora11r              (normal)
                      .jssora11l:hover        (normal mouseover)
                      .jssora11r:hover        (normal mouseover)
                      .jssora11ldn            (mousedown)
                      .jssora11rdn            (mousedown)
                      */
                      .jssora11l, .jssora11r, .jssora11ldn, .jssora11rdn {
                          position: absolute;
                          cursor: pointer;
                          display: block;
                          background: url({{ asset('mobile-ci/images/a11.png') }}) no-repeat;
                          overflow: hidden;
                      }

                      .jssora11l {
                          background-position: -11px -41px;
                      }

                      .jssora11r {
                          background-position: -71px -41px;
                      }

                      .jssora11l:hover {
                          background-position: -131px -41px;
                      }

                      .jssora11r:hover {
                          background-position: -191px -41px;
                      }

                      .jssora11ldn {
                          background-position: -251px -41px;
                      }

                      .jssora11rdn {
                          background-position: -311px -41px;
                      }
                  </style>
                  <!-- Arrow Left -->
                  <span u="arrowleft" class="jssora11l" style="width: 37px; height: 37px; top: 123px; left: 8px;">
                  </span>
                  <!-- Arrow Right -->
                  <span u="arrowright" class="jssora11r" style="width: 37px; height: 37px; top: 123px; right: 8px">
                  </span>
                  <!-- Arrow Navigator Skin End -->
                  <a style="display: none" href="http://www.jssor.com">bootstrap carousel</a>
              </div>
            </section>
          </div>
          <div class="single-widget-container col-xs-6 col-sm-6">
            <header class="widget-title">
              <span>{{ Lang::get('mobileci.widgets.coupon') }}</span>
            </header>
            <section class="widget-single">
              <img class="img-responsive text-center" src="{{ asset('mobile-ci/images/products/product1.png') }}" />   
            </section>
          </div>
        </div>
      </div>
    </div>
@stop

@section('modals')
  <!-- Modal -->
  <div class="modal fade" id="promoModal" tabindex="-1" role="dialog" aria-labelledby="promoModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
      <div class="modal-content">
        <div class="modal-header orbit-modal-header">
          <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Tutup</span></button>
          <h4 class="modal-title" id="promoModalLabel">Promosi</h4>
        </div>
        <div class="modal-body">
          <p id="promoModalText">
            @if(! is_null($promotion->image)) 
            <img class="img-responsive" src="{{ asset($promotion->image) }}"><br> 
            @endif 
            <b>{{ $promotion->promotion_name }}</b> <br> 
            {{ $promotion->description }}
          </p>
        </div>
        <div class="modal-footer">
          <div class="pull-right"><button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button></div>
        </div>
      </div>
    </div>
  </div>
@stop

@section('ext_script_bot')
  {{ HTML::script('mobile-ci/scripts/ie10-viewport-bug-workaround.js') }}
  {{ HTML::script('mobile-ci/scripts/jssor.slider.mini.js') }}
  <script type="text/javascript">

        jQuery(document).ready(function ($) {
            @if(! is_null($promotion))
              $('#promoModal').modal();
            @endif
            var options = {
                $AutoPlay: false,                                    //[Optional] Whether to auto play, to enable slideshow, this option must be set to true, default value is false
                $AutoPlaySteps: 1,                                  //[Optional] Steps to go for each navigation request (this options applys only when slideshow disabled), the default value is 1
                $AutoPlayInterval: 3000,                            //[Optional] Interval (in milliseconds) to go for next slide since the previous stopped if the slider is auto playing, default value is 3000
                $PauseOnHover: 1,                                   //[Optional] Whether to pause when mouse over if a slider is auto playing, 0 no pause, 1 pause for desktop, 2 pause for touch device, 3 pause for desktop and touch device, 4 freeze for desktop, 8 freeze for touch device, 12 freeze for desktop and touch device, default value is 1

                $ArrowKeyNavigation: true,                    //[Optional] Allows keyboard (arrow key) navigation or not, default value is false
                $SlideEasing: $JssorEasing$.$EaseOutQuint,          //[Optional] Specifies easing for right to left animation, default value is $JssorEasing$.$EaseOutQuad
                $SlideDuration: 800,                                //[Optional] Specifies default duration (swipe) for slide in milliseconds, default value is 500
                $MinDragOffsetToSlide: 20,                          //[Optional] Minimum drag offset to trigger slide , default value is 20
                //$SlideWidth: 600,                                 //[Optional] Width of every slide in pixels, default value is width of 'slides' container
                //$SlideHeight: 300,                                //[Optional] Height of every slide in pixels, default value is height of 'slides' container
                $SlideSpacing: 0,                           //[Optional] Space between each slide in pixels, default value is 0
                $DisplayPieces: 1,                                  //[Optional] Number of pieces to display (the slideshow would be disabled if the value is set to greater than 1), the default value is 1
                $ParkingPosition: 0,                                //[Optional] The offset position to park slide (this options applys only when slideshow disabled), default value is 0.
                $UISearchMode: 1,                                   //[Optional] The way (0 parellel, 1 recursive, default value is 1) to search UI components (slides container, loading screen, navigator container, arrow navigator container, thumbnail navigator container etc).
                $PlayOrientation: 1,                                //[Optional] Orientation to play slide (for auto play, navigation), 1 horizental, 2 vertical, 5 horizental reverse, 6 vertical reverse, default value is 1
                $DragOrientation: 3,                                //[Optional] Orientation to drag slide, 0 no drag, 1 horizental, 2 vertical, 3 either, default value is 1 (Note that the $DragOrientation should be the same as $PlayOrientation when $DisplayPieces is greater than 1, or parking position is not 0)

                $FillMode: 1,
                $ArrowNavigatorOptions: {                           //[Optional] Options to specify and enable arrow navigator or not
                    $Class: $JssorArrowNavigator$,                  //[Requried] Class to create arrow navigator instance
                    $ChanceToShow: 2,                               //[Required] 0 Never, 1 Mouse Over, 2 Always
                    $AutoCenter: 2,                                 //[Optional] Auto center arrows in parent container, 0 No, 1 Horizontal, 2 Vertical, 3 Both, default value is 0
                    $Steps: 1,                                      //[Optional] Steps to go for each navigation request, default value is 1
                    $Scale: false,                                  //Scales bullets navigator or not while slider scale
                },

                
            };
            var options2 = {
                $AutoPlay: true,                                    //[Optional] Whether to auto play, to enable slideshow, this option must be set to true, default value is false
                $AutoPlaySteps: 1,                                  //[Optional] Steps to go for each navigation request (this options applys only when slideshow disabled), the default value is 1
                $AutoPlayInterval: 3000,                            //[Optional] Interval (in milliseconds) to go for next slide since the previous stopped if the slider is auto playing, default value is 3000
                $PauseOnHover: 1,                                   //[Optional] Whether to pause when mouse over if a slider is auto playing, 0 no pause, 1 pause for desktop, 2 pause for touch device, 3 pause for desktop and touch device, 4 freeze for desktop, 8 freeze for touch device, 12 freeze for desktop and touch device, default value is 1

                $ArrowKeyNavigation: true,                    //[Optional] Allows keyboard (arrow key) navigation or not, default value is false
                $SlideEasing: $JssorEasing$.$EaseOutQuint,          //[Optional] Specifies easing for right to left animation, default value is $JssorEasing$.$EaseOutQuad
                $SlideDuration: 800,                                //[Optional] Specifies default duration (swipe) for slide in milliseconds, default value is 500
                $MinDragOffsetToSlide: 20,                          //[Optional] Minimum drag offset to trigger slide , default value is 20
                //$SlideWidth: 600,                                 //[Optional] Width of every slide in pixels, default value is width of 'slides' container
                //$SlideHeight: 300,                                //[Optional] Height of every slide in pixels, default value is height of 'slides' container
                $SlideSpacing: 0,                           //[Optional] Space between each slide in pixels, default value is 0
                $DisplayPieces: 1,                                  //[Optional] Number of pieces to display (the slideshow would be disabled if the value is set to greater than 1), the default value is 1
                $ParkingPosition: 0,                                //[Optional] The offset position to park slide (this options applys only when slideshow disabled), default value is 0.
                $UISearchMode: 1,                                   //[Optional] The way (0 parellel, 1 recursive, default value is 1) to search UI components (slides container, loading screen, navigator container, arrow navigator container, thumbnail navigator container etc).
                $PlayOrientation: 1,                                //[Optional] Orientation to play slide (for auto play, navigation), 1 horizental, 2 vertical, 5 horizental reverse, 6 vertical reverse, default value is 1
                $DragOrientation: 3,                                //[Optional] Orientation to drag slide, 0 no drag, 1 horizental, 2 vertical, 3 either, default value is 1 (Note that the $DragOrientation should be the same as $PlayOrientation when $DisplayPieces is greater than 1, or parking position is not 0)

                $FillMode: 1,
                $ArrowNavigatorOptions: {                           //[Optional] Options to specify and enable arrow navigator or not
                    $Class: $JssorArrowNavigator$,                  //[Requried] Class to create arrow navigator instance
                    $ChanceToShow: 2,                               //[Required] 0 Never, 1 Mouse Over, 2 Always
                    $AutoCenter: 2,                                 //[Optional] Auto center arrows in parent container, 0 No, 1 Horizontal, 2 Vertical, 3 Both, default value is 0
                    $Steps: 1,                                      //[Optional] Steps to go for each navigation request, default value is 1
                    $Scale: false,                                  //Scales bullets navigator or not while slider scale
                },

                
            };

            //Make the element 'slider1_container' visible before initialize jssor slider.
            $("#slider1_container").css("display", "block");
            $("#slider2_container").css("display", "block");
            var jssor_slider1 = new $JssorSlider$("slider1_container", options);
            var jssor_slider2 = new $JssorSlider$("slider2_container", options2);

            //responsive code begin
            //you can remove responsive code if you don't want the slider scales while window resizes
            function ScaleSlider() {
                var parentWidth = jssor_slider1.$Elmt.parentNode.clientWidth;
                var parentWidth2 = jssor_slider2.$Elmt.parentNode.clientWidth;
                if (parentWidth) {
                    jssor_slider1.$ScaleWidth(parentWidth - 30);
                }
                else
                    window.setTimeout(ScaleSlider, 30);
                if (parentWidth2) {
                    jssor_slider2.$ScaleWidth(parentWidth2 - 30);
                }
                else
                    window.setTimeout(ScaleSlider, 30);
            }
            ScaleSlider();

            $(window).bind("load", ScaleSlider);
            $(window).bind("resize", ScaleSlider);
            $(window).bind("orientationchange", ScaleSlider);
            //responsive code end
        });
    </script>
@stop