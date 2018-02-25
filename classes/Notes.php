<?php
/**
 * Notes.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Notes
 *
 * @author Eric
 */
class Notes {

    public static function markupShell($notesText, $taId, $txtboxRows = '1', $taClass = 'hhk-feeskeys') {

        if (is_null($notesText)) {
            $notesText = '';
        }

        $inputTa = HTMLContainer::generateMarkup(
                     'textarea',
                     '',
                     array('name'=>$taId, 'rows'=>$txtboxRows, 'class'=>$taClass)
                     );

        // reverse output
        $lines = explode("\n", $notesText);
        $reverse = "";

        for ($i = (count($lines) - 1); $i >= 0; $i--) {
            $reverse .= $lines[$i] . "<br/>";
        }

        $output = HTMLContainer::generateMarkup('div', $reverse, array('id'=>'hhk-existgNotes'));

        return HTMLContainer::generateMarkup('div', $output . $inputTa, array('class'=>'hhk-noteBox'));

    }

    public static function getNotesDiv($notesText, $class = 'hhk-currentNotes') {

        // reverse output
        $lines = explode("\n", $notesText);
        $reverse = "";

        for ($i = (count($lines) - 1); $i >= 0; $i--) {
            $reverse .= $lines[$i] . "<br/>";
        }


        return HTMLContainer::generateMarkup('div', $reverse, array('class'=>$class));
    }
}
