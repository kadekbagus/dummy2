
<!-- Push Notification Popup -->
<div id="orbit-push-notification-wrapper"></div>
{{ HTML::script('mobile-ci/scripts/jquery.ba-replacetext.min.js') }}
<script>

    $(document).ready(function() {
        var langs = {};
        langs.coupon = {};
        langs.lucky_draw = {};
        langs = {
            coupon : {
                txt_subject: '{{ Lang::get('mobileci.inbox.coupon.subject') }}',
                txt_hello: '{{ Lang::get('mobileci.lucky_draw.hello') }}',
                txt_congrats: '{{ Lang::get('mobileci.coupon.congratulations_you_get') }}',
                txt_coupons: '{{ Lang::get('mobileci.coupon.here_are_your_coupons') }}',
                txt_coupon: '{{ Lang::get('mobileci.coupon.here_is_your_coupon') }}',
                txt_check: '{{ Lang::get('mobileci.coupon.check_coupon') }}',
                txt_happy: '{{ Lang::get('mobileci.coupon.happy_shopping') }}',
                txt_close: '{{ Lang::get('mobileci.coupon.close') }}'
            },
            lucky_draw : {
                txt_subject: '{{ Lang::get('mobileci.inbox.lucky_draw.subject') }}',
                txt_congrats: '{{ Lang::get('mobileci.lucky_draw.congratulation') }}',
                txt_no_lucky_draw: '{{ Lang::get('mobileci.lucky_draw.no_lucky_draw') }}',
                txt_lucky_draw_info_1: '{{ Lang::get('mobileci.lucky_draw.lucky_draw_info_1') }}',
                txt_lucky_draw_info_2: '{{ Lang::get('mobileci.lucky_draw.lucky_draw_info_2') }}',
                txt_lucky_draw_info_3: '{{ Lang::get('mobileci.lucky_draw.lucky_draw_info_3') }}',
                txt_lucky_draw_info_4: '{{ Lang::get('mobileci.lucky_draw.lucky_draw_info_4') }}',
                txt_lucky_draw_info_5: '{{ Lang::get('mobileci.lucky_draw.lucky_draw_info_5') }}',
                txt_lucky_draw: '{{ Lang::get('mobileci.lucky_draw.lucky_draw') }}',
                txt_goodluck: '{{ Lang::get('mobileci.lucky_draw.goodluck') }}'
            }
        };

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
                        var new_content = notif.content;

                        // replace text placeholder for coupon popup
                        new_content = new_content.replace('|#|hello|#|', langs.coupon.txt_hello);
                        new_content = new_content.replace('|#|coupon_subject|#|', langs.coupon.txt_subject);
                        new_content = new_content.replace('|#|congratulations_you_get|#|', langs.coupon.txt_congrats);
                        new_content = new_content.replace('|#|here_are_your_coupons|#|', langs.coupon.txt_coupons);
                        new_content = new_content.replace('|#|here_is_your_coupon|#|', langs.coupon.txt_coupon);
                        new_content = new_content.replace('|#|check_coupon|#|', langs.coupon.txt_check);
                        new_content = new_content.replace('|#|happy_shopping|#|', langs.coupon.txt_happy);
                        new_content = new_content.replace('|#|close|#|', langs.coupon.txt_close);

                        // replace text placeholder for lucky draw popup
                        new_content = new_content.replace('|#|lucky_draw_subject|#|', langs.lucky_draw.txt_subject);
                        new_content = new_content.replace('|#|ld_congratulations_you_get|#|', langs.lucky_draw.txt_congrats);
                        new_content = new_content.replace('|#|no_lucky_draw|#|', langs.lucky_draw.txt_no_lucky_draw);
                        new_content = new_content.replace('|#|lucky_draw_info_1|#|', langs.lucky_draw.txt_lucky_draw_info_1);
                        new_content = new_content.replace('|#|lucky_draw_info_2|#|', langs.lucky_draw.txt_lucky_draw_info_2);
                        new_content = new_content.replace('|#|lucky_draw_info_3|#|', langs.lucky_draw.txt_lucky_draw_info_3);
                        new_content = new_content.replace('|#|lucky_draw_info_4|#|', langs.lucky_draw.txt_lucky_draw_info_4);
                        new_content = new_content.replace('|#|lucky_draw_info_5|#|', langs.lucky_draw.txt_lucky_draw_info_5);
                        new_content = new_content.replace('|#|lucky_draw|#|', langs.lucky_draw.txt_lucky_draw);
                        new_content = new_content.replace('|#|goodluck|#|', langs.lucky_draw.txt_goodluck);
                        new_content = new_content.replace('|#|close|#|', langs.coupon.txt_close);

                        $('#orbit-push-notification-wrapper').html(new_content);

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

        $(document).on('hidden.bs.modal', '.modal', function () {
            $('.modal:visible').length && $(document.body).addClass('modal-open');
        });
    });
</script>
