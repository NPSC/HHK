<?php
namespace HHK\SysConst;

/**
 * Mode.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

// operating mode of site, live, demo, training and dev
// in site.cfg file.
class Mode {
    public const string Live = "live";
    public const string Demo = "demo";
    public const string Training = "train";
    public const string Dev = "dev";
}
