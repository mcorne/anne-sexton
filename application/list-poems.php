<?php
/**
 * Poems of Anne Sexton
 *
 * Command to display the list of poems
 *
 * @author    Michel Corne <mcorne@yahoo.com>
 * @copyright 2013 Michel Corne
 * @license   http://www.opensource.org/licenses/gpl-3.0.html GNU GPL v3
 * @link      http://anne-sexton.blogspot.com
 */

require_once 'common.php';

/**
 * Main function to display the list of poems
 */
 function exec_list_poems()
{
    $poems = get_poems();
    $string = new String();
    $list = array();

    foreach($poems as $index => $poem) {
        $title = $string->utf8ToInternalString($poem['title']['french']);
        $title = remove_special_characters($title);
        $list[] = sprintf('%2u : %s', $index, $title);
    }

    echo "\n" . implode("\n", $list) . "\n";
}