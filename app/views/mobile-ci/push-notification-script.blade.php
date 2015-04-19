
<!-- Push Notification Popup -->
<div id="orbit-push-notification-wrapper"></div>

<script>
    var orbitIsViewing = false;

    $(document).ready(function() {
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
                console.log(resp.data);
            }).fail(function(resp) {
                // Fail
            }).always(function(resp) {
                // Fail or Success
            });
        };

        // Callback function to get the notification
        var getNotif = function() {
            // No need to poll if one is viewing
            console.log(orbitIsViewing);
            if (orbitIsViewing) {
                return;
            }

            $.ajax({
                url: apiPath + 'alert/poll',
                method: 'GET',
                data: {}
            }).done(function(resp) {
                // Succeed
                console.log(resp.data.records);

                if (resp.data.records) {
                    var notif = resp.data.records[0];

                    if (resp.data.total_records > 0) {
                        orbitIsViewing = true;

                        // Show the notification to the user
                        $('#orbit-push-notification-wrapper').html(notif.content);

                        // Fire event when the pop up closed
                        $('#orbit-push-modal-' + notif.inbox_id).on('hidden.bs.modal', function(e) {
                            console.log("closed");

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
