<?php

namespace HHK\Member\RoleMember;

use HHK\Config_Lite\Config_Lite;
use HHK\HTMLControls\HTMLTable;
use HHK\SysConst\VolMemberType;
use HHK\sec\Session;

/**
 * PatientMember.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of PatientMember
 *
 * @author Eric
 */
 
class PatientMember extends AbstractRoleMember {
    
    protected function getMyMemberType() {
        return VolMemberType::Patient;
    }
    
    public function createThinMarkupRow($patientRelationship = '', $hideRelChooser = TRUE, $lockRelChooser = FALSE) {
        
        $labels = new Config_Lite(LABEL_FILE);
        return parent::createThinMarkupRow() . HTMLTable::makeTd($labels->getString('MemberType', 'patient', 'Patient'), array('style'=>'text-align:center;'));
        
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