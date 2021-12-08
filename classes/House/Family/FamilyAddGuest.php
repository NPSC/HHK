<?php

namespace HHK\House\Family;

use HHK\HTMLControls\{HTMLContainer, HTMLTable, HTMLInput};
use HHK\House\ReserveData\ReserveData;
use HHK\Member\RoleMember\AbstractRoleMember;
use HHK\sec\Labels;

/**
 * Description of FamilyAddGuest
 *
 * @author Eric
 */

class FamilyAddGuest extends Family {

    public function createFamilyMarkup(\PDO $dbh, ReserveData $rData, $patientUserData = array()) {

        $rowClass = 'odd';
        $mk1 = '';
        $trs = array();
        $familyName = '';

        $AdrCopyDownIcon = HTMLContainer::generateMarkup('ul'
            ,  HTMLContainer::generateMarkup('li',
                HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-arrowthick-1-s'))
                , array('class'=>'ui-state-default ui-corner-all', 'id'=>'adrCopy', 'style'=>'float:right;', 'title'=>'Copy top address down to any blank addresses.'))
            .HTMLContainer::generateMarkup('span', 'Addr', array('style'=>'float:right;margin-top:5px;margin-right:.4em;'))
            , array('class'=>'ui-widget ui-helper-clearfix hhk-ui-icons'));


        // Name Header
        $th = HTMLContainer::generateMarkup('tr',
            HTMLTable::makeTh('Staying')
            . HTMLTable::makeTh(Labels::getString('MemberType', 'primaryGuestAbrev', 'PG'), array('title'=>Labels::getString('MemberType', 'primaryGuest', 'Primary Guest')))
            . AbstractRoleMember::createThinMarkupHdr($rData->getPatLabel(), FALSE, $rData->getShowBirthDate())
            . HTMLTable::makeTh('Phone')
            . HTMLTable::makeTh($AdrCopyDownIcon));


        // Patient.
        if ($this->patientPrefix > 0) {

            $demoMu = '';
            $role = $this->roleObjs[$this->patientPrefix];
            $idPrefix = $role->getRoleMember()->getIdPrefix();

            $trs[0] = HTMLContainer::generateMarkup('tr',
                $role->createThinMarkup($rData->getPsgMember($idPrefix), TRUE)
                , array('id'=>$role->getIdName() . 'n', 'class'=>$rowClass));

            if ($this->patientAddr || ($this->patientAsGuest && $this->showGuestAddr)) {

                if ($this->IncldEmContact) {
                    // Emergency Contact
                    $demoMu = $this->getEmergencyConntactMu($dbh, $role);
                }

                if ($this->showDemographics) {
                    // Demographics
                    $demoMu = $this->getDemographicsMarkup($dbh, $role);
                }

                if ($this->showInsurance) {
                    // Demographics
                    $demoMu .= $this->getInsuranceMarkup($dbh, $role);
                }

                $trs[1] = HTMLContainer::generateMarkup('tr', HTMLTable::makeTd('') . HTMLTable::makeTd($role->createAddsBLock() . $demoMu, array('colspan'=>'11')), array('id'=>$role->getIdName() . 'a', 'class'=>$rowClass . ' hhk-addrRow'));
            }
        }

        $trsCounter = 2;

        // List each member
        foreach ($this->roleObjs as $role) {

            $idPrefix = $role->getRoleMember()->getIdPrefix();

            if ($rData->getPsgMember($idPrefix)->isPrimaryGuest()) {
                $familyName = $role->getRoleMember()->get_lastName();
            }

            // Skip the patient who was taken care of above
            if ($role->getIdName() > 0 && $role->getIdName() == $this->getPatientId()) {
                continue;
            }


            if ($rowClass == 'odd') {
                $rowClass = 'even';
            } else if ($rowClass == 'even') {
                $rowClass = 'odd';
            }

            // Remove guest button.
            $removeIcons = HTMLContainer::generateMarkup('ul'
                , HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-trash'))
                    , array('class'=>'ui-state-default ui-corner-all hhk-removeBtn', 'style'=>'float:right;', 'data-prefix'=>$idPrefix, 'title'=>'Remove guest'))
                , array('class'=>'ui-widget ui-helper-clearfix hhk-ui-icons'));


            $trs[$trsCounter++] = HTMLContainer::generateMarkup('tr',
                $role->createThinMarkup($rData->getPsgMember($idPrefix), ($rData->getIdPsg() == 0 ? FALSE : TRUE))
                . ($role->getIdName() == 0 ? HTMLTable::makeTd($removeIcons) : '')
                , array('id'=>$role->getIdName() . 'n', 'class'=>$rowClass));


            // Add addresses and demo's
            if ($this->showGuestAddr) {

                $demoMu = '';

                if ($this->IncldEmContact) {
                    // Emergency Contact
                    $demoMu .= $this->getEmergencyConntactMu($dbh, $role);
                }

                if ($this->showDemographics) {
                    // Demographics
                    $demoMu .= $this->getDemographicsMarkup($dbh, $role);
                }

                $trs[$trsCounter++] = HTMLContainer::generateMarkup('tr',
                    HTMLTable::makeTd('')
                    . HTMLTable::makeTd($role->createAddsBLock() . $demoMu, array('colspan'=>'11'))
                    , array('id'=>$role->getIdName() . 'a', 'class'=>$rowClass . ' hhk-addrRow'));
            }

        }

        // Guest search
        $mk1 .= HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('span', 'Add people - Last Name search: ')
            .HTMLInput::generateMarkup('', array('id'=>'txtPersonSearch', 'style'=>'margin-right:2em;', 'title'=>'Enter the first three characters of the person\'s last name'))

        	, array('id'=>'divPersonSearch', 'style'=>'margin-top:10px;'));


        // Header
        $hdr = HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('span', $familyName . ' Family')
            , array('style'=>'float:left;', 'class'=>'hhk-checkinHdr'));

        return array('hdr'=>$hdr, 'tblHead'=>$th, 'tblBody'=>$trs, 'adtnl'=>$mk1, 'mem'=>$rData->getMembersArray(), 'addrs'=>$this->getAddresses($this->roleObjs), 'tblId'=>FAMILY::FAM_TABLE_ID);

    }

}
?>