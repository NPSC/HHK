<?php

namespace HHK\Admin\MemberSalutation;

/**
 * MemberSalutationInterface.php
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

interface MemberSalutationInterface {
    public function formalMarkup($purpose, $alt);
    public function firstLastMarkup($purpose, $alt);
    public function firstOnlyMarkup($purpose, $alt);
    public function retroMarkup($purpose, $alt);
    public function getMarkup($purpose, $format_Code, $alt);
}