<?php

namespace HHK\House;

use HHK\Document\FormDocument;
use HHK\HTMLControls\{HTMLContainer,HTMLTable};
use HHK\Member\MemberSearch;
use HHK\Member\ProgressiveSearch\ProgressiveSearch;
use HHK\Member\ProgressiveSearch\SearchNameData\{SearchNameData, SearchResults, SearchFor};
use HHK\SysConst\{AddressPurpose, EmailPurpose, PhonePurpose, ReferralFormStatus, VolMemberType, GLTableNames, RelLinkType, ReservationStatus};
use HHK\Member\Address\CleanAddress;
use HHK\HTMLControls\HTMLInput;
use HHK\sec\Session;
use HHK\Member\Role\{AbstractRole, Patient, Guest};
use HHK\Tables\EditRS;
use HHK\Tables\Name\NameAddressRS;
use HHK\House\Reservation\Reservation_1;
use HHK\House\Hospital\HospitalStay;
use HHK\Tables\Reservation\Reservation_GuestRS;
use HHK\Tables\Reservation\Reservation_ReferralRS;



class ReferralForm {

    /**
     *
     * @var integer The unique document id
     */
	protected $referralDocId;

	/**
	 *
	 * @var array Form data
	 */
	protected $formUserData;

	protected $patSearchFor;
	protected $patResults;

	protected $gstSearchFor = [];
	protected $gstResults = [];

	protected $idPatient;
	protected $idPsg;

	protected $CkinDT = NULL;
	protected $CkoutDT = NULL;

	// Patient search includes
	const HTML_Incl_Birthday = 'cbPIncludeBD';
	const HTML_Incl_Phone = 'cbPIncludePhone';
	const HTML_Incl_Email = 'cbPIncludeEmail';

    const MAX_GUESTS = 3;

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

	public static function loadSearchFor(\PDO $dbh, array $formUserData, array $searchIncludes = []) {

	    $searchFor = new SearchFor();

	    // Relationship is static
	    $searchFor->setRelationship(RelLinkType::Self);

	    // First and last names
	    if (isset($formUserData['firstName'])) {
	        $searchFor->setNameFirst($formUserData['firstName']);
	    }
	    if (isset($formUserData['lastName'])) {
	        $searchFor->setNameLast($formUserData['lastName']);
	    }

	    if (isset($formUserData['middleName'])) {
	        $searchFor->setNameLast($formUserData['middleName']);
	    }

	    if (isset($formUserData['nickname'])) {
	        $searchFor->setNameLast($formUserData['nickname']);
	    }

	    // patient Birthdate
	    if (isset($formUserData['birthdate']) && $formUserData['birthdate'] != '') {
	        $searchFor->setBirthDate($formUserData['birthdate'], (isset($searchIncludes[self::HTML_Incl_Birthday]) ? TRUE : FALSE));
	    }

	    // patient gender
	    if (isset($formUserData['demogs']['gender']) && $formUserData['demogs']['gender'] != '') {
	        $searchFor->setGender($formUserData['demogs']['gender']);
	    }

	    // Phone
	    if (isset($formUserData['phone']) && $formUserData['phone'] != '') {
	        $searchFor->setPhone($formUserData['phone'], (isset($searchIncludes[self::HTML_Incl_Phone]) ? TRUE : FALSE));
	    }

	    // email
	    if (isset($formUserData['email']) && $formUserData['email'] != '') {
	        $searchFor->setEmail($formUserData['email'], (isset($searchIncludes[self::HTML_Incl_Email]) ? TRUE : FALSE));
	    }

	    // Street
	    if (isset($formUserData['address']['adrstreet']) && $formUserData['address']['adrstreet'] != '') {
	        $searchFor->setAddressStreet($formUserData['address']['adrstreet'], new CleanAddress($dbh));
	    }

	    // City
	    if (isset($formUserData['address']['adrcity']) && $formUserData['address']['adrcity'] != '') {
	        $searchFor->setAddressCity($formUserData['address']['adrcity']);
	    }

	    // County
	    if (isset($formUserData['address']['adrcounty']) && $formUserData['address']['adrcounty'] != '') {
	        $searchFor->setAddressCounty($formUserData['address']['adrcounty']);
	    }

	    // State
	    if (isset($formUserData['address']['adrstate']) && $formUserData['address']['adrstate'] != '') {
	        $searchFor->setAddressState($formUserData['address']['adrstate']);
	    }

	    // Zip
	    if (isset($formUserData['address']['adrzip']) && $formUserData['address']['adrzip'] != '') {
	        $searchFor->setAddressZip($formUserData['address']['adrzip']);
	    }

	    // Country
	    if (isset($formUserData['address']['adrcountry']) && $formUserData['address']['adrcountry'] != '') {
	        $searchFor->setAddressCountry($formUserData['address']['adrcountry']);
	    }

	    return $searchFor;

	}

