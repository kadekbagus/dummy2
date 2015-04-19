@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
    @if($data->status === 1)
        @if(sizeof($data->records) > 0)
            <div id="search-tool">
                <div class="row">
                    <div class="col-xs-5 search-tool-col">
                        <div class="dropdown">
                            <button id="dLabel" type="button" class="btn btn-info btn-block" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="buttonLabel">
                                    @if(!empty(Input::get('cid')))
                                        <?php
                                            $namex = $categories->filter(function ($item) {
                                                return $item->category_id == Input::get('cid');
                                            })->first()->category_name; 
                                            echo $namex;
                                        ?>
                                    @else
                                        Category
                                    @endif
                                </span>
                                <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu" role="menu" aria-labelledby="dLabel" id="category">
                                <li data-category=""><span>All</span></li>
                                @foreach($categories as $category)
                                <li data-category="{{ $category->category_id }}"><span>{{ $category->category_name }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <div class="col-xs-5 search-tool-col">
                        <div class="dropdown">
                            <button id="dLabel2" type="button" class="btn btn-info btn-block" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="buttonLabel">
                                    @if(!empty(Input::get('fid')))
                                        {{ Input::get('fid') }}
                                    @else
                                        Floor
                                    @endif
                                </span>
                                <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu" role="menu" aria-labelledby="dLabel2" id="floor">
                                <li data-category=""><span>All</span></li>
                                <li data-floor="LG"><span>LG</span></li>
                                <li data-floor="UG"><span>UG</span></li>
                                <li data-floor="G"><span>G</span></li>
                                <li data-floor="L1"><span>L1</span></li>
                                <li data-floor="L2"><span>L2</span></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-xs-2 search-tool-col text-right">
                        <a href="{{ url('/customer/tenants') }}" class="btn btn-info btn-block reset-btn">
                            <span class="fa-stack fa-lg">
                                <i class="fa fa-filter fa-stack-2x"></i>
                                <i class="fa fa-times fa-stack-1x"></i>
                            </span>
                        </a>
                    </div>
                </div>
            </div>
            @foreach($data->records as $product)
                <div class="main-theme-mall catalogue" id="product-{{$product->product_id}}">
                    <div class="row catalogue-top">
                        <div class="col-xs-6 catalogue-img">
                            @foreach($product->mediaLogo as $media)
                            @if($media->media_name_long == 'retailer_logo_orig')
                            <a href="{{ asset($media->path) }}" data-featherlight="image" class="text-left"><img class="img-responsive" alt="" src="{{ asset($media->path) }}"></a>
                            @endif
                            @endforeach
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-xs-9">
                            <h4>{{ $product->name }} at</h4>
                            <h3>{{ $retailer->name }} - {{ $product->floor }} - {{ $product->unit }}</h3>
                            <h5 class="tenant-category">
                            @foreach($product->categories as $cat)
                                <span>{{$cat->category_name}}</span>
                            @endforeach
                            </h5>
                        </div>
                        <div class="col-xs-3">
                            <div class="circlet btn-blue detail-btn pull-right">
                                <a href="{{ url('customer/tenant?id='.$product->merchant_id) }}"><span class="link-spanner"></span><i class="fa fa-ellipsis-h"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @else
            <div id="search-tool">
                <div class="row">
                    <div class="col-xs-5 search-tool-col">
                        <div class="dropdown">
                            <button id="dLabel" type="button" class="btn btn-info btn-block" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="buttonLabel">
                                    @if(!empty(Input::get('cid')))
                                        <?php
                                            $namex = $categories->filter(function ($item) {
                                                return $item->category_id == Input::get('cid');
                                            })->first()->category_name; 
                                            echo $namex;
                                        ?>
                                    @else
                                        Category
                                    @endif
                                </span>
                                <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu" role="menu" aria-labelledby="dLabel" id="category">
                                <li data-category=""><span>All</span></li>
                                @foreach($categories as $category)
                                <li data-category="{{ $category->category_id }}"><span>{{ $category->category_name }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <div class="col-xs-5 search-tool-col">
                        <div class="dropdown">
                            <button id="dLabel2" type="button" class="btn btn-info btn-block" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="buttonLabel">
                                    @if(!empty(Input::get('fid')))
                                        {{ Input::get('fid') }}
                                    @else
                                        Floor
                                    @endif
                                </span>
                                <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu" role="menu" aria-labelledby="dLabel2" id="floor">
                                <li data-category=""><span>All</span></li>
                                <li data-floor="LG"><span>LG</span></li>
                                <li data-floor="UG"><span>UG</span></li>
                                <li data-floor="G"><span>G</span></li>
                                <li data-floor="L1"><span>L1</span></li>
                                <li data-floor="L2"><span>L2</span></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-xs-2 search-tool-col text-right">
                        <a href="{{ url('/customer/tenants') }}" class="btn btn-info btn-block reset-btn">
                            <span class="fa-stack fa-lg">
                                <i class="fa fa-filter fa-stack-2x"></i>
                                <i class="fa fa-times fa-stack-1x"></i>
                            </span>
                        </a>
                    </div>
                </div>
            </div>
            <div class="row padded">
                <div class="col-xs-12">
                    <h4>{{ Lang::get('mobileci.search.no_item') }}</h4>
                </div>
            </div>
        @endif
    @else
        <div class="row padded">
            <div class="col-xs-12">
                <h4>{{ Lang::get('mobileci.search.too_much_items') }}</h4>
            </div>
        </div>
    @endif
@stop

@section('ext_script_bot')
{{ HTML::script('mobile-ci/scripts/jquery-ui.min.js') }}
{{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
<script type="text/javascript">
    function updateQueryStringParameter(uri, key, value) {
        var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
        var separator = uri.indexOf('?') !== -1 ? "&" : "?";
        if (uri.match(re)) {
            return uri.replace(re, '$1' + key + "=" + value + '$2');
        } else {
            return uri + separator + key + "=" + value;
        }
    }
    $(document).ready(function(){
        var promo = '';
        @if(!empty(Input::get('promotion_id'))) 
            promo = '&promotion_id='+{{Input::get('promotion_id')}};
        @endif
        var path = '{{ url('/customer/tenants?keyword='.Input::get('keyword').'&sort_by=name&sort_mode=asc&cid='.Input::get('cid').'&fid='.Input::get('fid')) }}'+promo;
        $('#dLabel').dropdown();
        $('#dLabel2').dropdown();

        $('#category>li').click(function(){
            if(!$(this).data('category')) {
                $(this).data('category', '');
            }
            path = updateQueryStringParameter(path, 'cid', $(this).data('category'));
            console.log(path);
            window.location.replace(path);
        });
        $('#floor>li').click(function(){
            if(!$(this).data('floor')) {
                $(this).data('floor', '');
            }
            path = updateQueryStringParameter(path, 'fid', $(this).data('floor'));
            console.log(path);
            window.location.replace(path);
        });
    });
</script>
@stop