<?php

namespace HHK\Member;

use HHK\Checklist;
use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLSelector, HTMLTable};
use HHK\Member\Relation\{Children, Company, Parents, Partner};
use HHK\SysConst\{GLTableNames, MemBasis, MemDesignation, MemStatus, RelLinkType};
use HHK\SysConst\ChecklistType;
use HHK\Tables\EditRS;
use HHK\Tables\Name\{Name_InsuranceRS, Name_LanguageRS};
use HHK\sec\Session;
use HHK\AuditLog\NameLog;
use HHK\Exception\RuntimeException;
use HHK\Exception\InvalidArgumentException;
use HHK\Member\Relation\Siblings;
use HHK\Member\Relation\Relatives;
use HHK\sec\Labels;
use HHK\CrmExport\AbstractExportManager;

/**
 * IndivMember.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * IndivMember
 * @author Eric
 */
class IndivMember extends AbstractMember {

    /**
     *
     * @var array
     */
    protected $languageRSs = array();

    /**
     *
     * @var array
     */
    protected $insuranceRSs = array();


    /**
     * Summary of getDefaultMemBasis
     * @return string
     */
    protected function getDefaultMemBasis() {
        return MemBasis::Indivual;
    }

    /**
     *
     * @return string
     */
    public function getMemberDesignation(){
        return MemDesignation::Individual;
    }

    /**
     * Replaces first name with nickname if present.
     *
     * @return string
     */
    public function getMemberName() {
        return ($this->get_nickName() != '' ? $this->get_nickName() : $this->get_firstName()) . " " . $this->get_lastName();
    }

    /**
     * Get the first and last name.
     *
     * @return string
     */
    public function getMemberFrmlName() {
        return $this->get_firstName() . " " . $this->get_lastName();
    }

    /**
     * Summary of getMemberFullName
     * @return string
     */
    public function getMemberFullName() {
        return $this->get_fullName();
    }

    /**
     *
     * @param \PDO $dbh
     * @param string $statusClass HTML Class attribute for status control
     * @param string $basisClass HTML class attribute for basis control
     * @param string $idPrefix
     * @return string HTML table markup
     */
    public function createMarkupTable() {

        $uS = Session::getInstance();
        $idPrefix = $this->getIdPrefix();

        $memPhotoMarkup = "";
        if($uS->ShowGuestPhoto){
            $memPhotoMarkup = HTMLContainer::generateMarkup("div", showGuestPicture($this->get_idName(), $uS->MemberImageSizePx), array("class"=>"mr-2"));
        }

        $table = new HTMLTable();
        $table->addHeaderTr(
                HTMLContainer::generateMarkup('th', 'Id')
                . HTMLContainer::generateMarkup('th', Labels::getString("MemberType", "namePrefix", "Prefix"))
                . HTMLContainer::generateMarkup('th', 'First Name')
                . HTMLContainer::generateMarkup('th', 'Middle')
                . HTMLContainer::generateMarkup('th', 'Last Name')
                . HTMLContainer::generateMarkup('th', 'Suffix')
                . HTMLContainer::generateMarkup('th', Labels::getString("MemberType", "nickname", "Nickname"))
                . HTMLContainer::generateMarkup('th', 'Status')
                . HTMLContainer::generateMarkup('th', 'Basis')
                );

        // Id
        $tr = HTMLContainer::generateMarkup('td',
                HTMLInput::generateMarkup(($this->nameRS->idName->getStoredVal() == 0 ? '' : $this->nameRS->idName->getStoredVal())
                        , array('name'=>$idPrefix.'idName', 'readonly'=>'readonly', 'size'=>'7', 'style'=>'border:none;background-color:transparent;'))
                );

        // Prefix
        $tr .= HTMLContainer::generateMarkup('td', HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($uS->nameLookups[GLTableNames::NamePrefix],
                        $this->nameRS->Name_Prefix->getstoredVal(), TRUE), array('name'=>$idPrefix.'selPrefix')));

        // First Name
        $tr .= HTMLContainer::generateMarkup('td',
                HTMLInput::generateMarkup($this->nameRS->Name_First->getstoredVal(), array('name'=>$idPrefix.'txtFirstName', 'data-prefix'=>$idPrefix, 'class'=>'hhk-firstname')));

        // Middle Name
        $tr .= HTMLContainer::generateMarkup('td', HTMLInput::generateMarkup($this->nameRS->Name_Middle->getstoredVal(), array('name'=>$idPrefix.'txtMiddleName', 'data-prefix'=>$idPrefix,  'size'=>'5')));

        // Last Name
        $tr .= HTMLContainer::generateMarkup('td', HTMLInput::generateMarkup($this->nameRS->Name_Last->getstoredVal(), array('name'=>$idPrefix.'txtLastName', 'data-prefix'=>$idPrefix,  'class'=>'hhk-lastname')));