	public function searchPatient(\PDO $dbh, array $searchIncludes = []) {

	    // Patient
	    if ( ! isset($this->formUserData['patient']['firstName']) || ! isset($this->formUserData['patient']['lastName'])) {
	        throw new \Exception('Patient first and/or last name fields are not set.');
	    }

	    if ($this->formUserData['patient']['firstName'] == '' || $this->formUserData['patient']['lastName'] == '') {
	        throw new \Exception('Patient first and last name must both be filled in.  First name = ' . $this->formUserData['patient']['firstName'] . ', Last name = ' . $this->formUserData['patient']['lastName']);
	    }

	    $this->patSearchFor = $this->loadSearchFor($dbh, $this->formUserData['patient'], $searchIncludes);

	    // Relationship
	    $this->patSearchFor->setRelationship(RelLinkType::Self);

	    $progSearch = new ProgressiveSearch();
	    $this->patResults = $progSearch->doSearch($dbh, $this->patSearchFor);  // returns an array of SearchResults objects

	    return $this->patResults;
	}

	public function searchGuests(\PDO $dbh, $maxGuests = self::MAX_GUESTS) {

	    $this->gstResults = [];

	    if (isset($this->formUserData['guests']) === FALSE || is_array($this->formUserData['guests']) === FALSE) {
	        throw new \Exception('Guests are missing from form data.  ');
	    }

	    for ($indx = 0; $indx < $maxGuests; $indx++) {

	        $gindx = 'g' . $indx;

	        if (isset($this->formUserData['guests'][$gindx]['firstName']) && isset($this->formUserData['guests'][$gindx]['lastName'])
	            && $this->formUserData['guests'][$gindx]['firstName'] != '' && $this->formUserData['guests'][$gindx]['lastName'] != '') {

	            $searchFor = $this->loadSearchFor($dbh, $this->formUserData['guests'][$gindx]);


	            // Relationship
	            if (isset($this->formUserData['guests'][$gindx]['relationship']) && $this->formUserData['guests'][$gindx]['relationship'] != '') {
	                $searchFor->setRelationship($this->formUserData['guests'][$gindx]['relationship']);
	            }


	            $this->gstSearchFor[$gindx] = $searchFor;

	            $progSearch = new ProgressiveSearch();
	            $this->gstResults[$gindx] = $progSearch->doSearch($dbh, $searchFor);
	        }

	    }

	    return $this->gstResults;

	}

	public function searchDoctor(\PDO $dbh) {

	    $doctorResults = [];

	    if (isset($this->formUserData['hospital']['doctor']) && $this->formUserData['hospital']['doctor'] != '') {

	       $memberSearch = new MemberSearch($this->formUserData['hospital']['doctor']);

	       $doctorResults = $memberSearch->volunteerCmteFilter($dbh, VolMemberType::Doctor, '');
	    }

	    return $doctorResults;
	}

	public function setPatient(\PDO $dbh, $idPatient) {

	    $uS = Session::getInstance();
	    $idP = intval($idPatient, 10);
	    $searchNameData = NULL;
	    $patient = NULL;

	    If ($idP < 0) {
	        return FALSE;
	    }

	    // Figure out which SearchNameData object to use
	    if ($idP == 0) {

	        $searchNameData = $this->loadSearchFor($dbh, $this->formUserData['patient']);
	    } else {

	        $searchNameData = $this->LoadMemberResult($dbh, $idP);
	    }

	    if (is_null($searchNameData) === FALSE) {
	       $patient = $this->savePatient($dbh, $idP, $searchNameData, $uS->username);

	    }

	    return $patient;

	}

