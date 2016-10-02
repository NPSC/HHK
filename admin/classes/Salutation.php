<?php
/**
 * Salutation.php
 *
 * Constructs formal, informal, or other name salutations
 *
 * @file      admin/classes/Salutation.php
 * @category  Donations
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

interface iMemberSalutation {
    public function formalMarkup($purpose, $alt);
    public function firstLastMarkup($purpose, $alt);
    public function firstOnlyMarkup($purpose, $alt);
    public function retroMarkup($purpose, $alt);
    public function getMarkup($purpose, $format_Code, $alt);
}

abstract class MemberSal implements iMemberSalutation {
    abstract public function formalMarkup($purpose, $alt);
    abstract public function firstLastMarkup($purpose, $alt);
    abstract public function firstOnlyMarkup($purpose, $alt);
    abstract public function retroMarkup($purpose, $alt);

    public function getMarkup($purpose, $code, $alt) {

        switch (trim($code)) {
            // Formal; Couple - Mr. Jack and Mrs. Irene Jacobson
            case SalutationCodes::Formal:
                $markup = $this->formalMarkup($purpose, $alt);
                break;

            // Retro Mr. & Mrs fname lname
            case SalutationCodes::Retro:
                $markup = $this->retroMarkup($purpose, $alt);
                break;

            // First names only
            case SalutationCodes::FirstOnly:
                $markup = $this->firstOnlyMarkup($purpose, $alt);
                break;

            // First and last
            case SalutationCodes::FirstLast:
                $markup = $this->firstLastMarkup($purpose, $alt);
                break;

            // anything else
            default:
                $markup = $this->formalMarkup($purpose, $alt);
                break;
        }

        return $markup;

    }

}


class IndividualSal extends MemberSal {
    private $Last = "";
    private $First = "";
    private $Middle = "";
    private $Nickname = "";
    private $Prefix = "";
    private $Suffix = "";
    private $Gender = "";

    function __construct($Last, $First, $Middle, $Nickname, $Prefix, $Suffix, $Gender = "") {
        $this->Last = $Last;
        $this->First = $First;
        $this->Middle = $Middle;
        $this->Nickname = $Nickname;
        $this->Prefix = $Prefix;
        $this->Suffix = $Suffix;
        $this->Gender = $Gender;
    }

    public function formalMarkup($purpose, $assoc) {
        if (is_null($assoc)) {
            return $this->getPrefix() . $this->getFirst() . $this->getMiddle() . $this->getLast() . $this->getSuffix();
        } else {
            if ($this->getLast() != $assoc->getLast()) {
                // seperate last names
                return $this->getPrefix() . $this->getFirst() . $this->getMiddle() . $this->getLast() . $this->getSuffix() . " & " .
                    $assoc->getPrefix() . $assoc->getFirst() . $assoc->getMiddle() . $assoc->getLast() . $assoc->getSuffix();
            } else {
                // same last name
                return $this->getPrefix() . $this->getFirst() . $this->getMiddle() . " & " . $assoc->getPrefix() . $assoc->getFirst() . $assoc->getMiddle() . $this->getLast();
            }
        }
    }

    public function firstLastMarkup($purpose, $assoc) {
        if (is_null($assoc)) {
            return $this->getNickname() . $this->getLast();
        } else {
            if ($this->getLast() != $assoc->getLast()) {
                // seperate last names
                return $this->getNickname() . $this->getLast() . " & " . $assoc->getNickname() . $assoc->getLast();
            } else {
                // same last name
                return $this->getNickname() . " & " . $assoc->getNickname() . $this->getLast();
            }
        }

    }

    public function firstOnlyMarkup($purpose, $assoc) {
        if (is_null($assoc)) {
            return $this->getNickname();
        } else {
            return $this->getNickname() . " & " . $assoc->getNickname();
        }

    }

    public function retroMarkup($purpose, $assoc) {
        if (is_null($assoc)) {
            return $this->formalMarkup($purpose, $assoc);
        } else {
            if ($this->getLast() != $assoc->getLast()) {
                return $this->formalMarkup($purpose, $assoc);
            } else {
                if ($this->getGender() == MemGender::Male || $assoc->getGender() == MemGender::Female) {
                    if ($this->getPrefix() != "") {
                        $prefix = $this->getPrefix() . "& Mrs. ";
                    } else {
                        $prefix = "Mr. & Mrs. ";
                    }
                    return $prefix . $this->getFirst() . $this->getMiddle() . $this->getLast() . $this->getSuffix();
                } else if ($assoc->getGender() == MemGender::Male || $this->getGender() == MemGender::Female) {
                    if ($assoc->getPrefix() != "") {
                        $prefix = $assoc->getPrefix() . "& Mrs. ";
                    } else {
                        $prefix = "Mr. & Mrs. ";
                    }
                    return $prefix . $assoc->getFirst() . $assoc->getMiddle() . $assoc->getLast() . $assoc->getSuffix();
                } else {
                    return $this->formalMarkup($purpose, $assoc);
                }
            }
        }

    }


    public function getLast() {
        return " " . $this->Last;
    }

    public function getFirst() {
        return $this->First;
    }

    public function getMiddle() {
        if ($this->Middle != "") {
            return " " . $this->Middle;
        } else {
            return "";
        }
    }

    public function getNickname() {
        if ($this->Nickname != "") {
            return $this->Nickname;
        } else {
            return $this->getFirst();
        }
    }

    public function getPrefix() {
        if ($this->Prefix != "") {
            return $this->Prefix . " ";
        } else {
            return "";
        }
    }

    public function getSuffix() {
        if ($this->Suffix != "") {
            return " " . $this->Suffix;
        } else {
            return "";
        }
    }

    public function getGender() {
        return $this->Gender;
    }

    public function setLast($v) {
        $this->Last = $v;
    }

    public function setFirst($v) {
        $this->First = $v;
    }

    public function setMiddle($v) {
        $this->Middle = $v;
    }

    public function setNickname($v) {
        $this->Nickname = $v;
    }

    public function setPrefix($v) {
        $this->Prefix = $v;
    }

    public function setSuffix($v) {
        $this->Suffix = $v;
    }

    public function setGender($v) {
        $this->Gender = $v;
    }

}

class OrganizationSal extends MemberSal {

    private $company = "";

    public function __construct($Company_Name) {
        $this->company = $Company_Name;
    }


    public function formalMarkup($purpose, $empl) {
        if (is_null($empl)) {
            return $this->company;
        } else {
            if ($purpose == SalutationPurpose::Envelope) {
                return $this->company . " C/O " . $empl->formalMarkup($purpose, NULL);
            } else {
                return $empl->formalMarkup($purpose, NULL);
            }
        }

    }

    public function firstLastMarkup($purpose, $empl) {
        if (is_null($empl)) {
            return $this->company;
        } else {
            if ($purpose == SalutationPurpose::Envelope) {
                return $this->company . " C/O " . $empl->firstLastMarkup($purpose, NULL);
            } else {
                return $empl->firstLastMarkup($purpose, NULL);
            }
        }

    }

    public function firstOnlyMarkup($purpose, $empl) {
        if (is_null($empl)) {
            return $this->company;
        } else {
            if ($purpose == SalutationPurpose::Envelope) {
                return $this->company . " C/O " . $empl->firstOnlyMarkup($purpose, NULL);
            } else {
                return $empl->firstOnlyMarkup($purpose, NULL);
            }
        }

    }

    public function retroMarkup($purpose, $empl) {

        return $this->formalMarkup($purpose, $empl);

    }

}
?>
