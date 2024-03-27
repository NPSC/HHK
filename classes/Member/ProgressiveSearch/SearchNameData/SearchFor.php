<?php
namespace HHK\Member\ProgressiveSearch\SearchNameData;

use HHK\sec\Session;
use HHK\SysConst\GLTableNames;

class SearchFor extends SearchNameData
{
    protected $whereClause = '';
    protected $psgId;

    /**
     * @return mixed
     */
    public function getPsgId()
    {
        return $this->psgId;
    }

    /**
     * @param mixed $psgId
     */
    public function setPsgId($psgId)
    {
        $this->psgId = $psgId;
        return $this;
    }

    /**
     * @param string $birthDate
     */
    public function setBirthDate($strBirthDate, $include = FALSE) {

        parent::setBirthDate($strBirthDate);

        if ($this->birthDate != '' && $include) {
            $bDay = new \DateTime($this->birthDate);
            //$this->whereClause .= " AND (DATE(n.BirthDate) = DATE('" . $bDay->format('Y-m-d') . "') OR n.BirthDate is NULL OR n.BirthDate = '') ";
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
     * @param string $phone
     */
    public function setSMS_Status($status, $include = TRUE) {

        parent::setSMS_Status($status);

        return $this;
    }

    public function getSuffixTitle() {

        $uS = Session::getInstance();

        if (isset($uS->nameLookups[GLTableNames::NameSuffix][$this->suffix])) {
            return $uS->nameLookups[GLTableNames::NameSuffix][$this->suffix][1];
        }

        return $this->suffix;

    }


    /**
     * @return string
     */
    public function getWhereClause() {
        return $this->whereClause;
    }

}

