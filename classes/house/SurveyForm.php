<?php
/**
 * SurveyForm.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of ConfirmationForm
 *
 * @author Eric
 */
class SurveyForm extends TemplateForm {


    public function makeReplacements(array $nameRow) {

        return array(
            'FirstName' => $nameRow['Name_First'],
            'LastName' => $nameRow['Name_Last'],
            'NameSuffix' => $nameRow['Name_Suffix'],
            'NamePrefix' => $nameRow['Name_Prefix'],
        );

    }


}
