<?php

namespace HHK\House;

use HHK\Document\FormDocument;
use HHK\SysConst\ReferralFormStatus;
use HHK\Member\ProgressiveSearch;
use HHK\HTMLControls\HTMLTable;

class ReferralForm {
	
	protected $referralDocId;
	protected $formUserData;

	
	public function __construct(\PDO $dbh, $referralDocId) {
		
		//Referral Form
		$this->referralDocId = $referralDocId;
		
		$formDoc = new FormDocument();
		
		if ($formDoc->loadDocument($dbh, $referralDocId)) {
		
		    if (is_null($this->formUserData = $formDoc->getUserData())) {
		        throw new \Exception("Referral form user input is blank.  Document Id = " . $referralDocId);
		    }
		  
		} else {
		    throw new \Exception("Referral form not found.  Document Id = " . $referralDocId);
		}
		
	}
	
	public function searchPatient(\PDO $dbh) {
	    
	    // Patient
	    if ( ! isset($this->formUserData['patientFirstName']) || ! isset($this->formUserData['patientLastName'])) {
	        
	        throw new \Exception('Patient first and/or last name not set.');
	    }
	    
	    $progSearch = new ProgressiveSearch();
	    
	    $progSearch->setNameFirst($this->formUserData['patientFirstName'])->setNameLast($this->formUserData['patientLastName']);
	    
	    // patientBirthdate
	    if (isset($this->formUserData['patientBirthdate']) && $this->formUserData['patientBirthdate'] != '') {
	        $progSearch->setBirthDate($this->formUserData['patientBirthdate']);
	    }
	    
	    // Phone
	    if (isset($this->formUserData['phone']) && $this->formUserData['phone'] != '') {
	        $progSearch->setPhone($this->formUserData['phone']);
	    }
	    
	    // email
	    if (isset($this->formUserData['email']) && $this->formUserData['email'] != '') {
	        $progSearch->setBirthDate($this->formUserData['email']);
	    }
	    
	    // City
	    if (isset($this->formUserData['adrCity']) && $this->formUserData['adrCity'] != '') {
	        $progSearch->setAddressCity($this->formUserData['adrCity']);
	    }
	    
	    // State
	    if (isset($this->formUserData['adrState']) && $this->formUserData['adrState'] != '') {
	        $progSearch->setAddressState($this->formUserData['adrState']);
	    }
	    
	    // Zip
	    if (isset($this->formUserData['adrZip']) && $this->formUserData['adrZip'] != '') {
	        $progSearch->setAddressZip($this->formUserData['adrZip']);
	    }
	    
	    // Country
	    if (isset($this->formUserData['adrCountry']) && $this->formUserData['adrCountry'] != '') {
	        $progSearch->setAddressCountry($this->formUserData['adrCountry']);
	    }
	    
	    return $progSearch->doSearch($dbh);
	    
	}
	
	public function createMarkup() {
	    
	    $tbl = new HTMLTable();
	    
	    $tbl->addHeaderTr(
	        HTMLTable::makeTh('First Name')
	        .HTMLTable::makeTh('Middle')
	        .HTMLTable::makeTh('Last Name')
	        .HTMLTable::makeTh('Nickame')
	        .HTMLTable::makeTh('Birth Date')
	        .HTMLTable::makeTh('Phone')
	        .HTMLTable::makeTh('Email')
	        .HTMLTable::makeTh('Street Address')
	        .HTMLTable::makeTh('City')
	        .HTMLTable::makeTh('State')
	        .HTMLTable::makeTh('Zip Code')
	        .HTMLTable::makeTh('Country')
	        );
	    
	    $tbl->addBodyTr(
	        HTMLTable::makeTd($this->formUserData['patientFirstName'])
	        .HTMLTable::makeTd('')
	        .HTMLTable::makeTd($this->formUserData['patientLastName'])
	        .HTMLTable::makeTd('')
	        .HTMLTable::makeTd($this->formUserData['patientBirthdate'])
	        .HTMLTable::makeTd($this->formUserData['phone'])
	        .HTMLTable::makeTd($this->formUserData['email'])
	        .HTMLTable::makeTd($this->formUserData['adrStreet'])
	        .HTMLTable::makeTd($this->formUserData['adrCity'])
	        .HTMLTable::makeTd($this->formUserData['adrState'])
	        .HTMLTable::makeTd($this->formUserData['adrZip'])
	        .HTMLTable::makeTd($this->formUserData['adrCountry'])

	        
	        );
	    
	    return $tbl->generateMarkup();
	}
	
	public function closeReferral($dbh, ReferralFormStatus $status) {
	    
	    $this->formDocument->updateStatus($dbh, $status);
	    
	}
}

