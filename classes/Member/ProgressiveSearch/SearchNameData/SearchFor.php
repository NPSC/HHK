<?php
namespace HHK\Member\ProgressiveSearch\SearchNameData;


class SearchFor extends SearchNameData
{
    protected $whereClause;
    
    /**
     * @param string $birthDate
     */
    public function setBirthDate($strBirthDate, $include = FALSE) {
        
        parent::setBirthDate($strBirthDate);
                
        if ($this->birthDate != '' && $include) {
            $bDay = new \DateTime($this->birthDate);
            $this->whereClause .= " AND (DATE(n.BirthDate) = DATE('" . $bDay->format('Y-m-d') . "') OR n.BirthDate is NULL OR n.BirthDate = '') ";
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

