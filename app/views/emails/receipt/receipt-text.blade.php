Thank you {{ $user->getFullName() }} for your shopping at {{ $retailer->name }}.

Here is a copy of your ticket and some information about your products.

Please come see us again soon!

@foreach($transactiondetails as $detail)
● {{ $detail->product->post_sales_url }} : {{ $detail->product_name }} @if(! is_null($detail->product_attribute_value1)) / {{ $detail->product_attribute_value1 }} @endif @if(! is_null($detail->product_attribute_value2)) / {{ $detail->product_attribute_value2 }} @endif @if(! is_null($detail->product_attribute_value3)) / {{ $detail->product_attribute_value3 }} @endif @if(! is_null($detail->product_attribute_value4)) / {{ $detail->product_attribute_value4 }} @endif @if(! is_null($detail->product_attribute_value5)) / {{ $detail->product_attribute_value5 }} @endif


@endforeach

Orbit - Powered by DominoPOS