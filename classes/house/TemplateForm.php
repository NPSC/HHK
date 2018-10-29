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


    function __construct($dbh, $fileName, $path = 'conf/') {

        $this->mime = array(
            'txt'      => 'text/html',
            'html'      => 'text/html',
            'htm'      => 'text/html',
            'mht'      => 'text/html',
            'mhtml'      => 'text/html',
        );

		if($dbh == null){
			$this->templateFileName = REL_BASE_DIR . $path . $fileName;
			$this->getFormTemplate();
		}else{
			
			$idDocument = Document::findDocument($dbh, $fileName, 'form', 'md');
			
			if($idDocument > 0){
				$document = new Document($dbh, $idDocument);
				$this->document = $document->getDoc();
				
			}
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

    protected function getFormTemplate() {

        $this->templateFile = '';

        if (file_exists($this->templateFileName)) {

            $pathInfo = pathinfo($this->templateFileName);

            if (isset($pathInfo['extension']) === FALSE || isset($this->mime[strtolower($pathInfo['extension'])]) === FALSE) {
                throw new Hk_Exception_Runtime("File extension not supported, file = " . $this->templateFileName);
            }

            if (($text = file_get_contents($this->templateFileName)) === FALSE) {
                throw new Hk_Exception_Runtime("File template not read, file = " . $this->templateFileName);
            }

            $this->templateFile = $text;

        } else {
            throw new Hk_Exception_Runtime("File template does not exist, file = " . $this->templateFileName);
        }

    }
}
