<?php
/**
 * Poems of Anne Sexton
 *
 * Common functions
 *
 * @author    Michel Corne <mcorne@yahoo.com>
 * @copyright 2013 Michel Corne
 * @license   http://www.opensource.org/licenses/gpl-3.0.html GNU GPL v3
 * @link      http://anne-sexton.blogspot.com
 */

require_once 'String.php';

define('FORMAT_ANCHORED_WORD'     , '~\|(.+?)\|(https?://.+?)\|~');
define('FORMAT_NOTE_DEFINITION'   , '~(#\d+[a-z]?): +~m');
define('FORMAT_NOTE_REFERENCE'    , '~ +(#\d+[a-z]?)~');
define('FORMAT_OTHER_TRANSLATIONS', '~\{(.+?), *(.+?)\}~');

/**
 * Completes a URL with the blog domain name
 *
 * @param string $url
 * @throws Exception
 * @return string
 */
function add_domain_to_url($url)
{
    if (empty($url)) {
        return null;
    }

    if (! preg_match('~20\d\d/\d\d/[a-z-]+\.html~', $url)) {
        throw new Exception("bad url $url");
    }

    return "http://anne-sexton.blogspot.com/$url";
}

/**
 * Completes the blog message URL of the poems with the blog domain name
 *
 * @param array $poems
 * @return array
 */
function add_domain_to_urls($poems)
{
    foreach($poems as &$poem) {
        if (empty($poem['url']['english'])) {
            throw new Exception('missing blog message URL in a poem');
        }

        $poem['url']['english'] = add_domain_to_url($poem['url']['english']);
    }

    return $poems;
}

/**
 * Echos the command title
 *
 * @param string $command_title
 * @param bool   $add_dots
 */
function echo_command_title($command_title, $add_dots = true)
{
    echo $command_title;

    if ($add_dots) {
        echo ' ... ';
    }
}

/**
 * Echos a message stating if the content has changed or not
 *
 * @param bool $has_content_changed true if the content has changed, false otherwise
 */
function echo_has_content_changed($has_content_changed)
{
    echo $has_content_changed? '(content has changed)' : '(content has not changed)';
    echo "\n";
}

/**
 * Fixes a file content line endings to the Unix line ending
 *
 * @param string  $content the file content
 * @return string          the file content with Unix style line endings
 */
function fix_line_endings($content)
{
    // fixes windows line ending
    $content = str_replace("\r\n", "\n", $content);
    // fixes mac line ending
    $content = str_replace("\r", "\n", $content);

    return $content;
}

/**
 * Replaces spaces with HTML non-breaking spaces around some punctuation characters
 *
 * @param string $string
 * @return string
 */
function fix_non_breaking_spaces($string)
{
    $string = preg_replace('~([«/]) +~u', '$1&nbsp;', $string);
    $string = preg_replace('~ +([»/;:?!])~u', '&nbsp;$1', $string);

    return $string;
}

/**
 * Returns the column headers from the first row of a CSV file
 *
 * @param array $rows the array of rows
 * @return array      the list of column headers
 */
function get_column_headers($rows)
{
    $first_row = current($rows);
    $column_names = array_keys($first_row);

    return array_combine($column_names, $column_names);
}

/**
 * Returs the content of the poems
 *
 * @return array
 */
function get_poems()
{
    $poems = parse_files();
    $poems = add_domain_to_urls($poems);
    $poems = sort_poems_by_title($poems, 'french');

    return $poems;
}

/**
 * Indexes an array of rows with one of the keys
 *
 * The key is meant to be a column file header.
 *
 * @param array  $rows               the array of rows, each row being an associative array,
 *                                   one of the keys must be set to the column name
 * @param string $column_header      the name of the key or column header
 * @param array  $non_unique_indexes non unique indexes, eg "strophe"
 * @throws Exception
 * @return array                     the associative array of rows
 */
