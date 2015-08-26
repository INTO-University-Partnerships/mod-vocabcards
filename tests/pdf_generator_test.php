<?php

use Mockery as m;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../src/pdf_generator.php';

class pdf_generator_test extends advanced_testcase {

    /**
     * @var pdf_generator
     */
    protected $_cut;

    /**
     * @var TCPDF
     */
    protected $_mock;

    /**
     * setUp
     */
    public function setUp() {
        $this->_mock = m::mock('TCPDF');
        $this->_mock->shouldReceive('SetTitle')
            ->once()
            ->with(get_string('pdf:title', 'mod_vocabcards'));
        $this->_mock->shouldReceive('setPrintHeader')
            ->once()
            ->with(false);
        $this->_mock->shouldReceive('setPrintFooter')
            ->once()
            ->with(false);
        $this->_mock->shouldReceive('SetMargins')
            ->once()
            ->with(5, 5, 5, true);
        $this->_mock->shouldReceive('SetAutoPageBreak')
            ->once()
            ->with(true, 5);
        $this->_mock->shouldReceive('SetFont')
            ->once()
            ->with('freesans', '', 10);
        $this->_mock->shouldReceive('setFontSubsetting')
            ->once()
            ->with(false);
        $this->_mock->shouldIgnoreMissing();
    }

    /**
     * tearDown
     */
    public function tearDown() {
        m::close();
    }

    /**
     * tests instantiation
     */
    public function test_instantiation() {
        $this->_cut = new pdf_generator($this->_mock);
        $this->assertInstanceOf('pdf_generator', $this->_cut);
    }

    /**
     * tests adding a page
     */
    public function test_add_page() {
        $this->_mock->shouldReceive('AddPage')->once();
        $this->_cut = new pdf_generator($this->_mock);
        $this->_cut->add_page();
    }

    /**
     * tests writing some html
     */
    public function test_write() {
        $s = '<ul><li>one</li><li>two</li><li>three</li></ul>';
        $this->_mock->shouldReceive('writeHTML')
            ->once()
            ->with($s, true, false, false, false, '');
        $this->_cut = new pdf_generator($this->_mock);
        $this->_cut->write($s);
    }

    /**
     * tests rendering as a string
     */
    public function test_render() {
        $s = '<ul><li>one</li><li>two</li><li>three</li></ul>';
        $this->_mock->shouldReceive('lastPage')->once();
        $this->_mock->shouldReceive('Output')
            ->once()
            ->with(null, 'S')
            ->andReturn($s);
        $this->_cut = new pdf_generator($this->_mock);
        $this->assertEquals($s, $this->_cut->render());
    }

}
