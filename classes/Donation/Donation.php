<?php

namespace HHK\Donation;

use HHK\Exception\RuntimeException;
use HHK\Member\AbstractMember;
use HHK\SysConst\{AddressPurpose, SalutationCodes, ActivityTypes, CampaignType, MemBasis, MemDesignation, MemStatus};
use HHK\Tables\{ActivityRS, EditRS};
use HHK\Tables\Donate\DonationsRS;
use HHK\Tables\Name\{NameAddressRS, NameEmailRS, NamePhoneRS};
use HHK\sec\Session;

/**
 * Donation.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * One donation: validates the input (newDonation) and writes the activity and donations records (saveDonation).
 * Validation problems throw HHK\Exception\RuntimeException with a message suitable for the user.
 *
 * @author Eric
 */
class Donation {

    protected $amount;
    protected $donor;
    protected $assocDonorId;
    protected $careofDonorId;
    protected $DateDT;
    protected $campaign;
    protected $envelopeSalutationCode;
    protected $salutationCode;
    protected $addressPurposeCode;
    protected $payTypeCode;
    protected $maxDonationAmt;
    protected $payCodes;
    protected $note = '';
    protected $fundCode = '';

    function __construct(\PDO $dbh, array $payCodes, $maxDonationAmt, $campaignCode = '') {
        $this->maxDonationAmt = $maxDonationAmt;
        $this->payCodes = $payCodes;

        if ($campaignCode != '') {
            $this->setCampaign($dbh, $campaignCode);
        } else {
            $this->campaign = null;
        }

    }


    /**
     * Validate and load a new donation.
     *
     * @param \PDO $dbh
     * @param float|string $amount
     * @param int $donorId
     * @param int $includedId spouse (individual donor) or sponsor employee (organization donor); 0 for none
     * @param string $payType
     * @param string $date any date string DateTime accepts; '' for now
     * @param string $addressPurpose which of the donor's addresses to copy onto the donation
     * @param string $salCode letter salutation code
     * @param string $envCode envelope salutation code
     * @param string $note
     * @param string $fundCode scholarship student id; ignored unless the campaign is a scholarship
     * @throws RuntimeException
     */
    public function newDonation(\PDO $dbh, $amount, $donorId, $includedId, $payType, $date = '', $addressPurpose = AddressPurpose::Home, $salCode = SalutationCodes::FirstOnly, $envCode = SalutationCodes::Formal, $note = '', $fundCode = '') {

        if (is_null($this->campaign)) {
            throw new RuntimeException('The Campaign is not specified.  ');
        }

        $this->setAmount($amount);
        $this->setDonor($dbh, $donorId, $includedId);
        $this->setPayTypeCode($payType)->setDate($date);
        $this->setAddressPurposeCode($addressPurpose)->setSalutationCode($salCode)->setEnvelopeSalutationCode($envCode);

        $this->note = (string)$note;
        $this->fundCode = ($this->campaign->get_type() == CampaignType::Scholarship ? (string)$fundCode : '');
    }

