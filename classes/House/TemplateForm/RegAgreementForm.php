<?php

namespace HHK\House\TemplateForm;

use HHK\House\RegistrationForm\CustomRegisterForm;
use HHK\sec\Session;

/**
 * RegAgreementForm.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2024 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of RegAgreementForm
 *
 * @author Will
 */
class RegAgreementForm extends AbstractTemplateForm {


    public function makeReplacements(\PDO $dbh, CustomRegisterForm $regForm, array $guests, int $primaryGuestId, string $room, string $arrival, string $departure) {

        $uS = Session::getInstance();

        return array(
            'Room' => $room,
            'ArrivalDate' => date('M j, Y', strtotime($arrival)),
            'ArrivalTime' => date('g:i a', strtotime($arrival)),
            'ExpectedDepartureDate' => date('M j, Y', strtotime($departure)),
            'SignatureLines' => $regForm->SignatureLinesMkup($guests, $primaryGuestId, true),
            'InitialLine' => $regForm->InitialsLineMkup(),
            'BlankSignatureLine' => $regForm->BlankSignatureLineMkup(),
            'BlankTextBox' => $regForm->BlankTextBox(),
            'BlankInlineTextBox' => $regForm->BlankInlineTextBox(),
            'BlankTextArea' => $regForm->BlankTextarea(),
            'CheckBox' => $regForm->checkbox(),
            'DateToday' => date('M j, Y'),
            'logoUrl' => $uS->resourceURL .'conf/' . $uS->statementLogoFile,
        );

    }

}