<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @author    Jordi Pujol-Ahulló <jordi.pujol@urv.cat>
 * @author    John Hoopes <hoopes@wisc.edu>, University of Wisconsin - Madison
 * @copyright 2013 Servei de Recursos Educatius (http://www.sre.urv.cat)
 */
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once(__DIR__ . '/select_form.php');
require_once(__DIR__ . '/review_form.php');
require_once(__DIR__ . '/locallib.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/mergeusers/lib.php');

/**
 * Renderer for the merge user plugin.
 *
 * @package    tool
 * @subpackage mergeuser
 * @copyright  2013 Jordi Pujol-Ahulló, SREd, Universitat Rovira i Virgili
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_mergeusers_renderer extends plugin_renderer_base {
    /**
     * On index page, show only the search form.
     */
    const INDEX_PAGE_SEARCH_STEP = 1;
    /**
     * On index page, show both search and select forms.
     */
    const INDEX_PAGE_SEARCH_AND_SELECT_STEP = 2;
    /**
     * On index page, show only the list of users to merge.
     */
    const INDEX_PAGE_CONFIRMATION_STEP = 3;
    /**
     * On index page, show the merging results.
     */
    const INDEX_PAGE_RESULTS_STEP = 4;

    /**
     * Renderers a progress bar.
     *
     * @param array $items An array of items
     *
     * @return string
     */
    public function progress_bar(array $items): string {
        foreach ($items as &$item) {
            $text = $item['text'];
            unset($item['text']);
            if (array_key_exists('link', $item)) {
                $link = $item['link'];
                unset($item['link']);
                $item = html_writer::link($link, $text, $item);
            } else {
                $item = html_writer::tag('span', $text, $item);
            }
        }
        return html_writer::tag(
                'div', join(get_separator(), $items),
                ['class' => 'merge_progress clearfix']
        );
    }

    /**
     * Returns the HTML for the progress bar, according to the current step.
     *
     * @param int $step current step
     *
     * @return string HTML for the progress bar.
     */
    public function build_progress_bar(int $step): string {
        $steps = [
                ['text' => '1. ' . mergusergetstring('choose_users', 'tool_mergeusers')],
                ['text' => '2. ' . mergusergetstring('review_users', 'tool_mergeusers')],
                ['text' => '3. ' . mergusergetstring('results', 'tool_mergeusers')],
        ];

        switch ($step) {
            case self::INDEX_PAGE_SEARCH_STEP:
            case self::INDEX_PAGE_SEARCH_AND_SELECT_STEP:
                $steps[0]['class'] = 'bold';
                break;
            case self::INDEX_PAGE_CONFIRMATION_STEP:
                $steps[1]['class'] = 'bold';
                break;
            case self::INDEX_PAGE_RESULTS_STEP:
                $steps[2]['class'] = 'bold';
        }

        return $this->progress_bar($steps);
    }

    /**
     * Shows form for merging users.
     *
     * @param moodleform $mform form for merging users.
     * @param int $step step to show in the index page.
     * @param UserSelectTable|null $ust table for users to merge after searching
     *
     * @return       string html to show on index page.
     * @throws moodle_exception
     * @noinspection PhpUndefinedMethodInspection
     */
    public function index_page(moodleform $mform, int $step, UserSelectTable $ust = null): string {
        $output = $this->header();
        $output .= $this->heading_with_help(mergusergetstring('mergeusers', 'tool_mergeusers'), 'header', 'tool_mergeusers');

        $output .= $this->build_progress_bar($step);

        switch ($step) {
            case self::INDEX_PAGE_SEARCH_STEP:
                $output .= $this->moodleform($mform);
                break;
            case self::INDEX_PAGE_SEARCH_AND_SELECT_STEP:
                $output .= $this->moodleform($mform);
                // Render user select table if available.
                if ($ust !== null) {
                    $this->page->requires->js_init_call('M.tool_mergeusers.init_select_table', []);
                    $output .= $this->render_user_select_table($ust);
                }
                break;
            case self::INDEX_PAGE_CONFIRMATION_STEP:
                break;
        }

        $output .= $this->render_user_review_table($step);
        $output .= $this->footer();
        return $output;
    }

    /**
     * Renders user select table
     *
     * @param UserSelectTable $ust the user select table
     *
     * @return string $tablehtml html string rendering
     */
    public function render_user_select_table(UserSelectTable $ust): string {
        return $this->moodleform(new selectuserform($ust));
    }

    /**
     * Builds and renders a user review table
     *
     * @param $step
     *
     * @return string $reviewtable HTML of the review table section
     * @throws moodle_exception
     */
    public function render_user_review_table($step): string {
        return $this->moodleform(
                new reviewuserform(
                        new UserReviewTable($this),
                        $this,
                        $step === self::INDEX_PAGE_CONFIRMATION_STEP
                )
        );
    }

    /**
     * Displays merge users tool error message
     *
     * @param string $message The error message
     * @throws coding_exception
     * @noinspection PhpUndefinedMethodInspection
     */
    public function mu_error(string $message) {

        echo $this->header();
        $errorhtml = '';
        $errorhtml .= $this->output->box($message, 'generalbox align-center');
        $returnurl = new moodle_url('/admin/tool/mergeusers/index.php');
        $returnbutton = html_writer::link($returnurl, mergusergetstring('error_return', 'tool_mergeusers'));
        $errorhtml .= $returnbutton;
        echo $errorhtml;
        echo $this->footer();
    }

    /**
     * Shows the result of a merging action.
     *
     * @param object $to stdClass with at least id and username fields.
     * @param object $from stdClass with at least id and username fields.
     * @param bool $success true if merging was ok; false otherwise.
     * @param array $data logs of actions done if success, or list of errors on failure.
     * @param $logid
     *
     * @return       string html with the results.
     * @throws       ReflectionException
     * @noinspection PhpUndefinedMethodInspection
     */
    public function results_page(object $to, object $from, bool $success, array $data, $logid): string {
        if ($success) {
            $resulttype = 'ok';
            $dbmessage = 'dbok';
            $notifytype = 'notifysuccess';
        } else {
            $transactions = (tool_mergeusers_transactionssupported()) ?
                    '_transactions' :
                    '_no_transactions';

            $resulttype = 'ko';
            $dbmessage = 'dbko' . $transactions;
            $notifytype = 'notifyproblem';
        }

        $output = $this->header();
        $output .= $this->heading(mergusergetstring('mergeusers', 'tool_mergeusers'));
        $output .= $this->build_progress_bar(self::INDEX_PAGE_RESULTS_STEP);
        $output .= html_writer::empty_tag('br');
        $output .= html_writer::start_tag('div', ['class' => 'result']);
        $output .= html_writer::start_tag('div', ['class' => 'title']);
        $output .= mergusergetstring('merging', 'tool_mergeusers');
        if (!is_null($to) && !is_null($from)) {
            $output .= ' ' . mergusergetstring('usermergingheader', 'tool_mergeusers', $from) . ' ' .
                    mergusergetstring('into', 'tool_mergeusers') . ' ' .
                    mergusergetstring('usermergingheader', 'tool_mergeusers', $to);
        }
        $output .= html_writer::empty_tag('br') . html_writer::empty_tag('br');
        $output .= mergusergetstring('logid', 'tool_mergeusers', $logid);
        $output .= html_writer::empty_tag('br');
        $output .= mergusergetstring('log' . $resulttype, 'tool_mergeusers');
        $output .= html_writer::end_tag('div');
        $output .= html_writer::empty_tag('br');

        $output .= html_writer::start_tag('div', ['class' => 'resultset' . $resulttype]);
        foreach ($data as $item) {
            $output .= $item . html_writer::empty_tag('br');
        }
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');
        $output .= html_writer::tag('div', html_writer::empty_tag('br'));
        $output .= $this->notification(html_writer::tag('center', mergusergetstring($dbmessage, 'tool_mergeusers')), $notifytype);
        $output .= html_writer::tag('center',
                $this->single_button(new moodle_url('/admin/tool/mergeusers/index.php'), mergusergetstring('continue'), 'get'));
        $output .= $this->footer();

        return $output;
    }

    /**
     * Helper method dealing with the fact we can not just fetch the output of moodle forms
     *
     * @param moodleform $mform
     *
     * @return string HTML
     */
    protected function moodleform(moodleform $mform): string {
        ob_start();
        $mform->display();
        $o = ob_get_contents();
        ob_end_clean();

        return $o;
    }

    /**
     * This method produces the HTML to show the details of a user.
     *
     * @param int $userid user.id
     * @param object $user an object with firstname and lastname attributes.
     *
     * @return string the corresponding HTML.
     * @throws moodle_exception
     */
    public function show_user(int $userid, object $user): string {
        return html_writer::link(
                new moodle_url('/user/view.php', ['id' => $userid, 'sesskey' => sesskey()]),
                fullname($user) . ' (' . $user->username . ') ' .
                ' &lt;' . $user->email . '&gt;' . ' ' . $user->idnumber
        );
    }

    /**
     * Produces the page with the list of logs.
     * TODO: make pagination.
     *
     * @param array $logs array of logs.
     *
     * @return string the corresponding HTML.
     * @throws moodle_exception
     * @noinspection PhpUndefinedMethodInspection
     * @global object $CFG
     */
    public function logs_page(array $logs): string {
        global $CFG;

        $output = $this->header();
        $output .= $this->heading(mergusergetstring('viewlog', 'tool_mergeusers'));
        $output .= html_writer::start_tag('div', ['class' => 'result']);
        if (empty($logs)) {
            $output .= mergusergetstring('nologs', 'tool_mergeusers');
        } else {
            $output .= html_writer::tag('div',
                    mergusergetstring('loglist', 'tool_mergeusers'), ['class' => 'title']);

            $flags = [];
            $flags[] = $this->pix_icon('i/invalid', mergusergetstring('eventusermergedfailure', 'tool_mergeusers'));
            // Failure icon.
            $flags[] = $this->pix_icon('i/valid', mergusergetstring('eventusermergedsuccess', 'tool_mergeusers'));
            // Ok icon.

            $table = new html_table();
            $table->align = ['center', 'center', 'center', 'center', 'center', 'center'];
            $table->head = [mergusergetstring('olduseridonlog', 'tool_mergeusers'),
                    mergusergetstring('newuseridonlog', 'tool_mergeusers'),
                    mergusergetstring('date'), mergusergetstring('status'), ''];

            $rows = [];
            foreach ($logs as $log) {
                $row = new html_table_row();
                $row->cells = [
                        ($log->from)
                                ? $this->show_user($log->fromuserid, $log->from)
                                : mergusergetstring('deleted', 'tool_mergeusers', $log->fromuserid),
                        ($log->to)
                                ? $this->show_user($log->touserid, $log->to)
                                : mergusergetstring('deleted', 'tool_mergeusers', $log->touserid),
                        userdate($log->timemodified, mergusergetstring('strftimedaydatetime', 'langconfig')),
                        $flags[$log->success],
                        html_writer::link(
                                new moodle_url(
                                        '/' . $CFG->admin . '/tool/mergeusers/log.php',
                                        ['id' => $log->id, 'sesskey' => sesskey()]
                                ),
                                mergusergetstring('more'),
                                ['target' => '_blank']
                        ),
                ];
                $rows[] = $row;
            }

            $table->data = $rows;
            $output .= html_writer::table($table);
        }

        $output .= html_writer::end_tag('div');
        $output .= $this->footer();

        return $output;
    }
}
