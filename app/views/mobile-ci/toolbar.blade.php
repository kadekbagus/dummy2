<header class="mobile-ci ci-header header-container">
    <div class="header-buttons-container">
        <!-- <form id="qrform" action="#" method="post" enctype="multipart/form-data">
            <div style='height: 0px;width:0px; overflow:hidden;'><input id="get_camera" name="qrphoto" type="file" accept="image/*;capture=camera" value="camera" /></div>
        </form> -->
        <ul class="buttons-list right">
            <!-- <li><a id="barcodeBtn"><span><i class="glyphicon glyphicon-barcode"></i></span></a></li> -->
            <li id="orbit-tour-search"><a id="searchBtn"><span><i class="glyphicon glyphicon-search"></i></span></a></li>
            <li id="orbit-tour-tenant"><a href="{{ url('/customer/tenants') }}"><span class="fa fa-list-ul"></span></a></li>
            <li id="orbit-tour-profile"><a data-toggle="dropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span><i class="glyphicon glyphicon-cog"></i></span></a>
                <ul class="dropdown-menu" role="menu">
                    @if($retailer->enable_membership === 'true')
                        <li class="complimentary-bg"><a id="membership-card"><span><span class="glyphicon glyphicon-credit-card"></span> {{ Lang::get('mobileci.page_title.membership') }}</span></a></li>
                    @else
                        <li class="complimentary-bg" id="dropdown-disable" style="color:#999999;"><span><span class="glyphicon glyphicon-credit-card"></span> {{ Lang::get('mobileci.page_title.membership') }}</span></li>
                    @endif

                    <li class="complimentary-bg"><a id="multi-language"><span><span class="glyphicon glyphicon-globe"></span> {{ Lang::get('mobileci.page_title.language') }}</span></a></li>
                    <li class="complimentary-bg"><a href="{{ url('/customer/home?show_tour=yes') }}" id="orbit-tour-setting"><span><span class="glyphicon glyphicon-info-sign"></span> {{ Lang::get('mobileci.page_title.orbit_tour') }}</span></a></li>
                    <li class="complimentary-bg"><a href="{{ url('/customer/logout') }}"><span><span class="glyphicon glyphicon-off"></span> {{ Lang::get('mobileci.page_title.logout') }}</span></a></li>
                </ul>
            </li>
        </ul>
        <ul class="buttons-list">
            <li id="orbit-tour-home"><a href="{{ url('/customer/home') }}"><span><i class="glyphicon glyphicon-home"></i></span></a></li>
            <li id="orbit-tour-back"><a id="backBtn"><span><i class="fa fa-arrow-left"></i></span></a></li>
        </ul>
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
        <div id="orbit-tour-connection" class="text-center pull-right">
            <span class="fa-stack offline">
              <i class="fa fa-globe fa-stack-1x globe"></i>
              <i id="offlinemark"></i>
            </span>
        </div>
    </div>
    </div>
</header>
