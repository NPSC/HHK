<?php

namespace HHK\House;

use HHK\Document\FormDocument;
use HHK\SysConst\ReferralFormStatus;
use HHK\Member\ProgressiveSearch;

class ReferralForm {
	
	protected $referralDocId;
	protected $formDocument;
	protected $errors;
	
	public function __construct(\PDO $dbh, $referralDocId) {
		
		//Referral Form
		$this->formDocument = new FormDocument();
		$this->formDocument->loadDocument($dbh, $referralDocId);
		
	}
	
	public function searchPatient(\PDO $dbh) {
	    
	    $userData = $this->formDocument->getUserData();
	    
	    // Patient
	    if ( ! isset($userData['patientFirstName']) || ! isset($userData['patientLastName'])) {
	        $this->errors[] = 'Patient first and/or last name not set.';
	        return;
	    }
	    
	    $progSearch = new ProgressiveSearch();
	    
	    $progSearch->setNameFirst($userData['patientFirstName'])->setNameLast($userData['patientLastName']);
	    
	    // patientBirthdate
	    if (isset($userData['patientBirthdate']) && $userData['patientBirthdate'] != '') {
	        $progSearch->setBirthDate($userData['patientBirthdate']);
	    }
	    
	    // Phone
	    if (isset($userData['phone']) && $userData['phone'] != '') {
	        $progSearch->setPhone($userData['phone']);
	    }
	    
	    // email
	    if (isset($userData['email']) && $userData['email'] != '') {
	        $progSearch->setBirthDate($userData['email']);
	    }
	    
	    // City
	    if (isset($userData['adrCity']) && $userData['adrCity'] != '') {
	        $progSearch->setAddressCity($userData['adrCity']);
	    }
	    
	    // State
	    if (isset($userData['adrState']) && $userData['adrState'] != '') {
	        $progSearch->setAddressState($userData['adrState']);
	    }
	    
	    // Country
	    if (isset($userData['adrCountry']) && $userData['adrCountry'] != '') {
	        $progSearch->setAddressCountry($userData['adrCountry']);
	    }
	    
	    return $progSearch->doSearch($dbh);
	    
	}
	
	public function closeReferral($dbh, ReferralFormStatus $status) {
	    
	    $this->formDocument->updateStatus($dbh, $status);
	    
	}
}

