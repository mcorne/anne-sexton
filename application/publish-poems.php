<?php
/**
 * Poems of Anne Sexton
 *
 * Command to publish poems
 *
 * @author    Michel Corne <mcorne@yahoo.com>
 * @copyright 2013 Michel Corne
 * @license   http://www.opensource.org/licenses/gpl-3.0.html GNU GPL v3
 * @link      http://anne-sexton.blogspot.com
 */

require_once 'Blog.php';
require_once 'common.php';

/**
 * Check mandatory items are present in a a poem
 *
 * @param array $poem
 * @throws Exception
 */
function check_mandatory_items($poem)
{
    $mandatory_items = array(
        'image-href',
        'image-src',
        'source',
        'strophe',
        'title',
        'url',
    );

    foreach($mandatory_items as $mandatory_item) {
        if (!isset($poem[$mandatory_item])) {
            throw new Exception("missing $mandatory_item");
        }
    }
}

/**
 * Checks there is no unprocessed special characters left over in a string
 *
 * @param string $string
 * @throws Exception
 */
function check_no_unprocessed_special_characters($string)
{
    if (preg_match('~[{}|]~', $string)) {
        throw new Exception("unprocessed special characters in: $string");
    }
}

/**
 * Main function to publish poems
 */
function exec_publish_poems($user, $password, $numbers)
{
    $poems = get_poems();
    $htmls = make_messages($poems, $numbers);
    $blog = new Blog($user, $password, 'Anne Sexton');
    echo "\n" . save_messages($htmls, $poems, $blog) . "\n";
}

/**
 * Main function to save a poem as a draft
 */
function exec_save_draft($number)
{
    $poems = get_poems();

    if (! isset($poems[$number])) {
        throw new Exception('invalid poem number');
    }

    $html = make_message($poems, $number);
    echo "\n" . save_temp_message($html, $number) . "\n";
}

/**
 * Main function to verify the HTML generation of example poem
 */
function exec_verify_example()
{
    $poems[]['url']['english'] = '2010/01/abc.html';
    $poems[] = parse_file(__DIR__ . "/../data/example.csv");
    $poems = add_domain_to_urls($poems);

    $html = make_message($poems, 1);
    save_temp_message($html, 1);

    $prevHtml = read_file(__DIR__ . '/../messages/verification-DO-NOT-REMOVE.html');

    if (remove_generated_date($html) == remove_generated_date($prevHtml)) {
        $result = 'The verification was successful.';
    } else {
        $result[] = 'The verification failed.';
        $result[] = 'Compare "messages/verification-DO-NOT-REMOVE.html" and "messages/temp.html" to find the difference.';
        $result[] = 'Fix accordingly.';
    }

    echo "\n" . implode("\n", (array) $result) . "\n";
}

/**
 * Fixes notes numbers so they follow each other
 *
 * @param string $note_number
 * @param bool $expecting_note_number
 * @throws Exception
 * @return int|null
 */
function fix_note_number($note_number, $expecting_note_number = false)
{
    static $note_numbers = array();
    static $is_used = array();

    if ($note_number === 'reset') {
        // resets note numbers
        $note_numbers = array();
        $is_used = array();
        return;
    }

    if ($note_number === 'check') {
        // checks that all note numbers have corresponding text
        $unused_note_numbers = array_diff_key($note_numbers, $is_used);

        if (! empty($unused_note_numbers)) {
            $unused_note_numbers = implode(', ', array_flip($unused_note_numbers));
            throw new Exception("note numbers with no text: $unused_note_numbers");
        }

        return;
    }

    if (! isset($note_numbers[$note_number])) {
        if ($expecting_note_number) {
            throw new Exception("invalid note number: $note_number");
        }

        $note_numbers[$note_number] = end($note_numbers) + 1;

    } else {
        if ($expecting_note_number) {
            $is_used[$note_number] = true;
        }
    }


    return $note_numbers[$note_number];
}

/**
 * Applies various fixes to a string
 *
 * @param string $string
 * @return mixed
 */
function fix_string($string)
{
    $string = fix_non_breaking_spaces($string);
    $string = make_anchored_words($string);
    $string = make_no_translate_words($string);
    $string = trim($string);
    $string = str_replace("\n", '<br />', $string);
    check_no_unprocessed_special_characters($string);

    return $string;
}

/**
 * Creates the HTML for an anchor
 *
 * @param string $string
 * @return mixed
 */
function make_anchored_words($string)
{
    return preg_replace(FORMAT_ANCHORED_WORD, '<a href="$2">$1</a>', $string);
}

