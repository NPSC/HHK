<?php
namespace HHK\Admin\MemberSalutation;

use HHK\SysConst\MemGender;

/**
 * IndividualSalutation.php
 *
 * Constructs formal, informal, or other name salutations
 *
 * @category Donations
 * @package Hospitality HouseKeeper
 * @author Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license GPL and MIT
 * @link https://github.com/ecrane57/Hospitality-HouseKeeper
 */
class IndividualSalutation extends AbstractMemberSalutation
{

    private $Last = "";

    private $First = "";

    private $Middle = "";

    private $Nickname = "";

    private $Prefix = "";

    private $Suffix = "";

    private $Gender = "";

    function __construct($Last, $First, $Middle, $Nickname, $Prefix, $Suffix, $Gender = "")
    {
        $this->Last = $Last;
        $this->First = $First;
        $this->Middle = $Middle;
        $this->Nickname = $Nickname;
        $this->Prefix = $Prefix;
        $this->Suffix = $Suffix;
        $this->Gender = $Gender;
    }

    public function formalMarkup($purpose, $assoc)
    {
        if (is_null($assoc)) {
            return $this->getPrefix() . $this->getFirst() . $this->getMiddle() . $this->getLast() . $this->getSuffix();
        } else {
            if ($this->getLast() != $assoc->getLast()) {
                // seperate last names
                return $this->getPrefix() . $this->getFirst() . $this->getMiddle() . $this->getLast() . $this->getSuffix() . " & " . $assoc->getPrefix() . $assoc->getFirst() . $assoc->getMiddle() . $assoc->getLast() . $assoc->getSuffix();
            } else {
                // same last name
                return $this->getPrefix() . $this->getFirst() . $this->getMiddle() . " & " . $assoc->getPrefix() . $assoc->getFirst() . $assoc->getMiddle() . $this->getLast();
            }
        }
    }

    public function firstLastMarkup($purpose, $assoc)
    {
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

    public function firstOnlyMarkup($purpose, $assoc)
    {
        if (is_null($assoc)) {
            return $this->getNickname();
        } else {
            return $this->getNickname() . " & " . $assoc->getNickname();
        }
    }

    public function retroMarkup($purpose, $assoc)
    {
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

    public function getLast()
    {
        return " " . $this->Last;
    }

    public function getFirst()
    {
        return $this->First;
    }

    public function getMiddle()
    {
        if ($this->Middle != "") {
            return " " . $this->Middle;
        } else {
            return "";
        }
    }

    public function getNickname()
    {
        if ($this->Nickname != "") {
            return $this->Nickname;
        } else {
            return $this->getFirst();
        }
    }

    public function getPrefix()
    {
        if ($this->Prefix != "") {
            return $this->Prefix . " ";
        } else {
            return "";
        }
    }

    public function getSuffix()
    {
        if ($this->Suffix != "") {
            return " " . $this->Suffix;
        } else {
            return "";
        }
    }

    public function getGender()
    {
        return $this->Gender;
    }

    public function setLast($v)
    {
        $this->Last = $v;
    }

    public function setFirst($v)
    {
        $this->First = $v;
    }

    public function setMiddle($v)
    {
        $this->Middle = $v;
    }

    public function setNickname($v)
    {
        $this->Nickname = $v;
    }

    public function setPrefix($v)
    {
        $this->Prefix = $v;
    }

    public function setSuffix($v)
    {
        $this->Suffix = $v;
    }

    public function setGender($v)
    {
        $this->Gender = $v;
    }
}
?>