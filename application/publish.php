<?php
/**
 * Poems of Anne Sexton
 *
 * Command line to publish poems or update the table of contents
 *
 * @author    Michel Corne <mcorne@yahoo.com>
 * @copyright 2012 Michel Corne
 * @license   http://www.opensource.org/licenses/gpl-3.0.html GNU GPL v3
 * @link      http://anne-sexton.blogspot.com
 */

require_once 'common.php';
require_once 'String.php';

define('OPTION_A', '-c -t');

/**
 * The command help
 */
$help =
'Usage:
-a              Options: %1$s.
-c              Update the copyright widget with the current year.
-l              Display the list of poems.
-n number,...   Optional comma separated list of numbers of poems.
                By default, all poems are processed.
                Mandatory in logged off mode, only one number allowed.
-p password     Blogger account Password.
-t              Update the table of contents widget.
-u name         Blogger user/email/login name.
-v              Verify the HTML generation of data/poems/example.html.

Notes:
In logged on mode, the poem HTML is created/updated in the messages directory.
In logged off mode, the poem HTML is stored in messages/temp.html.

Examples:
# publish poem(s) in Blogger
publish -u abc -p xyz

# publish poems 10 and 11 in Blogger
publish -u abc -p xyz -n 10,11

# create/update poem 10 in messages/temp.html
publish -n 10
';

try {
    if (! $options = getopt("hacln:p:tu:v")) {
        throw new Exception('invalid or missing option(s)');
    }

    if (isset($options['h'])) {
        // displays the command usage (help)
        exit(sprintf($help, OPTION_A));
    }

    if (isset($options['l'])) {
        // displays the list of poems
        require_once 'list-poems.php';
        exec_list_poems();
        exit;
    }

    if (isset($options['v'])) {
        // displays the list of poems
        require_once 'publish-poems.php';
        exec_verify_example();
        exit;
    }

    if (isset($options['a'])) {
        // this is the (combined) option A, adds the options
        preg_match_all('~\w~', (string)OPTION_A, $matches);
        $options += array_fill_keys($matches[0], false);
        unset($options['a']);
    }

    if (isset($options['u']) and isset($options['p'])) {
        // this is the logged on mode, publishes one more poems in Blogger and saves them in local files
        require_once 'publish-poems.php';
        $numbers = isset($options['n'])? $options['n'] : null;
        exec_publish_poems($options['u'], $options['p'], $numbers);

    } else if (isset($options['u']) or isset($options['p'])) {
        throw new Exception('missing user name or password');

    } else if (isset($options['n'])) {
        // this is the logged off mode, makes an poem HTML and saves the content in messages/temp.html
        require_once 'publish-poems.php';
        exec_save_draft($options['n']);
    }

    if (isset($options['c'])) {
        // updates the copyright
        require_once 'update-copyright.php';
        exec_update_copyright();
    }

    if (isset($options['t'])) {
        // updates the table of contents
        require_once 'update-table-of-contents.php';
        exec_update_table_of_contents();
    }

} catch(Exception $e) {
    $string = new String();
    $message = $string->utf8ToInternalString($e->getMessage());
    echo "\nerror! $message";
}
