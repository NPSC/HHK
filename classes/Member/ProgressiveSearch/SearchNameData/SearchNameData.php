<?php
namespace HHK\Member\ProgressiveSearch\SearchNameData;

class SearchNameData {
   
    protected $nameFirst;
    protected $nameMiddle;
    protected $nameLast;
    protected $nickname;
    protected $birthDate;
    
    protected $email;
    protected $phone;
    protected $addressStreet;
    protected $addressStreet2;
    protected $addressCity;
    protected $addressState;
    protected $addressZip;
    protected $addressCountry;
    
    
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
     * @param string $birthDate
     */
    public function setBirthDate($strBirthDate) {
        try {
            
            $birthDT = new \DateTime($strBirthDate);
            $this->birthDate = $birthDT->format('Y-m-d');
            
        } catch (\Exception $ex) {
            $this->birthDate = '';
        }
        
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
     * @param string $addressStreet
     */
    public function setAddressStreet($addressStreet) {
        $this->addressStreet = trim($addressStreet);
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
    public function getBirthDate()
    {
        return $this->birthDate;
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
    public function getAddressStreet()
    {
        return $this->addressStreet;
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
    
}

