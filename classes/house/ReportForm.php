<?php

/**
 * ReportForm.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of ReportForm
 *
 * @author Will
 */
class ReportForm extends TemplateForm {


    public function makeReplacements(\PDO $dbh, Report $report) {

        $uS = Session::getInstance();

		$idGuest = 0;
		$idGuest = $report->getGuestId();

		$guest = new Guest($dbh, '', $idGuest);

        return array(
	        'Username' => $report->getAuthor(),
            'GuestName' => $guest->getRoleMember()->get_fullName(),
            'IncidentDate' => date('M j, Y', strtotime($report->getReportDate())),
            'IncidentDescription' => $report->getDescription(),
            'IncidentStatus' => $report->getStatus(),
            'IncidentResolution' => $report->getResolution(),
            'ResolutionDate' => ($report->getResolutionDate() == "" ? "" : date('M j, Y', strtotime($report->getResolutionDate()))),
        );
    }
}