/**
 * Cretes the HTML for an English verse
 *
 * @param string $verse
 * @param bool $is_note
 * @return string
 */
function make_english_verse($verse, $is_note)
{
    $verse = fix_non_breaking_spaces($verse);

    if ($is_note) {
        // the French verse has a note, adds an empty note reference
        // to help some browsers align French and English verses properly
        $verse .= '<span class="as-note-ref"></span>';
    }

    check_no_unprocessed_special_characters($verse);

    return $verse;
}

/**
 * Creates the HTML for a French verse
 *
 * @param string $verse
 * @param string $url
 * @param string $note_prefix
 * @param unknown_type $is_note
 * @return Ambigous <string, mixed>
 */
function make_french_verse($verse, $url, $note_prefix, $is_note)
{
    $verse = fix_non_breaking_spaces($verse);
    $verse = make_note_references($verse, $url, $note_prefix, $is_note, true);
    $verse = make_other_translations($verse);
    check_no_unprocessed_special_characters($verse);

    return $verse;
}

/**
 * Make an HTML reference
 *
 * @param string $url
 * @return string
 */
function make_href($url)
{
    return sprintf('href="%s"', $url);
}

/**
 * Creates the HTML for the introduction
 *
 * @param array $lines
 * @param string $url
 * @param string $note_prefix
 * @return array
 */
function make_introduction($lines, $url, $note_prefix)
{
    static $template = "<span class=\"as-intro\">%s</span><br />\n<br />";

    list($english_verses, $french_verses) = make_verses($lines, $url, $note_prefix);
    $english_verses = sprintf($template, $english_verses);
    $french_verses  = sprintf($template, $french_verses);

    return array($english_verses, $french_verses);
}

/**
 * Creates the HTML for a line, that is a French and an English verse
 *
 * @param array $line
 * @param string $url
 * @param string $note_prefix
 * @return array
 */
function make_line($line, $url, $note_prefix)
{
    if (empty($line)) {
        $english_verse = null;
        $french_verse = null;

    } else {
        $is_note = strpos($line['notes'], '#') !== false;
        $english_verse = make_english_verse($line['english'], $is_note);
        $french_verse = make_french_verse($line['french'], $url, $note_prefix, $is_note);
    }

    return array($english_verse, $french_verse);
}

/**
 * Creates the HTML for a set of notes in separate lines within the same cell
 *
 * @param string $notes
 * @param string $url
 * @param string $note_prefix
 * @throws Exception
 * @return string
 */
function make_line_notes($notes, $url, $note_prefix)
{
    $pieces = preg_split(FORMAT_NOTE_DEFINITION, $notes, -1, PREG_SPLIT_DELIM_CAPTURE);
    array_shift($pieces);

    if (empty($pieces)) {
        throw new Exception("expecting notes in: $notes");
    }

    if (count($pieces) % 2) {
        // there is an odd number of pieces
        throw new Exception("missing last note in: $notes");
    }

    $notes = array();

    foreach($pieces as $index => $piece) {
        if ($index % 2 == 0) {
            // this is the note number
            $note_number = $piece;

        } else {
            // this is the note text
            $note_text = $piece;

            if (empty($note_text)) {
                throw new Exception("missing text note for: $note_number in: $notes");
            }

            $notes[] = make_note($note_number, $note_text, $url, $note_prefix);
        }
    }

    return implode("\n", $notes);
}

/**
 * Creates the HTML of a blog message containing a poem
 *
 * @param array $poem The poem details
 * @return string     The HTML content of the poem
 */
function make_message($poems, $number)
{
    static $template = null;

    if (! isset($template)) {
        $template = load_template('poem.html');
    }

    $poem = $poems[$number];
    check_mandatory_items($poem);

    fix_note_number('reset');
    $note_prefix = hash('crc32', $poem['url']['english']);
    list($english_title, $french_title) = make_line($poem['title'], $poem['url']['english'], $note_prefix);

    if (isset($poem['introduction'])) {
        list($english_introduction, $french_introduction) = make_introduction($poem['introduction'], $poem['url']['english'], $note_prefix);
    } else {
        $english_introduction = '';
        $french_introduction  = '';
    }

    list($english_verses, $french_verses) = make_verses($poem['strophe'], $poem['url']['english'], $note_prefix);
    $notes = make_notes($poem, $note_prefix);
    fix_note_number('check'); // verifies all note references have a corresponding text

    if (isset($poem['other'])) {
        $other_sources = make_other_sources($poem['other']);
    } else {
        $other_sources = '';
    }

    if (isset($poems[$number - 1]['url']['english'])) {
        $previous_poem = make_href($poems[$number - 1]['url']['english']);
    } else {
        $previous_poem = '';
    }

    if (isset($poems[$number + 1]['url']['english'])) {
        $next_poem = make_href($poems[$number + 1]['url']['english']);
    } else {
        $next_poem = '';
    }

    $html = sprintf($template,
        remove_special_characters($poem['title']['french']),
        date('c'), // generation date
        date('Y'), // copyright year
        $previous_poem,
        $next_poem,
        $french_title,
        $poem['image-href']['english'],
        $poem['image-src']['english'],
        $english_title,
        $french_introduction  . $french_verses,
        $english_introduction . $english_verses,
        $notes,
        fix_string($poem['source']['english']),
        fix_string($poem['source']['french']),
        $other_sources,
        $previous_poem,
        $next_poem
    );

    return $html;
}

