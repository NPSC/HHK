<?php
namespace HHK\Admin\Import;

/**
 * Common contract for data importers driven by admin pages
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
interface ImportInterface {

    /**
     * Import the next batch of pending records
     *
     * @param int $limit Max number of records to process in this batch
     * @return array ['success'=>true, 'workerId'=>..., 'progress'=>[...]] or ['error'=>...]
     */
    public function startImport(int $limit = 100): array;

    /**
     * Undo an import
     *
     * @return array ['success'=>...] or ['error'=>...]
     */
    public function undoImport(): array;
}
