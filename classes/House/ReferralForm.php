<?php

namespace HHK\House;

use HHK\Document\FormDocument;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLSelector;
use HHK\Member\MemberSearch;
use HHK\Member\ProgressiveSearch\ProgressiveSearch;
use HHK\Member\ProgressiveSearch\SearchNameData\{SearchFor};
use HHK\SysConst\ReferralFormStatus;
use HHK\SysConst\VolMemberType;

class ReferralForm {
	
	protected $referralDocId;
	protected $formUserData;
	
	protected $patSearchFor;
	protected $patResults;
	
	protected $gstSearchFor = [];
	protected $gstResults = [];
	
	protected $doctorResults;

	protected $hospitalResults;

	
	
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
	    if ( ! isset($this->formUserData['patient']['firstName']) || ! isset($this->formUserData['patient']['lastName'])) {
	        throw new \Exception('Patient first and/or last name fields are not set.');
	    }
	    
	    if ($this->formUserData['patient']['firstName'] == '' || $this->formUserData['patient']['lastName'] == '') {
	        throw new \Exception('Patient first and last name must both be filled in.  First name = ' . $this->formUserData['patient']['firstName'] . ', Last name = ' . $this->formUserData['patient']['lastName']);
	    }
	    
	    $this->patSearchFor = new SearchFor();
	    
	    // First and last names
	    $this->patSearchFor->setNameFirst($this->formUserData['patient']['firstName'])
	       ->setNameLast($this->formUserData['patient']['lastName']);
	    
	    // patient Birthdate
	    if (isset($this->formUserData['patient']['birthdate']) && $this->formUserData['patient']['birthdate'] != '') {
	        $this->patSearchFor->setBirthDate($this->formUserData['patient']['birthdate']);
	    }
	       
	    // patient gender
	    if (isset($this->formUserData['patient']['gender']) && $this->formUserData['patient']['gender'] != '') {
	        $this->patSearchFor->setGender($this->formUserData['patient']['gender']);
	    }
	       
	       // Phone
	    if (isset($this->formUserData['patient']['phone']) && $this->formUserData['patient']['phone'] != '') {
	        $this->patSearchFor->setPhone($this->formUserData['patient']['phone']);
	    }
	    
	    // email
	    if (isset($this->formUserData['patient']['email']) && $this->formUserData['patient']['email'] != '') {
	        $this->patSearchFor->setEmail($this->formUserData['patient']['email']);
	    }
	    
	    // City
	    if (isset($this->formUserData['patient']['address']['adrcity']) && $this->formUserData['patient']['address']['adrcity'] != '') {
	        $this->patSearchFor->setAddressCity($this->formUserData['patient']['address']['adrcity']);
	    }
	    
	    // State
	    if (isset($this->formUserData['patient']['address']['adrstate']) && $this->formUserData['patient']['address']['adrstate'] != '') {
	        $this->patSearchFor->setAddressState($this->formUserData['patient']['address']['adrstate']);
	    }
	    
	    // Zip
	    if (isset($this->formUserData['patient']['address']['zip']) && $this->formUserData['patient']['address']['zip'] != '') {
	        $this->patSearchFor->setAddressZip($this->formUserData['patient']['address']['zip']);
	    }
	    
	    // Country
	    if (isset($this->formUserData['patient']['address']['adrcountry']) && $this->formUserData['patient']['address']['adrcountry'] != '') {
	        $this->patSearchFor->setAddressCountry($this->formUserData['patient']['address']['adrcountry']);
	    }
	    
	    $progSearch = new ProgressiveSearch();
	    $this->patResults = $progSearch->doSearch($dbh, $this->patSearchFor);  // returns an array of SearchResults objects
	    
