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
class TemplateForm {

    protected $template;
    protected $templateDoc;
    protected $templateTags;

    function __construct($dbh, $idDoc, $docName = '') {

        $this->template = '';

        if (is_null($this->templateDoc = $this->loadTemplate($dbh, $idDoc, $docName))) {
            throw new Hk_Exception_Runtime('Template document not found.  ');
        }

        $this->templateTags = self::loadTemplateTags($dbh, $this->templateDoc->getName());
    }

    public static function loadTemplate(\PDO $dbh, $idDoc, $docName = '') {

        $name = addslashes($docName);
        $id = intval($idDoc, 10);

        if ($id < 1 && $name !== '') {
            $id = Document::findDocumentId($dbh, '', '', '', $name);
        }

        // Find anything
        if ($id < 1) {
            return NULL;
        }

       return new Document($dbh, $id);
    }

    public static function loadTemplateTags(\PDO $dbh, $docName) {

        $tags = array();
        $name = addslashes($docName);

        if ($name == ''){
            return $tags;
        }

        $stmt = $dbh->query("Select Tag_Name, Tag_Title, '' as Substitute, Replacement_Wrapper from `template_tag` where `Doc_Name` = '$name';");

        while ($r = $stmt->fetch()) {

            $tags[$r['Tag_Name']] = $r;
        }

        return $tags;
    }

    public function createForm($replacements) {

        $this->template = $this->templateDoc->getDoc();

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

    public function getTagSelector($ctrlName) {

        if (count($this->templateTags) > 0) {
            return HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($this->templateTags, '', TRUE), array('id'=>$ctrlName, 'name'=>$ctrlName));
        }

        return '';
    }

    public function getTemplateDoc() {

        if (is_null($this->templateDoc)) {
            return '';
        }

        return $this->templateDoc;
    }

    public function getTemplateTags() {

        if (is_null($this->templateTags)) {
            return array();
        }

        return $this->templateTags;
    }

}
