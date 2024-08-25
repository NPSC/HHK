<?php
namespace HHK\Member\ProgressiveSearch\SearchNameData;

use HHK\SysConst\GLTableNames;
use HHK\sec\Session;

class SearchResults extends SearchNameData
{

    public function setNameFirst($nameFirst) {
        $this->nameFirst = preg_replace_callback("/(&#[0-9]+;)/",
            function($m) {
                return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
            },
            $nameFirst
            );
        return $this;
    }

    /**
     * @param string $nameMiddle
     */
    public function setNameMiddle($nameMiddle) {
        $this->nameMiddle =  preg_replace_callback("/(&#[0-9]+;)/",
            function($m) {
                return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
            },
            $nameMiddle
            );
        return $this;
    }

    /**
     * @param string $nameLast
     */
    public function setNameLast($nameLast) {
        $this->nameLast =  preg_replace_callback("/(&#[0-9]+;)/",
            function($m) {
                return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
            },
            $nameLast
            );
        return $this;
    }

    /**
     * @param string $nickname
     */
    public function setNickname($nickname) {
        $this->nickname =  preg_replace_callback("/(&#[0-9]+;)/",
            function($m) {
                return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
            },
            $nickname
            );
        return $this;
    }

    public function getBirthDate() {

        if ($this->birthDate != '') {
            return date('M d, Y', strtotime($this->birthDate));
        }

        return $this->birthDate;
    }

    public function getPhone() {
        return preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $this->phone);
    }

    public function getSMS_Status() {
        return $this->sms_status;
    }

    public function getRelationship() {

        $uS = Session::getInstance();

        if (isset($uS->guestLookups[GLTableNames::PatientRel][$this->relationship])) {
            return $uS->guestLookups[GLTableNames::PatientRel][$this->relationship][1];
        }

        return $this->relationship;

    }

    public function getPrefix() {

        $uS = Session::getInstance();

        if (isset($uS->nameLookups[GLTableNames::NamePrefix][$this->prefix])) {
            return $uS->nameLookups[GLTableNames::NamePrefix][$this->prefix][1];
        }

        return $this->prefix;

    }

    public function getSuffix() {

        $uS = Session::getInstance();

        if (isset($uS->nameLookups[GLTableNames::NameSuffix][$this->suffix])) {
            return $uS->nameLookups[GLTableNames::NameSuffix][$this->suffix][1];
        }

        return $this->suffix;

    }

    public function setEmrgFirst($nameFirst) {
        $this->emrgFirst = preg_replace_callback("/(&#[0-9]+;)/",
            function($m) {
                return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
            },
            $nameFirst
            );
        return $this;
    }

    public function setEmrgLast($nameLast) {
        $this->emrgLast = preg_replace_callback("/(&#[0-9]+;)/",
            function($m) {
                return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
            },
            $nameLast
            );
        return $this;
    }

    public function getEmrgPhone() {
        return preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $this->emrgPhone);
    }

    public function getEmrgAltPhone() {
        return preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $this->emrgAltPhone);
    }

    public function getEmrgRelation() {

        $uS = Session::getInstance();

        if (isset($uS->guestLookups[GLTableNames::PatientRel][$this->emrgRelation])) {
            return $uS->guestLookups[GLTableNames::PatientRel][$this->emrgRelation][1];
        }

        return $this->emrgRelation;

    }



}

