<?php

namespace HHK;
use HHK\Document\Document;
use HHK\Notification\Mail\HHKMailer;
use HHK\sec\Session;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * ExcelHelper.php
 *
 * Helper class for mk-j\XLSXWriter
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ExcelHelper extends \XLSXWriter{

    CONST hdrStyle = ['font-style'=>'bold', 'halign'=>'center', 'auto_filter'=>true, 'widths'=>[]];

    const ACTION_DOWNLOAD = "download";
    const ACTION_EMAIL    = "email";
    const ACTION_SAVE_DOC = "save";
    protected $filename = '';

    /**
     * Helper class for mk-j\XLSXWriter
     *
     * Extends mk-j\XLSXWriter
     *
     * @param string $filename
     */
    public function __construct($filename){
        $this->filename = $filename;
        parent::__construct();
    }

    /**
     * Sets download headers and sends document to stdOut
     */
    public function download(){
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $this->filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        $this->writeToStdOut();
        exit();
    }

    public function saveDoc(\PDO $dbh, string $username, string $reportInputSetName = ""){
        
        $document = Document::createNew($this->filename, "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", $this->writeToString(), $username);

        $document->saveNew($dbh);

        if($document->linkNew($dbh, null, null, $reportInputSetName) > 0){
            return true;
        }else{
            return false;
        }
    }

    public function emailDoc(\PDO $dbh, string $to = ""){
        $uS = Session::getInstance();
        if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $mail = new HHKMailer($dbh);
            $mail->From = ($uS->NoReplyAddr ? $uS->NoReplyAddr : "no_reply@nonprofitsoftwarecorp.org");
            $mail->FromName = htmlspecialchars_decode($uS->siteName, ENT_QUOTES);
            $mail->addAddress($to);

            $mail->isHTML(true);

            $mail->Subject = "Your HHK Report is ready";
            $mail->msgHTML("Your requested report is attached.");

            $mail->addStringAttachment($this->writeToString(), $this->filename . ".xlsx", PHPMailer::ENCODING_BASE64, "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");

            if ($mail->send() === FALSE) {
                return false;
            } else {
                return true;
            }
        }else{
            return false;
        }
    }

    /**
     *
     * Decodes all html entities and removes all html tags on fields defined as string in the header
     *
     * @param array $header
     * @param array $row
     * @return array $row;
     */
    public static function convertStrings(array $header, array $row){
        $n = 0;

        foreach($header as $val){
            if($val == "string" && isset($row[$n])){
                $row[$n] = html_entity_decode(strval($row[$n]), ENT_QUOTES, 'UTF-8'); //decode html entities
                $row[$n] = strip_tags($row[$n]); //remove html tags
            }
            $n++;
        }

        return $row;
    }

    /**
     * Gets predefined header styles and adds column widths
     *
     * @param array $colWidths
     * @return array $hdrStyle
     */
    public static function getHdrStyle(array $colWidths = []){
        $hdrStyle = self::hdrStyle;

        foreach($colWidths as $width){
            $hdrStyle['widths'][] = $width;
        }

        return $hdrStyle;
    }

    public function setFilename(String $filename){
        $this->filename = $filename;
    }

}

?>