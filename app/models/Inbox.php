<?php

use \Carbon\Carbon as Carbon;

/**
 * Model for Inbox or alert.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class Inbox extends Eloquent
{
    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'inboxes';
    protected $primaryKey = 'inbox_id';

    /**
     * Get the latest one.
     */
    public function scopeLatestOne($query, $userId = NULL, $mallId = NULL)
    {
        $latest =  $query->orderBy('inboxes.created_at', 'asc')
                         ->where('is_read', 'N')
                         ->excludeDeleted();

        if ($userId !== NULL) {
            $latest->where('user_id', $userId);
        }

        if ($mallId !== NULL) {
            $latest->where('merchant_id', $mallId);
        }

        return $latest;
    }

    /**
     * Get the alert type inbox.
     */
    public function scopeAlert($query)
    {
        return $query->where('inbox_type', 'alert');
    }

    /**
     * Get the not alert type inbox.
     */
    public function scopeIsNotAlert($query)
    {
        return $query->where('inbox_type', '<>', 'alert');
    }

    /**
     * Get the inbox read status.
     */
    public function scopeIsNotRead($query)
    {
        return $query->where('is_read', 'N');
    }

    /**
     * Get the inbox notified status.
     */
    public function scopeIsNotNotified($query)
    {
        return $query->where('is_notified', 'N');
    }

    /**
     * Insert issued lucky draw numbers into inbox table.
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @param int $userId - The user id
     * @param array $response - Object
     * @param int $retailerId - The retailer
     * @return void
     */
    public function addToInbox($userId, $response, $retailerId, $type)
    {
        $user = User::find($userId);

        if (empty($user)) {
            throw new Exception ('Customer user ID not found.');
        }

        if (empty($type)) {
            $type = 'alert';
        }

        $name = $user->getFullName();
        $name = ! empty(trim($name)) ? $name : $user->user_email;

        $inbox = new Inbox();
        $inbox->user_id = $userId;
        $inbox->merchant_id = $retailerId;
        $inbox->from_id = 0;
        $inbox->from_name = 'Orbit';
        $inbox->content = '';
        $inbox->inbox_type = $type;
        $inbox->status = 'active';
        $inbox->is_read = 'N';

        $retailer = Mall::where('merchant_id', $retailerId)->first();

        $dateIssued = Carbon::now($retailer->timezone->timezone_name);

        $listItem = null;
        switch ($type) {
            case 'activation':
                $inbox->subject = "Account Activation";
                break;

            case 'lucky_draw_issuance':
                $inbox->subject = "You've got lucky number(s)";
                $listItem = $response->records;
                break;

            case 'lucky_draw_blast':
                $inbox->subject = $response->title;
                break;

            case 'coupon_issuance':
                $inbox->subject = "You've got coupon(s)";
                $listItem = $response;
                break;

            default:
                break;
        }

        $inbox->save();

        $data = [
            'fullName'              => $name,
            'subject'               => $inbox->subject,
            'inbox'                 => $inbox,
            'item'                  => $response,
            'listItem'              => $listItem,
            'mallName'              => $retailer->name,
            'dateIssued'            => $dateIssued,
            'user'                  => $user
        ];

        $template = View::make('mobile-ci.mall-push-notification-content', $data);
        $template = $template->render();

        $inbox->content = $template;
        $inbox->save();
    }
}