    /**
     * Insert the activity and donations records and mark the donor (and joint donor) as donors.
     *
     * @param \PDO $dbh
     * @param int $paymentId unused
     * @return int|null the new donation id, or null if there is no amount
     * @throws RuntimeException
     */
    public function saveDonation(\PDO $dbh, $paymentId = 0) {

        if ($this->getAmount() == 0) {
            return null;
        }

        // activity record
        $activRS = new ActivityRS();
        $activRS->idName->setNewVal($this->getDonorId());
        $activRS->Trans_Date->setNewVal(date('Y-m-d H:i:s'));
        $activRS->Type->setNewVal(ActivityTypes::Donation);
        $activRS->Product_Code->setNewVal('gift');
        $activRS->Source_System->setNewVal('manual');
        $activRS->Quantity->setNewVal(1);
        $activRS->Member_Type->setNewVal($this->donor->get_type());
        $activRS->Status_Code->setNewVal('a');
        $activRS->Effective_Date->setNewVal($this->getDate()->format('Y-m-d H:i:s'));
        $activRS->Amount->setNewVal($this->getAmount());
        $activRS->Pay_Method->setNewVal($this->getPayTypeCode());
        $activRS->Campaign_Code->setNewVal($this->campaign->get_campaigncode());
        $activRS->Note->setNewVal($this->note);

        $acId = EditRS::insert($dbh, $activRS);

        if ($acId <= 0) {
            throw new RuntimeException("DB Error, table=activity - insert failure.");
        }

        //Insert donation record
        $donRS = new DonationsRS();
        $donRS->Activity_Id->setNewVal($acId);
        $donRS->Donor_Id->setNewVal($this->getDonorId());
        $donRS->Type->setNewVal('local');
        $donRS->Member_type->setNewVal($this->donor->get_type());
        $donRS->Donation_Type->setNewVal('gift');
        $donRS->Org_Code->setNewVal('');
        $donRS->Status->setNewVal('a');
        $this->fillDonation($donRS);

        $this->loadContactInfo($dbh, $donRS);

        $donId = EditRS::insert($dbh, $donRS);

        if ($donId > 0) {

            self::markAsDonor($dbh, $this->getDonorId());

            if ($this->getAssocDonorId() > 0) {
                self::markAsDonor($dbh, $this->getAssocDonorId());
            }

        } else {
            throw new RuntimeException("DB Error, table=donations - insert failure.");
        }

        return $donId;
    }

    /**
     * Load an active (not deleted) donation record.
     *
     * @param \PDO $dbh
     * @param int $donId
     * @return DonationsRS
     * @throws RuntimeException when the donation doesn't exist or is deleted
     */
    public static function loadDonation(\PDO $dbh, $donId) {

        $donRS = new DonationsRS();
        $donRS->iddonations->setStoredVal(intval($donId));
        $rows = EditRS::select($dbh, $donRS, array($donRS->iddonations));

        if (\count($rows) == 0) {
            throw new RuntimeException("Donation not found.  ");
        }

        EditRS::loadRow($rows[0], $donRS);

        if ($donRS->Status->getStoredVal() != 'a') {
            throw new RuntimeException("This donation has been deleted.  ");
        }

        return $donRS;
    }

    /**
     * Write this donation's values over an existing donation record.  Call newDonation()
     * first, with the existing donation's donor.  The contact details are copied again only when a
     * different address is selected; otherwise the ones saved with the gift are kept.
     *
     * @param \PDO $dbh
     * @param int $donId
     * @return int rows updated (0 when nothing changed)
     * @throws RuntimeException
     */
    public function updateDonation(\PDO $dbh, $donId) {

        $donRS = self::loadDonation($dbh, $donId);

        if ($donRS->Donor_Id->getStoredVal() != $this->getDonorId()) {
            throw new RuntimeException("This donation belongs to a different donor.  ");
        }

        $this->fillDonation($donRS);

        if ($donRS->Address_Purpose->getStoredVal() != $this->getAddressPurposeCode()) {
            $this->loadContactInfo($dbh, $donRS);
        }

        // The activity row written by saveDonation() is a log of the original entry, so it is left as is;
        // EditRS::update() records this change in the audit log.
        $count = EditRS::update($dbh, $donRS, array($donRS->iddonations));

        if ($count > 0 && $this->getAssocDonorId() > 0) {
            self::markAsDonor($dbh, $this->getAssocDonorId());
        }

        return $count;
    }

