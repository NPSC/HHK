<?php

namespace HHK\House\TemplateForm;
use HHK\House\RegistrationForm\CustomRegisterForm;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLSelector;
use HHK\HTMLControls\HTMLTable;
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\SysConst\ReservationStatus;


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
    public $template;
    public $replacedTemplate;
    public $subjectLine;

   /**
    * @param \PDO $dbh
    * @param integer $docId
    */
   function __construct(\PDO $dbh, $docId){

        $this->template = "";
        $this->subjectLine = "";

       if(intval($docId) > 0 && $dbh){
           $stmt = $dbh->query("Select `Doc`,`Abstract` from `document` where `idDocument` = $docId");
           $docRow = $stmt->fetch(\PDO::FETCH_ASSOC);

           $this->template = (isset($docRow['Doc']) ? $docRow['Doc']: '');

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

    public static function getEditMkup(\PDO $dbh, $formType){
        $rarry = readGenLookupsPDO($dbh, 'Form_Upload');
        $uS = Session::getInstance();
        $labels = Labels::getLabels();

        // get available doc replacements
        $replacementStmt = $dbh->query("SELECT `idTemplate_tag`, `Tag_Title`, `Tag_Name` FROM `template_tag` WHERE `Doc_Name` = '$formType'");
        $replacementRows = $replacementStmt->fetchAll();
        $rTbl = new HTMLTable();

        $rTbl->addHeaderTr(HTMLTable::makeTh('Name') . HTMLTable::makeTh('Code'));

        foreach ($replacementRows as $row) {
            $rTbl->addBodyTr(HTMLTable::makeTd($row[1]) . HTMLTable::makeTd($row[2]));
        }

        // Look for a match
        foreach ($rarry as $f) {

            if ($formType === $f['Code']) {
                $formDef = $f['Substitute'];
                $formTitle = $f['Description'];
                break;
            }
        }

        if (empty($formDef)) {

            $formDef = "FormDef-" . incCounter($dbh, 'codes');
            $dbh->exec("UPDATE `gen_lookups` SET `Substitute` = '$formDef' WHERE `Table_Name` = 'Form_Upload' AND `Code` = '$formType'");
        }

        $formstmt = $dbh->query("Select g.`Code`, g.`Description`, d.`Doc`, d.idDocument, ifnull(d.Abstract, '') as `Abstract` from `document` d join gen_lookups g on d.idDocument = g.`Substitute` where g.`Table_Name` = '$formDef' order by g.Order asc");
        $docRows = $formstmt->fetchAll();

        $li = '';
        $tabContent = '';

        //set help text
        $help = '';

        $editMkup = '';

        foreach ($docRows as $r) {

            if ($formType == 'ra' && $uS->RegForm == "3") {
                $regSettings = [];
                if (!empty($r['Abstract']) && @json_decode($r['Abstract'], true)) {
                    $regSettings = json_decode($r['Abstract'], true);
                }

                $regForm = new CustomRegisterForm($r['Code'], $regSettings);
                $editMkup = $regForm->getEditMkup();
            }

            //subject line
            $subjectLine = "";
            try {
                $abstract = json_decode($r["Abstract"], true);
                if (isset($abstract['subjectLine'])) {
                    $subjectLine = $abstract['subjectLine'];
                }
            } catch (\Exception $e) {

            }

            $li .= HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', $r['Description'],
                [
                    'href' => '#' . $r['Code'],
                    'id' => "docTab-" . $r['Code']
                ]
            ), ['class' => 'hhk-sortable', 'data-code' => $r['Code']]);

            if ($r['Doc'] && $formType == 'ra' && $uS->RegForm == "3") {
                $content = HTMLContainer::generateMarkup(
                    'iframe',
                    '',
                    [
                        'id' => 'form' . $r['idDocument'],
                        'class' => 'p-3 mr-3 user-agent-spacing ui-widget-content ui-corner-all',
                        'style' => 'width: 844px;'
                    ]
                );
            }else if($r['Doc']){
                $content = HTMLContainer::generateMarkup(
                    'div',
                    $r['Doc'],
                    [
                        'id' => 'form' . $r['idDocument'],
                        'class' => 'p-3 mr-3 user-agent-spacing ui-widget-content ui-corner-all loading',
                        'style' => 'width: 844px;'
                    ]
                );
            }

            $tabContent .= HTMLContainer::generateMarkup('div', $help . HTMLContainer::generateMarkup("div", "<h3>Current Form</h3><span class='ml-2 p-1 ui-corner-all ui-state-highlight' style='display: none;'>Unsaved Changes</span>" . ($formType == 'ra' && $uS->RegForm == "3" ? self::getRegFormPreviewResvSelector($dbh) : ""), ["class"=>"regTabDivTitle hhk-flex mb-2", 'style'=>'align-items: center;']) . HTMLContainer::generateMarkup('div', $content .
                '<div><div class="d-inline-block p-3 uploadFormDiv ui-widget-content ui-corner-all"><form enctype="multipart/form-data" action="ResourceBuilder.php" method="POST" style="padding: 5px 7px;">
    <input type="hidden" name="docId" value="' . $r['idDocument'] . '"/>' .

                ($editMkup != '' ? $editMkup : '') .

                ($formType == 'c' || $formType == 's' ? '<div class="form-group mb-3"><label for="emailSubjectLine">Email Subject Line: </label><input type="text" name="emailSubjectLine" placeholder="Email Subject Line" value="' . $subjectLine . '" size="35"></div>' : '') .
                '<input type="hidden" name="filefrmtype" value="' . $formType . '"/>' .
                '<input type="hidden" name="docAction">' .
                '<input type="hidden" name="formDef" value="' . $formDef . '">' .
                '<input type="hidden" name="docCode" value="' . $r["Code"] . '">' .
                '<div class="form-group mb-3"><label for="formfile">Upload new HTML file: </label><input name="formfile" type="file" accept="text/html" /></div>' .
                '<div class="form-group mb-3"><small>File must have UTF-8 or Windows-1252 caracter encoding. <br>Other character sets may produce unexpected behavior</small></div>' .
                '<div class="hhk-flex" style="justify-content: space-evenly">' .
                '<button type="submit" id="docDelFm"><span class="ui-icon ui-icon-trash"></span>Delete</button>' .
                ($formType == 'ra' && $uS->RegForm == "3" ? '<button type="submit" id="docPreviewFm">Preview</button>' : '') .
                '<button type="submit" id="docSaveFm"><span class="ui-icon ui-icon-disk"></span>Save</button>' .
                '</div>' .
                '</form></div></div>',
                ['class'=>'hhk-flex', 'style'=>'align-items: stretch']),
                [
                    'id' => $r['Code']
                ]);
        }

        if (count($replacementRows) > 0) {

            // add replacements tab
            $li .= HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', 'Replacement Codes',
                    [
                        'href' => '#replacements'
                    ]
            ),
                [
                    'id' => 'liReplacements',
                    'style' => 'float: right;'
                ]
            );

            $tabContent .= HTMLContainer::generateMarkup('div', '<div class="mb-3">You may use the following codes in your document to personalize the document to each ' . $labels->getString('MemberType', 'guest', 'Guest') . '</div>' . $rTbl->generateMarkup(), array(
                'id' => 'replacements'
            )
            );
        }

        // Make the final tab control
        $ul = HTMLContainer::generateMarkup('ul', $li, []);
        $output = HTMLContainer::generateMarkup('div', $ul . $tabContent,
            [
                'id' => 'regTabDiv',
                'data-formDef' => $formDef
            ]
        );

        $dataArray['type'] = $formType;
        $dataArray['title'] = $formTitle;
        $dataArray['mkup'] = $output;

        return $dataArray;
    }

    public static function getRegFormPreviewResvSelector(\PDO $dbh){

        $options = [];
        $stmt = $dbh->query('select r.idReservation, r.`Guest Name`, l.Title as "statusName" from vreservation_events r join lookups l on r.Status = l.Code and l.Category = "ReservStatus" where `Status` in ("' . ReservationStatus::Committed . '", "' . ReservationStatus::UnCommitted . '", "' . ReservationStatus::Waitlist . '") ORDER BY `Expected_Arrival` desc;');

        $stmt->execute();

        if($stmt->rowCount() > 0){
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach($rows as $row){
                $options[] = [$row['idReservation'], $row["statusName"] . ": " . $row["Guest Name"]];
            }
        }

        return HTMLContainer::generateMarkup("label", "Select Reservation to preview:", ['class'=>'ml-3 mr-1']) . HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($options, ''), array('class' => 'selResvId'));
    }
}
