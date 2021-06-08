<?php

namespace HHK\House;

use HHK\Document\FormDocument;
use HHK\SysConst\ReferralFormStatus;

class ReferralForm {
	
	protected $referralDocId;
	
	public function __construct(\PDO $dbh, $referralDocId) {
		
		//Referral Form
		$formDocument = new FormDocument();
		$formDocument->loadDocument($dbh, $referralDocId);
		$userData = $formDocument->getUserData();
		
		// Patient
		if (isset($userData['patientFirstName']) && isset($userData['patientLastName'])) {
			
			
			
			// Guests
			
			// hospital & doctor search results
			
			
			
			// When resv is created and saved:
			$formDocument->updateStatus($dbh, ReferralFormStatus::Accepted);
		}else {
			$paymentMarkup .= "The Patient Name is not set. ";
		}
		
	}
}

