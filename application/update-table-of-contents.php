<?php
/**
 * Poems of Anne Sexton
 *
 * Command to update the table of contents widget
 *
 * @author    Michel Corne <mcorne@yahoo.com>
 * @copyright 2013 Michel Corne
 * @license   http://www.opensource.org/licenses/gpl-3.0.html GNU GPL v3
 * @link      http://anne-sexton.blogspot.com
 */

require_once 'common.php';

/**
 * Main function to update the table of contents widget
 */
 function exec_update_table_of_contents()
{
    $poems = get_poems();
    $html = make_tables_of_contents($poems);
    echo "\n" . save_widget($html, 'table-of-contents.html', 'table of content') . "\n";
}

/**
 * Creates the HTML of the table of contents
 *
 * @param array $poems
 * @param string $language
 * @return string
 */
function make_table_of_contents($poems, $language)
{
    $poems = sort_poems_by_title($poems, $language);
    $table_of_contents = array();

    foreach($poems as $poem) {
        if (! isset($poem['translation-in-progress'])) {
            if (empty($poem['title'][$language]) or empty($poem['url']['english'])) {
                throw new Exception('empty title in a poem');
            }

            $table_of_contents[] = make_table_of_contents_entry($poem['title'][$language], $poem['url']['english']);
        }
    }

    return implode("\n", $table_of_contents);
}

/**
 * Creates the HTML of an entry in the table of contents
 *
 * @param string $title
 * @param string $url
 * @return string
 */
function make_table_of_contents_entry($title, $url)
{
    static $template = '<tr><td><a href="%s">%s</a></td></tr>';

    $title = remove_special_characters($title);
    $title = fix_non_breaking_spaces($title);
    $title = trim($title);
    $title = str_replace("\n", ' ', $title);

    return sprintf($template, $url, $title);
}

/**
 * Creates the HTML of the English and the French tables of contents
 *
 * @param array $poems
 * @return string
 */
function make_tables_of_contents($poems)
{
    $english_table_of_contents = make_table_of_contents($poems, 'english');
    $french_table_of_contents  = make_table_of_contents($poems, 'french');

    return  sprintf(
        load_template('table-of-contents.html'),
        date('c'), // generation date/time
        date('Y'), // copyright year
        $french_table_of_contents,
        $english_table_of_contents
    );
}
