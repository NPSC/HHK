<?php
namespace HHK\Member\ProgressiveSearch\SearchNameData;

use HHK\Member\Address\CleanAddress;
use HHK\SysConst\MemType;
use HHK\SysConst\VolMemberType;

class SearchFor extends SearchNameData
{
    protected $whereClause;
    
    /**
     * @param string $birthDate
     */
    public function setBirthDate($strBirthDate, $include = TRUE) {
        
        parent::setBirthDate($strBirthDate);
        
        if ($this->birthDate != '' && $include) {
            $this->whereClause .= " AND DATE(n.BirthDate) = DATE('" . $this->birthDate . "')";
        }
        
        return $this;
    }
    
    /**
     * @param string $email
     */
    public function setEmail($email, $include = TRUE) {
        
        parent::setEmail($email);
        
        if ($this->email != '' && $include) {
            $this->whereClause .= " OR ne.Email = '" . $this->email . "' ";
        }
        return $this;
    }
    
    /**
     * @param string $phone
     */
    public function setPhone($phone, $include = TRUE) {
        
        parent::setPhone($phone);
        
        if ($this->phone != '' && $include) {
            $this->whereClause .= " OR np.Phone_Search = '" . $this->phone . "' ";
        }
        return $this;
    }
    
    
    
    /**
     * @return string
     */
    public function getWhereClause() {
        return $this->whereClause;
    }

}

