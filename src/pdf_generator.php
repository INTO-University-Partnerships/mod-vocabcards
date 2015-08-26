<?php

defined('MOODLE_INTERNAL') || die;

require_once __DIR__ . '/../../../lib/tcpdf/tcpdf.php';

class pdf_generator {

    /**
     * @var TCPDF
     */
    protected $_tcpdf;

    /**
     * c'tor
     * @param TCPDF $tcpdf
     */
    public function __construct(TCPDF $tcpdf = null) {
        $this->_tcpdf = empty($tcpdf) ? new TCPDF() : $tcpdf;

        $this->_tcpdf->SetTitle(get_string('pdf:title', 'mod_vocabcards'));
        $this->_tcpdf->setPrintHeader(false);
        $this->_tcpdf->setPrintFooter(false);
        $this->_tcpdf->SetMargins(5, 5, 5, true);
        $this->_tcpdf->SetAutoPageBreak(true, 5);

        // this font can render phonemic symbols where others can't
        $this->_tcpdf->SetFont('freesans', '', 10);
        $this->_tcpdf->setFontSubsetting(false);
    }

    /**
     * adds a page
     */
    public function add_page() {
        $this->_tcpdf->AddPage();
    }

    /**
     * writes some html
     * @param string $html
     */
    public function write($html) {
        $this->_tcpdf->writeHTML($html, true, false, false, false, '');
    }

    /**
     * render as a string
     * @return string
     */
    public function render() {
        $this->_tcpdf->lastPage();
        return $this->_tcpdf->Output(null, 'S');
    }

}
