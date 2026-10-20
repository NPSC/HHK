<?php
/**
 * DiagnosisBuilder.php
 *
 * Retired: Diagnosis and Location lookups are edited on Resource Builder's Lookups tab, which sends the
 * whole list as one JSON field and so doesn't hit max_input_vars (the reason this page existed).
 * Kept only so old bookmarks land in the right place.
 *
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

header('Location: ResourceBuilder.php#lkTable', true, 301);
exit();
