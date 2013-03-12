<?php
/**
 * Poems of Anne Sexton
 *
 * Command to update the introduction widget
 *
 * @author    Michel Corne <mcorne@yahoo.com>
 * @copyright 2013 Michel Corne
 * @license   http://www.opensource.org/licenses/gpl-3.0.html GNU GPL v3
 * @link      http://anne-sexton.blogspot.com
 */

require_once 'common.php';

/**
 * Main function to update the introduction widget
 */
 function exec_update_introduction()
{
    $poems = get_poems();
    $html = update_introduction($poems);
    echo "\n" . save_widget($html, 'introduction.html', 'introduction') . "\n";
}

/**
 * Updates the HTML of the introduction widget with or without the link to the poem being translated
 *
 * @param array $poems
 * @return string
 */
function update_introduction($poems)
{
    $html = read_file(__DIR__ . '/../widgets/introduction.html');
    $display = is_translation_in_progress($poems) ? 'block' : 'none';

    return preg_replace('~<div style="display:(block|none)">~', "<div style=\"display:$display\">", $html);
}
