<?php

namespace HHK;

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