    /**
     * Set the user-entered values on a donations record (shared by insert and update).
     *
     * @param DonationsRS $donRS
     * @return void
     */
    protected function fillDonation(DonationsRS $donRS) {

        $uS = Session::getInstance();

        $donRS->Amount->setNewVal($this->getAmount());
        $donRS->Care_Of_Id->setNewVal($this->getCareofDonorId());
        $donRS->Assoc_Id->setNewVal($this->getAssocDonorId());
        $donRS->Date_Entered->setNewVal($this->getDate()->format('Y-m-d H:i:s'));
        $donRS->Pay_Type->setNewVal($this->getPayTypeCode());
        $donRS->Salutation_Code->setNewVal($this->getSalutationCode());
        $donRS->Envelope_Code->setNewVal($this->getEnvelopeSalutationCode());
        $donRS->Note->setNewVal($this->note);
        $donRS->Fund_Code->setNewVal($this->fundCode);
        $donRS->Campaign_Code->setNewVal($this->campaign->get_campaigncode());
        $donRS->Address_Purpose->setNewVal($this->getAddressPurposeCode());
        $donRS->Updated_By->setNewVal($uS->username);
        $donRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
    }

    /**
     * Copy the donor's selected address and preferred phone and email onto the donation, so the
     * record keeps the contact details as they were when the gift was made.
     *
     * @param \PDO $dbh
     * @param DonationsRS $donRS
     * @return void
     */
    protected function loadContactInfo(\PDO $dbh, DonationsRS $donRS) {

        $id = $this->getDonorId();

        $addrRS = new NameAddressRS();
        $addrRS->idName->setStoredVal($id);
        $addrRS->Purpose->setStoredVal($this->getAddressPurposeCode());
        $rows = EditRS::select($dbh, $addrRS, array($addrRS->idName, $addrRS->Purpose));
        if (count($rows) > 0) {
            EditRS::loadRow($rows[0], $addrRS);
        }

        $donRS->Address_1->setNewVal($addrRS->Address_1->getStoredVal());
        $donRS->Address_2->setNewVal($addrRS->Address_2->getStoredVal());
        $donRS->City->setNewVal($addrRS->City->getStoredVal());
        $donRS->State->setNewVal($addrRS->State_Province->getStoredVal());
        $donRS->Postal_Code->setNewVal($addrRS->Postal_Code->getStoredVal());
        $donRS->Country->setNewVal($addrRS->Country->getStoredVal());
        $donRS->Address_Purpose->setNewVal($this->getAddressPurposeCode());

        $phoneRS = new NamePhoneRS();
        $phoneRS->idName->setStoredVal($id);
        $phoneRS->Phone_Code->setStoredVal($this->donor->get_preferredPhone());
        $rows = EditRS::select($dbh, $phoneRS, array($phoneRS->idName, $phoneRS->Phone_Code));
        if (count($rows) > 0) {
            EditRS::loadRow($rows[0], $phoneRS);
        }

        $emailRS = new NameEmailRS();
        $emailRS->idName->setStoredVal($id);
        $emailRS->Purpose->setStoredVal($this->donor->get_preferredEmail());
        $rows = EditRS::select($dbh, $emailRS, array($emailRS->idName, $emailRS->Purpose));
        if (count($rows) > 0) {
            EditRS::loadRow($rows[0], $emailRS);
        }

        $donRS->Phone->setNewVal($phoneRS->Phone_Num->getStoredVal());
        $donRS->Email->setNewVal($emailRS->Email->getStoredVal());
    }


    /**
     * Give a member the donor volunteer type (vol_type = d) if they don't have it.  The InsertDonor
     * procedure writes name_volunteer2 and activity directly, so it gets one activity log entry.
     *
     * @param \PDO $dbh
     * @param int $idName
     * @return void
     */
    public static function markAsDonor(\PDO $dbh, $idName) {

        $idName = intval($idName);
        if ($idName == 0) {
            return;
        }

        $dbh->prepare("call InsertDonor(:id);")->execute([':id' => $idName]);

        EditRS::logActivity($dbh, "Marked as a donor (InsertDonor)", ['idName' => $idName], 'default', 'name', $idName);
    }

    public function setCampaign(\PDO $dbh, $campaignCode) {

        $this->campaign = new Campaign($dbh, $campaignCode);

        // Must be an existing campaign
        if ($this->campaign->get_idcampaign() == 0) {
            throw new RuntimeException("Bad Campaign Code: " . $campaignCode);
        }

    }


