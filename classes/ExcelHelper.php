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
     * Adds ExcelRichText cells (an inline string of colored runs) to the cell types XLSXWriter knows.
     */
    protected function writeCell(\XLSXWriter_BuffererWriter &$file, $row_number, $column_number, $value, $num_format_type, $cell_style_idx)
    {
        if ($value instanceof ExcelRichText && !$value->isEmpty()) {

            $runs = '';

            foreach ($value->getRuns() as [$text, $color]) {

                // Runs with their own properties don't inherit the cell font, so match XLSXWriter's default font.
                $rPr = '<rFont val="Arial"/><sz val="10"/>';
                if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                    $rPr .= '<color rgb="FF' . strtoupper(substr($color, 1)) . '"/>';
                }

                $runs .= '<r><rPr>' . $rPr . '</rPr><t xml:space="preserve">' . self::xmlspecialchars($text) . '</t></r>';
            }

            $file->write('<c r="' . self::xlsCell($row_number, $column_number) . '" s="' . $cell_style_idx . '" t="inlineStr"><is>' . $runs . '</is></c>');
            return;
        }

        parent::writeCell($file, $row_number, $column_number, $value, $num_format_type, $cell_style_idx);
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
            if($val == "string" && isset($row[$n]) && is_scalar($row[$n])){ // leave ExcelRichText values alone
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


    /**
     * Generate and download Excel file from multidimentional array
     *
     * @param array $rows
     * @param string $fileName
     * @return void
     */
    public static function doExcelDownLoad(array $rows, string $fileName):void
    {
        $writer = new ExcelHelper($fileName);

        if (count($rows) === 0) {
            $writer->writeSheetRow("Sheet1", ['No records found']);
            $writer->download();
        }

        $reportRows = 1;

        // build header
        $hdr = array();
        $colWidths = array();

        $keys = array_keys($rows[0]);

        foreach ($keys as $t) {
            $hdr[$t] = "string";
            $colWidths[] = "20";
        }

        $hdrStyle = $writer->getHdrStyle($colWidths);

        $writer->writeSheetHeader("Sheet1", $hdr, $hdrStyle);

        foreach ($rows as $r) {

            $flds = array_values($r);

            $row = $writer->convertStrings($hdr, $flds);
            $writer->writeSheetRow("Sheet1", $row);
        }
        $writer->download();
    }

}

?>