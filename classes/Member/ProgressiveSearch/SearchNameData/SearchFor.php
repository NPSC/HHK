<?php
namespace HHK\Member\ProgressiveSearch\SearchNameData;

use HHK\Member\Address\CleanAddress;

class SearchFor extends SearchNameData
{
    
    protected $whereClause;
    
    /**
     * @param string $birthDate
     */
    public function setBirthDate($strBirthDate) {
        
        parent::setBirthDate($strBirthDate);
        
        if ($this->birthDate != '') {
            $this->whereClause .= " AND DATE(n.BirthDate) = " . $this->birthDate;
        }
        
        return $this;
    }
    
    /**
     * @param string $email
     */
    public function setEmail($email) {
        
        parent::setEmail($email);
        
        if ($this->email != '') {
            $this->whereClause .= " OR ne.Email = '" . $this->email . "' ";
        }
        return $this;
    }
    
    /**
     * @param string $phone
     */
    public function setPhone($phone) {
        
        parent::setPhone($phone);
        
        if ($this->phone != '') {
            $this->whereClause .= " OR np.Phone_Search = '" . $this->phone . "' ";
        }
        return $this;
    }
    
    
    
    /**
     * @param string $addressStreet
     */
    public function setAddressStreet($addressStreet, CleanAddress $cleanAddress = NULL) {
        
        if (is_null($cleanAddress)) {
            $this->addressStreet = trim($addressStreet);
        } else {
            
            $addrs = $cleanAddress->cleanAddr(trim(filter_var($addressStreet, FILTER_SANITIZE_STRING)));
                        
            $this->addressStreet = $addrs[0];
            $this->addressStreet2 =  $addrs[1];
            
        }
        
        return $this;
    }
    
    /**
     * @return string
     */
    public function getAddressStreet() {
        return $this->addressStreet . ($this->addressStreet2 == '' ? '' : ', ' . $this->addressStreet2);
    }
    
    /**
     * @return string
     */
    public function getWhereClause() {
        return $this->whereClause;
    }
    
    
}

