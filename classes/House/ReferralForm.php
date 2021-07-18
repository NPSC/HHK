<?php

namespace HHK\House;

use HHK\Document\FormDocument;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLTable;
use HHK\Member\MemberSearch;
use HHK\Member\ProgressiveSearch\ProgressiveSearch;
use HHK\Member\ProgressiveSearch\SearchNameData\{SearchFor};
use HHK\SysConst\ReferralFormStatus;
use HHK\SysConst\VolMemberType;
use HHK\SysConst\GLTableNames;
use HHK\SysConst\RelLinkType;
use HHK\Member\Address\CleanAddress;
use HHK\HTMLControls\HTMLInput;
use HHK\sec\Session;

class ReferralForm {

	protected $referralDocId;
	protected $formUserData;

	protected $patSearchFor;
	protected $patResults;

	protected $gstSearchFor = [];
	protected $gstResults = [];

	protected $doctorResults;

	// Patient search includes
	const HTML_Incl_Birthday = 'cbPIncludeBD';
	const HTML_Incl_Phone = 'cbPIncludePhone';
	const HTML_Incl_Email = 'cbPIncludeEmail';



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


	public function searchPatient(\PDO $dbh, $searchIncludes = []) {

	    // Patient
	    if ( ! isset($this->formUserData['patient']['firstName']) || ! isset($this->formUserData['patient']['lastName'])) {
	        throw new \Exception('Patient first and/or last name fields are not set.');
	    }

	    if ($this->formUserData['patient']['firstName'] == '' || $this->formUserData['patient']['lastName'] == '') {
	        throw new \Exception('Patient first and last name must both be filled in.  First name = ' . $this->formUserData['patient']['firstName'] . ', Last name = ' . $this->formUserData['patient']['lastName']);
	    }

	    $this->patSearchFor = new SearchFor();

	    // Relationship
	    $this->patSearchFor->setRelationship(RelLinkType::Self);

	    // First and last names
	    $this->patSearchFor->setNameFirst($this->formUserData['patient']['firstName'])
	       ->setNameLast($this->formUserData['patient']['lastName']);

	    // patient Birthdate
	    if (isset($this->formUserData['patient']['birthdate']) && $this->formUserData['patient']['birthdate'] != '') {
	        $this->patSearchFor->setBirthDate($this->formUserData['patient']['birthdate'], (isset($searchIncludes[self::HTML_Incl_Birthday]) ? TRUE : FALSE));
	    }

	    // patient gender
	    if (isset($this->formUserData['patient']['demogs']['gender']) && $this->formUserData['patient']['demogs']['gender'] != '') {
	        $this->patSearchFor->setGender($this->formUserData['patient']['demogs']['gender']);
	    }

	       // Phone
	    if (isset($this->formUserData['patient']['phone']) && $this->formUserData['patient']['phone'] != '') {
	        $this->patSearchFor->setPhone($this->formUserData['patient']['phone'], (isset($searchIncludes[self::HTML_Incl_Phone]) ? TRUE : FALSE));
	    }

	    // email
	    if (isset($this->formUserData['patient']['email']) && $this->formUserData['patient']['email'] != '') {
	        $this->patSearchFor->setEmail($this->formUserData['patient']['email'], (isset($searchIncludes[self::HTML_Incl_Email]) ? TRUE : FALSE));
	    }

	    // Street
	    if (isset($this->formUserData['patient']['address']['street']) && $this->formUserData['patient']['address']['street'] != '') {
	        $this->patSearchFor->setAddressStreet($this->formUserData['patient']['address']['street'], new CleanAddress($dbh));
	    }

	    // City
	    if (isset($this->formUserData['patient']['address']['adrcity']) && $this->formUserData['patient']['address']['adrcity'] != '') {
	        $this->patSearchFor->setAddressCity($this->formUserData['patient']['address']['adrcity']);
	    }

	    // County
	    if (isset($this->formUserData['patient']['address']['adrcounty']) && $this->formUserData['patient']['address']['adrcounty'] != '') {
	        $this->patSearchFor->setAddressCounty($this->formUserData['patient']['address']['adrcounty']);
	    }

	    // State
	    if (isset($this->formUserData['patient']['address']['adrstate']) && $this->formUserData['patient']['address']['adrstate'] != '') {
	        $this->patSearchFor->setAddressState($this->formUserData['patient']['address']['adrstate']);
	    }

	    // Zip
	    if (isset($this->formUserData['patient']['address']['adrzip']) && $this->formUserData['patient']['address']['adrzip'] != '') {
	        $this->patSearchFor->setAddressZip($this->formUserData['patient']['address']['adrzip']);
	    }

	    // Country
	    if (isset($this->formUserData['patient']['address']['adrcountry']) && $this->formUserData['patient']['address']['adrcountry'] != '') {
	        $this->patSearchFor->setAddressCountry($this->formUserData['patient']['address']['adrcountry']);
	    }

	    $progSearch = new ProgressiveSearch();
	    $this->patResults = $progSearch->doSearch($dbh, $this->patSearchFor);  // returns an array of SearchResults objects

	    return $this->patResults;
	}

