<footer>
	@yield('mall-fb-footer')
    <div class="row text-center">
    	<div class="col-xs-12">
	    	<span>{{ 'Orbit v' . ORBIT_APP_VERSION }}</span>
	    	@if(Config::get('orbit.contact_information.privacy_policy_url'))
	        	<span> . <a target="_blank" href="{{ Config::get('orbit.contact_information.privacy_policy_url') }}">Privacy Policy</a></span>
	        @endif
	        @if(Config::get('orbit.contact_information.terms_of_service_url'))
	        	<span> . <a target="_blank" href="{{ Config::get('orbit.contact_information.terms_of_service_url') }}">Terms and Conditions</a></span>
	        @endif
	    </div>
    </div>
</footer>