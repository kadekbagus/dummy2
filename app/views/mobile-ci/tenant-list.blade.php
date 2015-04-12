@foreach($data->records as $product)
    <div class="main-theme catalogue">
        <div class="row row-xs-height catalogue-top">
            <div class="col-xs-6 catalogue-img col-xs-height col-middle">
                <a href="{{ asset($product->logo) }}" data-featherlight="image"><img class="img-responsive" alt="" src="{{ asset($product->logo) }}"></a>
            </div>
            <div class="col-xs-6 catalogue-detail bg-catalogue col-xs-height">
                <div class="row">
                    <div class="col-xs-12">
                        <h3>{{ $product->name }}</h3>
                    </div>
                    <div class="col-xs-12">

                    </div>
                    <div class="col-xs-12 price">
                        
                    </div>
                </div>
            </div>
        </div>
        <div class="row catalogue-control-wrapper">
            <div class="col-xs-6 catalogue-short-des ">
                <p>{{ $product->description }}</p>
            </div>
            <div class="col-xs-2 catalogue-control text-center">
                <div class="circlet btn-blue detail-btn">
                    <a href="{{ url('customer/product?id='.$product->merchant_id) }}"><span class="link-spanner"></span><i class="fa fa-ellipsis-h"></i></a>
                </div>
            </div>
        </div>
    </div>
@endforeach
