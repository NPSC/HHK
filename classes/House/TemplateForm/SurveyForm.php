<?php

namespace HHK\House\TemplateForm;

/**
 * SurveyForm.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of SurveyForm
 *
 * @author Eric
 */
class SurveyForm extends AbstractTemplateForm {


    public function makeReplacements(array $nameRow) {

        return array(
            'FirstName' => $nameRow['Name_First'],
            'LastName' => $nameRow['Name_Last'],
            'NameSuffix' => $nameRow['Name_Suffix'],
            'NamePrefix' => $nameRow['Name_Prefix'],
            'ActualDeparture' => ($nameRow['Actual_Departure'] ? date('M j, Y', strtotime($nameRow['Actual_Departure'])) : ""),
        );

    }
    
}
