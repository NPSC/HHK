<?php
namespace HHK\Member\ProgressiveSearch\SearchNameData;

use HHK\Member\Address\CleanAddress;

class SearchNameData implements SearchNameDataInterface {

    protected $id = 0;
    protected $nameFirst = '';
    protected $nameMiddle = '';
    protected $nameLast = '';
    protected $nickname = '';
    protected $suffix = '';
    protected $prefix = '';
    protected $gender = '';
    protected $ethnicity = '';
    protected $birthDate = '';
    protected $relationship = '';
    protected $email = '';
    protected $phone = '';
    protected $sms_status = '';
    protected $addressStreet1 = '';
    protected $addressStreet2 = '';
    protected $addressCity = '';
    protected $addressState = '';
    protected $addressCounty = '';
    protected $addressZip = '';
    protected $addressCountry = '';
    protected $noReturn = '';
    protected $emrgFirst = '';
    protected $emrgLast = '';
    protected $emrgPhone = '';
    protected $emrgAltPhone = '';
    protected $emrgRelation = '';


    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @param string $nameFirst
     */
    public function setNameFirst($nameFirst) {
        $this->nameFirst = trim($nameFirst);
        return $this;
    }

    /**
     * @param string $nameMiddle
     */
    public function setNameMiddle($nameMiddle) {
        $this->nameMiddle = trim($nameMiddle);
        return $this;
    }

    /**
     * @param string $nameLast
     */
    public function setNameLast($nameLast) {
        $this->nameLast = trim($nameLast);
        return $this;
    }

    /**
     * @param string $nickname
     */
    public function setNickname($nickname) {
        $this->nickname = trim($nickname);
        return $this;
    }

    /**
     * @param string $prefix
     */
    public function setPrefix($prefix) {
        $this->prefix = trim($prefix);
        return $this;
    }

        /**
     * @param string $suffix
     */
    public function setSuffix($suffix) {
        $this->suffix = trim($suffix);
        return $this;
    }

/**
     * @param string $gender
     */
    public function setGender($gender) {
        $this->gender = trim($gender);
        return $this;
    }

    /**
     * @param string $ethnicity
     */
    public function setEthnicity($ethnicity) {
        $this->ethnicity = trim($ethnicity);
        return $this;
    }

/**
     * @param string $birthDate
     */
    public function setBirthDate($strBirthDate) {
        $this->birthDate = $strBirthDate;
        return $this;
    }

    /**
     * @param string $relationship
     */
    public function setRelationship($rel) {
        $this->relationship = trim($rel);
        return $this;
    }

    /**
     * @param string $email
     */
    public function setEmail($email) {
        $this->email = trim(filter_var($email, FILTER_SANITIZE_EMAIL));
        return $this;
    }

    /**
     * @param string $phone
     */
    public function setPhone($phone) {
        $ary = array('+', '-');
        $this->phone = str_replace($ary, '', filter_var($phone, FILTER_SANITIZE_NUMBER_INT));
        return $this;
    }

    /**
     * @param string $sms
     */
    public function setSMS_Status($sms) {
        $this->sms_status = trim($sms);
        return $this;
    }

    /**
     * @param string $addressStreet1
     */
    public function setAddressStreet1($addressStreet1) {
        $this->addressStreet1 = trim($addressStreet1);
        return $this;
    }

    /**
     * @param string $addressStreet2
     */
    public function setAddressStreet2($addressStreet2) {
        $this->addressStreet2 = trim($addressStreet2);
        return $this;
    }

    /**
     * @param string $addressCity
     */
    public function setAddressCity($addressCity) {
        $this->addressCity = trim($addressCity);
        return $this;
    }

    /**
     * @param string $addressCounty
     */
    public function setAddressCounty($addressCounty) {
        $this->addressCounty = trim($addressCounty);
        return $this;
    }

    /**
     * @param string $addressState
     */
    public function setAddressState($addressState) {
        $this->addressState = trim($addressState);
        return $this;
    }

    /**
     * @param string $addressZip
     */
    public function setAddressZip($addressZip) {
        $this->addressZip = trim($addressZip);
        return $this;
    }

    /**
     * @param string $addressCountry
     */
    public function setAddressCountry($addressCountry) {
        $this->addressCountry = trim($addressCountry);
        return $this;
    }

