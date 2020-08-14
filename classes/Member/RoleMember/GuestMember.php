<?php

namespace HHK\Member\RoleMember;

use HHK\HTMLControls\{HTMLTable, HTMLSelector};
use HHK\SysConst\{GLTableNames, RelLinkType, VolMemberType};
use HHK\sec\Session;

/**
 * GuestMember.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of GuestMember
 *
 * @author Eric
 */
 
class GuestMember extends AbstractRoleMember {
    
    public function createThinMarkupRow($patientRelationship = '', $hideRelChooser = FALSE, $lockRelChooser = FALSE) {
        
        $uS = Session::getInstance();
        $tr = parent::createThinMarkupRow();
        
        if ($hideRelChooser === FALSE) {
            
            $parray = $uS->guestLookups[GLTableNames::PatientRel]; // removeOptionGroups($uS->guestLookups[GL_TableNames::PatientRel]);
            
            // freeze control if patient is self.
            if ($lockRelChooser) {
                
                if ($patientRelationship == RelLinkType::Self) {
                    $parray = array($patientRelationship => $parray[$patientRelationship]);
                } else {
                    unset($parray[RelLinkType::Self]);
                    $parray = removeOptionGroups($parray);
                }
                
                if ($patientRelationship == '') {
                    $allowEmpty = TRUE;
                } else {
                    $allowEmpty = FALSE;
                }
            } else {
                $allowEmpty = TRUE;
            }
            
            // Patient relationship
            $tr .= HTMLTable::makeTd(HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($parray, $patientRelationship, $allowEmpty), array('name'=>$this->getIdPrefix() . 'selPatRel', 'data-prefix'=>$this->getIdPrefix(), 'class'=>'patientRelch')));
            
        } else {
            
            $tr .= HTMLTable::makeTd('');
        }
        
        return $tr;
    }
    
    protected function getMyMemberType() {
        return VolMemberType::Guest;
    }
    
    public function saveChanges(\PDO $dbh, array $post) {
        
        $msg = '';
        $uS = Session::getInstance();
        
        $msg .= parent::saveChanges($dbh, $post);
        
        //  Save Languages
        if ($uS->LangChooser) {
            $this->saveLanguages($dbh, $post, $this->getIdPrefix(), $uS->username);
        }
        
        //  Save Insurance
        if ($uS->InsuranceChooser) {
            $this->saveInsurance($dbh, $post, $this->getIdPrefix(), $uS->username);
        }
        
        
        if ($uS->LangChooser && $this->get_idName() > 0) {
            $this->getLanguages($dbh, $this->get_idName());
        }
        
        if ($uS->InsuranceChooser && $this->get_idName() > 0) {
            $this->getInsurance($dbh, $this->get_idName());
        }
        
        return $msg;
    }
    
}
?>