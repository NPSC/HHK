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

   public $template;
       
   function __construct($template){
       $this->template = $template;
   }
    
    public function createForm($replacements) {

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