function index_rows($rows, $column_header, $non_unique_indexes = null)
{
    settype($non_unique_indexes, 'array');

    $indexed_rows = array();
    $current_index = null;

    foreach($rows as $row) {
        if (! isset($row[$column_header])) {
            throw new Exception("missing column $column_header");
        }

        // eg indexes by type
        $index = $row[$column_header];

        if (empty($index)) {
            if (is_null($current_index)) {
                throw new Exception('missing index/type');
            }

            // adds the row, typically the second line of a strophe and so on
            $indexed_rows[$current_index][] = $row;

        } else if (in_array($index, $non_unique_indexes)) {
            // this is multi line type entry, eg a strophe
            $current_index = $index;

            if (isset($index_instance[$current_index])) {
                // eg this is a new strophe, adds a blank row
                // TODO: fix as needed, as strophes are expected to follow each other
                $indexed_rows[$current_index][] = null;
            } else {
                // eg this is a new type
                $index_instance[$current_index] = true;
            }

            // adds the row to the current type, eg adds a line to a strophe
            $indexed_rows[$current_index][] = $row;

        } else if (isset($indexed_rows[$index])) {
            // eg the title may be defined only once
            throw new Exception("index already used: $index");

        } else {
            $indexed_rows[$index] = $row;
        }
    }

    return $indexed_rows;
}

/**
 * Loads an HTML template
 *
 * @param string $basename The base name of the template
 * @return string          The HTML content of the template excluding the docblock
 */
function load_template($basename)
{
    $html = read_file(__DIR__ . "/../templates/$basename");
    // removes docblock
    $html = preg_replace('~^<!--.+?-->\s*~s', '', $html);

    return trim($html);
}

/**
 * Parses a CSV formatted poem
 *
 * @param string $filename
 * @return array
 */
function parse_file($filename)
{
    $content = read_csv($filename, 'type', array('introduction', 'other', 'strophe'));

    return $content;
}

/**
 * Parses CSV formatted poems
 *
 * @return array
 */
function parse_files()
{
    $filenames = glob(__DIR__ . "/../data/poems/*.csv");

    return array_map('parse_file', $filenames);
}

/**
 * Reads a CSV file
 *
 * @param string $filename           the file name
 * @param string $column_header      the name of the key or column header to index with, or null for no indexing
 * @param array  $non_unique_indexes non unique indexes, eg "strophe"
 * @return                           the array of rows, each row being an associative array containing the cell values
 *                                   with the column headers as keys
 */
function read_csv($filename, $column_header = null, $non_unique_indexes = null)
{
    $content = read_file($filename);

    $content = fix_line_endings($content);
    $content = replace_line_breaks_in_cells($content);
    $lines = explode("\n", $content);
    $lines = array_filter($lines, function($line) { return preg_match('~[^\t]~', $line); });
    $lines = array_map('restore_line_breaks_in_cells', $lines);

    $first_line = array_shift($lines);
    $column_headers = read_line($first_line);
    $columns_count = count($column_headers);

    $rows = array();
    foreach($lines as $line) {
        $cells = read_line($line);
        $cells = array_pad($cells, $columns_count, null);
        $rows[] = array_combine($column_headers, $cells);
    }

    if (isset($column_header)) {
        $rows = index_rows($rows, $column_header, $non_unique_indexes);
    }

    return $rows;
}

/**
 * Reads a file
 *
 * @param string $filename the file name
 * @throws Exception
 * @return string          the file content
 */
function read_file($filename)
{
    if (! $content = @file_get_contents($filename)) {
        throw new Exception("cannot read $filename");
    }

    return $content;
}

/**
 * Reads a TAB separated line content
 *
 * @param string $line the line
 * @return array an array of cell values
 */
function read_line($line)
{
    // splits the line by tabs
    $cells = explode("\t", $line);

    foreach($cells as &$cell) {
        // trims the enclosing quotes
        $cell = trim($cell, '" '); // TODO: fix as needed, as enclosing escaped quotes will be removed as well
        // unescapes quotes
        $cell = str_replace('""', '"', $cell);
    }

    return $cells;
}

/**
 * Removes special characters and URLs used to define anchors
 *
 * @param string $string
 * @return string
 */
function remove_anchored_words($string)
{
    return preg_replace(FORMAT_ANCHORED_WORD, '$1', $string);
}

