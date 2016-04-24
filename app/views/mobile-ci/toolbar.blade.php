<header class="mobile-ci ci-header header-container">
    <div class="header-buttons-container">
        <div class="col-xs-2 pull-right text-right">
            <ul class="buttons-list">
                <li id="orbit-tour-profile"><a id="slide-trigger"><span><i class="fa fa-bars" style="font-size: 26px;font-weight: bold;"><span class="notification-badge-txt notification-badge">0</span></i></span></a></li>
            </ul>
        </div>

        <div class="col-xs-2">
            <ul class="buttons-list">
                <li id="orbit-tour-home"><a href="{{ (new \Orbit\Helper\Net\UrlChecker)->blockedRoute('ci-customer-home') }}"><span><i class="glyphicon glyphicon-home"></i></span></a></li>
            </ul>
        </div>
        <div class="col-xs-8 text-center">
        @if (!empty($retailer->logo))
            <img class="img-responsive toolbar-header-logo img-center" src="{{asset($retailer->logo)}}" />
        @endif
        </div>
    </div>
    @if(!is_null($page_title))
    <div class="header-location-banner">
        @if(empty(Input::get('keyword')))
        <span>
            @if(is_null($page_title))
            {{ 'ORBIT' }}
            @else
                @if(mb_strlen($page_title) >= 30)
                {{{ substr($page_title, 0, 30) . '...' }}}
                @else
                {{{ $page_title }}}
                @endif
            @endif
        </span>
        @else
        <div class="col-xs-6">
            <span>
                @if(is_null($page_title))
                {{ 'ORBIT' }}
                @else
                    @if(mb_strlen($page_title) >= 30)
                    {{ substr($page_title, 0, 30) . '...' }}
                    @else
                    {{ $page_title }}
                    @endif
                @endif
            </span>
        </div>
        <div class="col-xs-6 text-right">
            <div class="col-xs-10 text-right search-keyword search-text">
                <span>
                    {{Lang::get('mobileci.search.all_search_results')}} "{{{Input::get('keyword')}}}"
                </span>
            </div>
            <div class="col-xs-2 text-right search-keyword">
                <a href="{{Request::url()}}"><i class="fa fa-close"></i></a>
            </div>
        </div>
        @endif
    </div>
    @endif
    @yield('tenant_tab')
    <div class="slide-menu-container">
        <div class="slide-menu-middle-container">
            <ul class="slide-menu" role="menu">
                @if($retailer->enable_membership === 'true')
                    <li class=""><a id="membership-card"><span><span class="glyphicon glyphicon-credit-card fa-relative"></span> {{ ucwords(strtolower(Lang::get('mobileci.page_title.membership'))) }}</span></a></li>
                @else
                    <li class="" id="dropdown-disable" style="color:#999999;"><span style="padding: .3em"><span class="glyphicon glyphicon-credit-card fa-relative"></span> {{ ucwords(strtolower(Lang::get('mobileci.page_title.membership'))) }}</span></li>
                @endif
                <li class="fa-pad-left"><a data-href="{{ route('ci-my-account') }}" href="{{ $urlblock->blockedRoute('ci-my-account') }}"><span><span class="fa fa-user fa-relative"></span> {{ ucwords(strtolower(Lang::get('mobileci.page_title.my_account'))) }}</span></a></li>
                <li class="fa-pad-left">
                    <a data-href="{{ route('ci-notification-list') }}" href="{{ $urlblock->blockedRoute('ci-notification-list') }}">
                        <span>
                            <i class="fa fa-inbox fa-relative"></i>
                            {{ ucwords(strtolower(Lang::get('mobileci.page_title.my_messages'))) }}
                        </span>
                        <span class="notification-badge-txt notification-badge-sub text-right">0</span>
                    </a>
                </li>
                <li id="orbit-tour-search"><a id="searchBtn"><span><i class="glyphicon glyphicon-search fa-relative"></i></span> {{ ucwords(strtolower(Lang::get('mobileci.modals.search_button'))) }}</a></li>
                <li class=""><a id="multi-language"><span><span class="glyphicon glyphicon-globe fa-relative"></span> {{ ucwords(strtolower(Lang::get('mobileci.page_title.language'))) }}</span></a></li>
                <li class=""><a href="{{ Config::get('orbit.shop.back_to_map_url') }}"><span><i class="fa fa-map fa-relative"></i> {{ Lang::get('mobileci.page_title.back_to_map_lower') }}</span></a></li>
                @if($urlblock->isLoggedIn())
                <li class=""><a href="{{ url('/customer/logout') }}"><span><span class="glyphicon glyphicon-off fa-relative"></span> {{ ucwords(strtolower(Lang::get('mobileci.page_title.logout'))) }}</span></a></li>
                @endif
            </ul>
        </div>
    </div>
    <div class="slide-menu-backdrop"></div>
</header>