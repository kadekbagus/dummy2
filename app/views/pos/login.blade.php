
@extends('pos.layouts.default')
@section('content')
<div class="main-container ng-cloak"   ng-controller="loginCtrl" data-ng-init="shownall = true" data-ng-show="shownall">
  <div class="page-signin"  >
    <div class="signin-header">
      <section class="logo text-center">
        <h4><% language.orbitkasir %></h4>
       <img ng-src="{{ URL::asset('templatepos/images/orbit-logo.png') }}"   data-ng-init="showloader = false" data-ng-if="!showloader" alt="Orbit Logo" />
       <img ng-src="{{ URL::asset('templatepos/images/orbit_circle.gif') }}"   data-ng-if="showloader" style="height: 60px; width: 60px; " alt="Orbit Logo" />
      </section>
    </div>

        <div class="signin-body">
               <div class="container">
                   <div class="form-container">
                       <div class="orbit-component alert ng-isolate-scope alert-danger alert-dismissable" ng-repeat="alert in signin.alerts" ng-class="{active: alert.active}">
                           <span class="close-button" ng-click="signin.alertDismisser($index)"><i class="fa fa-times"></i></span>
                           <span> <%alert.text %></span>
                       </div>
                       <form name="signform" class="form-horizontal">
                           <fieldset>
                               <div class="form-group">
                                   <span class="glyphicon glyphicon-user"></span>
                                   <input type="text" name="username" class="orbit-component form-control input-lg input-round text-center" placeholder="Login ID" ng-model="login.username" required />
                               </div>
                               <div class="form-group">
                                   <span class="glyphicon glyphicon-lock"></span>
                                   <input type="password" name="password" class="orbit-component form-control input-lg input-round text-center" ng-disabled="!login.username" placeholder="Password" ng-model="login.password" required />
                               </div>
                               <div class="form-group">
                                   <button ng-disabled="signform.$invalid" class="btn btn-primary btn-lg btn-round btn-block text-center" data-ng-click="loginFn()" type="submit"><% language.masuk %></button>
                               </div>
                           </fieldset>
                       </form>
                        <section>
                             <p class="text-center text-muted text-small"><% versions.strings %></p>
                         </section>
                   </div>
               </div>
        </div>
  </div>
  <div class="text-center">
     <img src="{{ URL::asset('templatepos/images/orbit_circle.gif') }}"   style="height: 50px; width: 50px; padding-top: 50px" alt="Orbit Logo" />
  </div>

</div>
@stop

