<header class="mobile-ci ci-header header-container">
    <div class="header-buttons-container">
        <div class="button-container">
            <ul class="buttons-list">
                <li id="orbit-tour-home"><a href="{{ \Orbit\Helper\Net\UrlChecker::blockedRoute('ci-customer-home', [], $session) }}"><span><i class="glyphicon glyphicon-home"></i></span></a></li>
                <li id="orbit-tour-map"><a href="{{ Config::get('orbit.shop.back_to_map_url') }}"><span><i class="fa fa-map-marker" style="font-size: 26px;font-weight: bold;"></i></span></a></li>
            </ul>
        </div>
        @if (!empty($retailer->logo))
            <div class="logo" style="background-image: url('{{asset($retailer->logo)}}')">
            </div>
        @endif
        <div class="button-container pull-right">
            <ul class="buttons-list">
                <li id="orbit-tour-search"><a id="searchBtn"><span><i class="fa fa-search" style="font-size: 26px;font-weight: bold;"></i></span></a></li>
                <li id="orbit-tour-profile"><a id="slide-trigger"><span><i class="fa fa-bars" style="font-size: 26px;font-weight: bold;"><span class="notification-badge-txt notification-badge">0</span></i></span></a></li>
            </ul>
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
                <li class="fa-pad-left"><a data-href="{{ route('ci-my-account') }}" href="{{ \Orbit\Helper\Net\UrlChecker::blockedRoute('ci-my-account', [], $session) }}"><span><span class="fa fa-user fa-relative"></span> {{ ucwords(strtolower(Lang::get('mobileci.page_title.my_account'))) }}</span></a></li>
                <li class="fa-pad-left">
                    <a data-href="{{ route('ci-notification-list') }}" href="{{ \Orbit\Helper\Net\UrlChecker::blockedRoute('ci-notification-list', [], $session) }}">
                        <span>
                            <i class="fa fa-inbox fa-relative"></i>
                            {{ ucwords(strtolower(Lang::get('mobileci.page_title.my_messages'))) }}
                        </span>
                        <span class="notification-badge-txt notification-badge-sub text-right">0</span>
                    </a>
                </li>
                <li class=""><a id="multi-language"><span><span class="glyphicon glyphicon-globe fa-relative"></span> {{ ucwords(strtolower(Lang::get('mobileci.page_title.language'))) }}</span></a></li>
                @if($is_logged_in)
                <li class=""><a href="{{ url('/customer/logout') }}"><span><span class="glyphicon glyphicon-off fa-relative"></span> {{ ucwords(strtolower(Lang::get('mobileci.page_title.logout'))) }}</span></a></li>
                @endif
            </ul>
        </div>
    </div>
    <div class="slide-menu-backdrop"></div>
</header>