	public function searchGuests(\PDO $dbh, $maxGuests = 5) {

	    $this->gstResults = [];

	    if (isset($this->formUserData['guests']) === FALSE || is_array($this->formUserData['guests']) === FALSE) {
	        throw new \Exception('Guests are missing from form data.  ');
	    }

	    for ($indx = 0; $indx < $maxGuests; $indx++) {

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

	public function datesMarkup() {

	    $ckinDate = '';
	    $ckoutDate = '';

	    if (isset($this->formUserData['checkindate'])) {

	        $dateDT = new \DateTime($this->formUserData['checkindate']);

	        $ckinDate = HTMLContainer::generateMarkup('span', 'Arrival: '
	            . HTMLInput::generateMarkup($dateDT->format('M j, Y'), array('size'=>'13')) );
	    }

	    if (isset($this->formUserData['checkoutdate'])) {

	        $dateDT = new \DateTime($this->formUserData['checkoutdate']);

	        $ckoutDate = HTMLContainer::generateMarkup('span', 'Expected Departure: '
	            . HTMLInput::generateMarkup($dateDT->format('M j, Y'), array('size'=>'13'))
	            , array('style'=>'margin-left:.9em;'));
	    }

	    return HTMLContainer::generateMarkup('div', $ckinDate . $ckoutDate, array('style'=>'font-size:.9em;'));
	}

	public function searchDoctor(\PDO $dbh) {

	    if (isset($this->formUserData['hospital']['doctor']) && $this->formUserData['hospital']['doctor'] != '') {

	       $memberSearch = new MemberSearch($this->formUserData['hospital']['doctor']);

	       $this->doctorResults = $memberSearch->volunteerCmteFilter($dbh, VolMemberType::Doctor, '');
	    }
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
	        .HTMLTable::makeTh('Birth Date' . HTMLInput::generateMarkup('', array('type'=>'checkbox', 'class'=>'hhk-includeSearch', 'name'=>self::HTML_Incl_Birthday, 'style'=>'margin-left:3px;', 'title'=>'Check to include in search parameters')))
	        .HTMLTable::makeTh('Phone' . HTMLInput::generateMarkup('', array('type'=>'checkbox', 'class'=>'hhk-includeSearch', 'name'=>self::HTML_Incl_Phone, 'style'=>'margin-left:3px;', 'title'=>'Check to include in search parameters')))
	        .HTMLTable::makeTh('Email' . HTMLInput::generateMarkup('', array('type'=>'checkbox', 'class'=>'hhk-includeSearch', 'name'=>self::HTML_Incl_Email, 'style'=>'margin-left:3px;', 'title'=>'Check to include in search parameters')))
	        .HTMLTable::makeTh('Street Address')
	        .HTMLTable::makeTh('City')
	        .HTMLTable::makeTh('State')
	        .HTMLTable::makeTh('Zip Code')
	        .HTMLTable::makeTh('Country')
	        .HTMLTable::makeTh('No Return')
	        );

	    // Original data
	    $tbl->addBodyTr(
	        HTMLTable::makeTd(HTMLInput::generateMarkup('0', array('type'=>'radio', 'name'=>'rPatient', 'id'=>'patSel0', 'data-nid'=>'0')))
	        .HTMLTable::makeTd($this->patSearchFor->getNameFirst())
	        .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	        .HTMLTable::makeTd($this->patSearchFor->getNameLast())
	        .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	        .HTMLTable::makeTd(($this->patSearchFor->getBirthDate() == '' ? '' : date('M d, Y', strtotime($this->patSearchFor->getBirthDate()))))
	        .HTMLTable::makeTd(preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $this->patSearchFor->getPhone()))
	        .HTMLTable::makeTd($this->patSearchFor->getEmail())
	        .HTMLTable::makeTd($this->patSearchFor->getAddressStreet())
	        .HTMLTable::makeTd($this->patSearchFor->getAddressCity())
	        .HTMLTable::makeTd($this->patSearchFor->getAddressState())
	        .HTMLTable::makeTd($this->patSearchFor->getAddressZip())
	        .HTMLTable::makeTd($this->patSearchFor->getAddressCountry())
	        .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	        , array('class'=>'hhk-origUserData'));

	    $tbl->addBodyTr(HTMLTable::makeTd('', array('colspan'=>'14')));

	    // Searched data
	    foreach ($this->patResults as $r) {
	        $tbl->addBodyTr(
	            HTMLTable::makeTd(HTMLInput::generateMarkup($r->getId(), array('type'=>'radio', 'name'=>'rPatient', 'id'=>'patSel'.$r->getId(), 'data-nid'=>$r->getId())))
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
	            , array('class'=>'hhk-resultUserData'));
	    }

	    return $tbl->generateMarkup(array('class'=>'hhk-tdbox'));
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

	   $uS = Session::getInstance();
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
	       HTMLTable::makeTd(HTMLInput::generateMarkup('0', array('type'=>'radio', 'name'=>'rGuest', 'id'=>'gstSel0')))
	        .HTMLTable::makeTd($this->formUserData['guests'][$gindx]['firstName'])
	       .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	        .HTMLTable::makeTd($this->formUserData['guests'][$gindx]['lastName'])
	       .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	        .HTMLTable::makeTd((isset($uS->guestLookups[GLTableNames::PatientRel][$this->formUserData['guests'][$gindx]['relationship']]) ? $uS->guestLookups[GLTableNames::PatientRel][$this->formUserData['guests'][$gindx]['relationship']][1] : $this->formUserData['guests'][$gindx]['relationship']))
	        .HTMLTable::makeTd(preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $this->formUserData['guests'][$gindx]['phone']))
	       .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	       .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	       .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	       .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	       .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	       .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	       .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	        , array('class'=>'hhk-origUserData'));

	   $tbl->addBodyTr(HTMLTable::makeTd('', array('colspan'=>'14')));

	   // Searched data
	   foreach ($guestResults as $r) {
	       $tbl->addBodyTr(
	           HTMLTable::makeTd(HTMLInput::generateMarkup($r->getId(), array('type'=>'radio', 'name'=>'rGuest', 'id'=>'gstSel'.$r->getId())))
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


	   return $tbl->generateMarkup(array('class'=>'hhk-tdbox'));
	}


	public function setReferralStatus($dbh, ReferralFormStatus $status) {

	    $this->formDocument->updateStatus($dbh, $status);

	}
}

