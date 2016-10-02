<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of BatchView
 *
 * @author Eric
 */
class BatchView {

    public static function todaysBatch(\PDO $dbh, $batchClosingHour) {

        $dt = new DateTime();
        $dt->setTime($batchClosingHour, 0, 0);

        $stmt = $dbh->query("Select * from vcredit_payments cp where cp.Last_Updated > '" . $dt->format('Y-m-d H:i:s')) . "'";
        $tbl = new HTMLTable();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $avsResult = new AVSResult($r['AVS']);
            $cvvResult = new CVVResult($r['CVV']);

            if ($avsResult->isZipMatch() === FALSE || $cvvResult->isCvvMatch() === FALSE) {

                // Show these
                
            }

        }

        return $tbl->generateMarkup();
    }
}
