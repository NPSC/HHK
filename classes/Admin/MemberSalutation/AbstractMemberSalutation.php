<?php

namespace HHK\Admin\MemberSalutation;

use HHK\SysConst\SalutationCodes;

/**
 * AbstractMemberSalutation.php
 *
 * Constructs formal, informal, or other name salutations
 *
 * @category  Donations
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

abstract class AbstractMemberSalutation implements MemberSalutationInterface {
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
?>