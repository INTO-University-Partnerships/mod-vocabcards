<?php

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../lib.php';

class mod_vocabcards_lib_test extends advanced_testcase {

    /**
     * setUp
     */
    protected function setUp() {
        // empty
    }

    /**
     * tests the features that vocabcards supports
     */
    public function test_vocabcards_supports() {
        $features = array(
            FEATURE_COMPLETION_TRACKS_VIEWS,
            FEATURE_BACKUP_MOODLE2,
        );
        foreach ($features as $feature) {
            $this->assertTrue(plugin_supports('mod', 'vocabcards', $feature));
        }
    }

    /**
     * tests the features that vocabcards does not support
     */
    public function test_vocabcards_not_supports() {
        $features = array(
            FEATURE_GRADE_HAS_GRADE,
            FEATURE_GRADE_OUTCOMES,
            FEATURE_ADVANCED_GRADING,
            FEATURE_CONTROLS_GRADE_VISIBILITY,
            FEATURE_PLAGIARISM,
            FEATURE_COMPLETION_HAS_RULES,
            FEATURE_NO_VIEW_LINK,
            FEATURE_IDNUMBER,
            FEATURE_GROUPS,
            FEATURE_GROUPINGS,
            FEATURE_MOD_ARCHETYPE,
            FEATURE_MOD_INTRO,
            FEATURE_MODEDIT_DEFAULT_COMPLETION,
            FEATURE_COMMENT,
            FEATURE_RATE,
            FEATURE_SHOW_DESCRIPTION,
        );
        foreach ($features as $feature) {
            $this->assertFalse(plugin_supports('mod', 'vocabcards', $feature));
        }
    }

    /**
     * @return array
     */
    public function test_vocabcards_get_extra_capabilities() {
        $expected = array(
            'moodle/site:accessallgroups',
        );
        $this->assertEquals($expected, vocabcards_get_extra_capabilities());
    }

    /**
     * test _all() function
     */
    public function test_all() {
        $f = function ($b) {
            return (boolean)$b;
        };
        $this->assertTrue(_all($f, array(true, true, true)));
        $this->assertFalse(_all($f, array(true, false, true)));
        $this->assertFalse(_all($f, array(false, false, false)));
        $this->assertTrue(_all($f, array()));
    }

    /**
     * test _any() function
     */
    public function test_any() {
        $f = function ($b) {
            return (boolean)$b;
        };
        $this->assertTrue(_any($f, array(false, false, true)));
        $this->assertTrue(_any($f, array(true, true, true)));
        $this->assertFalse(_any($f, array(false, false, false)));
        $this->assertFalse(_any($f, array()));
    }

    /**
     * test _and() function
     */
    public function test_and() {
        $this->assertTrue(_and(array(true, true, true, true)));
        $this->assertFalse(_and(array(true, false, true, true)));
        $this->assertFalse(_and(array(false)));
        $this->assertTrue(_and(array()));
    }

    /**
     * test _or() function
     */
    public function test_or() {
        $this->assertTrue(_or(array(false, false, true, false)));
        $this->assertTrue(_or(array(true, true, true, true)));
        $this->assertFalse(_or(array(false, false, false, false)));
        $this->assertFalse(_or(array()));
    }

}