/**
 * Creates the HTML messages for all poems of a subset of poems given by their number
 *
 * @param array $poems
 * @param array $numbers
 * @return array
 */
function make_messages($poems, $numbers)
{
    if (isset($numbers)) {
        $numbers = explode(',', $numbers);
        $numbers = array_fill_keys($numbers, true);
    }
    $htmls = array();

    foreach($poems as $number => $poem) {
        if (! isset($numbers) or isset($numbers[$number])) {
            $htmls[] = make_message($poems, $number);
        }
    }

    return $htmls;
}

/**
 * Makes the HTML for a set of words that should be translated by Google translate or the like
 *
 * @param string $string
 * @return mixed
 */
function make_no_translate_words($string)
{
    return preg_replace('~\{(.+?)\}~', '<span class="notranslate">$1</span>', $string);
}

/**
 * Makes the HTML for a note
 *
 * @param string $note_number
 * @param string $note_text
 * @param string $url
 * @param string $note_prefix
 * @return string
 */
function make_note($note_number, $note_text, $url, $note_prefix)
{
    static $template = '<tr><td><a class="as-note-number" href="%1$s#%2$s-note-ref-%3$d" id="%2$s-note-text-%3$d">%3$d</a></td> <td>%4$s</td></tr>';

    $note_number = fix_note_number($note_number, true);
    $note_text = make_note_references($note_text, $url, $note_prefix, false, false);
    $note_text = fix_string($note_text);
    $note_text = sprintf($template, $url, $note_prefix, $note_number, $note_text);

    return $note_text;
}

/**
 * Makes the HTML for poem notes
 *
 * @param array $poem
 * @param string $note_prefix
 * @return string
 */
function make_notes($poem, $note_prefix)
{
    $lines[] = $poem['title'];

    if (isset($poem['introduction'])) {
        $lines = array_merge($lines, $poem['introduction']);
    }

    $lines = array_merge($lines, $poem['strophe']);
    $notes = array();

    foreach($lines as $line) {
        if (! empty($line['notes'])) {
            $notes[] = make_line_notes($line['notes'], $poem['url']['english'], $note_prefix);
        }
    }

    return implode("\n", $notes);
}

/**
 * Makes the HTML for a note reference
 *
 * @param string $note_number
 * @param string $url
 * @param string $note_prefix
 * @param bool   $in_verse
 * @return string
 */
function make_note_reference($note_number, $url, $note_prefix, $in_verse)
{
    static $note_in_verse_template = '<span class="as-note-ref"> <a href="%1$s#%2$s-note-text-%3$d" id="%2$s-note-ref-%3$d">%3$d</a></span>';
    static $note_in_note_template  = '&nbsp;<span class="as-note-text"><a href="%1$s#%2$s-note-text-%3$d">%3$d</a></span>';

    $note_number = fix_note_number($note_number, false);
    $template = $in_verse? $note_in_verse_template : $note_in_note_template;

    return sprintf($template, $url, $note_prefix, $note_number);
}

/**
 * Makes HTML for note references in verses
 *
 * @param string $verse
 * @param string $url
 * @param string $note_prefix
 * @param bool $is_note
 * @param bool $in_verse
 * @throws Exception
 * @return string
 */
function make_note_references($verse, $url, $note_prefix, $is_note, $in_verse)
{
    if (! preg_match_all(FORMAT_NOTE_REFERENCE, $verse, $matches, PREG_SET_ORDER)) {
        if ($is_note) {
            throw new Exception("expecting reference in: $verse");
        } else {
            return $verse;
        }
    }

    foreach($matches as $match) {
        list($untrimmed_note_number, $note_number) = $match;
        $note_reference = make_note_reference($note_number, $url, $note_prefix, $in_verse);
        $verse = str_replace($untrimmed_note_number, $note_reference, $verse);
    }

    return $verse;
}

