<footer class="footer">
    <div class="row container-footer">
        <div class="col-xs-7 version-and-privacy">
            <span>{{ 'Orbit v' . str_limit(ORBIT_APP_VERSION, 3, '') }}</span>
            <span>
                <a target="_blank" href="{{ Config::get('orbit.contact_information.privacy_policy_url') }}">Privacy Policy</a>.
            </span>
            <span>
                <a target="_blank" href="{{ Config::get('orbit.contact_information.terms_of_service_url') }}">Terms and Conditions</a>
            </span>
        </div>
        <div class="col-xs-4 col-xs-offset-1 powered-by text-right">
            <span>Powered by</span>
            <img class="img-responsive image-footer" src="{{ asset('mobile-ci/images/dominopos-footer.png') }}">
        </div>
    </div>
</footer>