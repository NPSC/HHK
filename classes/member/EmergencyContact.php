<?php
/**
 * EmergencyContact.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


interface iEmergencyContact {
    public function getEcNameFirst();
    public function getEcNameLast();
    public function getEcPhone();
    public function getEcAltPhone();
    public function getEcRelationship();
}
/**
 * Description of EmergencyContact
 *
 */
class EmergencyContact implements iEmergencyContact {

    protected $ecRS;

    public function __construct(PDO $dbh, $id) {

        $this->ecRS = $this->loadDbRecord($dbh, $id);

    }

    public static function loadDbRecord(PDO $dbh, $id) {

        $ecRS = new EmergContactRs();

        if ($id > 0) {
            $ecRS->idName->setStoredVal($id);
            $rows = EditRS::select($dbh, $ecRS, array($ecRS->idName));

            if (count($rows) > 0) {
                EditRS::loadRow($rows[0], $ecRS);
            }
        }

        return $ecRS;
    }

    public function save(PDO $dbh, $id, $pData, $uname, $idPrefix = '') {
        // Emergency Contact
        $rtnMsg = "";

        if ($id > 0) {

            // Set These values for any update or insert
            $this->ecRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
            $this->ecRS->Updated_By->setNewVal($uname);

            $this->extractMarkup($pData, $idPrefix);


            if ($this->ecRS->idEmergency_contact->getStoredVal() == 0) {
                // Insert
                $this->ecRS->idName->setNewVal($id);

                $n = EditRS::insert($dbh, $this->ecRS);
                if ($n > 0) {
                    $rtnMsg .= "Emergency Contact Inserted.  ";
                }

            } else {
                //update
                $n = EditRS::update($dbh, $this->ecRS, array($this->ecRS->idName));
                if ($n > 0) {
                    $rtnMsg .= "Emergency Contact Updated.  ";
                }
            }

            EditRS::updateStoredVals($this->ecRS);
        }
        return $rtnMsg;
    }

    protected function extractMarkup($pData, $idPrefix = "") {

        if (isset($pData[$idPrefix.'txtEmrgFirst'])) {
            $this->ecRS->Name_First->setNewVal(ucfirst(filter_var($pData[$idPrefix.'txtEmrgFirst'], FILTER_SANITIZE_STRING)));
        }
        if (isset($pData[$idPrefix.'txtEmrgLast'])) {
            $this->ecRS->Name_Last->setNewVal(ucfirst(filter_var($pData[$idPrefix.'txtEmrgLast'], FILTER_SANITIZE_STRING)));
        }
        if (isset($pData[$idPrefix.'txtEmrgPhn'])) {
            $this->ecRS->Phone_Home->setNewVal(filter_var($pData[$idPrefix.'txtEmrgPhn'], FILTER_SANITIZE_STRING));
        }
        if (isset($pData[$idPrefix.'txtEmrgAlt'])) {
            $this->ecRS->Phone_Alternate->setNewVal(filter_var($pData[$idPrefix.'txtEmrgAlt'], FILTER_SANITIZE_STRING));
        }
        if (isset($pData[$idPrefix.'selEmrgRel'])) {

            $val = filter_var($pData[$idPrefix.'selEmrgRel'], FILTER_SANITIZE_STRING);

            if ($val = RelLinkType::Self) {
                $val = '';
            }

            $this->ecRS->Relationship->setNewVal($val);
        }

    }

    public static function createMarkup(iEmergencyContact $emContact, $relOptions, $idPrefix = "", $checkLater = FALSE) {

        if (isset($relOptions[RelLinkType::Self])) {
            unset($relOptions[RelLinkType::Self]);
        }

        $markup = new HTMLTable();
        $markup->addBodyTr(HTMLTable::makeTd('First Name', array('class'=>'tdlabel')) . HTMLTable::makeTd(HTMLInput::generateMarkup($emContact->getEcNameFirst(), array('name'=>$idPrefix.'txtEmrgFirst', 'size'=>'14'))));
        $markup->addBodyTr(HTMLTable::makeTd('Last Name', array('class'=>'tdlabel')) . HTMLTable::makeTd(HTMLInput::generateMarkup($emContact->getEcNameLast(), array('name'=>$idPrefix.'txtEmrgLast', 'size'=>'14'))));
        $markup->addBodyTr(HTMLTable::makeTd('Phone', array('class'=>'tdlabel')) . HTMLTable::makeTd(HTMLInput::generateMarkup($emContact->getEcPhone(), array('name'=>$idPrefix.'txtEmrgPhn', 'class'=>'hhk-phoneInput', 'size'=>'14'))));
        $markup->addBodyTr(HTMLTable::makeTd('Alternate', array('class'=>'tdlabel')) . HTMLTable::makeTd(HTMLInput::generateMarkup($emContact->getEcAltPhone(), array('name'=>$idPrefix.'txtEmrgAlt', 'class'=>'hhk-phoneInput', 'size'=>'14'))));
        $markup->addBodyTr(HTMLTable::makeTd('Relationship to Guest', array('class'=>'tdlabel')) . HTMLTable::makeTd(
                HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($relOptions), $emContact->getEcRelationship()), array('name'=>$idPrefix."selEmrgRel"))));

        $attr = array('type'=>'checkbox', 'name'=>$idPrefix.'cbEmrgLater');
        if ($checkLater) {
            $attr['checked'] = 'checked';
        }
        $later = HTMLContainer::generateMarkup('div', HTMLInput::generateMarkup('', $attr) . HTMLContainer::generateMarkup('label', ' Skip for now', array('for'=>$idPrefix.'cbEmrgLater')), array('style'=>'margin-top:10px; margin-left:40px;'));
        return $markup->generateMarkup() . $later;
    }

    public function getEcNameFirst() {
        return $this->ecRS->Name_First->getStoredVal();
    }

    public function getEcNameLast() {
        return $this->ecRS->Name_Last->getStoredVal();
    }

    public function getEcPhone() {
        return $this->ecRS->Phone_Home->getStoredVal();
    }

    public function getEcAltPhone() {
        return $this->ecRS->Phone_Alternate->getStoredVal();
    }

    public function getEcRelationship() {
        return $this->ecRS->Relationship->getStoredVal();
    }

}