/**
 * Removes the generated date from a docblock for comparison purposes
 *
 * @param string $html The HTML content of an episode
 * @return string      The HTML content without the generated date
 */
function remove_generated_date($html)
{
    // removes the date in the episode being translated
    $html = preg_replace('~^ +<input id="rdr-translation-in-progress-date" type="hidden" value=".+?"/>$~m', '', $html);
    $html = preg_replace('~^\s*Generated.+?$~m', '', $html);
    $html = preg_replace('~^\s*@copyright.+?$~m', '', $html);

    return $html;
}

/**
 * Removes special characters used for note references from a string
 *
 * @param string $string
 * @return string
 */
function remove_note_references($string)
{
    $string = preg_replace(FORMAT_NOTE_REFERENCE, '', $string);

    return $string;
}

/**
 * Removes special characters used for other translations from a string
 *
 * @param string $string
 * @return string
 */
function remove_other_translations($string)
{
    $string = preg_replace(FORMAT_OTHER_TRANSLATIONS, '$1', $string);

    return $string;
}

/**
 * Removes special characters from a string
 *
 * @param string $string
 * @return string
 */
function remove_special_characters($string)
{
    $string = remove_note_references($string);
    $string = remove_other_translations($string);
    $string = remove_anchored_words($string);

    return $string;
}

/**
 * Replaces line breaks within cells with HTML line breaks
 *
 * @param string $content
 * @return mixed
 */
function replace_line_breaks_in_cells($string)
{
    $string = str_replace('""', '_ESCAPED_QUOTES_', $string);
    $string = preg_replace_callback('~"[^"]+"~', function($matches) { return str_replace("\n", '_LINE_BREAK_', $matches[0]);}, $string);
    $string = str_replace('_ESCAPED_QUOTES_', '""', $string);

    return $string;
}

/**
 * Restores line breaks in a string
 *
 * @param string $string
 * @return string
 */
function restore_line_breaks_in_cells($string)
{
    return str_replace('_LINE_BREAK_', "\n", $string);
}

/**
 * Saves the HTML content of a widget
 *
 * @param  string $html     The HTML content of the widget
 * @param  string $basename The file base name
 * @param  string $widget   The widget name
 * @return string           The result of the action to be displayed to the output
 */
function save_widget($html, $basename, $widget)
{
    $file = "widgets/$basename";
    $path = __DIR__ . "/../$file";
    $prev_html = file_exists($path)? read_file($path) : null;

    if (remove_generated_date($html) == remove_generated_date($prev_html)) {
        $result[] = "The $widget is already up to date.";
        $result[] = "No changes were made to $file.";

    } else {
        write_file($path, $html);
        $result[] = "The $widget was updated successfully.";
        $result[] = "Please, COPY & PASTE the content of $file";
        $result[] = 'into the corresponding blog widget.';
    }

    return implode("\n", $result);
}

/**
 * Sorts the poems by title in a given language
 *
 * @param array $poems
 * @param string $language
 * @return array
 */
function sort_poems_by_title($poems, $language)
{
    $string = new String();
    $sorted = array();

    foreach($poems as $poem) {
        if (empty($poem['title'][$language])) {
            throw new Exception('missing title in a poem');
        }

        $title = $string->utf8toASCII($poem['title'][$language]);
        $sorted[$title] = $poem;
    }

    ksort($sorted);

    return array_values($sorted);
}

/**
 * Writes a file
 *
 * @param string $filename the file name
 * @param string $content  the file content
 * @throws Exception
 * @return boolean         true if the file content has changed (and the file was actually written),
 *                         false otherwise
 */
function write_file($filename, $content)
{
    if (is_array($content)) {
        $content = implode("\n", $content);
    }

    $is_file = file_exists($filename);

    if ($is_file and read_file($filename) == $content) {
        $has_content_changed = false;

    } else {
        $has_content_changed = true;

        if (! @file_put_contents($filename, $content)) {
            throw new Exception("cannot write $filename");
        }
    }

    return $has_content_changed;
}