/**
 * Makes the HTML for another source of information
 *
 * @param array $line
 * @return string
 */
function make_other_source($line)
{
    $template = '<tr><td>%s</td> <td>%s</td></tr>';

    $source_name = fix_string($line['english']);
    $source_text = fix_string($line['french']);

    return sprintf($template, $source_name, $source_text);
}

/**
 * Makes the HTML for other sources of information
 *
 * @param array $lines
 * @return string
 */
function make_other_sources($lines)
{
    $other_sources = array_map('make_other_source', $lines);

    return implode("\n", $other_sources);
}

/**
 * Makes HTML for other translations
 *
 * @param string $string
 * @return string
 */
function make_other_translations($string)
{
    return preg_replace(FORMAT_OTHER_TRANSLATIONS, '<span class="as-other-translation" title="$2">$1</span>', $string);
}

/**
 * Makes the HTML for the English and French sets of verses
 *
 * @param array $lines
 * @param string $url
 * @param string $note_prefix
 * @return array
 */
function make_verses($lines, $url, $note_prefix)
{
    $english_verses = array();
    $french_verses  = array();

    foreach($lines as $line) {
        list($english_verse, $french_verse) = make_line($line, $url, $note_prefix);
        $english_verses[] = $english_verse;
        $french_verses[]  = $french_verse;
    }

    $english_verses = implode("<br />\n", $english_verses);
    $french_verses  = implode("<br />\n", $french_verses);

    return array($english_verses, $french_verses);
}

/**
 * Saves a poem in a blog message (publishes a poem)
 *
 * The poem is also saved in a file.
 * The blog message is published only if the HTML content of the poem has changed.
 *
 * @param string $html    The HTML content of the poem
 * @param array  $poem The poem details
 * @param Blog   $blog    The Blog object
 * @param int    $number  The poem number
 * @return boolean        True if the poem has changed and was saved in the blog, false otherwise
 */
function save_message($html, $poem, Blog $blog, $number)
{
    if (empty($poem['url']['english'])) {
        throw new Exception("the blog message url is empty for poem $number");
    }

    $url = $poem['url']['english'];
    $file = __DIR__ . '/../messages/' . basename($url);
    $prev_html = file_exists($file)? read_file($file) : null;

    if (remove_generated_date($html) != remove_generated_date($prev_html)) {
        // the poem is different from the currently saved version
        echo "$number : " . basename($url, '.html') . "\n";

        // removes line breaks because Blogger replaces them with <br> for some reason which screws up the display
        // although messages are set to use HTML as it is and to use <br> for line feeds
        $content = str_replace("\n", ' ', $html);
        $blog->savePost($poem['title']['french'], $content, $url);
        write_file($file, $html);
        $isPublished = true;

    } else {
        $isPublished = false;
    }

    return $isPublished;
}

/**
 * Saves the poems in the blog (publishes the poems)
 *
 * @param array $htmls    The HTML contents of the poems
 * @param array $poems    The poems details
 * @param Blog  $blog     The Blog object
 * @return string         The result of the action to be displayed to the output
 */
function save_messages($htmls, $poems, Blog $blog)
{
    $published_count = 0;

    foreach($htmls as $number => $html) {
        $published_count += save_message($html, $poems[$number], $blog, $number);
    }

    if ($published_count == 0) {
        $result = 'No poem has changed, no poem was published.';
    } else if ($published_count == 1) {
        $result = 'The poem has changed, the poem was published successfully.';
    } else {
        $result = "The $published_count poems were published successfully.";
    }

    return $result;
}

/**
 * Saves the HTML content of a poem into a temporary file
 *
 * The poem is saved in messages/temp.html that is used for checking changes before commiting them to the blog.
 *
 * @param string $html   The HTML content of the poem
 * @param int    $number The poem number
 * @return string        The result of the action to be displayed to the output
 */
function save_temp_message($html, $number)
{
    $temp = 'messages/temp.html';
    $file = __DIR__ . "/../$temp";
    $prevHtml = file_exists($file)? read_file($file) : null;

    if (remove_generated_date($html) == remove_generated_date($prevHtml)) {
        $result = "The poem is already up to date in $temp.";

    } else {
        write_file(__DIR__ . "/../$temp", $html);
        $result = "The poem was saved successfully in $temp.";
    }

    return $result;
}