	public function setGuests(\PDO $dbh, $post, PSG $psg, $maxGuests = self::MAX_GUESTS) {

	    $uS = Session::getInstance();
	    $guests = [];
	    $psg = NULL;


	    for ($indx = 0; $indx < $maxGuests; $indx++) {
	        $gindx = 'g' . $indx;

	        if (isset($post['rbGuest'.$gindx])) {
	            // Save this one

	            $searchNameData = NULL;

	            $id = intval(filter_var($post['rbGuest'.$gindx], FILTER_SANITIZE_NUMBER_INT), 10);

	            if ($id == 0) {
	                $searchNameData = $this->loadSearchFor($dbh, $this->formUserData['Guests'][$gindx]);
	            } else {
	                $searchNameData = $this->LoadMemberResult($dbh, $id);
	            }

	            if (is_null($searchNameData) === FALSE) {
	                $guest = $this->saveGuest($dbh, $id, $psg, $searchNameData, $uS->username);
                    $guests[] = $guest->getIdName();
	           }
	       }
	   }

	   return $guests;
	}

	public function setDates() {

	    if (isset($this->formUserData['checkindate'])) {
	        $this->CkinDT = new \DateTime($this->formUserData['checkindate']);
	    } else {
	        throw new \Exception('Check-in date is missing.');
	    }

	    if (isset($this->formUserData['checkoutdate'])) {
	        $this->CkoutDT = new \DateTime($this->formUserData['checkoutdate']);
	    } else {
	        throw new \Exception('Checkout date is missing.');
	    }

	}

	protected function LoadMemberResult(\PDO $dbh, $id) {

	    $searchResult = new SearchResults();

	    $stmt = $dbh->query(ProgressiveSearch::getMemberQuery($id));

	    while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
	        $searchResult->loadMeFrom($r);
	    }

