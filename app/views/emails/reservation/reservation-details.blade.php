                        <table width="100%">
                          <thead>
                            <tr>
                              <th colspan="2" class="uppercase reservation-table-title">
                                {{ trans('email-reservation.labels.reservation_details', [], '', $lang) }}</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr>
                              <td class="mobile w-35 bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-reservation.labels.transaction_id', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $reservationId }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-reservation.labels.user_email', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $customerEmail }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-reservation.labels.store_location', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ trans('email-reservation.labels.store_location_detail', $store, '', $lang) }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-reservation.labels.reserve_date', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $reservationTime }}</span>
                              </td>
                            </tr>
                            @if ($showExpirationTime)
                              <tr>
                                <td class="mobile bold reservation-table-item-label">
                                  <span class="p-8 block">{{ trans('email-reservation.labels.expiration_date', [], '', $lang) }}</span>
                                </td>
                                <td class="mobile reservation-table-item-value">
                                  <span class="p-8 block">{{ $expirationTime }}</span>
                                </td>
                              </tr>
                            @endif

                            @if ($showCancelledTime)
                              <tr>
                                <td class="mobile bold reservation-table-item-label">
                                  <span class="p-8 block">{{ trans('email-reservation.labels.cancelled_date', [], '', $lang) }}</span>
                                </td>
                                <td class="mobile reservation-table-item-value">
                                  <span class="p-8 block">{{ $cancelledTime }}</span>
                                </td>
                              </tr>
                            @endif

                            @if ($showDeclinedTime)
                              <tr>
                                <td class="mobile bold reservation-table-item-label">
                                  <span class="p-8 block">{{ trans('email-reservation.labels.declined_date', [], '', $lang) }}</span>
                                </td>
                                <td class="mobile reservation-table-item-value">
                                  <span class="p-8 block">{{ $declinedTime }}</span>
                                </td>
                              </tr>
                            @endif

                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-reservation.labels.total_payment', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $totalPayment }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-reservation.labels.status', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block text-{{ $statusColor[$status] }}">
                                  {{ trans('email-reservation.labels.status_detail.' . $status, [], '', $lang) }}
                                </span>
                              </td>
                            </tr>

                            @if ($status === 'declined')
                              <tr>
                                <td class="mobile bold reservation-table-item-label">
                                  <span class="p-8 block">{{ trans('email-reservation.labels.reason', [], '', $lang) }}</span>
                                </td>
                                <td class="mobile reservation-table-item-value">
                                  <span class="p-8 block">{{ $reason }}</span>
                                </td>
                              </tr>
                            @endif
                          </tbody>
                        </table>