        // Suffix
        $tr .= HTMLContainer::generateMarkup('td', HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($uS->nameLookups[GLTableNames::NameSuffix],
                        $this->nameRS->Name_Suffix->getstoredVal(), TRUE), array('name'=>$idPrefix.'selSuffix')));

        // Nick Name
        $tr .= HTMLContainer::generateMarkup('td', HTMLInput::generateMarkup($this->nameRS->Name_Nickname->getstoredVal(), array('name'=>$idPrefix.'txtNickname', 'data-prefix'=>$idPrefix,  'size'=>'10')));

        // Status
        $tr .= HTMLContainer::generateMarkup('td', HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup(removeOptionGroups($uS->nameLookups[GLTableNames::MemberStatus]),
                        $this->nameRS->Member_Status->getstoredVal(), FALSE), array('name'=>$idPrefix.'selStatus')));

        // Basis
        $basis = array();
        foreach ($uS->nameLookups[GLTableNames::MemberBasis] as $b) {
            if ($b[AbstractMember::SUBT] == $this->getMemberDesignation()) {
                $basis[$b[AbstractMember::CODE]] = $b;
            }
        }
        $tr .= HTMLContainer::generateMarkup(
                'td',
                HTMLSelector::generateMarkup(
                        HTMLSelector::doOptionsMkup(
                                removeOptionGroups($basis),
                                $this->nameRS->Member_Type->getstoredVal(), FALSE), array('name'=>$idPrefix.'selMbrType')
                        )
                );

        $table->addBodyTr($tr);
        return $memPhotoMarkup . $table->generateMarkup();
    }


    /**
     *
     * @param \PDO $dbh
     * @param string $inputClass HTML class attribute for each control
     * @param bool $showOrientDate
     * @return string HTML UL with following DIV tab panels
     */
    public function createMiscTabsMarkup(\PDO $dbh) {

        $panels = "";
        $tabs = "";
        $attrs = array('id'=>'adminTab', 'class'=>'ui-tabs-hide');

        $panels .= HTMLContainer::generateMarkup(
                'div',
                $this->createAdminPanel(),
                $attrs);

        $excl = $this->createExcludesPanel($dbh);
        $attrs['id'] = 'excludesTab';
        $panels .= HTMLContainer::generateMarkup(
                'div',
                $excl['markup'],
                $attrs);

        $attrs['id'] = 'miscTab';
        $panels .= HTMLContainer::generateMarkup(
                'div',
                $this->createDemographicsPanel($dbh),
                $attrs);

        $tabs .= HTMLContainer::generateMarkup('li',
                HTMLContainer::generateMarkup('a', 'Admin', array('href'=>'#adminTab', 'title'=>'Administrative Details'))
                );

        $tabs .= HTMLContainer::generateMarkup('li',
                HTMLContainer::generateMarkup('a', $excl['tabIcon'] . 'Exclude', array('href'=>'#excludesTab', 'title'=>'Exclude Addresses'))
                );

        $tabs .= HTMLContainer::generateMarkup('li',
                HTMLContainer::generateMarkup('a', 'Demographics', array('href'=>'#miscTab', 'title'=>'Miscellaneous demographics'))
                );

                // wrap tabs in a UL
        $ul = HTMLContainer::generateMarkup('ul', $tabs);

        return $ul . $panels;

    }

    /**
     * Summary of createAdminPanel
     * @return string
     */
    public function createAdminPanel() {

        $table = new HTMLTable();

        $table->addBodyTr(
                HTMLTable::makeTd('Last Updated:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $this->get_lastUpdated()), array('style'=>'display:table-cell;'))
                );

        $table->addBodyTr(
                HTMLTable::makeTd('Updated By:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $this->get_updatedBy()), array('style'=>'display:table-cell;'))
                );

        $table->addBodyTr(
                HTMLTable::makeTd('Date Added:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $this->get_dateAdded()), array('style'=>'display:table-cell;'))
                );

        $table->addBodyTr(
                HTMLTable::makeTd('Orientation:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd(HTMLInput::generateMarkup(($this->get_orientationDate() != "" ? date('M j, Y', strtotime($this->get_orientationDate())) : ""), array('name'=>'txtOrienDate', 'class'=>'ckdate')), array('style'=>'display:table-cell;'))
                );
        return $table->generateMarkup();
    }
    /**
     *
      * @return string HTML table structure
     */
    public function createDemographicsPanel(\PDO $dbh, $limited = FALSE, $includeBirthDate = TRUE, $demographicsUserData = [], $includeInsurance = false) {

        $uS = Session::getInstance();
        $idPrefix = $this->idPrefix;

        $demos = readGenLookupsPDO($dbh, 'Demographics', 'Order');

        $table = new HTMLTable();

        // Demographics
        foreach ($demos as $d) {

            if ($d[2] == 'y') {

                $table->addBodyTr(
                    HTMLTable::makeTd($d[1], array('class'=>'tdlabel'))
                    . HTMLTable::makeTd(
                        HTMLSelector::generateMarkup(
                                HTMLSelector::doOptionsMkup(removeOptionGroups($uS->nameLookups[$d[0]]),
                                    (isset($demographicsUserData[$d[0]]) && $demographicsUserData[$d[0]] != '' ? $demographicsUserData[$d[0]] : $this->getDemographicsEntry($d[0]))),
                        		array('name'=>$idPrefix.'sel_' . $d[0], 'class'=>$idPrefix.'hhk-demog-input', 'style'=>"min-width: max-content")
                                )
                        , array('style'=>'display:table-cell;')
                        )
                );
            }
        }

        if ($limited) {
            return $table->generateMarkup(array('style'=>'float:left;'));
        }

        // No Return
        $nreasons = readGenLookupsPDO($dbh, 'NoReturnReason', 'order');
        $table->addBodyTr(
            HTMLTable::makeTd('No Return', array('class'=>'tdlabel', 'title'=>'Flag for No Return'))
            . HTMLTable::makeTd(
                    HTMLSelector::generateMarkup(
                            HTMLSelector::doOptionsMkup($nreasons, $this->getNoReturnDemog(), TRUE)
                                ,array('name'=>$idPrefix.'selnoReturn', 'title'=>'Set No Return reason'))
                    , array('style'=>'display:table-cell;', 'title'=>'Set No Return reason')
                    )
            );

        // Second row
        $tbl2 = new HTMLTable();

        if ($includeBirthDate) {
            // BirthDate
            $tbl2->addBodyTr(
                HTMLTable::makeTd('Birth Date:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd(
                        HTMLInput::generateMarkup(($this->get_birthDate() == '' ? '' : date('M j, Y', strtotime($this->get_birthDate()))), ['name' => $idPrefix . 'txtBirthDate', 'class' => 'ckbdate'])
                , ['style' => 'display:table-cell;'])
            );

        }

        // Deceased checkbox and date
        $deAttr = array('type'=>'checkbox', 'name'=>$idPrefix.'cbdeceased', 'title'=>'Check if deceased.');
        $dateAttr = array('style'=>'display:none;', 'id'=>'disp_deceased');

        if ($this->get_status() == MemStatus::Deceased) {
            $deAttr['checked'] = 'checked';
            $dateAttr['style'] = 'display:table-cell;';
        }

        $tbl2->addBodyTr(
                HTMLTable::makeTd('Deceased: ' . HTMLInput::generateMarkup('', $deAttr)
                        . HTMLInput::generateMarkup('0', array('type'=>'hidden','name'=>'cbMarker_deceased'))
                        , array('class'=>'tdlabel', 'title'=>'Check if deceased.'))
                . HTMLTable::makeTd(HTMLContainer::generateMarkup('div',
                        'Date: ' . HTMLInput::generateMarkup(($this->get_DateDeceased() == '' ? '' : date('M j, Y', strtotime($this->get_DateDeceased()))), array('name'=>$idPrefix.'txtDeathDate', 'class'=>'ckbdate')), $dateAttr))
                );

        // Language
        if ($uS->LangChooser) {

            $langs = array();

            $stmt = $dbh->query("Select idLanguage, Title, ISO_639_1 from language where Display = 1");

            while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

                $langs[$r['idLanguage']] = array(0=>$r['idLanguage'], 1=>$r['Title'] . ' (' . $r['ISO_639_1'] . ')');

            }

            $choices = array();
            foreach ($this->languageRSs as $lRs) {
                $choices[$lRs->Language_Id->getStoredVal()] = $lRs->Language_Id->getStoredVal();
            }

            $tbl2->addBodyTr(
                HTMLTable::makeTd('Languages:', array('class'=>'tdlabel', 'title'=>'Choose languages'))
                . HTMLTable::makeTd(
                        HTMLSelector::generateMarkup(
                                HTMLSelector::doOptionsMkup($langs, $choices, FALSE),
                                array('name'=>$idPrefix.'selLanguage[]', 'class'=>'hhk-multisel', 'title'=>'Choose languages', 'multiple'=>'multiple', 'size'=>'2')
                                )
                        , array('style'=>'display:table-cell;')
                        )
                );

        }

        // Insurance
        $insuranceMarkup = "";
        if ($uS->InsuranceChooser && $includeInsurance) {
            $insuranceMarkup = $this->createInsurancePanel($dbh, $idPrefix);
        }

        //Previous Name
        $tbl2->addBodyTr(
        HTMLTable::makeTd('Previous Name:', array('class'=>'tdlabel'))
        . HTMLTable::makeTd(
                HTMLInput::generateMarkup(
                        $this->nameRS->Name_Previous,
                        array('name'=>$idPrefix.'txtPreviousName', 'style'=>'width: 122px')
                        )
                , array('style'=>'display:table-cell;')
                )
        );

        //Background Check Date
        $bcAttr = array('type'=>'checkbox', 'name'=>$idPrefix.'cbbackgroundcheck', 'title'=>'Check if background check completed.');
        $bcdateAttr = array('style'=>'display:none;', 'id'=>'disp_backgroundcheck');

        if ($this->get_DateBackgroundCheck() != NULL) {
            $bcAttr['checked'] = 'checked';
            $bcdateAttr['style'] = 'display:table-cell;';
        }

        $tbl2->addBodyTr(
            HTMLTable::makeTd('Background Check: ' . HTMLInput::generateMarkup('', $bcAttr)
                . HTMLInput::generateMarkup('0', array('type'=>'hidden','name'=>'cbMarker_background_check'))
                , array('class'=>'tdlabel', 'title'=>'Check if background check completed.'))
            . HTMLTable::makeTd(HTMLContainer::generateMarkup('div',
                'Date: ' . HTMLInput::generateMarkup(($this->get_DateBackgroundCheck() == '' ? '' : date('M j, Y', strtotime($this->get_DateBackgroundCheck()))), array('name'=>$idPrefix.'txtBackgroundCheckDate', 'class'=>'ckbdate')), $bcdateAttr))
            );

        //CRM External Id
        if ($uS->ContactManager != '') {

            $CmsManager = AbstractExportManager::factory($dbh, $uS->ContactManager);

            $tbl2->addBodyTr(
                HTMLTable::makeTd($CmsManager->getServiceTitle() . ' Id:', array('class' => 'tdlabel'))
                . HTMLTable::makeTd(
                    HTMLInput::generateMarkup(
                        $this->nameRS->External_Id,
                        array('name' => $idPrefix . 'txtExternalId', 'style' => 'width: 222px')
                    )
                    ,
                    array('style' => 'display:table-cell;')
                )
            );
         }

        return HTMLContainer::generateMarkup("div", $table->generateMarkup(array('style'=>'margin-right:10px;')) . $tbl2->generateMarkup(array('style'=>'margin-right:10px;')) . $insuranceMarkup, array("class"=>"hhk-flex hhk-flex-wrap"));

    }

    /**
     * Summary of createInsurancePanel
     * @param \PDO $dbh
     * @param mixed $idPrefix
     * @return string
     */
    public function createInsurancePanel(\PDO $dbh, $idPrefix) {

        $uS = Session::getInstance();

        if (!$uS->InsuranceChooser) {
            return '';
        }

        $this->getInsurance($dbh, $this->get_idName());

        // Insurance Companies
        $stmt = $dbh->query("select * from insurance where `Status` = 'a' order by `idInsuranceType`, `Title`");
        $ins = array();

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $ins[$r['idInsuranceType']][$r['idInsurance']] = array(0=>$r['idInsurance'], 1=>$r['Title']);
        }

        // Insurance Types
        $stmt2 = $dbh->query("SELECT
    idInsurance_type,
    Title,
    CASE
        WHEN Is_Primary = 1 THEN '1'
        ELSE '0'
    END AS `Is_Primary`,
    List_Order
FROM
    `insurance_type`
WHERE
    `Status` = 'a'
ORDER BY `List_Order`");
        $insTypes = array();


        while ($r = $stmt2->fetch(\PDO::FETCH_ASSOC)) {
            $insTypes[$r['idInsurance_type']] = $r;

        }

        $sumTbl = new HTMLTable();
        $sumTbl->addHeaderTr(
            $sumTbl->makeTh("Insurance", array('colspan'=>"2"))
        );
        $tabs = HTMLContainer::generateMarkup('li',
            HTMLContainer::generateMarkup('a', 'Summary', array('href'=>"#sumInsTab", 'title'=>"Show Insurance summary")));


        $divs = "";

        foreach ($insTypes as $i) {

            if (isset($ins[$i['idInsurance_type']]) === FALSE) {
                continue;
            }

            $tabs .= HTMLContainer::generateMarkup('li',
                HTMLContainer::generateMarkup('a', $i["Title"], array('href'=>"#". $i["idInsurance_type"] . "InsTab", 'title'=>"Edit " . $i["Title"] . " Insurance")));

            $tbl = new HTMLTable();
            $chosen = new Name_InsuranceRS();
            $chosenTitle = "";
            foreach ($this->insuranceRSs as $lRs) {
                if (isset($ins[$i['idInsurance_type']][$lRs->Insurance_Id->getStoredVal()])) {
                    $choices[$lRs->Insurance_Id->getStoredVal()] = $lRs->Insurance_Id->getStoredVal();
                    $chosen = $lRs;
                    $chosenTitle = $ins[$i['idInsurance_type']][$lRs->Insurance_Id->getStoredVal()][1];
                }
            }

            $sumTbl->addBodyTr(
                $sumTbl->makeTd($i["Title"], array('class'=>"tdlabel"))
                .$sumTbl->makeTd($chosenTitle, array('style'=>"width:100%"))
            );

            $tbl->addBodyTr(
                $tbl->makeTd("Insurance", array('class'=>"tdlabel"))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($ins[$i['idInsurance_type']], array($chosen->Insurance_Id->getStoredVal()), true),array('name'=>$idPrefix.'Insurance[' . $i['idInsurance_type'] . '][insuranceId]')))
            );

            $tbl->addBodyTr(
                $tbl->maketd("Group Number", array('class'=>"tdlabel"))
                .HTMLTable::makeTd(HTMLInput::generateMarkup($chosen->Group_Num->getStoredVal(), array('style'=>'width:100%;', 'name'=>$idPrefix."Insurance[".$i['idInsurance_type']."][groupNum]")))
            );

            $tbl->addBodyTr(
                $tbl->makeTd("Member Number", array('class'=>"tdlabel"))
                .HTMLTable::makeTd(HTMLInput::generateMarkup($chosen->Member_Num->getStoredVal(), array('style'=>'width:100%;', 'name'=>$idPrefix."Insurance[".$i['idInsurance_type']."][memNum]")))
            );

            $divs .= HTMLContainer::generateMarkup('div', $tbl->generateMarkup(array('style'=>'width:100%;')), array('id'=>$i["idInsurance_type"] .'InsTab', 'class'=>'ui-tabs-hide'));

            // Chosen Insurnaces...
            $choices = array();
            foreach ($this->insuranceRSs as $lRs) {
                if (isset($ins[$i['idInsurance_type']][$lRs->Insurance_Id->getStoredVal()])) {
                    $choices[$lRs->Insurance_Id->getStoredVal()] = $lRs->Insurance_Id->getStoredVal();
                }
            }
        }

        $ul = HTMLContainer::generateMarkup('ul',$tabs, array('style'=>'font-size:0.9em','class'=>"hhk-flex"));
        $divs = HTMLContainer::generateMarkup('div', $sumTbl->generateMarkup(array('style'=>'width:100%;')), array('id'=>'sumInsTab', 'class'=>'ui-tabs-hide')) . $divs;

        return HTMLContainer::generateMarkup('div', $ul . $divs, array('id'=>'InsTabs'));
    }

    /**
     * Summary of createInsuranceSummaryPanel
     * @param \PDO $dbh
     * @return string
     */
    public function createInsuranceSummaryPanel(\PDO $dbh) {

        $uS = Session::getInstance();

        if (!$uS->InsuranceChooser) {
            return '';
        }

        $this->getInsurance($dbh, $this->get_idName());

        // Insurance Companies
        $stmt = $dbh->query("select * from insurance where `Status` = 'a' order by `idInsuranceType`, `Title`");
        $ins = array();

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $ins[$r['idInsuranceType']][$r['idInsurance']] = array(0=>$r['idInsurance'], 1=>$r['Title']);
        }

        // Insurance Types
        $stmt2 = $dbh->query("SELECT
    idInsurance_type,
    Title,
    CASE
        WHEN Is_Primary = 1 THEN '1'
        ELSE '0'
    END AS `Is_Primary`,
    List_Order
FROM
    `insurance_type`
WHERE
    `Status` = 'a'
ORDER BY `List_Order`");
        $insTypes = array();


        while ($r = $stmt2->fetch(\PDO::FETCH_ASSOC)) {
            $insTypes[$r['idInsurance_type']] = $r;

        }

        $sumTbl = new HTMLTable();
        $sumTbl->addHeaderTr(
            $sumTbl->makeTh("Insurance for " . $this->get_fullName(), array('colspan'=>"4"))
        );

        $sumTbl->addHeaderTr(
           $sumTbl->makeTh("Type")
           .$sumTbl->makeTh("Name")
           .$sumTbl->makeTh("Group Number")
           .$sumTbl->makeTh("Member Number")
        );

        foreach ($insTypes as $i) {

            if (isset($ins[$i['idInsurance_type']]) === FALSE) {
                continue;
            }


            $chosen = new Name_InsuranceRS();
            $chosenTitle = "";
            foreach ($this->insuranceRSs as $lRs) {
                if (isset($ins[$i['idInsurance_type']][$lRs->Insurance_Id->getStoredVal()])) {
                    $chosen = $lRs;
                    $chosenTitle = $ins[$i['idInsurance_type']][$lRs->Insurance_Id->getStoredVal()][1];
                }
            }

            $sumTbl->addBodyTr(
                $sumTbl->makeTd($i["Title"], array('class'=>"tdlabel"))
                .$sumTbl->makeTd($chosenTitle)
                .$sumTbl->makeTd($chosen->Group_Num->getStoredVal())
                .$sumTbl->makeTd($chosen->Member_Num->getStoredVal())
            );
        }

        return HTMLContainer::generateMarkup('div', $sumTbl->generateMarkup(array('style'=>"width: 100%;")), array("class"=>"ui-widget ui-widget-content ui-corner-all hhk-tdbox hhk-visitdialog", "style"=>"width: 500px; font-size: 0.9em; padding: 0.25em;", "id"=>"insDetailDiv"));

    }

    /**
     *
     * @param array $rel Array of relationship types
     * @param string $page Link to page for related members
     * @return string HTML markup
     */
    public function createRelationsTabs(array $rel, $page = "NameEdit.php") {

        $relTab = $this->createOrgMarkup($rel, $page)
                 .HTMLContainer::generateMarkup('div', $rel[RelLinkType::Spouse]->createMarkup($page), array('style'=>'float:left; margin-left:20px;'))
                .HTMLContainer::generateMarkup('div',$rel[RelLinkType::Sibling]->createMarkup($page), array('style'=>'float:left; margin-left:20px;'))
                . HTMLContainer::generateMarkup('div',$rel[RelLinkType::Parnt]->createMarkup($page), array('style'=>'float:left; margin-left:20px;'))
                . HTMLContainer::generateMarkup('div',$rel[RelLinkType::Child]->createMarkup($page), array('style'=>'float:left; margin-left:20px;'))
                . HTMLContainer::generateMarkup('div',$rel[RelLinkType::Relative]->createMarkup($page), array('style'=>'float:left; margin-left:20px;'));

        return $relTab;
    }

    /**
     *
     * @param array $rel Array of relationship types
     * @param string $page link
     * @return string HTML markup
     */
    public function createOrgMarkup(array $rel, $page = "NameEdit.php") {

        $table = new HTMLTable();
        $table->addHeaderTr(
                HTMLTable::makeTh('Title')
        );

        $table->addBodyTr(
                 // title
                HTMLTable::makeTd(HTMLInput::generateMarkup($this->get_title(), array('name' => 'txtTitle', 'size' => '10'))
                )
        );

        $coTab = $table->generateMarkup(array('style'=>'float:left;')) . HTMLContainer::generateMarkup('div', $rel[RelLinkType::Company]->createMarkup($page), array('style'=>'float:left; margin-left:20px;'));

        return $coTab;
    }


    /**
     * Summary of loadRealtionships
     * @param \PDO $dbh
     * @return array
     */
    public function loadRealtionships(\PDO $dbh) {

       return array(
            RelLinkType::Sibling => new Siblings($dbh, $this->get_idName()),
            RelLinkType::Child => new Children($dbh, $this->get_idName()),
            RelLinkType::Parnt => new Parents($dbh, $this->get_idName()),
            RelLinkType::Spouse => new Partner($dbh, $this->get_idName()),
            RelLinkType::Company => new Company($dbh, $this->get_idName()),
            RelLinkType::Relative => new Relatives($dbh, $this->get_idName())
            );
    }

    /**
     * Summary of getAssocDonorLabel
     * @return string
     */
    public function getAssocDonorLabel() {
        return "Associate";
    }

    /**
     * Summary of getAssocDonorList
     * @param array $rel
     * @return array
     */
    public function getAssocDonorList(array $rel) {
        $rA = array();
        $partner = $rel[RelLinkType::Spouse];

        if (count($partner->getRelNames()) > 0) {
            $rNames = $partner->getRelNames();
            $rA[$rNames[0]['Id']] = array(0=>$rNames[0]['Id'], 1=>'Spouse');
        }
        return $rA;
    }

    /**
     * Summary of getDefaultDonor
     * @param array $rel
     * @return mixed
     */
    public function getDefaultDonor(array $rel) {

        $partner = $rel[RelLinkType::Spouse];

        if (count($partner->getRelNames()) > 0) {
            $rNames = $partner->getRelNames();
            return $rNames[0]['Id'];
        }
        return '';

    }

    /**
     *
     * @param \PDO $dbh
     * @param array $post
     * @throws RuntimeException
     */
    protected function processMember(\PDO $dbh, array $post) {

        $uS = Session::getInstance();
        // Convenience var
        $n = $this->nameRS;
        $idPrefix = $this->getIdPrefix();

        //  Name
        $last = trim($n->Name_Last->getStoredVal());
        if (isset($post[$idPrefix.'txtLastName'])) {
            $last = ucfirst(trim(filter_var($post[$idPrefix.'txtLastName'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)));
            $n->Name_Last->setNewVal($last);
        }

        // Minimum requirements for saving a record.
        if ($last == '') {
            throw new RuntimeException("The Last Name cannot be blank.");
        }


        $first = $n->Name_First->getStoredVal();
        if (isset($post[$idPrefix.'txtFirstName'])) {
            $n->Name_First->setNewVal(ucfirst(trim(filter_var($post[$idPrefix.'txtFirstName'], FILTER_SANITIZE_FULL_SPECIAL_CHARS))));
            $first = $n->Name_First->getNewVal();
        }

        $middle = $n->Name_Middle->getStoredVal();
        if (isset($post[$idPrefix.'txtMiddleName'])) {
            $n->Name_Middle->setNewVal(ucfirst(trim(filter_var($post[$idPrefix.'txtMiddleName'], FILTER_SANITIZE_FULL_SPECIAL_CHARS))));
            $middle = $n->Name_Middle->getNewVal();
        }

        $prefix = '';
        // Check existing value
        if (isset($uS->nameLookups[GLTableNames::NamePrefix][$n->Name_Prefix->getStoredVal()])) {
            $prefix = $n->Name_Prefix->getStoredVal();
        } else {
            $n->Name_Prefix->setNewVal($prefix);
        }
        if (isset($post[$idPrefix.'selPrefix'])) {
            // Check new value
            $pre = filter_var($post[$idPrefix.'selPrefix'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            if (isset($uS->nameLookups[GLTableNames::NamePrefix][$pre])) {
                $prefix = $pre;
                $n->Name_Prefix->setNewVal($prefix);
            }else{
                $n->Name_Prefix->setNewVal('');
                $prefix = '';
            }
        }

        $suffix = '';
        // Check existing value
        if (isset($uS->nameLookups[GLTableNames::NameSuffix][$n->Name_Suffix->getStoredVal()])) {
            $suffix = $n->Name_Suffix->getStoredVal();
        } else {
            $n->Name_Suffix->setNewVal($suffix);
        }
        if (isset($post[$idPrefix.'selSuffix'])) {
            // Check new value
            $suf = filter_var($post[$idPrefix.'selSuffix'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            if (isset($uS->nameLookups[GLTableNames::NameSuffix][$suf])) {
                $suffix = $suf;
                $n->Name_Suffix->setNewVal($suffix);
            }else{
                $n->Name_Suffix->setNewVal('');
                $suffix = '';
            }
        }

        // Name Last-First
        $comma = '';
        if ($first != '') {
            $comma = ', ';
        }
        $n->Name_Last_First->setNewVal(trim($last . $comma . $first));


        // Name Full
        if (isset($uS->nameLookups[GLTableNames::NamePrefix][$prefix])) {
            $prefix = $uS->nameLookups[GLTableNames::NamePrefix][$prefix][AbstractMember::DESC];
        }

        if (isset($uS->nameLookups[GLTableNames::NameSuffix][$suffix])) {
            $suffix = $uS->nameLookups[GLTableNames::NameSuffix][$suffix][AbstractMember::DESC];
        }

        $nstring = '';

        if ($middle != '') {
            $nstring .= trim($prefix . ' ' . $first . ' ' . $middle . ' ' . $last . ' ' . $suffix);
        } else {
            $nstring .= trim($prefix . ' ' . $first . ' ' . $last . ' ' . $suffix);
        }

        $n->Name_Full->setNewVal($nstring);


        //  Title
        if (isset($post[$idPrefix.'txtTitle'])) {
            $n->Title->setNewVal(filter_var($post[$idPrefix.'txtTitle'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }

        //  Previous Name
        if (isset($post[$idPrefix.'txtPreviousName'])) {
            $n->Name_Previous->setNewVal(ucfirst(trim(filter_var($post[$idPrefix.'txtPreviousName'], FILTER_SANITIZE_FULL_SPECIAL_CHARS))));
        }

        //  Nickname
        if (isset($post[$idPrefix.'txtNickname'])) {
            $n->Name_Nickname->setNewVal(ucfirst(trim(filter_var($post[$idPrefix.'txtNickname'], FILTER_SANITIZE_FULL_SPECIAL_CHARS))));
        }


        //  Birth Month
        if (isset($post[$idPrefix.'selBirthMonth'])) {
            $n->Birth_Month->setNewVal(filter_var($post[$idPrefix.'selBirthMonth'], FILTER_SANITIZE_NUMBER_INT));
        }

        //  Birth Date
        if (isset($post[$idPrefix.'txtBirthDate'])) {
            $bd = filter_var($post[$idPrefix.'txtBirthDate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            if (strtolower($bd) == 'minor' && $uS->RegNoMinorSigLines) {
                // set demographic  #816, EKC, 5/23/2023
                $this->demogRS->Is_Minor->setNewVal(1);
                $n->BirthDate->setNewVal('');
            } else if ($bd != '') {
                $n->BirthDate->setNewVal(date('Y-m-d H:i:s', strtotime($bd)));
                $n->Birth_Month->setNewVal(date('m', strtotime($bd)));
                $this->demogRS->Is_Minor->setNewVal(0);
            } else {
                $n->BirthDate->setNewVal('');
                $this->demogRS->Is_Minor->setNewVal(0);
            }
        }


        $demos = readGenLookupsPDO($dbh, 'Demographics');

        foreach ($demos as $d) {

            if (isset($post[$idPrefix.'sel_' . $d[0]])) {

                $field = $this->getDemographicField($d[0]);

                if (is_null($field) === FALSE) {
                    $field->setNewVal(filter_var($post[$idPrefix.'sel_' . $d[0]], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
                }
            }
        }


        //  No Return
        if (isset($post[$idPrefix.'selnoReturn'])) {

            $reason = filter_var($post[$idPrefix.'selnoReturn'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            if (isset($uS->nameLookups['NoReturnReason'][$reason])) {
                $this->demogRS->No_Return->setNewVal($reason);
            } else {
                $this->demogRS->No_Return->setNewVal('');
            }

        }

        //CRM External Id
        if ($uS->ContactManager != '' && isset($post[$idPrefix . 'txtExternalId']) && $n->External_Id->getNewVal() != AbstractExportManager::EXCLUDE_TERM && $n->External_Id->getStoredVal() != AbstractExportManager::EXCLUDE_TERM) {
            $n->External_Id->setNewVal(filter_var($post[$idPrefix.'txtExternalId'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }

    }

    /**
     * Summary of getLanguages
     * @param \PDO $dbh
     * @param mixed $nid
     * @return void
     */
    protected function getLanguages(\PDO $dbh, $nid) {

        $nlangRs = new Name_LanguageRS();
        $nlangRs->idName->setStoredVal($nid);

        $rows = EditRS::select($dbh, $nlangRs, array($nlangRs->idName));

        foreach ($rows as $r) {
            $nlangRs = new Name_LanguageRS();
            EditRS::loadRow($r, $nlangRs);
            $this->languageRSs[$nlangRs->Language_Id->getStoredVal()] = $nlangRs;
        }
    }

    /**
     * Summary of getInsurance
     * @param \PDO $dbh
     * @param mixed $nid
     * @return void
     */
    protected function getInsurance(\PDO $dbh, $nid) {

        $nInsRs = new Name_InsuranceRS();
        $nInsRs->idName->setStoredVal($nid);

        $rows = EditRS::select($dbh, $nInsRs, array($nInsRs->idName));

        foreach ($rows as $r) {
            $nInsRs = new Name_InsuranceRS();
            EditRS::loadRow($r, $nInsRs);
            $this->insuranceRSs[$nInsRs->Insurance_Id->getStoredVal()] = $nInsRs;
        }
    }

    /**
     * Summary of saveLanguages
     * @param \PDO $dbh
     * @param mixed $post
     * @param mixed $idPrefix
     * @param string $username
     * @return void
     */
    protected function saveLanguages(\PDO $dbh, $post, $idPrefix, $username) {

        if ($this->get_idName() > 0) {

            $myLangs = array();
            $langs = array();
            $langs2 = array();

            if (isset($post[$idPrefix.'selLanguage'])) {
                $langs = filter_var_array($post[$idPrefix.'selLanguage'], FILTER_SANITIZE_NUMBER_INT);
                $langs2 = array_flip($langs);
            }


            // Remove any unset languages.
            foreach ($this->languageRSs as $langRs) {

                if (!isset($langs2[$langRs->Language_Id->getStoredVal()])) {
                    // remove recordset
                    $numRecords = EditRS::delete($dbh, $langRs, array($langRs->Language_Id, $langRs->idName));

                    if ($numRecords > 0) {
                        NameLog::writeDelete($dbh, $langRs, $langRs->idName, $username, $langRs->Language_Id->getStoredVal());
                    }
                } else {
                    $myLangs[] = $langRs;
                }
            }

            // set any new languages
            foreach ($langs as $v) {

                $idLang = intval($v, 10);

                if ($idLang < 1) {
                    continue;
                }

                $found = FALSE;

                foreach ($this->languageRSs as $lRs) {
                    if ($lRs->Language_Id->getStoredVal() == $idLang) {
                        $found = TRUE;
                    }
                }

                if (!$found) {
                    $langRs = new Name_LanguageRS();
                    $langRs->Language_Id->setNewVal($idLang);
                    $langRs->idName->setNewVal($this->get_idName());
                    $langRs->Updated_By->setNewVal($username);
                    EditRS::insert($dbh, $langRs);

                    $langRs->Language_Id->setStoredVal($idLang);
                    $myLangs[] = $langRs;
                    NameLog::writeInsert($dbh, $langRs, $this->get_idName(), $username, $idLang);
                }
            }

            $this->languageRSs = $myLangs;
        }
    }

    /**
     * Summary of saveInsurance
     * @param \PDO $dbh
     * @param mixed $post
     * @param mixed $idPrefix
     * @param mixed $username
     * @return void
     */
    protected function saveInsurance(\PDO $dbh, $post, $idPrefix, $username) {

        $uS = Session::getInstance();

        if (!$uS->InsuranceChooser) {
            return;
        }

        $myInss = array();

        // Insurance Types
        $stmt2 = $dbh->query("Select * from `insurance_type` order by `List_Order`");
        $insTypes = array();
        $primaryInsType = '';

        while ($r = $stmt2->fetch(\PDO::FETCH_ASSOC)) {
            $insTypes[$r['idInsurance_type']] = $r;

            if ($r['Is_Primary']) {
                $primaryInsType = $r['Title'];
            }
        }

        // Insurances
        $stmt3 = $dbh->query("select idInsurance, idInsuranceType, Title from insurance");
        $insCos = array();

        while ($c = $stmt3->fetch(\PDO::FETCH_ASSOC)) {
            $insCos[$c['idInsurance']] = $c;
        }

        // Make a primary selector if not present so I can delete them.
        if (isset($post[$idPrefix.'selIns'.$primaryInsType]) === FALSE) {
            $post[$idPrefix.'selIns'.$primaryInsType] = array();
        }

        foreach ($insTypes as $i) {

            if (isset($post[$idPrefix.'Insurance'][$i['idInsurance_type']]) && $this->get_idName() > 0) {

                $insId = filter_var($post[$idPrefix.'Insurance'][$i['idInsurance_type']]['insuranceId'], FILTER_SANITIZE_NUMBER_INT);
                $groupNum = '';
                if(isset($post[$idPrefix.'Insurance'][$i['idInsurance_type']]['groupNum'])){
                    $groupNum = filter_var($post[$idPrefix.'Insurance'][$i['idInsurance_type']]['groupNum'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                }
                $memNum = '';
                if(isset($post[$idPrefix.'Insurance'][$i['idInsurance_type']]['memNum'])){
                    $memNum = filter_var($post[$idPrefix.'Insurance'][$i['idInsurance_type']]['memNum'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                }
                $ins = ["id"=>$insId, "groupNum"=>$groupNum, "memNum"=>$memNum];
                $inss2[$insId] = $ins;

                // Remove any unset .
                foreach ($this->insuranceRSs as $insRs) {

                    if (!isset($inss2[$insRs->Insurance_Id->getStoredVal()])) {

                        if (isset($insCos[$insRs->Insurance_Id->getStoredVal()]['idInsuranceType']) && $insCos[$insRs->Insurance_Id->getStoredVal()]['idInsuranceType'] == $i['idInsurance_type']) {
                            // remove recordset
                            $numRecords = EditRS::delete($dbh, $insRs, array($insRs->Insurance_Id, $insRs->idName));

                            if ($numRecords > 0) {
                                NameLog::writeDelete($dbh, $insRs, $insRs->idName, $username, $i['Title'] . '-' . $insCos[$insRs->Insurance_Id->getStoredVal()]['Title']);
                            }
                        }

                    } else {
                        $myInss[] = $insRs;
                    }
                }
                $this->getInsurance($dbh, $this->get_idName());

                // set any new insurance
                foreach ($inss2 as $v) {


                    if (intval($v["id"]) < 1) {
                        continue;
                    }

                    $found = FALSE;
                    $insRs = new Name_InsuranceRS();

                    foreach ($this->insuranceRSs as $lRs) {
                        if ($lRs->Insurance_Id->getStoredVal() == $v["id"]) {
                            $found = TRUE;
                            $insRs = $lRs;
                        }
                    }

                    $insRs->Insurance_Id->setNewVal($v["id"]);
                    $insRs->Group_Num->setNewVal($v["groupNum"]);
                    $insRs->Member_Num->setNewVal($v["memNum"]);
                    $insRs->idName->setNewVal($this->get_idName());
                    $insRs->Updated_By->setNewVal($username);

                    if (!$found) {
                        EditRS::insert($dbh, $insRs);
                        $insRs->Insurance_Id->setStoredVal($v["id"]);
                        NameLog::writeInsert($dbh, $insRs, $this->get_idName(), $username, $i['Title'] . '-' . $insCos[$v["id"]]['Title']);
                    }else{
                        EditRS::update($dbh, $insRs, array($insRs->idName, $insRs->Insurance_Id));
                        $insRs->Insurance_Id->setStoredVal($v["id"]);
                        NameLog::writeUpdate($dbh, $insRs, $this->get_idName(), $username, $i['Title'] . '-' . $insCos[$v["id"]]['Title']);
                    }
                    $myInss[] = $insRs;
                }
            }
        }

        $this->insuranceRSs = array();
        $this->getInsurance($dbh, $this->get_idName());
    }
    /**
     *
     * @param mixed $v
     * @throws InvalidArgumentException
     */
    public function set_companyRcrd($v) {
        if ($v == 1 || $v == TRUE) {
            throw new InvalidArgumentException("Individual Member Record cannot be set to Organization.");
        }
    }

    /**
     * Summary of getNoReturnDemog
     * @return mixed
     */
    public function getNoReturnDemog() {
        return $this->demogRS->No_Return->getStoredVal();
    }

}
