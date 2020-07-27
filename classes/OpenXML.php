<?php

namespace HHK;

use HHK\Exception\RuntimeException;
use PHPExcel_CachedObjectStorageFactory;
use PHPExcel_Settings;

/**
 * OpenXML.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


//require THIRD_PARTY . 'PHPExcel.php';


class OpenXML {

    /**
     *
     * @param string $user
     * @param string $title
     * @param string $subject
     * @param string $description
     * @return \PHPExcel'
     */
    public static function createExcel($user, $title = '', $subject = '', $description = '') {

        // Set cache method
        $cacheMethod = PHPExcel_CachedObjectStorageFactory:: cache_to_phpTemp;

        $cacheSettings = array('memoryCacheSize' => '80MB');

        if (!PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings)) {
            throw new RuntimeException('Cache method unavailable.');
        }

        // Set value binder
        \PHPExcel_Cell::setValueBinder(new \PHPExcel_Cell_AdvancedValueBinder());

        // Create new PHPExcel object
        $objPHPExcel = new \PHPExcel();

        // Set document properties
        $objPHPExcel->getProperties()->setCreator($user)
                ->setLastModifiedBy($user)
                ->setTitle($title)
                ->setSubject($subject)
                ->setDescription($description)
                ->setCategory('Report');

        return $objPHPExcel;
    }

    /**
     *
     * @param \PHPExcel $objPHPExcel
     */
    public static function finalizeExcel(\PHPExcel $objPHPExcel) {
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
        $objPHPExcel->disconnectWorksheets();
        unset($objPHPExcel);
    }

    /**
     *
     * @param \PHPExcel $objPHPExcel
     * @param int $col
     * @param int $row
     * @param mixed $data
     * @param \PHPExcel_Cell_DataType $dataType Defualt: TYPE_NUMERIC
     * @param \PHPExcel_Style_NumberFormat $format Default: FORMAT_GENERAL
     * @return \PHPExcel
     */
    public static function writeAdvCell(\PHPExcel $objPHPExcel, $col, $row, $data, $dataType = \PHPExcel_Cell_DataType::TYPE_NUMERIC, $format = \PHPExcel_Style_NumberFormat::FORMAT_GENERAL) {

        self::writeCell($objPHPExcel, $col, $row, $data, $dataType);

        $objPHPExcel->getActiveSheet()
                ->getStyleByColumnAndRow($col, $row)
                ->getNumberFormat()
                ->setFormatCode($format);

        return $objPHPExcel;
    }

    /**
     *
     * @param \PHPExcel $objPHPExcel
     * @param int $col
     * @param int $row
     * @param mixed $data
     * @param string $dataType  Defualt: TYPE_NUMERIC
     * @return \PHPExcel
     */
    public static function writeCell(\PHPExcel $objPHPExcel, $col, $row, $data, $dataType = \PHPExcel_Cell_DataType::TYPE_NUMERIC) {

        $output = preg_replace_callback("/(&#[0-9]+;)/", function($m) { return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); }, $data);

        $objPHPExcel->getActiveSheet()
                ->getCellByColumnAndRow($col, $row)
                ->setValueExplicit($output, $dataType);

        return $objPHPExcel;
    }

    /**
     *
     * @param \PHPExcel $objPHPExcel
     * @param array $rowData
     * @param int $rowIndex
     * @return int
     */
    public static function writeNextRow(\PHPExcel $objPHPExcel, array $rowData, $rowIndex) {

        for ($c = 0; $c < count($rowData); $c++) {

            if (isset($rowData[$c]['style'])) {

                self::writeAdvCell($objPHPExcel, $c, $rowIndex, $rowData[$c]['value'], $rowData[$c]['type'], $rowData[$c]['style']);
            } else {

                self::writeCell($objPHPExcel, $c, $rowIndex, $rowData[$c]['value'], $rowData[$c]['type']);
            }
        }

        $rowIndex++;
        return $rowIndex;
    }

    /**
     *
     * @param \PHPExcel $objPHPExcel
     * @param array $rowData
     * @param int $rowIndex
     * @return int
     */
    public static function writeHeaderRow(\PHPExcel $objPHPExcel, array $rowData, $rowIndex = 1) {

        $styleArray = array(
            'font' => array(
                'bold' => true,
            ),
            'alignment' => array(
                'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'top' => array(
                    'style' => \PHPExcel_Style_Border::BORDER_THIN,
                ),
            ),
            'fill' => array(
                'type' => \PHPExcel_Style_Fill::FILL_GRADIENT_LINEAR,
                'rotation' => 90,
                'startcolor' => array(
                    'argb' => 'FFA0A0A0',
                ),
                'endcolor' => array(
                    'argb' => 'FFFFFFFF',
                ),
            ),
        );


        // Rows start at 1
        if ($rowIndex == 0) {
            $rowIndex = 1;
        }

        for ($c = 0; $c < count($rowData); $c++) {
            self::writeCell($objPHPExcel, $c, $rowIndex, $rowData[$c], \PHPExcel_Cell_DataType::TYPE_STRING);
            $objPHPExcel->getActiveSheet()->getColumnDimensionByColumn($c)->setAutoSize(true);
            $objPHPExcel->getActiveSheet()->getStyleByColumnAndRow($c, $rowIndex)->applyFromArray($styleArray);
        }

        $rowIndex++;
        return $rowIndex;
    }

}
?>