<?php

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../../course/moodleform_mod.php';

class mod_vocabcards_mod_form extends moodleform_mod {

    /**
     * definition
     */
    protected function definition() {
        global $CFG;

        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        // name
        $mform->addElement('text', 'name', get_string('vocabcardsname', 'vocabcards'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // header & footer
        foreach (array('header', 'footer') as $element) {
            $mform->addElement('editor', $element, get_string($element, 'vocabcards'), null, array(
                'maxfiles' => 0,
                'maxbytes' => 0,
                'trusttext' => false,
                'forcehttps' => false,
            ));
        }

        // start date field used as a baseline for sending time-bound notifications
        $mform->addElement('date_selector', 'startdate', get_string('startdate', 'vocabcards'), array(
            'optional' => false,
        ));
        $mform->addHelpButton('startdate', 'startdate', 'vocabcards');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * @param array $default_values
     */
    function data_preprocessing(&$default_values) {
        if ($this->current->instance) {
            $header = $default_values['header'];
            $default_values['header'] = array(
                'format' => FORMAT_HTML,
                'text' => $header,
            );
            $footer = $default_values['footer'];
            $default_values['footer'] = array(
                'format' => FORMAT_HTML,
                'text' => $footer,
            );
        }
    }

}