    public function getAmount() {
        return $this->amount;
    }

    protected function setAmount($amt) {

        if (is_float($amt) === FALSE) {
            $amt = floatval($amt);
        }

        if ($amt <= 0 || $amt > $this->maxDonationAmt) {
            throw new RuntimeException("Bad Amount: " . number_format($amt, 2, ".", ",") . ".  Amount must be greater than 0 and less than $" . number_format($this->maxDonationAmt, 2, ".", ","));
        }

        if ($this->campaign->isAmountValid($amt) === FALSE) {
            throw new RuntimeException("Amount outside campaign range limits: $" . number_format($amt, 2, ".", ","));
        }

        $this->amount = $amt;

    }

    public function getDonor() {
        return $this->donor;
    }

    public function setDonor(\PDO $dbh, $id, $includedId = 0) {

        $this->donor = AbstractMember::GetDesignatedMember($dbh, $id, MemBasis::Indivual);

        if ($this->donor->isNew()) {
            throw new RuntimeException("Bad Member Id: ".$id);
        }

        $includedId = max(0, intval($includedId));

        if ($this->donor->getMemberDesignation() == MemDesignation::Individual) {
            $this->setAssocDonorId($includedId)->setCareofDonorId(0);
        } else {
            // Organizations may name the employee the gift came through (care of); 0 = none.
            $this->setAssocDonorId(0)->setCareofDonorId($includedId);
        }

        if ($this->donor->get_status() != MemStatus::Active && $this->donor->get_status() != MemStatus::Deceased) {
            throw new RuntimeException("The donor's Member Status must be set to Active in order to donate.");
        }

    }

    public function getDonorId() {
        if (is_null($this->donor)) {
            return 0;
        } else {
            return $this->donor->get_idName();
        }
    }

    public function getAssocDonorId() {
        return $this->assocDonorId;
    }

    public function setAssocDonorId($assocDonorId) {
        $this->assocDonorId = $assocDonorId;
        return $this;
    }

    public function getCareofDonorId() {
        return $this->careofDonorId;
    }

    public function setCareofDonorId($careofDonorId) {
        $this->careofDonorId = $careofDonorId;
        return $this;
    }

    public function getDate() {
        return $this->DateDT;
    }

    public function setDate($DonationDateTimeStr) {

        if ($DonationDateTimeStr != '') {
            try {
                $this->DateDT = new \DateTime($DonationDateTimeStr);
            } catch (\Exception $ex) {
                throw new RuntimeException("Bad Date: " . $DonationDateTimeStr);
            }
        } else {
            $this->DateDT = new \DateTime();
        }

        return $this;
    }

    public function getEnvelopeSalutationCode() {
        return $this->envelopeSalutationCode;
    }

    public function setEnvelopeSalutationCode($envelopeSalutationCode) {
        $this->envelopeSalutationCode = $envelopeSalutationCode;
        return $this;
    }

    public function getSalutationCode() {
        return $this->salutationCode;
    }

    public function setSalutationCode($salutationCode) {
        $this->salutationCode = $salutationCode;
        return $this;
    }

    public function getAddressPurposeCode() {
        return $this->addressPurposeCode;
    }

    public function setAddressPurposeCode($addressPurposeCode) {
        $this->addressPurposeCode = $addressPurposeCode;
        return $this;
    }

    public function getPayTypeCode() {
        return $this->payTypeCode;
    }

    public function setPayTypeCode($payTypeCode) {

        if (isset($this->payCodes[$payTypeCode]) === FALSE) {
            throw new RuntimeException("Bad Pay type: " . $payTypeCode);
        }
        $this->payTypeCode = $payTypeCode;
        return $this;
    }

    public function getNote() {
        return $this->note;
    }

    public function getFundCode() {
        return $this->fundCode;
    }

}
