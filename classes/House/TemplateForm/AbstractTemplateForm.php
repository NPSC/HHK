<?php

namespace HHK\House\TemplateForm;


/**
 * AbstractTemplateForm.php
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
abstract class AbstractTemplateForm {

    public $docId;
    public $docTitle;
    public $template;
    public $replacedTemplate;
    public $subjectLine;

   /**
    * @param \PDO $dbh
    * @param integer $docId
    */
   function __construct(\PDO $dbh, $docId){

        $this->docTitle = "";
        $this->template = "";
        $this->subjectLine = "";

       $docIdInt = intval($docId);
       if($docIdInt > 0 && $dbh){
           $stmt = $dbh->prepare("SELECT IFNULL(`g`.`Description`, '') AS 'docTitle', `Doc`, `Abstract` FROM `document` `d` LEFT JOIN `gen_lookups` `g` ON `d`.`idDocument` = `g`.`Substitute` WHERE `idDocument` = :docId");
           $stmt->execute([':docId' => $docIdInt]);
           $docRow = $stmt->fetch(\PDO::FETCH_ASSOC);

           $this->template = (isset($docRow['Doc']) ? $docRow['Doc']: '');
           $this->docTitle = $docRow["docTitle"];

           try{
                if (isset($docRow['Abstract'])) {
                    $abstract = json_decode($docRow['Abstract'], true);
                    $this->subjectLine = (isset($abstract["subjectLine"]) ? $abstract["subjectLine"] : "");
                }
           }catch(\Exception $e){
               $this->subjectLine = "";
           }
       }
   }

    public function createForm($replacements) {

        $this->replacedTemplate = $this->template;
        $vars = $this->getVariables();

        foreach ($vars as $v) {

            if (isset($replacements[$v])) {
                $this->setValue($v, $replacements[$v]);
            }
        }

        return str_replace('  ', ' ', $this->replacedTemplate);
    }

    public function getSubjectLine(){
        return $this->subjectLine;
    }

    public function getDocTitle(){
        return $this->docTitle;
    }

    protected function setValue($search, $replace) {

        $this->replacedTemplate = str_replace(self::ensureMacroCompleted($search), $replace, $this->replacedTemplate);

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