	    return $searchResult;

	}

	protected function savePatient(\PDO $dbh, $idP, SearchNameData $searchNameData, $username) {

	    $post = $this->memberDataPost($searchNameData);

	    $patient = new Patient($dbh, '', $idP);

	    $patient->save($dbh, $post, $username);

	    // PSG
	    $psg = new Psg($dbh, 0, $patient->getIdName());
	    $psg->setNewMember($patient->getIdName(), RelLinkType::Self);
	    $psg->savePSG($dbh, $patient->getIdName(), $username);

	    // Registration
	    $reg = new Registration($dbh, $psg->getIdPsg());
	    $reg->saveRegistrationRs($dbh, $psg->getIdPsg(), $username);

	    return $patient;
	}

	protected function saveGuest(\PDO $dbh, $idName, PSG $psg, SearchNameData $searchNameData, $username) {

	    $post = $this->memberDataPost($searchNameData);

	    $guest = new Guest($dbh, '', $idName);

	    $guest->save($dbh, $post, $username);

        // PSG

	    $psg->setNewMember($guest->getIdName(), ($searchNameData->getRelationship() == "" ? RelLinkType::Friend : $searchNameData->getRelationship()));
	    $psg->saveMembers($dbh, $username);

	}

	public function makeNewReservation(\PDO $dbh, PSG $psg, array $guests) {

	    $uS = Session::getInstance();

	    $reg = new Registration($dbh, $psg->getIdPsg());
	    $reg->saveRegistrationRs($dbh, $this->reserveData->getIdPsg(), $uS->username);

	    $hospStay = new HospitalStay($dbh, $psg->getIdPatient());
	    $idHospStay = $hospStay->save($dbh, $psg, 0, $uS->username);


        $resv = Reservation_1::instantiateFromIdReserv($dbh, 0);

        $resv->setExpectedArrival($this->CkinDT_format('Y-m-d'))
            ->setExpectedDeparture($this->CkoutDT->format('Y-m-d'))
            ->setIdGuest($psg->getIdPatient())
            ->setStatus(ReservationStatus::Waitlist)
            ->setIdHospitalStay($idHospStay)
            ->setNumberGuests(count($guests))
            ->setIdResource(0);

        $resv->saveReservation($dbh, $reg->getIdRegistration(), $uS->username);

        $resv->saveConstraints($dbh, array());

        // Save Reservtaion geusts - patient
        $rgRs = new Reservation_GuestRS();
        $rgRs->idReservation->setStoredVal($resv->getIdResv());
        $rgRs->idGuest->setNewVal($psg->getIdPatient());
        $rgRs->Primary_Guest->setNewVal('1');
        EditRS::insert($dbh, $rgRs);

        foreach ($guests as $g) {

            $rgRs = new Reservation_GuestRS();
            $rgRs->idReservation->setStoredVal($resv->getIdResv());
            $rgRs->idGuest->setNewVal($g);
            $rgRs->Primary_Guest->setNewVal('');
            EditRS::insert($dbh, $rgRs);
        }

        // Save guest referral doc id
        $rrRs = new Reservation_ReferralRS();
        $rrRs->Reservation_Id->setNewVal($resv->getIdReservation());
        $rrRs->Document_Id->setNewVal($this->referralDocId);

        EditRS::insert($dbh, $rrRs);

        return $resv->getIdReservation();

	}

	public function chosenMemberMkup(AbstractRole $role) {

	    $tbl = new HTMLTable();

	    $r = $role->getRoleMember();


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
	        .HTMLTable::makeTh('Address')
	        .HTMLTable::makeTh('No Return')
	        );

	    $tbl->addBodyTr(
	        HTMLTable::makeTd($role->getIdName())
	        .HTMLTable::makeTd($r->get_firstName())
	        .HTMLTable::makeTd($r->get_middleName())
	        .HTMLTable::makeTd($r->get_lastName())
	        .HTMLTable::makeTd($r->get_nickName())
	        .HTMLTable::makeTd(date('M j, Y', strtotime($r->get_birthDate())))
	        .HTMLTable::makeTd($role->getPhonesObj()->get_Data(PhonePurpose::Home)['Phone_Num'])
	        .HTMLTable::makeTd($role->getEmailsObj()->get_Data(EmailPurpose::Home)['Email'])
	        .HTMLTable::makeTd($this->createAddrString($role->getAddrObj()->get_recordSet(AddressPurpose::Home)))
	        .HTMLTable::makeTd($role->getNoReturn())
	        , array('class'=>'hhk-resultUserData'));

	    return $tbl->generateMarkup(array('class'=>'hhk-tdbox'));
	}

	public function createAddrString(NameAddressRS $addr) {

	    if (is_null($addr) === FALSE) {

	    return ($addr->Address_2->getStoredVal() == '' ? $addr->Address_1->getStoredVal() : $addr->Address_1->getStoredVal() . ', ' . $addr->Address_2->getStoredVal())
    	    . ($addr->City->getStoredVal() == '' ? '' : ', ' . $addr->City->getStoredVal())
    	    . ($addr->State_Province->getStoredVal() == '' ? '' : ', ' . $addr->State_Province->getStoredVal())
    	    . ($addr->Postal_Code->getStoredVal() == '' ? '' : ', ' . $addr->Postal_Code->getStoredVal())
    	    . ($addr->Country_Code->getStoredVal() == '' ? '' : ', ' . $addr->Country_Code->getStoredVal());
	    }

	    return '';
	}

	public function createPatientMarkup() {

	    $uS = Session::getInstance();
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
	        .($uS->county ? HTMLTable::makeTh('County') : '')
	        .HTMLTable::makeTh('State')
	        .HTMLTable::makeTh('Zip Code')
	        .HTMLTable::makeTh('Country')
	        .HTMLTable::makeTh('No Return')
	        );

	    // Original data
	    $tbl->addBodyTr(
	        HTMLTable::makeTd(HTMLInput::generateMarkup('0', array('type'=>'radio', 'name'=>'rbPatient', 'id'=>'patSel0')))
	        .HTMLTable::makeTd($this->patSearchFor->getNameFirst())
	        .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	        .HTMLTable::makeTd($this->patSearchFor->getNameLast())
	        .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	        .HTMLTable::makeTd(($this->patSearchFor->getBirthDate() == '' ? '' : date('M d, Y', strtotime($this->patSearchFor->getBirthDate()))))
	        .HTMLTable::makeTd(preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $this->patSearchFor->getPhone()))
	        .HTMLTable::makeTd($this->patSearchFor->getEmail())
	        .HTMLTable::makeTd($this->patSearchFor->getAddressStreet())
	        .HTMLTable::makeTd($this->patSearchFor->getAddressCity())
	        .($uS->county ? HTMLTable::makeTd($this->patSearchFor->getAddressCounty()) : '')
	        .HTMLTable::makeTd($this->patSearchFor->getAddressState())
	        .HTMLTable::makeTd($this->patSearchFor->getAddressZip())
	        .HTMLTable::makeTd($this->patSearchFor->getAddressCountry())
	        .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	        , array('class'=>'hhk-origUserData'));

	    $cols = ($uS->county ? 15 : 14);
	    $tbl->addBodyTr(HTMLTable::makeTd('', array('colspan'=>$cols)));

	    // Searched data
	    foreach ($this->patResults as $r) {
	        $tbl->addBodyTr(
	            HTMLTable::makeTd(HTMLInput::generateMarkup($r->getId(), array('type'=>'radio', 'name'=>'rbPatient', 'id'=>'patSel'.$r->getId())))
	            .HTMLTable::makeTd($r->getNameFirst())
	            .HTMLTable::makeTd($r->getNameMiddle())
	            .HTMLTable::makeTd($r->getNameLast())
	            .HTMLTable::makeTd($r->getNickname())
	            .HTMLTable::makeTd($r->getBirthDate())
	            .HTMLTable::makeTd($r->getPhone())
	            .HTMLTable::makeTd($r->getEmail())
	            .HTMLTable::makeTd($r->getAddressStreet())
	            .HTMLTable::makeTd($r->getAddressCity())
	            .($uS->county ? HTMLTable::makeTd($r->getAddressCounty()) : '')
	            .HTMLTable::makeTd($r->getAddressState())
	            .HTMLTable::makeTd($r->getAddressZip())
	            .HTMLTable::makeTd($r->getAddressCountry())
	            .HTMLTable::makeTd($r->getNoReturn())
	            , array('class'=>'hhk-resultUserData'));
	    }

	    return $tbl->generateMarkup(array('class'=>'hhk-tdbox'));
	}

	public function guestsMarkup($numberGuests = self::MAX_GUESTS) {

	    $markup = '';


	    foreach ($this->gstSearchFor as $g => $d) {

	        $markup .= $this->createGuestMarkup($g, $d, $this->gstResults[$g]);

	    }

	    return $markup;
	}

	public function createGuestMarkup($gindx, SearchFor $guestSearchFor, array $guestResults) {

	   $uS = Session::getInstance();
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
	       .($uS->county ? HTMLTable::makeTh('County') : '')
	        .HTMLTable::makeTh('State')
	        .HTMLTable::makeTh('Zip Code')
	        .HTMLTable::makeTh('Country')
	        .HTMLTable::makeTh('No Return')
	        );

	   // Preset checked if only one.
	   $idArray = array('type'=>'radio', 'name'=>'rbGuest'.$gindx);

	   if (count($guestResults) == 0) {
	       $idArray['checked'] = 'checked';
	   }

	   // Original data
	   $tbl->addBodyTr(
	       HTMLTable::makeTd(HTMLInput::generateMarkup('0', $idArray))
	        .HTMLTable::makeTd($guestSearchFor->getNameFirst())
	       .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	       .HTMLTable::makeTd($guestSearchFor->getNameLast())
	       .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	       .HTMLTable::makeTd((isset($uS->guestLookups[GLTableNames::PatientRel][$guestSearchFor->getRelationship()]) ? $uS->guestLookups[GLTableNames::PatientRel][$guestSearchFor->getRelationship()][1] : ''))
	       .HTMLTable::makeTd(preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $guestSearchFor->getPhone()))
	       .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	       .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	       .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	       .($uS->county ? HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;')) : '')
	       .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	       .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	       .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	       .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	        , array('class'=>'hhk-origUserData'));

	   $cols = ($uS->county ? 15 : 14);
	   $tbl->addBodyTr(HTMLTable::makeTd('', array('colspan'=>$cols)));

	   // Searched data
	   foreach ($guestResults as $r) {
	       $tbl->addBodyTr(
	           HTMLTable::makeTd(HTMLInput::generateMarkup($r->getId(), array('type'=>'radio', 'name'=>'rbGuest'.$gindx)))
	           .HTMLTable::makeTd($r->getNameFirst())
	           .HTMLTable::makeTd($r->getNameMiddle())
	           .HTMLTable::makeTd($r->getNameLast())
	           .HTMLTable::makeTd($r->getNickname())
	           .HTMLTable::makeTd($r->getRelationship())
	           .HTMLTable::makeTd($r->getPhone())
	           .HTMLTable::makeTd($r->getEmail())
	           .HTMLTable::makeTd($r->getAddressStreet())
	           .HTMLTable::makeTd($r->getAddressCity())
	           .($uS->county ? HTMLTable::makeTd($r->getAddressCounty()) : '')
	           .HTMLTable::makeTd($r->getAddressState())
	           .HTMLTable::makeTd($r->getAddressZip())
	           .HTMLTable::makeTd($r->getAddressCountry())
	           .HTMLTable::makeTd($r->getNoReturn())
	           , array('class'=>'hhk-resultUserData'));
	   }


	   return $tbl->generateMarkup(array('class'=>'hhk-tdbox'));
	}

	public function datesMarkup() {

	    $ckinDate = '';
	    $ckoutDate = '';

	    if (is_null($this->CkinDT) === FALSE) {

	        $ckinDate = HTMLContainer::generateMarkup('span', 'Arrival: '
	            . HTMLInput::generateMarkup($this->CkinDT->format('M j, Y'), array('size'=>'13')) );
	    }

	    if (is_null($this->CkoutDT) === FALSE) {

	        $ckoutDate = HTMLContainer::generateMarkup('span', 'Expected Departure: '
	            . HTMLInput::generateMarkup($this->CkoutDT->format('M j, Y'), array('size'=>'13'))
	            , array('style'=>'margin-left:.9em;'));
	    }

	    return HTMLContainer::generateMarkup('div', $ckinDate . $ckoutDate, array('style'=>'font-size:.9em;'));
	}

	protected function memberDataPost(SearchNameData $data) {

	    $post = array(
	        'txtFirstName' => $data->getNameFirst(),
	        'txtLastName'=>  $data->getNameLast(),

	        'txtMiddleName'=>  $data->getNameMiddle(),
	        'txtNickname'=>  $data->getNickname(),
	        'txtBirthDate'=> $data->getBirthDate(),

	        'selStatus'=>'a',
	        'selMbrType'=>'ai',
	        'sel_Gender'=>$data->getGender(),
	    );


	    $post['rbEmPref'] = ($data->getEmail() == '' ? '' : '1');
	    $post['txtEmail'] = array('1'=>$data->getEmail());
	    $post['rbPhPref'] = "dh";
	    $post['txtPhone'] = array('dh'=>preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $data->getPhone()));

	    $adr1 = array('1' => array(
	        'address1' => $data->getAddressStreet1(),
	        'address2' => $data->getAddressStreet2(),
	        'city' => $data->getAddressCity(),
	        'county'=>  $data->getAddressCounty(),
	        'state' => $data->getAddressState(),
	        'country' => $data->getAddressCountry(),
	        'zip' => $data->getAddressZip()));

	    $post['rbPrefMail'] = '1';
	    $post['adr'] = $adr1;

	    return $post;

	}

	public function setReferralStatus($dbh, ReferralFormStatus $status, $idPsg) {

	    $this->formDocument->updateStatus($dbh, $status);
	    $this->formDocument->linkNew($dbh, 0, $idPsg);

	}
}

