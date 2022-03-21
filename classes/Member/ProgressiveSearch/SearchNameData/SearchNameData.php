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

            $addrs = $cleanAddress->cleanAddr(trim(filter_var($addressStreet, FILTER_SANITIZE_STRING)));

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
    public function loadMeFrom(array $r) {

        $this->setId($r['idName'])
        ->setNameFirst($r["Name_First"])
        ->setNameLast($r["Name_Last"])
        ->setNickname($r["Name_Nickname"])
        ->setNameMiddle($r["Name_Middle"])
        ->setPrefix($r['Name_Prefix'])
        ->setSuffix($r['Name_Suffix'])
        ->setGender($r['Gender'])
        ->setEthnicity($r['Ethnicity'])
        ->setBirthDate($r['Birthdate'])
        ->setPhone($r['Phone_Num'])
        ->setEmail($r['Email'])
        ->setAddressStreet1($r['Address1'])
        ->setAddressStreet2($r['Address2'])
        ->setAddressCity($r['City'])
        ->setAddressState($r['State_Province'])
        ->setAddressZip($r['Postal_Code'])
        ->setAddressCountry($r['Country_Code'])
        ->setNoReturn($r['No_Return']);

        if (isset($r['Relationship'])) {
            $this->setRelationship($r['Relationship']);
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




}