	    return $this->patResults;
	}

	public function searchGuests(\PDO $dbh) {
	    
	    $this->gstResults = [];
	    
	    if (isset($this->formUserData['guests']) === FALSE || is_array($this->formUserData['guests']) === FALSE) {
	        throw new \Exception('Guests are missing from form data.  ');
	    }

	    for ($indx = 0; $indx < 5; $indx++) {
	        
	        $gindx = 'g' . $indx;
	        
	        if (isset($this->formUserData['guests'][$gindx]['firstName']) && isset($this->formUserData['guests'][$gindx]['lastName'])
	            && $this->formUserData['guests'][$gindx]['firstName'] != '' && $this->formUserData['guests'][$gindx]['lastName'] != '') {
	            
	            $searchFor = new SearchFor();
	            
	            // First, last name
	            $searchFor->setNameFirst($this->formUserData['guests'][$gindx]['firstName'])
	            ->setNameLast($this->formUserData['guests'][$gindx]['lastName']);
	                
	            // Phone
	            if (isset($this->formUserData['guests'][$gindx]['phone']) && $this->formUserData['guests'][$gindx]['phone'] != '') {
	                $searchFor->setPhone($this->formUserData['guests'][$gindx]['phone']);
	            }
	            
	            // Relationship
	            if (isset($this->formUserData['guests'][$gindx]['relationship']) && $this->formUserData['guests'][$gindx]['relationship'] != '') {
	                $searchFor->setRelationship($this->formUserData['guests'][$gindx]['relationship']);
	            }
	            
	            
	            $this->gstSearchFor[] = $searchFor;
	            
	            $progSearch = new ProgressiveSearch();
	            $this->gstResults[] = $progSearch->doSearch($dbh, $searchFor);
	        }
	            
	    }
	    
	    return $this->gstResults;
	    
	}
	
	public function searchDoctor(\PDO $dbh) {
	    
	    if (isset($this->formUserData['hospital']['doctor']) && $this->formUserData['hospital']['doctor'] != '') {
	    
	       $memberSearch = new MemberSearch($this->formUserData['hospital']['doctor']);
	    
	       $this->doctorResults = $memberSearch->volunteerCmteFilter($dbh, VolMemberType::Doctor, '');
	    }
	}
	
	public function hosptialMarkup(\PDO $dbh, $hospList) {
	    
	    $hList = array();
	    $formHospitalName = '';
	    
	    if (isset($this->formUserData['hospital'])) {
	        $formHospitalName = $this->formUserData['hospital']['name'];
	    }

	    // Make hospital list
	    foreach ($hospList as $h) {
	        if ($h[2] == 'h' && $h[3] == 'a') {
	            $hList[] = array($h[0], $h[1]);
	        }
	    }
	    
	    $tbl = new HTMLTable();
	    
	    $tbl->addBodyTr(
	        HTMLTable::makeTh('Form Entry:') . HTMLTable::makeTd($formHospitalName)
	    );
	    
	    $tbl->addBodyTr(
	        HTMLTable::makeTh('Select Match:')
	        . HTMLTable::makeTd(HTMLSelector::generateMarkup(
	            HTMLSelector::doOptionsMkup(removeOptionGroups($hList), '', TRUE),
	            array('name'=>'selHospital', 'class'=>'ignrSave hospital-stay' ))
	            )
	    );
	    
	    return $tbl->generateMarkup(array('class'=>'hhk-visitdialog hhk-tdbox'));
	}
	
	public function searchVehicle(\PDO $dbh) {
	    
	    // State, License
	    
	    // Make Model
	    
	}
	
	
	public function createPatientMarkup() {
	    
	    $tbl = new HTMLTable();
	    
	    //Header titles
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
	        .HTMLTable::makeTh('No Return')
	        );
	    
	    // Original data
	    $tbl->addBodyTr(
	        HTMLTable::makeTd('-')
	        .HTMLTable::makeTd($this->patSearchFor->getNameFirst())
	        .HTMLTable::makeTd('')
	        .HTMLTable::makeTd($this->patSearchFor->getNameLast())
	        .HTMLTable::makeTd('')
	        .HTMLTable::makeTd(($this->patSearchFor->getBirthDate() == '' ? '' : date('M d, Y', strtotime($this->patSearchFor->getBirthDate()))))
	        .HTMLTable::makeTd(preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $this->patSearchFor->getPhone()))
	        .HTMLTable::makeTd($this->patSearchFor->getEmail())
	        .HTMLTable::makeTd($this->patSearchFor->getAddressStreet())
	        .HTMLTable::makeTd($this->patSearchFor->getAddressCity())
	        .HTMLTable::makeTd($this->patSearchFor->getAddressState())
	        .HTMLTable::makeTd($this->patSearchFor->getAddressZip())
	        .HTMLTable::makeTd($this->patSearchFor->getAddressCountry())
	        .HTMLTable::makeTd('')
	        , array('class'=>'hhk-origUserData'));
	    
	    // Searched data
	    foreach ($this->patResults as $r) {
	        $tbl->addBodyTr(
	            HTMLTable::makeTd($r->getId())
	            .HTMLTable::makeTd($r->getNameFirst())
	            .HTMLTable::makeTd($r->getNameMiddle())
	            .HTMLTable::makeTd($r->getNameLast())
	            .HTMLTable::makeTd($r->getNickname())
	            .HTMLTable::makeTd(($r->getBirthDate() == '' ? '' : date('M d, Y', strtotime($r->getBirthDate()))))
	            .HTMLTable::makeTd(preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $r->getPhone()))
	            .HTMLTable::makeTd($r->getEmail())
	            .HTMLTable::makeTd($r->getAddressStreet())
	            .HTMLTable::makeTd($r->getAddressCity())
	            .HTMLTable::makeTd($r->getAddressState())
	            .HTMLTable::makeTd($r->getAddressZip())
	            .HTMLTable::makeTd($r->getAddressCountry())
	            .HTMLTable::makeTd($r->getNoReturn())
	            , array('class'=>'hhk-resultUserData'));
	    }
	    
	    // Offer New Patient
	    $tbl->addBodyTr(
	        HTMLTABLE::makeTd('')
	        .HTMLTable::makeTd('New Patient', array('colspan'=>'15'))
	        , array('class'=>'hhk-newPatient'));
	    
	    return $tbl->generateMarkup(array('class'=>'hhk-visitdialog hhk-tdbox'));
	}
	
	public function guestsMarkup() {
	    
	    $markup = '';
	    $indx = 0;
	    
	    for ($indx = 0; $indx < 3; $indx++) {
	        
	        if (isset($this->gstResults[$indx])) {
	        
	            $markup .= $this->createGuestMarkup($indx, $this->gstResults[$indx]);
	        }
	    }
	    
	    return $markup;
	}
	
	public function createGuestMarkup($indx, array $guestResults) {
	    
	    $gindx = 'g' . $indx;
	    $tbl = new HTMLTable();
	    
	    $tbl->addHeaderTr(
	        HTMLTable::makeTh('Id')
	        . HTMLTable::makeTh('First Name')
	        .HTMLTable::makeTh('Middle')
	        .HTMLTable::makeTh('Last Name')
	        .HTMLTable::makeTh('Nickame')
	        .HTMLTable::makeTh('Relationship')
	        .HTMLTable::makeTh('Phone')
	        .HTMLTable::makeTh('Email')
	        .HTMLTable::makeTh('Street Address')
	        .HTMLTable::makeTh('City')
	        .HTMLTable::makeTh('State')
	        .HTMLTable::makeTh('Zip Code')
	        .HTMLTable::makeTh('Country')
	        .HTMLTable::makeTh('No Return')
	        );
	    
	    // Original data
	    $tbl->addBodyTr(
    	    HTMLTable::makeTd('')
	        .HTMLTable::makeTd($this->formUserData['guests'][$gindx]['firstName'])
    	    .HTMLTable::makeTd('')
	        .HTMLTable::makeTd($this->formUserData['guests'][$gindx]['lastName'])
    	    .HTMLTable::makeTd('')
	        .HTMLTable::makeTd($this->formUserData['guests'][$gindx]['relationship'])
	        .HTMLTable::makeTd($this->formUserData['guests'][$gindx]['phone'])
    	    .HTMLTable::makeTd('')
    	    .HTMLTable::makeTd('')
    	    .HTMLTable::makeTd('')
    	        .HTMLTable::makeTd('')
    	        .HTMLTable::makeTd('')
    	        .HTMLTable::makeTd('')
    	        .HTMLTable::makeTd('')
	        , array('class'=>'hhk-origUserData'));

	        
	   // Searched data
	    foreach ($guestResults as $r) {
	       $tbl->addBodyTr(
	           HTMLTable::makeTd($r->getId())
	           .HTMLTable::makeTd($r->getNameFirst())
	           .HTMLTable::makeTd($r->getNameMiddle())
	           .HTMLTable::makeTd($r->getNameLast())
	           .HTMLTable::makeTd($r->getNickname())
	           .HTMLTable::makeTd($r->getRelationship())
	           .HTMLTable::makeTd($r->getPhone())
	           .HTMLTable::makeTd($r->getEmail())
	           .HTMLTable::makeTd($r->getAddressStreet())
	           .HTMLTable::makeTd($r->getAddressCity())
	           .HTMLTable::makeTd($r->getAddressState())
	           .HTMLTable::makeTd($r->getAddressZip())
	           .HTMLTable::makeTd($r->getAddressCountry())
	           .HTMLTable::makeTd($r->getNoReturn())
	           , array('class'=>'hhk-resultUserData'));
	   }
	   
	   // Offer New Guest
	   $tbl->addBodyTr(
	       HTMLTABLE::makeTd('')
	       .HTMLTable::makeTd('New Guest', array('colspan'=>'15'))
	       , array('class'=>'hhk-newGuest'));
	   
	   return $tbl->generateMarkup(array('class'=>'hhk-visitdialog hhk-tdbox'));
	}
	
	
	public function setReferralStatus($dbh, ReferralFormStatus $status) {
	    
	    $this->formDocument->updateStatus($dbh, $status);
	    
	}
}

