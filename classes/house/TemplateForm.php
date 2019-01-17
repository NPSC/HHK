<?php

/**
 * TemplateForm.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of TemplateForm
 *
 * @author Eric
 */
abstract class TemplateForm {

    protected $mime;
    protected $template;
    protected $templateFileName;
    public $templateFile;

    function __construct($dbh, $name) {

        $this->mime = array(
            'txt' => 'text/html',
            'html' => 'text/html',
            'htm' => 'text/html',
            'mht' => 'text/html',
            'mhtml' => 'text/html',
        );

        if ($name == '') {
            throw new Hk_Exception_Runtime("File name is missing");
        }

        $idDocument = Document::findDocument($dbh, '', '', '', $name);

        if ($idDocument > 0) {

            $document = new Document($dbh, $idDocument);
            $parsedown = new Parsedown();
            $this->templateFile = $parsedown->setBreaksEnabled(true)->text($document->getDoc());

        } else {
            throw new Hk_Exception_Runtime("File template does not exist, name = " .$name);
        }
    }

    public function createForm($replacements) {

        $this->template = $this->templateFile;

        $vars = $this->getVariables();

        foreach ($vars as $v) {

            if (isset($replacements[$v])) {
                $this->setValue($v, $replacements[$v]);
            }
        }

        return str_replace('  ', ' ', $this->template);
    }

    protected function setValue($search, $replace) {

        $this->template = str_replace(self::ensureMacroCompleted($search), $replace, $this->template);
    }

    protected static function ensureMacroCompleted($macro) {

        if (substr($macro, 0, 2) !== '${' && substr($macro, -1) !== '}') {
            $macro = '${' . $macro . '}';
        }

        return $macro;
    }

    protected function getVariables() {

        $matches = array();

        preg_match_all('/\$\{(.*?)}/i', $this->template, $matches);

        return array_unique($matches[1]);
    }

}
