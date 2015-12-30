<header class="mobile-ci ci-header header-container">
    <div class="header-buttons-container">
        <!-- <form id="qrform" action="#" method="post" enctype="multipart/form-data">
            <div style='height: 0px;width:0px; overflow:hidden;'><input id="get_camera" name="qrphoto" type="file" accept="image/*;capture=camera" value="camera" /></div>
        </form> -->
        <div class="col-xs-2 pull-right text-right">
            <ul class="buttons-list">
                <!-- <li><a id="barcodeBtn"><span><i class="glyphicon glyphicon-barcode"></i></span></a></li> -->
                <!-- <li id="orbit-tour-search"><a id="searchBtn"><span><i class="glyphicon glyphicon-search"></i></span></a></li> -->
                <!-- <li id="orbit-tour-tenant"><a href="{{ url('/customer/tenants') }}"><span class="fa fa-list-ul"></span></a></li> -->
                <li id="orbit-tour-profile"><a id="slide-trigger"><span><i class="fa fa-bars"><span class="notification-badge">0</span></i></span></a></li>
            </ul>
        </div>

        <div class="col-xs-2">
            <ul class="buttons-list">
                <li id="orbit-tour-home"><a href="{{ url('/customer/home') }}"><span><i class="glyphicon glyphicon-home"></i></span></a></li>
                <!-- <li id="orbit-tour-back"><a id="backBtn"><span><i class="fa fa-arrow-left"></i></span></a></li> -->
            </ul>
        </div>
        <div class="col-xs-8 text-center">
            <img class="img-responsive header-logo" src="{{asset($retailer->logo)}}" />
        </div>
    </div>
    <div class="header-location-banner">
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
    <div class="slide-menu-container">
        <ul class="slide-menu" role="menu">
            @if($retailer->enable_membership === 'true')
                <li class=""><a id="membership-card"><span><span class="glyphicon glyphicon-credit-card fa-relative"></span> {{ ucwords(strtolower(Lang::get('mobileci.page_title.membership'))) }}</span></a></li>
            @else
                <li class="" id="dropdown-disable" style="color:#999999;"><span style="padding: .3em"><span class="glyphicon glyphicon-credit-card fa-relative"></span> {{ ucwords(strtolower(Lang::get('mobileci.page_title.membership'))) }}</span></li>
            @endif
            <li class="fa-pad-left"><a href="{{ url('/customer/my-account') }}"><span><span class="fa fa-user fa-relative"></span> {{ ucwords(strtolower(Lang::get('mobileci.page_title.my_account'))) }}</span></a></li>
            <li class="fa-pad-left">
                <a href="{{ url('/customer/messages') }}">
                    <span>
                        <i class="fa fa-inbox fa-relative"><span class="notification-badge notification-badge-sub">0</span></i>
                        {{ ucwords(strtolower(Lang::get('mobileci.page_title.my_messages'))) }}
                    </span>
                </a>
            </li>
            <li id="orbit-tour-search"><a id="searchBtn"><span><i class="glyphicon glyphicon-search fa-relative"></i></span> {{ ucwords(strtolower(Lang::get('mobileci.modals.search_title'))) }}</a></li>
            <li class=""><a href="{{ url('/customer/home?show_tour=yes') }}" id="orbit-tour-setting"><span><span class="glyphicon glyphicon-info-sign fa-relative"></span> {{ ucwords(strtolower(Lang::get('mobileci.page_title.orbit_tour'))) }}</span></a></li>
            <li class=""><a id="multi-language"><span><span class="glyphicon glyphicon-globe fa-relative"></span> {{ ucwords(strtolower(Lang::get('mobileci.page_title.language'))) }}</span></a></li>
            <li class=""><a href="{{ url('/customer/logout') }}"><span><span class="glyphicon glyphicon-off fa-relative"></span> {{ ucwords(strtolower(Lang::get('mobileci.page_title.logout'))) }}</span></a></li>
        </ul>
    </div>
    <div class="slide-menu-backdrop"></div>
</header>