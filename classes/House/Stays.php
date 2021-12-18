<?php
namespace HHK\House;

use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\HTMLControls\{HTMLContainer, HTMLTable};
use HHK\HTMLControls\HTMLInput;
use HHK\SysConst\VisitStatus;
use HHK\SysConst\GLTableNames;

/**
 * Stays.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2021 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
*/

class Stays
{

    protected $includeCoAllButton = FALSE;
    protected $useRemoveHdr = FALSE;
    protected $ckOutTitle = '';
    protected $numberRows = 0;
    protected $priGuestColumn = FALSE;
    protected $idVisit = 0;
    protected $span = 0;
    
    
    /**
     */
    public function __construct()
    {}
    
    /**
     * Create a guest stays table
     *
     * @param \PDO $dbh
     * @param integer $idVisit
     * @param integer $span
     * @param boolean $isAdmin
     * @param integer $idGuest
     * @param string $action
     * @return string
     */
    public function createStaysMarkup(\PDO $dbh, $idResv, $idVisit, $span, $idPrimaryGuest, $isAdmin, $idGuest, $labels, $action = '', $coDates = []) {
        
        $includeActionHdr = FALSE;  // Checkout-All button.
        $useRemoveHdr = FALSE;
        $ckOutTitle = '';
        $sTable = new HTMLTable();
        $rows = array();
        $ckinRows = array();
        $numberRows = 0;
        $hdrPgRb = '';  // Primary guest column header.  blank = no primary guest column.
        $chkInTitle = 'Checked In';
        $visitStatus = '';
        $guestAddButton = '';
        $idV = intval($idVisit, 10);
        $idS = intval($span, 10);
        
        if ($idV > 0 && $idS > -1) {
            // load stays for this visit
            $stmt = $dbh->query("select * from `vstays_listing` where `idVisit` = $idVisit and `Visit_Span` = $span order by `Span_Start_Date` desc;");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $visitStatus = $rows[0]['Visit_Status'];
            $numberRows = count($rows);
        }
        
        // cherry pick the checked in stays.
        // Add them to the stays table.
        if ($visitStatus == VisitStatus::CheckedIn) {
            
            $ckOutTitle = "Exp'd Check Out";
            
            foreach ($rows as $k => $r) {
                
                if ($r['Status'] == VisitStatus::CheckedIn) {
                    
                    $bodyTr = self::createStayRowMarkup($r, $numberRows, $action, $idGuest, $coDates, $idPrimaryGuest, $useRemoveHdr, $includeActionHdr, $hdrPgRb);
                    $sTable->addBody($bodyTr);
                    $ckinRows[$k] = 'y';
                    
                }
            }
            
        } else {
            
            switch ($visitStatus) {
                case VisitStatus::ChangeRate:
                    $ckOutTitle = "Rate Changed";
                    break;
                    
                case VisitStatus::NewSpan:
                    $ckOutTitle = "Room Changed";
                    break;
                    
                case VisitStatus::CheckedOut:
                    $ckOutTitle = "Checked Out";
                    break;
                    
            }
        }
        
        // Add the rest to the stays table, skipping the checked-ins.
        foreach ($rows as $j => $r) {
            
            if (!isset($ckinRows[$j])) {
                
                $bodyTr = self::createStayRowMarkup($r, $numberRows, $action, $idGuest, $coDates, $idPrimaryGuest, $useRemoveHdr, $includeActionHdr, $hdrPgRb);
                $sTable->addBody($bodyTr);
            }
        }
        
        // Table header
        $th = ($hdrPgRb == '' ? '' : $hdrPgRb)
        . HTMLTable::makeTh('Name')
        . HTMLTable::makeTh($labels->getString('MemberType', 'patient', 'Patient') . ' Relation')
        . HTMLTable::makeTh('Status')
        . HTMLTable::makeTh('Room')
        . HTMLTable::makeTh($chkInTitle);
        
        // 'Add Guest' button
        if ($action == '') {
            $th .= HTMLTable::makeTh($ckOutTitle) . HTMLTable::makeTh('Nights');
            
            // Make add guest button
            $guestAddButton = HTMLInput::generateMarkup('Add ' . $labels->getString('MemberType', 'visitor', 'Guest') . '...', array('id'=>'btnAddGuest', 'type'=>'button', 'style'=>'margin-left:1.3em; font-size:.8em;', 'data-rid'=>$idResv, 'data-vstatus'=>$visitStatus, 'data-vid'=>$idVisit, 'data-span'=>$span, 'title'=>'Add another guest to this visit.'));
            
        }
        
        // 'Checkout All' button
        if ($includeActionHdr) {
            
            $td = 'Check Out';
            
            // Checkout ALL button.
            if ($numberRows > 1) {
                
                $td .= HTMLInput::generateMarkup('All', array('id'=>'cbCoAll', 'type'=>'button', 'style'=>'margin-right:.5em;margin-left:.5em;'));
            }
            
            $th .= HTMLTable::makeTh($td);
        }
        
        // add 'Remove' checkbox
        if ($useRemoveHdr) {
            $th .= HTMLTable::makeTh('Remove');
        }
        
        $sTable->addHeaderTr($th);
        
        $dvTable = HTMLContainer::generateMarkup('div', $sTable->generateMarkup(array('id' => 'tblStays', 'style'=>'width:99%')), array('style'=>'max-height:150px;overflow:auto'));
        
        $titleMkup = HTMLContainer::generateMarkup('span', $labels->getString('MemberType', 'visitor', 'Guest') . 's', array('style'=>'float:left;'));
        
        return HTMLContainer::generateMarkup('fieldset',
            HTMLContainer::generateMarkup('legend', $titleMkup . $guestAddButton, array('style'=>'font-weight:bold;'))
            . $dvTable
            , array('class'=>'hhk-panel', 'style'=>'margin-bottom:10px;'));
        
    }
    
    
    protected function createStayRowMarkup($r, $numberRows, $action, $idGuest, $coDates, &$idPrimaryGuest, &$useRemoveHdr, &$includeActionHdr, &$hdrPgRb) {
        
        $uS = Session::getInstance();
        $days = 0;
        
        $actionButton = "";
        $ckOutDate = "";
        $name = $r['Name_First'] . ' ' . $r['Name_Last'];
        
        if (($action == 'so' || $action == 'ref') && $r['Status'] != VisitStatus::CheckedIn) {
            return;
        }
        
        // Preselect checkout box
        if ($action == 'co' && $r['idName'] == $idGuest) {
            // Mark check-out checkbox
            $r['Cked'] = "y";
        }
        
        // Prepare checkbox attributes.
        $cbAttr = array(
            'id' => 'stayActionCb_' . $r['idName'],
            'name' => '[stayActionCb][' . $r['idName'] . ']',
            'data-nm' => $name,
            'type' => 'checkbox',
            'class' => 'hhk-ckoutCB',
            'style' => 'margin-right:.3em;'
        );
        
        if (isset($r['Cked']) || $action == 'ref') {
            $cbAttr['checked'] = 'checked';
        }
        
        // Primary guest selector.
        if ($r["Visit_Status"] == VisitStatus::CheckedIn && $numberRows > 1) {
            
            $pgAttrs = array('name'=>'rbPriGuest', 'type'=>'radio', 'class'=>'hhk-feeskeys', 'title'=>'Make the ' . Labels::getString('MemberType', 'primaryGuest', 'Primary Guest'));
            $pgRb = '';
            
            // Only set the first instance of primary guest.
            if ($r['idName'] == $idPrimaryGuest ) {
                $pgAttrs['checked'] = 'checked';
                $idPrimaryGuest = 0;
            }
            
            if ($idPrimaryGuest != 0) {
                $pgRb = HTMLInput::generateMarkup($r['idName'], $pgAttrs);
            }
            
            $hdrPgRb = HTMLTable::makeTh('Pri', array('title'=>Labels::getString('MemberType', 'primaryGuest', 'Primary Guest')));
        }
        
        $stDayDT = new \DateTime($r['Span_Start_Date']);
        $stDayDT->setTime(0, 0, 0);
        
        // Action button depends on status
        if ($r["Visit_Status"] == VisitStatus::CheckedIn) {
            
            if ($r['Status'] == VisitStatus::CheckedIn) {
                
                if ($action == 'ref' && isset($coDates[$r['idName']])) {
                    $edDay = new \DateTime($coDates[$r['idName']]);
                } else {
                    $edDay = new \DateTime(date('Y-m-d'));
                }
                
                $edDay->setTime(0, 0, 0);
                $days = $edDay->diff($stDayDT, TRUE)->days;
                
                $getCkOutDate = HTMLInput::generateMarkup($edDay->format('M j, Y'), array('id' => 'stayCkOutDate_' . $r['idName'], 'name' =>'[stayCkOutDate][' . $r['idName'] . ']', 'class' => 'ckdate hhk-ckoutDate', 'readonly'=>'readonly', 'data-gid'=>$r['idName']));
                
                if ($uS->CoTod) {
                    $getCkOutDate .= HTMLInput::generateMarkup(date('H'), array('id' => 'stayCkOutHour_' . $r['idName'], 'name' =>'[stayCkOutHour][' . $r['idName'] . ']', 'size'=>'3'));
                }
                
                $ckOutDate = HTMLInput::generateMarkup(date('M j, Y', strtotime($r['Expected_Co_Date'])), array('id' => 'stayExpCkOut_' . $r['idName'], 'name' => '[stayExpCkOut][' . $r['idName'] . ']', 'class' => 'ckdateFut hhk-expckout', 'readonly'=>'readonly'));
                $actionButton = HTMLInput::generateMarkup('', $cbAttr) . $getCkOutDate;
                
                //
                if ($action == 'co' || $action == 'ref' || $action == '') {
                    $includeActionHdr = TRUE;
                }
                
            } else {
                
                $edDay = new \DateTime($r['Span_End_Date']);
                $edDay->setTime(0, 0, 0);
                
                $days = $edDay->diff($stDayDT, TRUE)->days;
                
                // Don't show 0-day checked - out stays.
                if ($days == 0 && !$uS->ShowZeroDayStays) {
                    return;
                }
                
                $ckOutDate = HTMLContainer::generateMarkup('span', $r['Span_End_Date'] != '' ? date('M j, Y H:i', strtotime($r['Span_End_Date'])) : '');
                
            }
            
        } else {
            
            $edDay = new \DateTime($r['Span_End_Date']);
            $edDay->setTime(0, 0, 0);
            
            $days = $edDay->diff($stDayDT, TRUE)->days;
            
            // Don't show 0-day checked - out stays.
            if ($days == 0 && !$uS->ShowZeroDayStays) {
                return;
            }
            
            $ckOutDate = HTMLContainer::generateMarkup('span', $r['Span_End_Date'] != '' ? date('M j, Y H:i', strtotime($r['Span_End_Date'])) : '');
        }
        
        // guest Name
        if ($idGuest == $r['idName']) {
            $idMarkup = HTMLContainer::generateMarkup('a', $name, array('href' => 'GuestEdit.php?id=' . $r['idName'] . '&psg='.$r['idPsg'], 'class' => 'ui-state-highlight'));
        } else {
            $idMarkup = HTMLContainer::generateMarkup('a', $name, array('href' => 'GuestEdit.php?id=' . $r['idName'] . '&psg='.$r['idPsg']));
        }
        
        // Relationship to patient
        $rel = '';
        if (isset($uS->guestLookups[GLTableNames::PatientRel][$r['Relationship_Code']])) {
            $rel = $uS->guestLookups[GLTableNames::PatientRel][$r['Relationship_Code']][1];
        }
        
        
        $tr = ($hdrPgRb == '' ? '' : HTMLTable::makeTd($pgRb))
        // idName
        .HTMLTable::makeTd($idMarkup)
        // Relationship
        .HTMLTable::makeTd($rel)
        
        // Status
        . HTMLTable::makeTd($r['On_Leave'] > 0 ? 'On Leave' : $r['Status_Title'])
        
        // room
        . HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $r["Room"]))
        
        // CheckIn date
        . HTMLTable::makeTd(
            HTMLInput::generateMarkup(date('M j, Y', strtotime($r['Span_Start_Date'])), array('id' => 'stayCkInDate_' . $r['idStays'], 'class'=>'hhk-stayckin ckdate', 'readonly'=>'raadonly'))
            . ' ' . date('H:i', strtotime($r['Span_Start_Date'])));
        
        
        if ($action == '') {
            // Check Out/Expected check out date
            $tr .=  HTMLTable::makeTd($ckOutDate)
            
            // Days
            . HTMLTable::makeTd($days);
        }
        
        
        // Action button column
        $tr .=  ($includeActionHdr === TRUE ? HTMLTable::makeTd($actionButton) : "");
        
        // Remove button - only if more than one guest is staying
        if ($action == ''
            && $numberRows > 1
            && $r['On_Leave'] == 0
            && $r['Status'] != VisitStatus::CheckedIn
            && $r['idName'] != $idPrimaryGuest
            //                    && $r['Visit_Span'] == 0
            //                    && ($r["Visit_Status"] == VisitStatus::CheckedIn || $r["Visit_Status"] == VisitStatus::CheckedOut)
            ) {
                
                $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup('', array('id' => 'removeCb_' . $r['idStays'], 'name' => '[removeCb][' . $r['idStays'] . ']',
                    'data-nm' => $name,
                    'type' => 'checkbox',
                    'class' => 'hhk-removeCB' )), array('style'=>'text-align:center;'));
                
                $useRemoveHdr = TRUE;
            }
            
            if ($r['Status'] == VisitStatus::CheckedIn) {
                $bodyTr = HTMLContainer::generateMarkup('tr', $tr, array());
            } else {
                $bodyTr = HTMLContainer::generateMarkup('tr', $tr, array('style'=>'background-color:#f2f2f2;'));
            }
            
            return $bodyTr;
            
    }
    
    
}

