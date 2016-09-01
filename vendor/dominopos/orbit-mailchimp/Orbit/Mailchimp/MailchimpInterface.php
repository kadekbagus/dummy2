<?php namespace Orbit\Mailchimp;
/**
 * Interface which define the way generic Mailchimp works via Orbit.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
interface MailchimpInterface
{
    public function setConfig(array $config);
    public function getConfig();
    public function postMembers($listId, array $params=[]);
}