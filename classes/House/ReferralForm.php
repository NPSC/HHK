<?php

namespace HHK\House;

use HHK\Document\FormDocument;
use HHK\HTMLControls\HTMLTable;
use HHK\Member\Address\CleanAddress;
use HHK\Member\ProgressiveSearch\ProgressiveSearch;
use HHK\Member\ProgressiveSearch\SearchNameData\SearchFor;
use HHK\SysConst\ReferralFormStatus;

class ReferralForm {
	
	protected $referralDocId;
	protected $formUserData;
	protected $cleanAddress;

	
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
		
		$this->cleanAddress = new CleanAddress($dbh);
		
	}
	
	
	public function searchPatient(\PDO $dbh) {
	    
	    // Patient
	    if ( ! isset($this->formUserData['patientFirstName']) || ! isset($this->formUserData['patientLastName'])) {
	        
	        throw new \Exception('Patient first and/or last name not set.');
	    }
	    
	    $searchFor = new SearchFor();
	    
	    $searchFor->setNameFirst($this->formUserData['patientFirstName'])
	       ->setNameLast($this->formUserData['patientLastName']);
	    
	    // patientBirthdate
	    if (isset($this->formUserData['patientBirthdate']) && $this->formUserData['patientBirthdate'] != '') {
	        $searchFor->setBirthDate($this->formUserData['patientBirthdate']);
	    }
	    
	    // Phone
	    if (isset($this->formUserData['phone']) && $this->formUserData['phone'] != '') {
	        $searchFor->setPhone($this->formUserData['phone']);
	    }
	    
	    // email
	    if (isset($this->formUserData['email']) && $this->formUserData['email'] != '') {
	        $searchFor->setBirthDate($this->formUserData['email']);
	    }
	    
	    // City
	    if (isset($this->formUserData['adrCity']) && $this->formUserData['adrCity'] != '') {
	        $searchFor->setAddressCity($this->formUserData['adrCity']);
	    }
	    
	    // State
	    if (isset($this->formUserData['adrState']) && $this->formUserData['adrState'] != '') {
	        $searchFor->setAddressState($this->formUserData['adrState']);
	    }
	    
	    // Zip
	    if (isset($this->formUserData['adrZip']) && $this->formUserData['adrZip'] != '') {
	        $searchFor->setAddressZip($this->formUserData['adrZip']);
	    }
	    
	    // Country
	    if (isset($this->formUserData['adrCountry']) && $this->formUserData['adrCountry'] != '') {
	        $searchFor->setAddressCountry($this->formUserData['adrCountry']);
	    }
	    
	    $progSearch = new ProgressiveSearch();
	    return $progSearch->doSearch($dbh, $searchFor);  // returns an array of SearchResults objects
	    
	}
	
	public function createMarkup(array $results) {
	    
	    $tbl = new HTMLTable();
	    
	    $tbl->addHeaderTr(
	        HTMLTable::makeTh('Id')
	       . HTMLTable::makeTh('First Name')
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
	    
	    foreach ($results as $id => $r) {
	        $tbl->addBodyTr(
	            HTMLTable::makeTd($id)
    	        .HTMLTable::makeTd($r->getNameFirst())
    	        .HTMLTable::makeTd($r->getNameMiddle())
    	        .HTMLTable::makeTd($r->getNameLast())
    	        .HTMLTable::makeTd($r->getNickname())
    	        .HTMLTable::makeTd($r->getBirthDate())
    	        .HTMLTable::makeTd($r->getPhone())
    	        .HTMLTable::makeTd($r->getEmail())
    	        .HTMLTable::makeTd($r->getAddressStreet())
    	        .HTMLTable::makeTd($r->getAddressCity())
    	        .HTMLTable::makeTd($r->getAddressState())
    	        .HTMLTable::makeTd($r->getAddressZip())
    	        .HTMLTable::makeTd($r->getAddressCountry())
    	        .HTMLTable::makeTd($r->getNoReturn())
	        );
	    }
	    
	    
	    return $tbl->generateMarkup();
	}
	
	public function createOriginalDataTableRow() {
	    
	    return
	       HTMLTable::makeTd('')
	       .HTMLTable::makeTd($this->formUserData['patientFirstName'])
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
	        .HTMLTable::makeTd('');
	}
	
	public function closeReferral($dbh, ReferralFormStatus $status) {
	    
	    $this->formDocument->updateStatus($dbh, $status);
	    
	}
}

