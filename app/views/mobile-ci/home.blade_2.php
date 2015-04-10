@extends('mobile-ci.layout')

@section('content')
  <div class="container">
      <div class="row">
        <div class="col-xs-12 text-center">
          <img src="{{ asset('mobile-ci/images/logo-default.png') }}" />
        </div>
      </div>
      <div class="mobile-ci home-widget widget-container">
        <div class="single-widget-container">
          <header class="widget-title">
            <span>CATALOGUE</span>
          </header>
          <section class="widget-single">
            <div class="top-container">
              <span><i class="fa fa-chevron-circle-up"></i></span>
            </div>
            <div class="middle-container">
              <div class="left-arrow">
                <span><i class="fa fa-chevron-circle-left"></i></span>
              </div>
              <div class="widget-content-container">
                <div class="widget-content">
                  <img src="{{ asset('mobile-ci/images/products/product1.png') }}" />
                </div>
              </div>
              <div class="right-arrow">
                <span><i class="fa fa-chevron-circle-right"></i></span>
              </div>
            </div>
            <div class="bottom-container">
              <span><i class="fa fa-chevron-circle-down"></i></span>
            </div>
          </section>
        </div>
        <div class="single-widget-container">
          <header class="widget-title">
            <span>CATALOGUE</span>
          </header>
          <section class="widget-single">
            <div class="top-container">
              <span><i class="fa fa-chevron-circle-up"></i></span>
            </div>
            <div class="middle-container">
              <div class="left-arrow">
                <span><i class="fa fa-chevron-circle-left"></i></span>
              </div>
              <div class="widget-content-container">
                <div class="widget-content">
                  <img src="{{ asset('mobile-ci/images/products/product1.png') }}" />
                </div>
              </div>
              <div class="right-arrow">
                <span><i class="fa fa-chevron-circle-right"></i></span>
              </div>
            </div>
            <div class="bottom-container">
              <span><i class="fa fa-chevron-circle-down"></i></span>
            </div>
          </section>
        </div>
      </div>
    </div>
@stop
