<?php
/**
 * ConfirmationForm.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of ConfirmationForm
 *
 * @author Eric
 */
class ConfirmationForm {

    const NOTES = 'Notes';
    const GUESTNAME = 'GuestName';
    const ADDRESS = 'StreetAddress';
    const CITY = 'City';
    const STATE = 'State';
    const ZIP = 'Zip';
    const ARRIVAL = 'ExpectedArrival';
    const DEPARTURE = 'ExpectedDeparture';
    const AMOUNT = 'Amount';
    const NIGHTS = 'Nites';
    const DATE_TODAY = 'DateToday';
    
    protected $mime;
    protected $template;
    protected $replacements;


    function __construct($fileName) {
        
        $this->mime = array(
            'html'      => 'text/html',
            'htm'      => 'text/html',
            'mht'      => 'text/html',
            'mhtml'      => 'text/html',
        );
        
        $this->getFormTemplate($fileName);

    }

    protected function makeReplacements(\PDO $dbh, Reservation_1 $reserv, Guest $guest, $amount, $notes) {

        $addr = $guest->getAddrObj()->get_Data();

        $this->replacements = array(
            ConfirmationForm::GUESTNAME => $guest->getRoleMember()->get_fullName(),
            ConfirmationForm::ADDRESS => $addr['Address_1'] . ($addr['Address_2'] == '' ? '' : ' ' . $addr['Address_2']),
            ConfirmationForm::CITY => $addr['City'],
            ConfirmationForm::STATE => $addr['State_Province'],
            ConfirmationForm::ZIP => $addr['Postal_Code'],
            ConfirmationForm::ARRIVAL => date('M j, Y', strtotime($reserv->getExpectedArrival())),
            ConfirmationForm::DEPARTURE => date('M j, Y', strtotime($reserv->getExpectedDeparture())),
            ConfirmationForm::DATE_TODAY => date('M j, Y'),
            ConfirmationForm::NIGHTS => $reserv->getExpectedDays($reserv->getExpectedArrival(), $reserv->getExpectedDeparture()),
            ConfirmationForm::AMOUNT => number_format($amount, 2),
            ConfirmationForm::NOTES => $notes,
        );
    }

    public function createForm(\PDO $dbh, $resv, $guest, $amount, $notes = '') {
        
        $this->makeReplacements($dbh, $resv, $guest, $amount, $notes);

        $vars = $this->getVariables();
        
        foreach ($vars as $v) {
            
            if (isset($this->replacements[$v])) {
                $this->setValue($v, $this->replacements[$v]);
            }
        }

        return str_replace('  ', ' ', $this->template);
    }

    protected function setValue($search, $replace) {

        $this->template = str_replace(self::ensureMacroCompleted($search), $replace, $this->template);

    }
    
    protected static function ensureMacroCompleted($macro) {
        
        if (substr($macro, 0, 2) !== '${' && substr($macro, -1) !== '}') {
            $macro = '${' . $macro . '}';
        }

        return $macro;
    }
    
    public function getVariables() {
        
        $matches = array();
        
        preg_match_all('/\$\{(.*?)}/i', $this->template, $matches);

        return array_unique($matches[1]);

    }
    
    public static function createNotes($text, $editable) {
        
        $notesText = '';

        if ($editable) {
            $notesText .= HTMLContainer::generateMarkup('p', HTMLContainer::generateMarkup('span', "Special Note", array('style'=>'font-weight:bold;')));
            $notesText .= HTMLContainer::generateMarkup('textarea', '', array('id'=>'tbCfmNotes', 'name'=>'tbCfmNotes', 'rows'=>'3', 'cols'=>'80'));
        } else if (strlen($text) > 5) {
            $notesText .= HTMLContainer::generateMarkup('p', HTMLContainer::generateMarkup('span', "Special Note", array('style'=>'font-weight:bold;')) . "<br/>" . nl2br($text));
            $notesText .= '<br />';
        }
        
        return $notesText;
    }

    protected function getFormTemplate($fileName) {

        $path = REL_BASE_DIR . 'conf' . DS . $fileName;
        $this->template = '';

        if (file_exists($path)) {

            $pathInfo = pathinfo($fileName);
            
            if (isset($this->mime[strtolower($pathInfo['extension'])]) === FALSE) {
                throw new Hk_Exception_Runtime("Confirmation file extension not supported, file = " . $fileName);
            }
            
            if (($text = file_get_contents($path)) === FALSE) {
                throw new Hk_Exception_Runtime("Confirmation file template not read, file = " . $fileName);
            }
            
            $this->template = $text;

        } else {
            throw new Hk_Exception_Runtime("Confirmation file template does not exist, file = " . $fileName);
        }

    }
}
