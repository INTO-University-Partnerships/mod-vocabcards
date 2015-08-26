<?php

namespace mod_vocabcards\event;

defined('MOODLE_INTERNAL') || die();

abstract class mod_vocabcards_base_event extends \core\event\base {

    /**
     * @var \GuzzleHttp\Client
     */
    protected $_guzzler;

    /**
     * accessor
     * @return \GuzzleHttp\Client
     */
    public function get_guzzler() {
        return $this->_guzzler;
    }

    /**
     * accessor
     * @param \GuzzleHttp\Client $client
     */
    public function set_guzzler(\GuzzleHttp\Client $client) {
        $this->_guzzler = $client;
    }

}
