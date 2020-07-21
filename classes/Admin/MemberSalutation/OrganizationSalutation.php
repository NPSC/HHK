<?php

namespace HHK\Admin\MemberSalutation;

use HHK\SysConst\SalutationPurpose;

/**
 * OrganizationalSalutation.php
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

class OrganizationSalutation extends AbstractMemberSalutation {

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