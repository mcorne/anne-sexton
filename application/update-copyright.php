<?php
/**
 * Poems of Anne Sexton
 *
 * Command to update the copyright widget
 *
 * @author    Michel Corne <mcorne@yahoo.com>
 * @copyright 2013 Michel Corne
 * @license   http://www.opensource.org/licenses/gpl-3.0.html GNU GPL v3
 * @link      http://anne-sexton.blogspot.com
 */

require_once 'common.php';

/**
 * Main function to update the copyright widget
 */
 function exec_update_copyright()
{
    $html = read_file(__DIR__ . '/../widgets/copyright.html');
    $year = date('Y');
    $html = preg_replace('~<span style="white-space:nowrap">2009-\d+</span>~', "<span style=\"white-space:nowrap\">2009-$year</span>", $html);
    echo "\n" . save_widget($html, 'copyright.html', 'copyright') . "\n";
}