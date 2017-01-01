<?php
/**
 * Donation.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Donation
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

    function __construct(PDO $dbh, array $payCodes, $maxDonationAmt, $campaignCode = '') {
        $this->maxDonationAmt = $maxDonationAmt;
        $this->payCodes = $payCodes;

        if ($campaignCode != '') {
            $this->setCampaign($dbh, $campaignCode);
        } else {
            $this->campaign = null;
        }

    }


    public function newDonation(PDO $dbh, $amount, $donorId, $includedId, $payType, $date = '', $addressPurpose = Address_Purpose::Home, $salCode = SalutationCodes::FirstOnly, $envCode = SalutationCodes::Formal) {

        if (is_null($this->campaign)) {
            throw new Hk_Exception_Runtime('Campaign Code not set.  ');
        }

        $this->setAmount($amount);
        $this->setDonor($dbh, $donorId, $includedId);
        $this->setPayTypeCode($payType)->setDate($date);
        $this->setAddressPurposeCode($addressPurpose)->setSalutationCode($salCode)->setEnvelopeSalutationCode($envCode);

    }

    public function saveDonation(PDO $dbh, $paymentId = 0) {

        if ($this->getAmount() == 0) {
            return;
        }
        // activity record
        $activRS = new ActivityRS();
        $activRS->idName->setNewVal($this->getDonorId());
        $activRS->Trans_Date->setNewVal(date('Y-m-d H:i:s'));
        $activRS->Type->setNewVal(ActivityTypes::Donation);
        $activRS->Effective_Date->setNewVal($this->getDate()->format('Y-m-d H:i:s'));
        $activRS->Product_Code->setNewVal('gift');
        $activRS->Source_System->setNewVal('manual');
        $activRS->Quantity->setNewVal(1);
        $activRS->Amount->setNewVal($this->getAmount());
        $activRS->Pay_Method->setNewVal($this->getPayTypeCode());
        $activRS->Campaign_Code->setNewVal($this->campaign->get_campaigncode());
        $activRS->Member_Type->setNewVal($this->donor->get_type());
        $activRS->Status_Code->setNewVal('a');

        $acId = EditRS::insert($dbh, $activRS);

        $uS = Session::getInstance();

        //Insert donation record
        $donRS = new DonationsRS();
        $donRS->Activity_Id->setNewVal($acId);
        $donRS->Amount->setNewVal($this->getAmount());
        $donRS->Donor_Id->setNewVal($this->getDonorId());
        $donRS->Care_Of_Id->setNewVal($this->getCareofDonorId());
        $donRS->Assoc_Id->setNewVal($this->getAssocDonorId());
        $donRS->Type->setNewVal('local');
        $donRS->Date_Entered->setNewVal($this->getDate()->format('Y-m-d H:i:s'));
        $donRS->Pay_Type->setNewVal($this->getPayTypeCode());
        $donRS->Member_type->setNewVal($this->donor->get_type());
        $donRS->Donation_Type->setNewVal('gift');
        $donRS->Salutation_Code->setNewVal($this->getSalutationCode());
        $donRS->Envelope_Code->setNewVal($this->getEnvelopeSalutationCode());

        $donRS->Org_Code->setNewVal('');
        $donRS->Campaign_Code->setNewVal($this->campaign->get_campaigncode());
        $donRS->Status->setNewVal('a');
        $donRS->Updated_By->setNewVal($uS->username);
        $donRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

        $donId = EditRS::insert($dbh, $donRS);

        if ($donId > 0) {

            // insert vol_type = d if not there already...
            $query = "call InsertDonor(" . $this->getDonorId() . ");";
            $dbh->exec($query);

            if ($this->getAssocDonorId() > 0) {
                // insert vol_type = d if not there already...
                $query = "call InsertDonor(" . $this->getAssocDonorId() . ");";
                $dbh->exec($query);
            }

        } else {
            throw new Hk_Exception_Runtime("DB Error, table=donations - insert failure.");
        }

        return $donId;
    }


    public function setCampaign(PDO $dbh, $campaignCode) {

        $this->campaign = new Campaign($dbh, $campaignCode);

        // Must be an existing campaign
        if ($this->campaign->get_idcampaign() == 0) {
            throw new Hk_Exception_Runtime("Bad Campaign Code: " . $campaignCode);
        }

    }


    public function getAmount() {
        return $this->amount;
    }

    protected function setAmount($amt) {

        if (is_float($amt) === FALSE) {
            $amt = floatval($amt);
        }
        // Filter donation amouont
        if ($amt <= 0 || $amt > $this->maxDonationAmt || $this->campaign->isAmountValid($amt) === FALSE) {
            throw new Hk_Exception_Runtime("Invalid donation amount: " . $amt);
        }

        $this->amount = $amt;

    }

    public function getDonor() {
        return $this->donor;
    }

    public function setDonor(PDO $dbh, $id, $includedId = 0) {

        $this->donor = Member::GetDesignatedMember($dbh, $id, MemBasis::Indivual);

        if ($this->donor->isNew()) {
            throw new Hk_Exception_Runtime("Bad Member Id: ".$id);
        }

        if ($this->donor->getMemberDesignation() == MemDesignation::Individual) {
            $this->setAssocDonorId($includedId)->setCareofDonorId(0);
        } else {
            $this->setAssocDonorId(0)->setCareofDonorId($includedId);
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
            $this->DateDT = new DateTime($DonationDateTimeStr);
        } else {
            $this->DateDT = new DateTime();
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
            throw new Hk_Exception_Runtime("Bad Pay type: " . $payTypeCode);
        }
        $this->payTypeCode = $payTypeCode;
        return $this;
    }



}

