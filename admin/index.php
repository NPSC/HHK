<?php
/**
 * index.php
 *
 * The login page is shared by every site at the root.  A return page (xf) is relative
 * to this site, so it gets this site's folder in front for the root login to route it.
 *
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
$query = $_GET;

if (isset($query['xf']) && $query['xf'] != '') {
    $query['xf'] = basename(__DIR__) . '/' . $query['xf'];
}

header('location:../index.php' . (count($query) > 0 ? '?' . http_build_query($query) : ''));
