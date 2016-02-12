
<!-- Push Notification Popup -->
<div id="orbit-push-notification-wrapper"></div>
<script>

    $(document).ready(function() {
        var untoasteds = [];
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
            }).fail(function(resp) {
                // Fail
            }).always(function(resp) {
                // Fail or Success
            });
        };

        // Callback function to get the notification
        var getNotif = function() {
            // No need to poll if one is viewing
            if (orbitIsViewing) {
                return;
            }

            $.ajax({
                url: apiPath + 'inbox/unread-count',
                method: 'GET',
                data: {}
            }).done(function(resp) {
                // Succeed
                if (resp.data.records > 0 || resp.data.records === '9+') {
                    $('.notification-badge-txt').text(resp.data.records);
                    $('.notification-badge-txt').show();
                } else {
                    $('.notification-badge-txt').text('0');
                    $('.notification-badge-txt').hide();
                }
                if (resp.data.untoasted_records.length > 0) {
                    untoasteds = []; // reset untoasted stack
                    untoasteds = resp.data.untoasted_records;
                }
            }).fail(function(resp) {
                $('.notification-badge-txt').text('0');
                $('.notification-badge-txt').hide();
            }).always(function(resp) {

            });
        };

        getNotif();

        setInterval(function() {
            getNotif()
        }, pushNotificationDelay);

        if(notInMessagesPage){
            setInterval(function() {
                if (untoasteds.length > 0) {
                    var inboxId = untoasteds[0].inbox_id,
                        inboxUrl = untoasteds[0].url,
                        inboxSubject = untoasteds[0].subject;
                    toastr.options = {
                        closeButton: true,
                        closeHtml: '<button>×</button>',
                        closeDuration: 200,
                        showDuration: 30,
                        timeOut: 2500,
                        positionClass: 'toast-bottom-right',
                        onclick: function() {
                            window.location.href = inboxUrl;
                        },
                        onShown: function() {
                            $.ajax({
                                url: apiPath + 'inbox/notified',
                                method: 'POST',
                                data: {
                                    inbox_id: inboxId
                                }
                            }).done(function(resp) {
                                for(var i = untoasteds.length-1; i >= 0; i--) { // remove inbox with the last notified inbox_id
                                    if( untoasteds[i].inbox_id == inboxId) untoasteds.splice(i,1);
                                }
                            });
                        }
                    };
                    toastr.info(inboxSubject);
                }
            }, 2730);
        }

        $(document).on('hidden.bs.modal', '.modal', function () {
            $('.modal:visible').length && $(document.body).addClass('modal-open');
        });
    });
</script>
