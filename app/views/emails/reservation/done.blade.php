@extends('emails.layouts.default')

@section('title')
Reservation Done
@stop

@section('content')
      @foreach($langs as $lang)
        <tr>
          <td align="center" valign="top">

            <table width="640" cellpadding="0" cellspacing="0" border="0" class="wrapper" bgcolor="#FFFFFF" style="background-color: transparent;">
              <tr>
                <td align="center" valign="middle" style="box-shadow: 0 0 20px #e0e0e0; border-radius:5px;background-color: #FFF;">

                  <table width="640" cellpadding="0" cellspacing="0" border="0" class="container mobile-full-width">
                    <tr>
                      <td align="center" valign="middle" height="184" class="greeting-title-container" style="border-radius: 5px 5px 0 0;">
                          <h1 class="greeting-title">{{ trans('email-reservation.done.title', [], '', $lang) }}</h1>
                      </td>
                    </tr>
                  </table>

                  <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                    <tr>
                      <td class="mobile" align="left" valign="top">
                        <h3 class="greeting-username">
                          {{ trans('email-reservation.done.greeting', ['recipientName' => $recipientName], '', $lang) }}
                        </h3>
                        <p class="greeting-text" style="line-height: 1.75em;">
                          {{ trans('email-reservation.done.body.line-1', $store, '', $lang) }}
                        </p>
                      </td>
                    </tr>
                    <tr>
                      <td width="600" class="mobile greeting-text" valign="middle">

                        @include('emails.reservation.reservation-details')

                        <br>

                        @include('emails.reservation.product-details')

                      </td>
                    </tr>

                    <tr>
                      <td>
                        <p class="greeting-text">
                          <br>
                          {{ trans('email-reservation.done.body.line-2', [], '', $lang) }}
                          <br>
                          &nbsp;
                        </p>
                      </td>
                    </tr>

                    <tr>
                      <td class="text-center">
                        <a href="{{{ $myReservationUrl }}}" class="btn btn-primary mx-4">
                          {{{ trans('email-reservation.labels.btn_see_my_reservation', [], '', $lang) }}}
                        </a>
                      </td>
                    </tr>

                    <tr>
                      <td height="50" align="center" class="separator">&nbsp;</td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td height="30" align="center" class="separator">&nbsp;</td>
        </tr>
      @endforeach
@stop
