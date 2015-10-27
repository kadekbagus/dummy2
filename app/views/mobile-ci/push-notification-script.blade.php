
<!-- Push Notification Popup -->
<div id="orbit-push-notification-wrapper"></div>
{{ HTML::script('mobile-ci/scripts/jquery.ba-replacetext.min.js') }}
<script>

    $(document).ready(function() {
        var txt_congrats = '{{ Lang::get('mobileci.coupon.congratulations_you_get') }}';
        var txt_coupons = '{{ Lang::get('mobileci.coupon.here_are_your_coupons') }}';
        var txt_coupon = '{{ Lang::get('mobileci.coupon.here_is_your_coupon') }}';
        var txt_check = '{{ Lang::get('mobileci.coupon.check_coupon') }}';
        var txt_happy = '{{ Lang::get('mobileci.coupon.happy_shopping') }}';
        var txt_close = '{{ Lang::get('mobileci.coupon.close') }}';

        var pushNotificationDelay = 1000 * {{ Config::get('orbit.shop.poll_interval', 5) }}
        // Flag to see whether this notification is viewing by user
        var currentInboxId = -1;

        // Callback function to mark the notification as read
        var readNotif = function(inboxId) {
            $.ajax({
                url: apiPath + 'alert/read',
                method: 'POST',
                data: {
                    inbox_id: inboxId
                }
            }).done(function(resp) {
                // Succeed
                // console.log(resp.data);
            }).fail(function(resp) {
                // Fail
            }).always(function(resp) {
                // Fail or Success
            });
        };

        // Callback function to get the notification
        var getNotif = function() {
            // No need to poll if one is viewing
            // console.log(orbitIsViewing);
            if (orbitIsViewing) {
                return;
            }

            $.ajax({
                url: apiPath + 'alert/poll',
                method: 'GET',
                data: {}
            }).done(function(resp) {
                // Succeed
                // console.log(resp.data.records);

                if (resp.data.records) {
                    var notif = resp.data.records[0];

                    if (resp.data.total_records > 0) {
                        orbitIsViewing = true;

                        // Show the notification to the user
                        $('#orbit-push-notification-wrapper').html(notif.content);

                        $('#orbit-push-notification-wrapper').replaceText('|#|congratulations_you_get|#|', txt_congrats);
                        $('#orbit-push-notification-wrapper').replaceText('|#|here_are_your_coupons|#|', txt_coupons);
                        $('#orbit-push-notification-wrapper').replaceText('|#|check_coupon|#|', txt_check);
                        $('#orbit-push-notification-wrapper').replaceText('|#|happy_shopping|#|', txt_happy);
                        $('#orbit-push-notification-wrapper').replaceText('|#|close|#|', txt_close);

                        // Fire event when the pop up closed
                        $('#orbit-push-modal-' + notif.inbox_id).on('hidden.bs.modal', function(e) {
                            // console.log("closed");

                            // Mark this alert as read
                            readNotif(notif.inbox_id);

                            orbitIsViewing = false;
                        });
                        $('#orbit-push-modal-' + notif.inbox_id).modal();
                    } else {
                        orbitIsViewing = false;
                    }
                }
            }).fail(function(resp) {
                // Fail
            }).always(function(resp) {
                // Fail or Success
            });
        };

        setInterval(function() {
            getNotif()
        }, pushNotificationDelay);
    });
</script>
