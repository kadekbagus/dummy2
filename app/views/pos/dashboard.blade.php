@extends('pos.layouts.default')
@section('content')

<div class="ng-cloak" ng-controller="dashboardCtrl">

    <div class="container-fluid" style="border-bottom:1px solid #c0c0c0">
            <div class="header">
                <img ng-src="<% configs.baseUrlServerPublic %>/<% datauser['merchant']['logo'] %>" class="img" style="height: 64px">
                <h1><% datauser['merchant']['name'] %></h1>
                <div class="btn-group "   style="float: right; padding-top: 40px; padding-left: 10px; color:#46c2ff;" dropdown>
                     <% $parent.datauser.user.username %>&nbsp;<span class="down"  dropdown-toggle><i class="fa fa-caret-down"></i></span>
                      <ul class="dropdown-menu" style="min-width: 60px" role="menu">
                        <li> <a ng-click="logoutfn()"><% language.keluar %></a></li>
                      </ul>
                </div>
                <p  style="float: right; padding-top: 40px;color:#030000" ><% guests %> | <% $parent.datetime %></p>
            </div>
    </div>

    <div class="container-fluid" style="padding-top:0px !important">
        <div class="row" >

            <div class="col-md-7" style="margin-left: -8px">
                <div class="orbit-component table-attribute-top">
                    <div class="row">
                         <div class="col-md-6" style="margin-top: 6px"><h4><% language.keranjangbelanja %></h4></div>
                         <div class="col-md-6 text-right"> <button class="btn btn-primary" data-ng-disabled="successscant" style="background-color: #2c71a3;" data-toggle="modal" data-backdrop="static" data-target="#modalscancart" data-ng-click="scancartFn()" type="submit"><% language.scankeranjang %></button></div>
                    </div>

                </div>

                <div ng-class="table-responsive"  id="tablecart" style="overflow: auto;height: 350px" >
                    <table class="table">
                         <thead>
                           <tr>
                                <th class="text-center"><% language.nama %> + <% language.upc %></th>
                                <th class="text-center"><% language.jumlah %></th>
                                <th class="text-center"><% language.hargasatuan  +'('+ currency_symbol +')'%> </th>
                                <th class="text-center"><% language.hargatotal +'('+ currency_symbol +')'%></th>
                           </tr>
                         </thead>
                        <tbody data-ng-repeat="(k,v) in cart"  id="tablecart">
                           <tr>
                                <td style="max-width: 300px;word-wrap: break-word;" >
                                  <a href="" data-toggle="modal" data-backdrop="static" data-target="#myModal" data-ng-click="showdetailFn(v.product_id,'fc')"><b> <% v.product_name %> <% v.variants.value1 %> <% v.variants.value2 %> <% v.variants.value3 %> <% v.attributes.toString().replace(",", " "); %> </b></a> <br><% v.upc_code %>
                                </td>
                                <td style="width: 200px">

                                    <div class="input-group ui-spinner" data-ui-spinner="">
                                          <span class="input-group-btn">
                                                                  <button type="button" class="btn btn-primary"  data-ng-click="qaFn($index,'m')" data-spin="up">
                                                                      <i class="fa fa-minus"></i>
                                                                  </button>
                                                              </span>
                                          <div type="text" pattern="[0-9]*" class="spinner-input form-control"  data-ng-model="cart[k]['qty']" data-ng-click="virtualqtyFn(true,k)"  style="margin-top: 5px !important;"><% cart[k]['qty'] %></div>
                                          <span class="input-group-btn">
                                              <button type="button" class="btn btn-primary" data-spin="down" data-ng-click="qaFn($index,'p')">
                                                  <i class="fa fa-plus"></i>
                                              </button>
                                          </span>&nbsp;&nbsp;
                                          <span class="input-group-btn">
                                                <button type="button" class="btn btn-danger" data-ng-click="qaFn(k,'d')" >
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                          </span>
                                    </div>
                                </td>
                                <td class="text-right"><% v.price %></td>
                                <td class="text-right"><% v.hargatotal %></td>
                            </tr>
                            <tr data-ng-repeat="(a,r) in v.promotion">
                                <td><div class="foo promotion" style="margin-left: 23px;"></div><span style="margin-left: 5px;"><% r.promotion_name %></span></td>
                                <td></td>
                                <td class="text-right"><% r.discount_value %></td>
                                <td class="text-right">- <% r.afterpromotionprice %></td>
                            </tr>
                            <tr data-ng-repeat="(a,r) in v.coupon">
                                <td><div class="foo coupon" style="margin-left: 23px;"></div><span style="margin-left: 5px;"><% r.issuedcoupon.promotion_name %></span></td>
                                <td></td>
                                <td class="text-right"><% r.discount_value %></td>
                                <td class="text-right">- <% r.aftercouponprice %></td>
                            </tr>

                        </tbody>
                            <tr data-ng-if="tmpsubtotal">
                                <td colspan="2"><b><% language.subtotal %></b></td>
                                <td class="text-right"></td>
                                <td class="text-right"><b><% tmpsubtotal %></b></td>
                            </tr>
                            <tr id="bottom">
                                <!-- <td class="tdnoborder"></td>
                                 <td class="tdnoborder"></td>
                                 <td class="tdnoborder"></td> -->
                            </tr>
                    </table>
                      <table class="table  orbit-component table-noborder" >
                                <tr class="promotion" data-ng-if="applycartpromotion.length > 0">
                                   <th colspan="4" style="color: white;padding-top: 0px;padding-bottom: 0px"><% language.promosibelanja %></th>
                                </tr>

                                <tr data-ng-repeat="(e,o) in applycartpromotion" data-ng-if="applycartpromotion.length > 0">
                                    <td ><% o.promotion_name %></td>
                                    <td></td>
                                    <td class="text-right"><% o.promotionrule.discount %></td>
                                    <td class="text-right"><% o.promotionrule.discount_value %></td>
                                </tr>
                                 <tr class="coupon" data-ng-if="applycartcoupon.length > 0">
                                    <th colspan="4" style="color: white;padding-top: 0px;padding-bottom: 0px"><% language.kuponbelanja %></th>
                                 </tr>
                                 <tr data-ng-repeat="(b,l) in applycartcoupon" data-ng-if="applycartcoupon.length > 0">
                                      <td><% l.issuedcoupon.promotion_name %></td>
                                       <td></td>
                                      <td class="text-right"><% l.issuedcoupon.discount %></td>
                                      <td class="text-right"><% l.issuedcoupon.discount_value %></td>
                                  </tr>
                       </table>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <tr>
                            <td class="text-center"><b><% language.totalbarang %></b><br><% cart.totalitem %></td>
                            <td class="text-center"><b><% language.subtotal %></b><br><% cart.subtotal %></td>
                            <td class="text-center"><b><% language.pajak %><br></b><% cart.vat %></td>
                            <td class="text-center"><b><% language.totalbayar %></b><br><% cart.totalpay %></td>
                        </tr>
                        <tr>
                            <td><div class="foo promotion"><span style="margin-left: 23px;"><% language.promosi %></span></div></td>
                            <td><div class="foo coupon"><span style="margin-left: 23px;"><% language.kupon %></span></div></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </table>
                </div>
                <div class="table-responsive">
                    <table class="table  orbit-component table-noborder">
                        <tr>
                            <td colspan="3"> <button class="btn btn-danger" data-ng-disabled="cart.length == 0" data-toggle="modal" data-backdrop="static" data-target="#myModalNewCart" type="submit"><% language.keranjangbelanjabaru %></button> &nbsp; <button class="btn btn-primary" data-ng-disabled="cart.length == 0" style="background-color: #2c71a3;" data-toggle="modal" data-backdrop="static" data-target="#myModalDeleteCart"  type="submit"><% language.hapuskeranjangbelanja %></button></td>
                            <td class="text-right"> <button class="btn btn-success" data-ng-disabled="cart.length == 0" style="background-color: #009933;" data-toggle="modal" data-backdrop="static" data-target="#myModalcheckout" data-ng-click="showCh()" type="submit"><% language.bayar %></button></td>
                       </tr>
                    </table>
                </div>
                <div data-ng-show="isvirtualqty"   class="numpad numpadqty" style=" margin-top:-450px">
                         <div class="button-wrapper">
                               <button data-ng-click="keypaqtydFn('9')">9</button>
                               <button data-ng-click="keypaqtydFn('8')">8</button>
                               <button data-ng-click="keypaqtydFn('7')">7</button>
                               <button data-ng-click="keypaqtydFn('4')">4</button>
                               <button data-ng-click="keypaqtydFn('5')">5</button>
                               <button data-ng-click="keypaqtydFn('6')">6</button>
                               <button data-ng-click="keypaqtydFn('1')">1</button>
                               <button data-ng-click="keypaqtydFn('2')">2</button>
                               <button data-ng-click="keypaqtydFn('3')">3</button>

                               <button data-ng-click="keypaqtydFn('c')" class="smaller"><% language.bersihkan %></button>
                               <button data-ng-click="keypaqtydFn('0')">0</button>
                               <button data-ng-click="keypaqtydFn('r')" class="smaller"><span class="glyphicon glyphicon-arrow-left" aria-hidden="true"></span></button>
                               <button data-ng-click="virtualqtyFn(false)" class="button-wide smaller"><% language.selesai %></button>
                         </div>

                </div>
            </div>
            <div class="col-md-5" style="padding-left: 0px;padding-right: 0px;margin-right: -50px">
                <div class="orbit-component table-attribute-top" >
                      <div class="row">
                          <div class="col-md-12"><h4 class="text-center"><% language.katalogproduk %></h4><br>
                              <div class="input-group" id="loadingsearch">
                                   <div class="input-group-addon"><span class="glyphicon glyphicon-search" aria-hidden="true"></span></div>
                                        <input type="text" class="form-control"  data-ng-model="searchproduct" id="exampleInputEmail2" placeholder="<% language.cariproduk %>">
                                   <div class="input-group-addon" style="background-color : #D60000; border: none;cursor:pointer" data-ng-click="resetsearch()"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span></div>
                              </div>
                           </div>
                      </div>
                </div>
                <div class="orbit-component table-attribute-top" style="background-color: #B3B3B3;overflow: auto;height: 495px;overflow-x: hidden; padding-top: 1px" id="loading" >
                      <div class="row">
                      <div data-ng-if="productnotfound">
                           <p class="text-center" style="padding-top: 20px; font-size: 16px"><% language.produktidakditemukan %></p>
                      </div>
                          <div class="col-md-6" data-ng-repeat="(k,v) in product">
                                <button ng-class="k % 2 == 0 ? 'btn mini-box ' : 'btn mini-boxright'"  data-ng-click="showdetailFn(v.product_id,false,v.attribute_id1)">
                                       <div class="row no-gutter" >
                                          <div class="col-xs-4 col-xs-offset-1">
                                              <div class="col-xs-12"><img ng-src="<% configs.baseUrlServerPublic %>/<% v.image %>"  class="img64_64" style="object-fit: contain;" ></div>
                                           </div>
                                           <div class="col-xs-6">
                                               <div class="col-xs-12 text-left" style="margin-left: 3px;margin-top:-9px; ">
                                                <h5><b class="colorbold"><% v.product_name.substr(0,12) %></b><br><b style="font-size: 10px"><% v.upc_code.substr(0,12) %></b></h5>
                                               </div>
                                               <div class="col-xs-12 text-right"><h6 style="margin-top:1px"><% v.price %></h6></div>
                                           </div>
                                       </div>
                                </button>
                          </div>
                      </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Modal Product Detail-->
    <div class="modal fade" id="myModal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content" style="width: 400px;  margin: 30px auto;" >
            <div data-ng-show="!loadproductdetail" data-ng-init="loadproductdetail = false">
              <div class="modal-header"  data-ng-if="hiddenbtn">
                 <button class="btn  close closemodal" data-dismiss="modal" type="button">
                    <span class="glyphicon glyphicon-remove" aria-hidden="true"></span>
                 </button>
                   <h4 class="modal-title text-center" id="myModalLabel"><b>PRODUK DETAIL</b></h4>
             </div>
              <div class="modal-body">
            <div class="row">
                <div class="col-md-12">
                <div class="ribbon-wrapper-green ribbon2nd">
                        <div class="ribbon-green" data-ng-show="datapromotion.length">Promo</div>
                      </div>
                    <p class="text-center"><img ng-src="<% configs.baseUrlServerPublic %>/<% productmodal.image %>"  class="img product" style="width: 200px; height: 200px;" ></p>
                </div>
                <div class="col-md-12 main-theme" >
                    <div class="row">
                      <div class="col-xs-12">
                            <h4> <span class="orbit-component word-wrap"><% productmodal.product_name %></span></h4>
                          </div>
                      <div class="col-xs-12">
                            <p><% productmodal.short_description %></p>
                          </div>
                    </div>
                     <div class="additional-detail">
                     <h4 data-ng-show="datapromotion.length">Promo</h4>
                     <div data-ng-show="datapromotion.length" data-ng-repeat="(k,v) in datapromotion">
                        <div class="row" >
                                                  <div class="col-xs-9">
                                                           <h5><% v.promotion_name %></h5>
                                                        </div>
                                                  <div class="col-xs-3 ">
                                                    <h5><% v.discount_value  %></h5>
                                                  </div>

                        </div>
                        <div class="row" data-ng-show="v.is_permanent !='Y'">
                                                  <div class="col-xs-5 col-xs-height">
                                                    <p><% v.begin_date  %></p>
                                                  </div>
                                                  <div class="col-xs-2 col-xs-height">
                                                    <p>s/d</p>
                                                  </div>
                                                  <div class="col-xs-5 col-xs-height">
                                                    <p><% v.end_date  %></p>
                                                  </div>
                        </div>
                     </div>


                      <div class="row" data-ng-if="hiddenbtn">
                           <div class="col-xs-12" data-ng-show="datapromotion.length">
                                    <p><h5><del><% productmodal.price %></del></h5></p>
                                    <p><h4><% currency %> : <% productmodal.afterpromotionprice %> </h4></p>
                               </div>
                           <div class="col-xs-12" data-ng-show="!datapromotion.length">
                                     <p><h4><% currency %> : <% productmodal.price %></h4></p>
                               </div>
                      </div>
                     </div>
                </div>
                <div class="col-md-12" data-ng-if="!hiddenbtn">

                    <div class="col-xs-12 product-attributes" style="overflow: auto;height: 150px">
                        <div class="row">
                          <div class="col-xs-4 main-theme-text" data-ng-show="productdetail.product.attribute1">
                            <div class="radio-container">
                                     <h5><% productdetail.product.attribute1.product_attribute_name %></h5>
                                    <label class="ui-radio" data-ng-repeat="(k,v) in dataattrvalue1"><input name="checkbox1" type="radio" data-ng-model="chooseattr[0]" data-ng-click="changeattr(0,k)" value="<% v.value1  %>" ><span><% v.value1  %></span></label>
                            </div>
                          </div>
                          <div class="col-xs-4 main-theme-text" data-ng-show="productdetail.product.attribute2">
                            <div class="radio-container">
                                  <h5><% productdetail.product.attribute2.product_attribute_name %></h5>
                                   <label class="ui-radio" data-ng-repeat="(k,v) in dataattrvalue2" data-ng-if="v.value1 == chooseattr[0]"><input name="checkbox2" data-ng-click="changeattr(1,k)" data-ng-model="chooseattr[1]" type="radio" value="<% v.value2  %>" ><span><% v.value2 %></span></label>
                            </div>
                          </div>
                          <div class="col-xs-4 main-theme-text" data-ng-show="productdetail.product.attribute3">
                            <div class="radio-container">
                                <h5><% productdetail.product.attribute3.product_attribute_name %></h5>
                                <label class="ui-radio" data-ng-repeat="(k,v) in dataattrvalue3" data-ng-if="v.value2 == chooseattr[1]"><input name="checkbox3"  data-ng-click="changeattr(2,k)" data-ng-model="chooseattr[2]" type="radio" value="<% v.value3  %>" ><span><% v.value3  %></span></label>

                                    </div>
                          </div>
                        </div>
                    </div>
                    <div class="col-xs-12 product-attributes" data-ng-show="productdetail.product.attribute4 || productdetail.product.attribute5">
                        <div class="row">
                          <div class="col-xs-4 main-theme-text" data-ng-show="productdetail.product.attribute4">
                            <div class="radio-container">
                                <h5><% productdetail.product.attribute4.product_attribute_name %></h5>
                                      <label class="ui-radio" data-ng-repeat="(k,v) in dataattrvalue4" data-ng-if="v.value3 == chooseattr[2]"><input name="checkbox4" data-ng-change="changeattr(3,k)" data-ng-model="chooseattr[3]"  type="radio" value="<% v.value4  %>"  ><span><% v.value4  %></span></label>
                            </div>
                          </div>
                          <div class="col-xs-4 main-theme-text" data-ng-show="productdetail.product.attribute5">
                            <div class="radio-container">
                                <h5><% productdetail.product.attribute5.product_attribute_name %></h5>
                                         <label class="ui-radio" data-ng-repeat="(k,v) in dataattrvalue5" data-ng-if="v.value4 == chooseattr[3]"><input name="checkbox5" data-ng-change="changeattr(4,k)"  data-ng-model="chooseattr[4]" type="radio" value="<% v.value5  %>" ><span><% v.value5  %></span></label>
                            </div>
                          </div>

                        </div>

                    </div>
                </div>
                <div class="col-md-12  main-theme" data-ng-if="!hiddenbtn">
                    <div class="col-md-6" >
                        <div data-ng-show="datapromotion.length && showprice">
                            <p>UPC 1:<% productmodal.upc_code %> </p>
                            <p><h5><del><% productmodal.price %></del></h5></p>
                            <p><h4><% currency %> : <% productmodal.afterpromotionprice %></h3></p>

                        </div>
                        <div data-ng-show="!datapromotion.length && showprice && dataattrvalue1.length != '1'" >
                            <p>UPC 2:<% productmodal.upc_code %> </p>
                            <p><h4><% currency %> : <% productmodal.price %></h3></p>
                        </div>
                        <div data-ng-show="!datapromotion.length && dataattrvalue1.length == '1' && showprice">
                            <p>UPC 3:<% productmodal.upc_code %> </p>
                            <p><h4><% currency %> : <%  productmodal.price %></h3></p>
                        </div>
                        <div data-ng-show="datapromotion.length && dataattrvalue1.length == '1'">
                            <p>UPC 4:<% productmodal.upc_code %> </p>
                            <p><h5><del><% productmodal.price %></del></h5></p>
                            <p><h4><% currency %> : <% productmodal.afterpromotionprice %></h3></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                         <p class="text-center" >
                            <button type="button" class="btn btn-primary" data-dismiss="modal" style="background-color:#097494 ;padding-left: 20px; padding-right: 20px"><i class="fa fa-mail-reply"></i></button> &nbsp;
                            <button type="button"  data-ng-show="showprice" class="btn btn-primary" style="background-color:#097494 ;padding-left: 20px; padding-right: 20px" data-ng-click="inserttocartFn()" data-dismiss="modal" ><i class="fa fa-shopping-cart"></i></button>
                            <button type="button"  data-ng-show="productdetail.product.attribute1 == null" class="btn btn-primary" style="background-color:#097494 ;padding-left: 20px; padding-right: 20px" data-ng-click="inserttocartFn()" data-dismiss="modal" ><i class="fa fa-shopping-cart"></i></button>
                            </p>
                    </div>
                 </div>
            </div>
          </div>
            </div>
            <div data-ng-show="loadproductdetail">
                <div class="modal-body">
                   <span class="ouro ouro3">
                     <span class="left"><span class="anim"></span></span>
                     <span class="right"><span class="anim"></span></span>
                   </span>
                </div>
            </div>
        </div>
      </div>
    </div>
    <!-- Modal Product Not found-->
    <div class="modal fade" id="ProductNotFound" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only"><% language.tutup %></span></button>
            <h4 class="modal-title" id="myModalLabel">&nbsp;</h4>
          </div>
          <div class="modal-body">
                  <p class="text-center"><b><% language.produktidakditemukan %></b></p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-danger" data-dismiss="modal" ><% language.tutup %></button>
          </div>
        </div>
      </div>
    </div>
    <!-- Modal Cart Baru-->
    <div class="modal fade" id="myModalNewCart" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only"><% language.tutup %></span></button>
            <h4 class="modal-title" id="myModalLabel"><b><% language.keranjangbelanjabaru %></b></h4>
          </div>
          <div class="modal-body">
                  <p><b><% language.andayakin1 %></b></p>
                  <p><% language.andayakin1teks %></p>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal" style="background-color:#56BDF1;color:#2C71A3" data-ng-click="newdeletecartFn(true)"><% language.ya %></button>
            <button type="button" class="btn btn-danger" data-dismiss="modal" ><% language.tidak %></button>
          </div>
        </div>
      </div>
    </div>
    <!-- Modal Hapus Cart-->
    <div class="modal fade" id="myModalDeleteCart" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only"><% language.tutup %></span></button>
            <h4 class="modal-title" id="myModalLabel"><b><% language.hapuskeranjangbelanja %></b></h4>
          </div>
          <div class="modal-body">
              <p><b><% language.andayakin2 %></b></p>
              <p><% language.andayakin2teks %></p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal" style="background-color:#56BDF1;color:#2C71A3" data-ng-click="newdeletecartFn()"><% language.ya %></button>
            <button type="button" class="btn btn-danger" data-dismiss="modal"><% language.tidak %></button>
          </div>
        </div>
      </div>
    </div>
    <!-- Modal modalscancart-->
    <div class="modal fade" id="modalscancart" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content" style="width: 400px;  margin: 30px auto;">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" data-ng-click="cancelCart()"><span aria-hidden="true">&times;</span><span class="sr-only"><% language.tutup %></span></button>
            <h4 class="modal-title" id="myModalLabel"><b><% language.scankeranjang %></b></h4>
          </div>
          <div class="modal-body">
              <div  data-ng-show="errorscancart" class="alert alert-danger alert-dismissible fade in" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>
                <h4><% errorscancart %></h4>
              </div>
              <p class="text-center"><b><% language.scankeranjangsekarang %></b></p>
              <p>
                <div class="input-group" >
                       <div type="text" class="form-control"  data-ng-model="manualscancart" id="exampleInputEmail2" placeholder="Input Manual"><% manualscancart %></div>
                       <div class="input-group-addon" style="background-color : #46c2ff; border: none;cursor:pointer" data-ng-click="virtualscancartFn(true)"><i class="fa fa-keyboard-o"></i></div>
                 </div>
              </p>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-danger" data-dismiss="modal" data-ng-click="cancelCart()"><% language.batal %></button>
          </div>

        </div>
        <div data-ng-show="isvirtualscancart"   class="numpad numpadscan">
                                                                       <div class="button-wrapper">
                                                                             <button data-ng-click="keypadscantFn('9')">9</button>
                                                                             <button data-ng-click="keypadscantFn('8')">8</button>
                                                                             <button data-ng-click="keypadscantFn('7')">7</button>
                                                                             <button data-ng-click="keypadscantFn('4')">4</button>
                                                                             <button data-ng-click="keypadscantFn('5')">5</button>
                                                                             <button data-ng-click="keypadscantFn('6')">6</button>
                                                                             <button data-ng-click="keypadscantFn('1')">1</button>
                                                                             <button data-ng-click="keypadscantFn('2')">2</button>
                                                                             <button data-ng-click="keypadscantFn('3')">3</button>

                                                                             <button data-ng-click="keypadscantFn('c')" class="smaller"><% language.bersihkan %></button>
                                                                             <button data-ng-click="keypadscantFn('0')">0</button>
                                                                             <button data-ng-click="keypadscantFn('r')" class="smaller"><span class="glyphicon glyphicon-arrow-left" aria-hidden="true"></span></button>
                                                                             <button data-ng-click="keypadscantFn('d')" ng-disabled="manualscancart == ''" class="button-wide smaller"><% language.selesai %></button>
                                                                       </div>

                                                              </div>
      </div>

    </div>
    <!-- Modal Checkout-->
    <div class="modal fade" id="myModalcheckout" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content" style="width: 400px;  margin: 30px auto;">
          <div class="modal-header">
             <button class="btn  close closemodal" data-ng-if="action != 'done' || cardfile" data-dismiss="modal" data-ng-click="gotomain()"type="button">
              <span class="glyphicon glyphicon-remove" aria-hidden="true"></span>
             </button>
            <h4 class="modal-title text-center" id="myModalLabel"><b> <% cheader %></b></h4>
          </div>
          <div class="modal-body text-center">

                   <div class="row" data-ng-init="action = 'main'" data-ng-show="action == 'main'">
                          <p><button type="button" class="btn btn-success btn-lg"  style="background-color: #009933; padding-left: 83px; padding-right: 83px " data-ng-click="checkoutFn('t')"><% language.tunai %></button></p>
                          <p><button type="button" ng-disabled="!holdbtn || !terminalstatus" class="btn btn-success btn-lg"  style="background-color: #009933; padding-left: 58px; padding-right: 58px" data-ng-click="checkoutFn('k',language.terminal1)"><% language.terminal1 %></button> </p>
                          <p><button type="button" disabled class="btn btn-success btn-lg"  style="background-color: #009933; padding-left: 58px; padding-right: 58px" data-ng-click="checkoutFn('k',language.terminal2)"><% language.terminal2 %></button> </p>
                          <p><button type="button" disabled class="btn btn-success btn-lg"  style="background-color: #009933; padding-left: 58px; padding-right: 58px" data-ng-click="checkoutFn('k',language.terminal3)"><% language.terminal3 %></button> </p>
                          <p data-ng-show="!holdbtn"><% language.terminalbelumdisiapkan %></p>
                   </div>
                   <div class="row" ng-show="action == 'cash'">

                               <div class="form" style="padding-left: 20px;padding-right: 20px;">
                                         <div class="form-group text-left" >
                                             <label for="exampleInputEmail1"><% language.totalbayar %></label>
                                             <input type="text" class="form-control text-right"  id="exampleInputEmail1" style="cursor: default; color:#030000" disabled data-ng-model="cart.totalpay" placeholder="Total bayar">
                                         </div>
                                         <div data-ng-class="change < 0 ? 'form-group text-left has-error' : 'form-group text-left'">
                                             <label for="exampleInputEmail1"><% language.nominaltunai %></label>
                                             <div class="form-control text-right" id="tenderedcash"  data-ng-click="virtualFn(true)" pattern="[0-9]*"  autofocus="autofocus" tabindex="1"  numbers-only="numbers-only"  data-ng-model="cart.amount" placeholder="Nominal Tunai"><% cart.amount %></div>
                                         </div>
                                         <div class="form-group text-left">
                                             <label for="exampleInputEmail1"><% language.kembalian %></label>
                                             <input type="text" class="form-control text-right" id="exampleInputEmail1" style="cursor: default;color:#030000" disabled data-ng-model="cart.change" placeholder="<% language.kembalian %>">
                                          </div>
                               </div>
                               <div data-ng-show="isvirtual"  class="numpad">
                                      <div class="button-wrapper">
                                         <button data-ng-click="keypadFn('9')">9</button>
                                         <button data-ng-click="keypadFn('8')">8</button>
                                         <button data-ng-click="keypadFn('7')">7</button>
                                         <button data-ng-click="keypadFn('4')">4</button>
                                         <button data-ng-click="keypadFn('5')">5</button>
                                         <button data-ng-click="keypadFn('6')">6</button>
                                         <button data-ng-click="keypadFn('1')">1</button>
                                         <button data-ng-click="keypadFn('2')">2</button>
                                         <button data-ng-click="keypadFn('3')">3</button>


                                         <button data-ng-click="keypadFn('.')" class="smaller">.</button>
                                         <button data-ng-click="keypadFn('0')">0</button>
                                         <button data-ng-click="keypadFn('r')" class="smaller"><span class="glyphicon glyphicon-arrow-left" aria-hidden="true"></span></button>

                                          <button data-ng-click="keypadFn('c')" class=" smaller"><% language.bersihkan %></button>
                                          <button data-ng-click="virtualFn(false)" class="smaller" style="width: 110px"><% language.selesai %></button>

                                      </div>

                               </div>

                   </div>
                   <div class="row" ng-show="action == 'card'">

                    <div class="row">
                            <div class="col-md-12">
                              <span  class="text-center"><% cardfile ? headrcard+' '+language.gagal : gesek %> </span>
                            </div>
                            <div class="col-md-12">
                             <img data-ng-show="!cardfile" src='{{ URL::asset('templatepos/images/swipe.gif') }}' style='width:300px;height:300px'>
                             <img data-ng-show="cardfile" src='{{ URL::asset('templatepos/images/rejected.gif') }}' style='width:300px;height:300px'>
                            </div>
                    </div>
                </div>
                   <div class="row" ng-show="action == 'done'">
                          <p><i><% messagesendemail %></i></p>
                         <p><button type="button" class="btn btn-primary btn-lg" ng-if="cart.user_id" style="background-color: #2c71a3;" ng-click="sendticket()"><% language.kirimstruk %></button></p>
                         <p><button type="button" class="btn btn-primary btn-lg"  style="background-color: #2c71a3;" ng-click="ticketprint()"><% language.cetakstruk %></button></p>
                         <p><button type="button" class="btn btn-success btn-lg" data-dismiss="modal"  style="background-color: #009933; padding-left: 53px; padding-right: 53px"   data-ng-click="checkoutFn('d')"><% language.selesai %></button> </p>
                   </div>
          </div>
          <div class="modal-footer" data-ng-if="action !='main'">
                     <button type="button"  data-ng-if="cardfile && action != 'done' && cheader == language.pembayarankartu" class="btn btn-primary"  style="background-color: #2c71a3;" data-ng-click="checkoutFn('k')"><% language.ulangi %></button>
                     <button type="button"  data-ng-if="action !='done' && cardfile" class="btn btn-danger"  data-ng-click="gotomain()"><% language.batal %></button>
                     <button type="button"  data-ng-if="action =='cash'" data-ng-disabled="!changetf || lock" data-ng-init="change = 0" data-ng-click="checkoutFn('c')" class="btn btn-success" style="background-color: #009933;"><% language.lanjutkan %></button>
           </div>
        </div>

      </div>
    </div>

</div>
</div>

@stop