    public function setEmrgFirst($v) {
        $this->emrgFirst = trim($v);
        return $this;
    }
    public function setEmrgLast($v) {
        $this->emrgLast = trim($v);
        return $this;
    }
    public function setEmrgPhone($v) {
        $this->emrgPhone = trim($v);
        return $this;
    }
    public function setEmrgAltPhone($v) {
        $this->emrgAltPhone = trim($v);
        return $this;
    }
    public function setEmrgRelation($v) {
        $this->emrgRelation = trim($v);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getNameFirst()
    {
        return $this->nameFirst;
    }

    /**
     * @return string
     */
    public function getNameMiddle()
    {
        return $this->nameMiddle;
    }

    /**
     * @return string
     */
    public function getNameLast()
    {
        return $this->nameLast;
    }

    /**
     * @return string
     */
    public function getNickname()
    {
        return $this->nickname;
    }

    /**
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

        /**
     * @return string
     */
    public function getSuffix()
    {
        return $this->suffix;
    }

/**
     * @return string
     */
    public function getBirthDate()
    {
        return $this->birthDate;
    }

    /**
     * @return string
     */
    public function getGender()
    {
        return $this->gender;
    }

    /**
     * @return string
     */
    public function getEthnicity()
    {
        return $this->ethnicity;
    }

    /**
     * @return string
     */
    public function getRelationship()
    {
        return $this->relationship;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @return mixed
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * @return mixed
     */
    public function getSMS_Status()
    {
        return $this->sms_status;
    }

    /**
     * @return string
     */
    public function getAddressStreet1()
    {
        return $this->addressStreet1;
    }

    /**
     * @return string
     */
    public function getAddressStreet2()
    {
        return $this->addressStreet2;
    }

    /**
     * @return string
     */
    public function getAddressCity()
    {
        return $this->addressCity;
    }

    /**
     * @return string
     */
    public function getAddressCounty()
    {
        return $this->addressCounty;
    }

    /**
     * @return string
     */
    public function getAddressState()
    {
        return $this->addressState;
    }

    /**
     * @return string
     */
    public function getAddressZip()
    {
        return $this->addressZip;
    }

    /**
     * @return string
     */
    public function getAddressCountry()
    {
        return $this->addressCountry;
    }

    /**
     * @param string $addressStreet
     */
    public function setAddressStreet($addressStreet, CleanAddress $cleanAddress = NULL, $include = FALSE) {

        if (is_null($cleanAddress)) {
            $this->addressStreet1 = trim($addressStreet);
        } else {

            $addrs = $cleanAddress->cleanAddr(trim(filter_var($addressStreet, FILTER_SANITIZE_FULL_SPECIAL_CHARS)));

            $this->addressStreet1 = $addrs[0];
            $this->addressStreet2 =  $addrs[1];

        }

        return $this;
    }

    /**
     * @return string
     */
    public function getAddressStreet() {
        return $this->addressStreet1 . ($this->addressStreet2 == '' ? '' : ', ' . $this->addressStreet2);
    }

    public function getEmrgFirst()
    {
        return $this->emrgFirst;
    }
    public function getEmrgLast()
    {
        return $this->emrgLast;
    }
    public function getEmrgPhone()
    {
        return $this->emrgPhone;
    }
    public function getEmrgAltPhone()
    {
        return $this->emrgAltPhone;
    }
    public function getEmrgRelation()
    {
        return $this->emrgRelation;
    }


    /**
     *
     * @param array $r
     */
    public function loadMeFrom(array $r, SearchNameDataInterface $formData = null) {

        $this->setId($r['idName'])
            ->setNameFirst($this->setIfNew($r["Name_First"], $formData->getNameFirst()))
            ->setNameLast($this->setIfNew($r["Name_Last"], $formData->getNameLast()))
            ->setNickname($this->setIfNew($r["Name_Nickname"], $formData->getNickname()))
            ->setNameMiddle($this->setIfNew($r["Name_Middle"], $formData->getNameMiddle()))
            ->setPrefix($this->setIfNew($r["Name_Prefix"], $formData->getPrefix()))
            ->setSuffix($this->setIfNew($r["Name_Suffix"], $formData->getSuffix()))
            ->setGender($this->setIfNew($r["Gender"], $formData->getGender()))
            ->setEthnicity($this->setIfNew($r["Ethnicity"], ''))
            ->setBirthDate($this->setIfNew($r["Birthdate"], $formData->getBirthDate()))
            ->setPhone($this->setIfNew($r["Phone_Num"], $formData->getPhone()))
            ->setSMS_Status($this->setIfNew($r["SMS_Status"], $formData->getSMS_Status()))
            ->setEmail($this->setIfNew($r["Email"], $formData->getEmail()))
            ->setAddressStreet1($this->setIfNew($r["Address1"], $formData->getAddressStreet1()))
            ->setAddressStreet2($this->setIfNew($r["Address2"], $formData->getAddressStreet2()))
            ->setAddressCity($this->setIfNew($r["City"], $formData->getAddressCity()))
            ->setAddressState($this->setIfNew($r["State_Province"], $formData->getAddressState()))
            ->setAddressZip($this->setIfNew($r["Postal_Code"], $formData->getAddressZip()))
            ->setAddressCountry($this->setIfNew($r["Country_Code"], $formData->getAddressCountry()))
            ->setNoReturn($this->setIfNew($r["No_Return"], $formData->getNoReturn()));

        if (isset($r["Relationship"])) {
            $this->setRelationship($this->setIfNew($r["Relationship"], $formData->getRelationship()));
        }

        if (isset($r["County"])){
            $this->setAddressCounty($this->setIfNew($r["County"], $formData->getAddressCounty()));
        }

        if (isset($r["ec_First"])) {
            $this->setEmrgFirst($this->setIfNew($r["ec_First"], $formData->getEmrgFirst()));
        }
        if (isset($r["ec_Last"])) {
            $this->setEmrgLast($this->setIfNew($r["ec_Last"], $formData->getEmrgLast()));
        }
        if (isset($r["ec_Phone"])) {
            $this->setEmrgPhone($this->setIfNew($r["ec_Phone"], $formData->getEmrgPhone()));
        }
        if (isset($r["ec_Alternate"])) {
            $this->setEmrgAltPhone($this->setIfNew($r["ec_Alternate"], $formData->getEmrgAltPhone()));
        }
        if (isset($r["ec_Relationship"])) {
            $this->setEmrgRelation($this->setIfNew($r["ec_Relationship"], $formData->getEmrgRelation()));
        }

    }

    public function setNoReturn($v)
    {
        $this->noReturn = $v;
        return $this;
    }

    public function getNoReturn()
    {
        return $this->noReturn;
    }

    protected function setIfNew($old, $new) {

        if ($new != '') {
            return $new;
        }

        return $old;
    }


}

