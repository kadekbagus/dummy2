<header class="mobile-ci ci-header header-container">
    <div class="header-buttons-container">
        <!-- <form id="qrform" action="#" method="post" enctype="multipart/form-data">
            <div style='height: 0px;width:0px; overflow:hidden;'><input id="get_camera" name="qrphoto" type="file" accept="image/*;capture=camera" value="camera" /></div>
        </form> -->
        <ul class="buttons-list right">
            <!-- <li><a id="barcodeBtn"><span><i class="glyphicon glyphicon-barcode"></i></span></a></li> -->
            <li><a id="searchBtn"><span><i class="glyphicon glyphicon-search"></i></span></a></li>
            <li><a href="{{ url('/customer/catalogue') }}"><span class="fa fa-list-ul"></span></a></li>
            <li>
                <a href="{{ url('/customer/cart') }}">
                    <span id="shopping-cart-span">
                        <span class="fa-1x fa-stack cart-qty ">
                            <i class="fa fa-circle fa-stack-2x cart-circle"></i>
                            <?php
                            if(is_null($cartitems) || $cartitems == 0){
                            $cartnumber = 0;
                            } elseif ($cartitems > 9){
                            $cartnumber = '9+';
                            } else {
                            $cartnumber = $cartitems;
                            }
                            ?>
                            <strong class="fa-stack-1x" id="cart-number" data-cart-number="{{$cartnumber}}">
                            @if(! is_null($cartitems))
                            @if($cartitems > 9)
                            {{ '9+' }}
                            @else
                            {{ $cartitems }}
                            @endif
                            @endif
                            </strong>
                        </span>
                        <i id="shopping-cart" class="fa fa-shopping-cart"></i>
                    </span>
                </a>
            </li>
            <li><a data-toggle="dropdown" aria-expanded="true"><span><i class="glyphicon glyphicon-cog"></i></span></a>
                <ul class="dropdown-menu" role="menu">
                    <!-- <li class="complimentary-bg"><span><span class="glyphicon glyphicon-user"></span> {{ Lang::get('mobileci.page_title.my_account') }}</span></li> -->
                    <li class="complimentary-bg"><a href="{{ url('/customer/transfer') }}"><span><span class="fa fa-shopping-cart"></span> {{ Lang::get('mobileci.page_title.transfercart') }}</span></a></li>
                    <li class="complimentary-bg"><a href="{{ url('/customer/me') }}"><span><span class="fa fa-user"></span> {{ Lang::get('mobileci.page_title.recognize_me') }}</span></a></li>
                    <!-- <li class="complimentary-bg"><span><span class="glyphicon glyphicon-barcode"></span> {{ Lang::get('mobileci.page_title.customer_id') }}</span></li> -->
                    <li class="complimentary-bg"><a href="{{ url('/customer/logout') }}"><span><span class="glyphicon glyphicon-off"></span> {{ Lang::get('mobileci.page_title.logout') }}</span></a></li>
                </ul>
            </li>
        </ul>
        <ul class="buttons-list">
            <li><a href="{{ url('/customer/home') }}"><span><i class="glyphicon glyphicon-home"></i></span></a></li>
            <li><a id="backBtn"><span><i class="fa fa-arrow-left"></i></span></a></li>
        </ul>
    </div>
    <div class="header-location-banner">
        <span>
            @if(is_null($page_title))
            {{ 'ORBIT' }}
            @else
            {{ $page_title }}
            @endif
        </span>
        <div class="text-center pull-right">
            <span class="fa-stack offline">
              <i class="fa fa-globe fa-stack-1x globe"></i>
              <i id="offlinemark"></i>
            </span>
        </div>
    </div>
    </div>
</header>
