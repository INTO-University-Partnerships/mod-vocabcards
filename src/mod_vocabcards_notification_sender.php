<?php

defined('MOODLE_INTERNAL') || die;

require_once __DIR__ . '/../../../vendor/autoload.php';

class mod_vocabcards_notification_sender {

    /**
     * c'tor
     */
    public function __construct() {
        // empty
    }

    /**
     * sends a notification to Django on a configured endpoint
     * secured with basic auth
     * @global moodle_database $DB
     * @param \GuzzleHttp\Client $client
     * @param string $body
     * @param array $userids
     * @param string $url
     */
    public function send_notification(\GuzzleHttp\Client $client, $body, array $userids, $url) {
        global $DB, $CFG;

        // get usernames from userids
        $usernames = array();
        foreach ($userids as $userid) {
            $usernames[] = $DB->get_field('user', 'username', array(
                'id' => $userid,
                'deleted' => 0,
            ));
        }

        // do nothing if no usernames exist
        if (empty($usernames)) {
            return;
        }

        // data to send
        $data = array(
            'usernames' => $usernames,
            'url' => $url,
            'subject' => get_string('modulename', 'mod_vocabcards'),
            'body' => $body,
        );

        // send it using Guzzle
        $request = $client->createRequest(
            'POST',
            $CFG->djangowwwroot . $CFG->django_urls['send_notification'],
            array(
                'auth' => $CFG->django_notification_basic_auth,
                'body' => json_encode($data),
            )
        );
        $client->send($request);
    }

}
