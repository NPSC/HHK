<?php

namespace HHK\House;

use HHK\Document\FormDocument;
use HHK\HTMLControls\{HTMLContainer,HTMLTable};
use HHK\Member\ProgressiveSearch\ProgressiveSearch;
use HHK\Member\ProgressiveSearch\SearchNameData\{SearchNameData, SearchFor};
use HHK\SysConst\{AddressPurpose, EmailPurpose, PhonePurpose, GLTableNames, RelLinkType, ReservationStatus};
use HHK\Member\Address\CleanAddress;
use HHK\HTMLControls\HTMLInput;
use HHK\sec\Session;
use HHK\Member\Role\{AbstractRole, Patient, Guest};
use HHK\Tables\EditRS;
use HHK\Tables\Name\NameAddressRS;
use HHK\House\Reservation\Reservation_1;
use HHK\House\Hospital\HospitalStay;
use HHK\Tables\Reservation\Reservation_GuestRS;
use HHK\Member\ProgressiveSearch\SearchNameData\SearchNameDataInterface;
use HHK\SysConst\ReferralFormStatus;
use HHK\Exception\RuntimeException;


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
	protected $formDoc;

	protected $patSearchFor;
	protected $patResults;

	protected $gstSearchFor = [];
	protected $gstResults = [];

	protected $idPatient;
	protected $idPsg;
	protected $idRegistration;
	protected $idHospitalStay;

	protected $CkinDT = NULL;
	protected $CkoutDT = NULL;

	// Patient search includes
	const HTML_Incl_Birthday = 'cbPIncludeBD';
	const HTML_Incl_Phone = 'cbPIncludePhone';
	const HTML_Incl_Email = 'cbPIncludeEmail';

    const MAX_GUESTS = 4;

    /**
     * Open the referral and pull in the user data.
     *
     * @param \PDO $dbh
     * @param integer $referralDocId
     * @throws \Exception
     */
	public function __construct(\PDO $dbh, $referralDocId) {

		//Referral Form
		$this->referralDocId = intval($referralDocId, 10);

		$this->formDoc = new FormDocument();

		if ($this->formDoc->loadDocument($dbh, $this->referralDocId)) {

		    if (is_null($this->formUserData = $this->formDoc->getUserData())) {
		        throw new \Exception("Referral form user input is blank.  Document Id = " . $this->referralDocId);
		    }

		} else {
		    throw new \Exception("Referral form not found.  Document Id = " . $this->referralDocId);
		}

	}

	/**
	 * Fills a SearchFor object in preparatino for the db search.
	 *
	 * @param \PDO $dbh
	 * @param array $formUserData The userdata array with patient or guest already selected.
	 * @param array $searchIncludes The columns to include in the search.
	 * @return \HHK\Member\ProgressiveSearch\SearchNameData\SearchFor
	 */
	public static function loadSearchFor(\PDO $dbh, array $formUserData, array $searchIncludes = []) {

	    $searchFor = new SearchFor();

	    // First and last names
	    if (isset($formUserData['firstName'])) {
	        $searchFor->setNameFirst($formUserData['firstName']);
	    }
	    if (isset($formUserData['lastName'])) {
	        $searchFor->setNameLast($formUserData['lastName']);
	    }

	    if (isset($formUserData['middleName'])) {
	        $searchFor->setNameMiddle($formUserData['middleName']);
	    }

	    if (isset($formUserData['nickname'])) {
	        $searchFor->setNickname($formUserData['nickname']);
	    }

	    if (isset($formUserData['prefix'])) {
	        $searchFor->setPrefix($formUserData['prefix']);
	    }

	    if (isset($formUserData['suffix'])) {
	        $searchFor->setSuffix($formUserData['suffix']);
	    }

	    // Birthdate
	    if (isset($formUserData['birthdate']) && $formUserData['birthdate'] != '') {
	        $searchFor->setBirthDate($formUserData['birthdate'], (isset($searchIncludes[self::HTML_Incl_Birthday]) ? TRUE : FALSE));
	    }

	    // gender
	    if (isset($formUserData['demographics']['gender']) && $formUserData['demographics']['gender'] != '') {
	        $searchFor->setGender($formUserData['demographics']['gender']);
	    }

	    // ethnicity
	    if (isset($formUserData['demographics']['ethnicity']) && $formUserData['demographics']['ethnicity'] != '') {
	        $searchFor->setEthnicity($formUserData['demographics']['ethnicity']);
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
	    if (isset($formUserData['address']['street']) && $formUserData['address']['street'] != '') {
	        $searchFor->setAddressStreet($formUserData['address']['street'], new CleanAddress($dbh));
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

	    // Relationship
	    if (isset($formUserData['relationship']) && $formUserData['relationship'] != '') {
	        $searchFor->setRelationship($formUserData['relationship']);
	    }

	    // Emergency First
	    if (isset($formUserData['emerg']['firstName']) && $formUserData['emerg']['firstName'] != '') {
	        $searchFor->setEmrgFirst($formUserData['emerg']['firstName']);
	    }

	    // Emergency Last
	    if (isset($formUserData['emerg']['lastName']) && $formUserData['emerg']['lastName'] != '') {
	        $searchFor->setEmrgLast($formUserData['emerg']['lastName']);
	    }

	    // Emergency Phone
	    if (isset($formUserData['emerg']['phone']) && $formUserData['emerg']['phone'] != '') {
	        $searchFor->setEmrgPhone($formUserData['emerg']['phone']);
	    }

	    // Emergency altphone
	    if (isset($formUserData['emerg']['altphone']) && $formUserData['emerg']['altphone'] != '') {
	        $searchFor->setEmrgAltPhone($formUserData['emerg']['altphone']);
	    }

	    // Emergency relation
	    if (isset($formUserData['emerg']['relation']) && $formUserData['emerg']['relation'] != '') {
	        $searchFor->setEmrgRelation($formUserData['emerg']['relation']);
	    }

	    return $searchFor;

	}

	/**
	 *
	 * @param \PDO $dbh
	 * @param array $searchIncludes The columns to include in the search.
	 * @throws \Exception If pateint first and last name is missing.
	 * @return \HHK\Member\ProgressiveSearch\SearchNameData\SearchResults[]
	 */
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

	/**
	 *
	 * @param \PDO $dbh
	 * @param integer $maxGuests Defaults to const MAX_GUESTS.
	 * @return array An array of SearchResults objects, one per guest searched.
	 */
	public function searchGuests(\PDO $dbh, $maxGuests = self::MAX_GUESTS) {

	    $this->gstResults = [];

	    if (isset($this->formUserData['guests']) && is_array($this->formUserData['guests'])) {

    	    for ($indx = 0; $indx < $maxGuests; $indx++) {

    	        $gindx = 'g' . $indx;

    	        if (isset($this->formUserData['guests'][$gindx]['firstName']) && isset($this->formUserData['guests'][$gindx]['lastName'])
    	            && $this->formUserData['guests'][$gindx]['firstName'] != '' && $this->formUserData['guests'][$gindx]['lastName'] != '') {

    	            $searchFor = $this->loadSearchFor($dbh, $this->formUserData['guests'][$gindx]);
    	            $searchFor->setPsgId($this->idPsg);


    	            // Relationship
    	            if (isset($this->formUserData['guests'][$gindx]['relationship']) && $this->formUserData['guests'][$gindx]['relationship'] != '') {
    	                $searchFor->setRelationship($this->formUserData['guests'][$gindx]['relationship']);
    	            }

    	            $this->gstSearchFor[$gindx] = $searchFor;

                    // Do the search which creats a new SearchResult.
    	            $progSearch = new ProgressiveSearch();
    	            $this->gstResults[$gindx] = $progSearch->doSearch($dbh, $searchFor);

    	        }

    	    }
	    }

	    return $this->gstResults;
	}

	/**
	 * Defines and saves the selected patient.
	 *
	 * @param \PDO $dbh
	 * @param integer $idPatient
	 * @return boolean|NULL|\HHK\Member\Role\Patient
	 */
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

	        $searchNameData = $this->LoadMemberData($dbh, $idP, new SearchNameData());
	    }

	    if (is_null($searchNameData) === FALSE) {
	       $patient = $this->savePatient($dbh, $idP, $searchNameData, $uS->username);

	    }

	    return $patient;

	}

	/**
	 * Defines and saves the selected guests.
	 *
	 * @param \PDO $dbh
	 * @param array $post
	 * @param PSG $psg
	 * @param integer $maxGuests
	 * @return mixed[]
	 */
	public function setGuests(\PDO $dbh, $post, PSG $psg, $maxGuests = self::MAX_GUESTS) {

	    $uS = Session::getInstance();
	    $guests = [];


	    for ($indx = 0; $indx < $maxGuests; $indx++) {
	        $gindx = 'g' . $indx;

	        if (isset($post['rbGuest'.$gindx])) {
	            // Save this one

	            $searchNameData = NULL;

	            $id = intval(filter_var($post['rbGuest'.$gindx], FILTER_SANITIZE_NUMBER_INT), 10);

	            if ($id == 0) {

	                $searchNameData = $this->loadSearchFor($dbh, $this->formUserData['guests'][$gindx]);

	            } else {

	                $searchNameData = $this->LoadMemberData($dbh, $id, new SearchNameData());

	                // Update Relationship
	                if (isset($this->formUserData['guests'][$gindx]['relationship']) && $this->formUserData['guests'][$gindx]['relationship'] != '') {
	                    $searchNameData->setRelationship($this->formUserData['guests'][$gindx]['relationship']);
	                }

	            }

	            if (is_null($searchNameData) === FALSE) {
	                $guest = $this->saveGuest($dbh, $id, $psg, $searchNameData, $uS->username);
                    $guests[] = $guest->getIdName();
	           }
	       }
	   }

	   return $guests;
	}

	/**
	 * Sets the date parameter
	 * @throws \Exception If dates are missing.
	 */
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

	/**
	 * Loads member data from the database.
	 *
	 * @param \PDO $dbh
	 * @param integer $id IdName of member to get data from.
	 * @param SearchNameDataInterface $snd The object to be loaded.
	 * @return \HHK\Member\ProgressiveSearch\SearchNameData\SearchNameDataInterface
	 */
	protected function LoadMemberData(\PDO $dbh, $id, SearchNameDataInterface $snd) {

	    $stmt = $dbh->query(ProgressiveSearch::getMemberQuery($id));

	    while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
	        $snd->loadMeFrom($r);
	    }

	    return $snd;

	}

	/**
	 * Save defined patient (idP) along with PSG, Registration, and Hospital_Stay.
	 *
	 * @param \PDO $dbh
	 * @param integer $idP
	 * @param SearchNameData $searchNameData
	 * @param string $username
	 * @return \HHK\Member\Role\Patient
	 */
	protected function savePatient(\PDO $dbh, $idP, SearchNameData $searchNameData, $username) {

	    $post = $this->memberDataPost($searchNameData);

	    $patient = new Patient($dbh, '', $idP);

	    $patient->save($dbh, $post, $username);

	    // PSG
	    $psg = new PSG($dbh, 0, $patient->getIdName());
	    $psg->setNewMember($patient->getIdName(), RelLinkType::Self);
	    $psg->savePSG($dbh, $patient->getIdName(), $username);
	    $this->idPsg = $psg->getIdPsg();

	    // Registration
	    $reg = new Registration($dbh, $psg->getIdPsg());
	    $reg->saveRegistrationRs($dbh, $psg->getIdPsg(), $username);

	    $hospStay = new HospitalStay($dbh, $psg->getIdPatient());
	    $hospStay->setHospitalId($this->setHospital());
	    $hospStay->save($dbh, $psg, 0, $username);

	    return $patient;
	}

	/**
	 * Save defined guest (idname) and adds guest to patient's PSG.
	 *
	 * @param \PDO $dbh
	 * @param integer $idName
	 * @param PSG $psg Patient's PSG
	 * @param SearchNameData $searchNameData
	 * @param string $username
	 * @return \HHK\Member\Role\Guest
	 */
	protected function saveGuest(\PDO $dbh, $idName, PSG $psg, SearchNameData $searchNameData, $username) {

	    $post = $this->memberDataPost($searchNameData);

	    $guest = new Guest($dbh, '', $idName);

	    $guest->save($dbh, $post, $username);

        // PSG
	    $psg->setNewMember($guest->getIdName(), ($searchNameData->getRelationship() == "" ? RelLinkType::Friend : $searchNameData->getRelationship()));
	    $psg->saveMembers($dbh, $username);

	    return $guest;
	}

	/**
	 *
	 * @param \PDO $dbh
	 * @param integer $idPatient
	 * @throws RuntimeException
	 */
	public function finishReferral(\PDO $dbh, $idPatient) {

	    // Get idPsg
	    $psg = new PSG($dbh, 0, $idPatient);

	    if ($psg->getIdPsg() < 1) {
	        throw new RuntimeException('Patient has no PSG.  Patient Id = '.$idPatient . '.  ');
	    }else {

	        // Save Guests
	        $guests = $this->setGuests($dbh, $_POST, $psg);

	        // Create reservation
	        $idResv = $this->makeNewReservation($dbh, $psg, $guests);

	        if ($idResv > 0) {

	            // Set referral form status to done.
	            $this->setReferralStatus($dbh, ReferralFormStatus::Accepted, $psg->getIdPsg());

	            // Load reserve page.
	            header('location:Reserve.php?rid='.$idResv);
	        }

	        throw new RuntimeException('The People are Saved, but a reservation was not created yet.  ');
	    }

	}

	/**
	 * Make a new reservation for patient and any guests.
	 * @param \PDO $dbh
	 * @param PSG $psg Patient's PSG
	 * @param array $guests array of guest member id's
	 * @return number|mixed
	 */
	protected function makeNewReservation(\PDO $dbh, PSG $psg, array $guests) {

	    $uS = Session::getInstance();

	    // Reservation Checkin set?
	    if (is_null($this->CkinDT)) {
	        return 0;
	    }

	    // Replace missing checkout date with check-in + default days.
	    if (is_null($this->CkoutDT)) {
	        $this->CkoutDT = $this->CkinDT->add(new \DateInterval('P' . $uS->DefaultDays . 'D'));
	    }

	    $reg = new Registration($dbh, $psg->getIdPsg());
	    $hospStay = new HospitalStay($dbh, $psg->getIdPatient());
        $resv = Reservation_1::instantiateFromIdReserv($dbh, 0);

        $resv->setExpectedArrival($this->CkinDT->format('Y-m-d'))
            ->setExpectedDeparture($this->CkoutDT->format('Y-m-d'))
            ->setIdGuest($psg->getIdPatient())
            ->setStatus(ReservationStatus::Waitlist)
            ->setIdHospitalStay($hospStay->getIdHospital_Stay())
            ->setNumberGuests(count($guests))
            ->setIdResource(0)
            ->setRoomRateCategory($uS->RoomRateDefault)
            ->setIdReferralDoc($this->referralDocId);

        $resv->saveReservation($dbh, $reg->getIdRegistration(), $uS->username);
        $resv->saveConstraints($dbh, array());

        // Save Reservtaion guests - patient
        $rgRs = new Reservation_GuestRS();
        $rgRs->idReservation->setNewVal($resv->getIdReservation());
        $rgRs->idGuest->setNewVal($psg->getIdPatient());
        $rgRs->Primary_Guest->setNewVal('1');
        EditRS::insert($dbh, $rgRs);

        foreach ($guests as $g) {

            $rgRs = new Reservation_GuestRS();
            $rgRs->idReservation->setNewVal($resv->getIdReservation());
            $rgRs->idGuest->setNewVal($g);
            $rgRs->Primary_Guest->setNewVal('');
            EditRS::insert($dbh, $rgRs);
        }

        return $resv->getIdReservation();

	}

	/**
	 * Selected patient Markup.
	 *
	 * @param AbstractRole $role
	 * @return string An HTML Table
	 */
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
	        .HTMLTable::makeTd($r->get_birthDate() == '' ? '' : date('M j, Y', strtotime($r->get_birthDate())))
	        .HTMLTable::makeTd($role->getPhonesObj()->get_Data(PhonePurpose::Home)['Phone_Num'])
	        .HTMLTable::makeTd($role->getEmailsObj()->get_Data(EmailPurpose::Home)['Email'])
	        .HTMLTable::makeTd($this->createAddrString($role->getAddrObj()->get_recordSet(AddressPurpose::Home)))
	        .HTMLTable::makeTd($role->getNoReturn())
	        , array('class'=>'hhk-resultUserData'));

	    return $tbl->generateMarkup(array('class'=>'hhk-tdbox'));
	}

	/**
	 * Concatenates the full address into a single string.
	 *
	 * @param NameAddressRS $addr
	 * @return string
	 */
	public function createAddrString(NameAddressRS $addr) {

	    if (is_null($addr) === FALSE) {

	        $uS = Session::getInstance();

       	    return ($addr->Address_2->getStoredVal() == '' ? $addr->Address_1->getStoredVal() : $addr->Address_1->getStoredVal() . ', ' . $addr->Address_2->getStoredVal())
    	    . ($addr->City->getStoredVal() == '' ? '' : ', ' . $addr->City->getStoredVal())
    	    . ($uS->county ? ($addr->County->getStoredVal() == '' ? '' : ', ' . $addr->County->getStoredVal()) : '')
    	    . ($addr->State_Province->getStoredVal() == '' ? '' : ', ' . $addr->State_Province->getStoredVal())
    	    . ($addr->Postal_Code->getStoredVal() == '' ? '' : ', ' . $addr->Postal_Code->getStoredVal())
    	    . ($addr->Country_Code->getStoredVal() == '' ? '' : ', ' . $addr->Country_Code->getStoredVal());
	    }

	    return '';
	}

	/**
	 * Patient selection markup.
	 *
	 * @return string
	 */
	public function createPatientMarkup() {

	    $uS = Session::getInstance();
	    $tbl = new HTMLTable();

	    //Header titles
	    $tbl->addHeaderTr(
	        HTMLTable::makeTh('Id')
	        . HTMLTable::makeTh('First Name')
	        .HTMLTable::makeTh('Middle')
	        .HTMLTable::makeTh('Last Name')
	        .HTMLTable::makeTh('Suffix')
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

	    $idArray = array('type'=>'radio', 'name'=>'rbPatient');

	    if (count($this->patResults) == 0) {
	        $idArray['checked'] = 'checked';
	    }

	    $cols = ($uS->county ? 16 : 15);

	    // Original data
	    //$tbl->addBodyTr(HTMLTable::makeTd('Referral Form Patient Submission:', array('colspan'=>$cols)));

	    $tbl->addBodyTr(
	        HTMLTable::makeTd(HTMLInput::generateMarkup('0', $idArray))
	        .HTMLTable::makeTd($this->patSearchFor->getNameFirst())
	        .HTMLTable::makeTd($this->patSearchFor->getNameMiddle(), array('id'=>'tbPatMiddle'))
	        .HTMLTable::makeTd($this->patSearchFor->getNameLast())
	        .HTMLTable::makeTd($this->patSearchFor->getSuffixTitle(), array('id'=>'tbPatSuffix'))
	        .HTMLTable::makeTd($this->patSearchFor->getNickname(), array('id'=>'tbPatNickname'))
	        .HTMLTable::makeTd(($this->patSearchFor->getBirthDate() == '' ? '' : date('M d, Y', strtotime($this->patSearchFor->getBirthDate()))), array('id'=>'tbPatBD'))
	        .HTMLTable::makeTd(preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $this->patSearchFor->getPhone()), array('id'=>'tbPatPhone'))
	        .HTMLTable::makeTd($this->patSearchFor->getEmail(), array('id'=>'tbPatEmail'))
	        .HTMLTable::makeTd($this->patSearchFor->getAddressStreet(), array('id'=>'tbPatStreet'))
	        .HTMLTable::makeTd($this->patSearchFor->getAddressCity(), array('id'=>'tbPatCity'))
	        .($uS->county ? HTMLTable::makeTd($this->patSearchFor->getAddressCounty(), array('id'=>'tbPatCounty')) : '')
	        .HTMLTable::makeTd($this->patSearchFor->getAddressState(), array('id'=>'tbPatState'))
	        .HTMLTable::makeTd($this->patSearchFor->getAddressZip(), array('id'=>'tbPatZip'))
	        .HTMLTable::makeTd($this->patSearchFor->getAddressCountry(), array('id'=>'tbPatCountry'))
	        .HTMLTable::makeTd('', array('style'=>'background-color:#f7f1e8;'))
	        , array('class'=>'hhk-origUserData'));



	    if (count($this->patResults) > 0) {
	        // $tbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>'usePatContactData')).HTMLContainer::generateMarkup('label', ' Use this contact information', array('for'=>'usePatContactData')), array('colspan'=>$cols, 'style'=>'text-align:center;')));
	        $tbl->addBodyTr(HTMLTable::makeTd('', array('colspan'=>$cols)));
	        $tbl->addBodyTr(HTMLTable::makeTh('Matches Found in HHK', array('colspan'=>$cols, 'style'=>'text-align: left;')));
	    } else {
	        //$tbl->addBodyTr(HTMLTable::makeTd('', array('colspan'=>$cols)));
	        //$tbl->addBodyTr(HTMLTable::makeTd('No Matches Found', array('colspan'=>$cols)));
	    }

	    // Searched data
	    foreach ($this->patResults as $r) {

	        if (count($this->patResults) == 1) {
	            $idArray['checked'] = 'checked';
	        }

	        $tbl->addBodyTr(
	            HTMLTable::makeTd(HTMLInput::generateMarkup($r->getId(), $idArray))
	            .HTMLTable::makeTd($r->getNameFirst())
	            .HTMLTable::makeTd($r->getNameMiddle(), array('id'=>'tbPatMiddle'.$r->getId()))
	            .HTMLTable::makeTd($r->getNameLast())
	            .HTMLTable::makeTd($r->getSuffix(), array('id'=>'tbPatSuffix'.$r->getId()))
	            .HTMLTable::makeTd($r->getNickname(), array('id'=>'tbPatNickname'.$r->getId()))
	            .HTMLTable::makeTd($r->getBirthDate(), array('id'=>'tbPatBD'.$r->getId()))
	            .HTMLTable::makeTd($r->getPhone(), array('id'=>'tbPatPhone'.$r->getId()))
	            .HTMLTable::makeTd($r->getEmail(), array('id'=>'tbPatEmail'.$r->getId()))
	            .HTMLTable::makeTd($r->getAddressStreet(), array('id'=>'tbPatStreet'.$r->getId()))
	            .HTMLTable::makeTd($r->getAddressCity(), array('id'=>'tbPatCity'.$r->getId()))
	            .($uS->county ? HTMLTable::makeTd($r->getAddressCounty(), array('id'=>'tbPatCounty'.$r->getId())) : '')
	            .HTMLTable::makeTd($r->getAddressState(), array('id'=>'tbPatState'.$r->getId()))
	            .HTMLTable::makeTd($r->getAddressZip(), array('id'=>'tbPatZip'.$r->getId()))
	            .HTMLTable::makeTd($r->getAddressCountry(), array('id'=>'tbPatCountry'.$r->getId()))
	            .HTMLTable::makeTd($r->getNoReturn(), array('style'=>'font-weight:bold;'))
	            , array('class'=>'hhk-resultUserData' . ($r->getNoReturn() != "" ? ' hhk-warning':'')));
	    }

	    return $tbl->generateMarkup(array('class'=>'hhk-tdbox'));
	}

	/**
	 * Guests selections markup.
	 * @param integer $numberGuests
	 * @return string
	 */
	public function guestsMarkup() {

	    $markup = '';

        // Search Each guest
	    foreach ($this->gstSearchFor as $g => $d) {

	        // remove button

	        // Guest header
	        $guestWidget = HTMLContainer::generateMarkup('div', 'Guest: ' . $d->getNameFirst() . ' ' . $d->getNameLast(), array('class'=>"ui-widget ui-widget-header ui-state-default ui-corner-top hhk-panel"));

	        $guestWidget .= HTMLContainer::generateMarkup('div', $this->createGuestMarkup($g, $d, $this->gstResults[$g]), array('class'=>'ui-corner-bottom hhk-tdbox ui-widget-content','style'=>'padding: 5px;'));

	        $markup .= HTMLContainer::generateMarkup('div', $guestWidget, array('class'=>'guestWidget mb-4'));
	    }

	    return $markup;
	}

	/**
	 *
	 * @param string $gindx Indexes the specific guest.
	 * @param SearchFor $guestSearchFor
	 * @param array $guestResults
	 * @return string
	 */
	protected function createGuestMarkup($gindx, SearchFor $guestSearchFor, array $guestResults) {

	   $uS = Session::getInstance();
	   $tbl = new HTMLTable();


	   $tbl->addHeaderTr(
	        HTMLTable::makeTh('Id')
	        . HTMLTable::makeTh('First Name')
	        .HTMLTable::makeTh('Middle')
	        .HTMLTable::makeTh('Last Name')
	       .HTMLTable::makeTh('Suffix')
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
	   $cols = ($uS->county ? 16 : 15);

	   if (count($guestResults) == 0) {
	       $idArray['checked'] = 'checked';
	   }

	   // Original data

	   $rel = (isset($uS->guestLookups[GLTableNames::PatientRel][$guestSearchFor->getRelationship()]) ? $uS->guestLookups[GLTableNames::PatientRel][$guestSearchFor->getRelationship()][1] : '');

	   //$tbl->addBodyTr(HTMLTable::makeTd('Referral Form Guest Submission:', array('colspan'=>$cols)));
	   $tbl->addBodyTr(
	       HTMLTable::makeTd(HTMLInput::generateMarkup('0', $idArray))
	        .HTMLTable::makeTd($guestSearchFor->getNameFirst())
	       .HTMLTable::makeTd($guestSearchFor->getNameMiddle())
	       .HTMLTable::makeTd($guestSearchFor->getNameLast())
	       .HTMLTable::makeTd($guestSearchFor->getSuffixTitle())
	       .HTMLTable::makeTd($guestSearchFor->getNickname())
	       .HTMLTable::makeTd($rel, array('id'=>'tbGuestRel'.$gindx))
	       .HTMLTable::makeTd(preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $guestSearchFor->getPhone()), array('id'=>'tbGuestPhone'.$gindx))
	       .HTMLTable::makeTd($guestSearchFor->getEmail(), array('id'=>'tbGuestEmail'.$gindx))
	       .HTMLTable::makeTd($guestSearchFor->getAddressStreet())
	       .HTMLTable::makeTd($guestSearchFor->getAddressCity())
	       .($uS->county ? HTMLTable::makeTd($guestSearchFor->getAddressCounty()) : '')
	       .HTMLTable::makeTd($guestSearchFor->getAddressState())
	       .HTMLTable::makeTd($guestSearchFor->getAddressZip())
	       .HTMLTable::makeTd($guestSearchFor->getAddressCountry())
	       .HTMLTable::makeTd('')
	        , array('class'=>'hhk-origUserData'));



	   if (count($guestResults) > 0) {
	       $tbl->addBodyTr(HTMLTable::makeTd('', array('colspan'=>$cols)));
	       $tbl->addBodyTr(HTMLTable::makeTh('Matches Found in HHK', array('colspan'=>$cols, 'style'=>'text-align:left;')));
	   } else {
	       //$tbl->addBodyTr(HTMLTable::makeTd('No Matches Found', array('colspan'=>$cols)));
	   }

	   // Searched data
	   foreach ($guestResults as $r) {


	       if (count($guestResults) == 1) {
	           $idArray['checked'] = 'checked';
	       }

	       $tbl->addBodyTr(
	           HTMLTable::makeTd(HTMLInput::generateMarkup($r->getId(), $idArray))
	           .HTMLTable::makeTd($r->getNameFirst())
	           .HTMLTable::makeTd($r->getNameMiddle())
	           .HTMLTable::makeTd($r->getNameLast())
	           .HTMLTable::makeTd($r->getSuffix())
	           .HTMLTable::makeTd($r->getNickname())
	           .HTMLTable::makeTd($r->getRelationship(), array('id'=>'tbGuestRel'.$gindx.$r->getId()))
	           .HTMLTable::makeTd($r->getPhone(), array('id'=>'tbGuestPhone'.$gindx.$r->getId()))
	           .HTMLTable::makeTd($r->getEmail(), array('id'=>'tbGuestEmail'.$gindx.$r->getId()))
	           .HTMLTable::makeTd($r->getAddressStreet())
	           .HTMLTable::makeTd($r->getAddressCity())
	           .($uS->county ? HTMLTable::makeTd($r->getAddressCounty()) : '')
	           .HTMLTable::makeTd($r->getAddressState())
	           .HTMLTable::makeTd($r->getAddressZip())
	           .HTMLTable::makeTd($r->getAddressCountry())
	           .HTMLTable::makeTd($r->getNoReturn(), array('style'=>'font-weight:bold'))
	           , array('class'=>'hhk-resultUserData' . ($r->getNoReturn() != "" ? ' hhk-warning':'')));
	   }


	   return $tbl->generateMarkup(array('class'=>'hhk-tdbox'));
	}

	/**
	 *
	 * @return string
	 */
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

	protected function setHospital() {

	    $hospId = 0;

	    if (isset($this->formUserData['hospital']['idHospital'])) {
	        $hospId = intval($this->formUserData['hospital']['idHospital'], 10);
	    }

	    return $hospId;
	}

	/**
	 * Builds an array to simulate a member save from a page.
	 *
	 * @param SearchNameDataInterface $data
	 * @return string[]|mixed[][]|NULL[]|string[][]|string[][][]|NULL[][][]
	 */
	protected function memberDataPost(SearchNameDataInterface $data) {

	    $post = array(
	        'txtFirstName' => $data->getNameFirst(),
	        'txtLastName'=>  $data->getNameLast(),

	        'txtMiddleName'=>  $data->getNameMiddle(),
	        'txtNickname'=>  $data->getNickname(),
	        'selPrefix'=> $data->getPrefix(),
            'selSuffix'=> $data->getSuffix(),
	        'txtBirthDate'=> $data->getBirthDate(),

	        'selStatus'=>'a',
	        'selMbrType'=>'ai',
	        'sel_Gender'=>$data->getGender(),
	        'sel_Ethnicity'=>$data->getEthnicity(),

	        'txtEmrgFirst'=>$data->getEmrgFirst(),
	        'txtEmrgLast'=>$data->getEmrgLast(),
	        'txtEmrgPhn'=>$data->getEmrgPhone(),
	        'txtEmrgAlt'=>$data->getEmrgAltPhone(),
	        'selEmrgRel'=>$data->getEmrgRelation(),
	    );


	    $post['rbEmPref'] = ($data->getEmail() == '' ? '' : EmailPurpose::Home);
	    $post['txtEmail'] = array(EmailPurpose::Home=>$data->getEmail());
	    $post['rbPhPref'] = PhonePurpose::Home;
	    $post['txtPhone'] = array(PhonePurpose::Home=>preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $data->getPhone()));

	    $adr1 = array(AddressPurpose::Home => array(
	        'address1' => $data->getAddressStreet1(),
	        'address2' => $data->getAddressStreet2(),
	        'city' => $data->getAddressCity(),
	        'county'=>  $data->getAddressCounty(),
	        'state' => $data->getAddressState(),
	        'country' => $data->getAddressCountry(),
	        'zip' => $data->getAddressZip()));

	    $post['rbPrefMail'] = AddressPurpose::Home;
	    $post['adr'] = $adr1;

	    return $post;
	}

	/**
	 *
	 * @param \PDO $dbh
	 * @param string $status A ReferralFormStatus code.
	 * @param integer $idPsg
	 */
	public function setReferralStatus(\PDO $dbh, $status, $idPsg) {

	    $this->formDoc->updateStatus($dbh, $status);
	    $this->formDoc->linkNew($dbh, 0, $idPsg);

	}

	/**
	 *
	 * @return string A ReferralFormStatus code.
	 */
	public function getReferralStatus() {
	    return $this->formDoc->getStatus();
	}

	public function getGuestCount(){
	    if(is_countable($this->gstSearchFor)){
	       return count($this->gstSearchFor);
	    }else{
	        return 0;
	    }
	}
}

