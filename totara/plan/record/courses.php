<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @author Aaron Wells <aaronw@catalyst.net.nz>
 * @package totara
 * @subpackage totara_plan
 */

/**
 * Displays collaborative features for the current user
 *
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot.'/totara/reportbuilder/lib.php');
require_once($CFG->dirroot.'/totara/plan/lib.php');

require_login();

global $USER;

$courseid = optional_param('courseid', null, PARAM_INT);
$history = optional_param('history', false, PARAM_BOOL);
$userid = optional_param('userid', $USER->id, PARAM_INT); // Which user to show.
$sid = optional_param('sid', '0', PARAM_INT);
$format = optional_param('format','', PARAM_TEXT); // Export format.
$rolstatus = optional_param('status', 'all', PARAM_ALPHANUM);
if (!in_array($rolstatus, array('active','completed','all'))) {
    $rolstatus = 'all';
}

$pageparams = array(
    'courseid' => $courseid,
    'history' => $history,
    'userid' => $userid,
    'format' => $format,
    'status' => $rolstatus
);

if (!$user = $DB->get_record('user', array('id' => $userid))) {
    print_error('error:usernotfound', 'totara_plan');
}

if (!empty($courseid) && (!$course = $DB->get_record('course', array('id' => $courseid), 'fullname'))) {
    print_error(get_string('coursenotfound', 'totara_plan'));
}

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/totara/plan/record/courses.php', $pageparams));
$PAGE->set_pagelayout('noblocks');

$renderer =  $PAGE->get_renderer('totara_reportbuilder');

if ($USER->id != $userid) {
    $strheading = get_string('recordoflearningfor', 'totara_core').fullname($user, true);
} else {
    $strheading = get_string('recordoflearning', 'totara_core');
}
// Get subheading name for display.
$strsubheading = get_string($rolstatus.'coursessubhead', 'totara_plan');

$shortname = 'plan_courses';
$data = array(
    'userid' => $userid,
);
if ($rolstatus !== 'all') {
    $data['rolstatus'] = $rolstatus;
}
if ($history) {
    $shortname = 'plan_courses_completion_history';
    $data['courseid'] = $courseid;
    if (!empty($courseid)) {
        $strsubheading = get_string('coursescompletionhistoryforsubhead', 'totara_plan', $course->fullname);
    } else {
        $strsubheading = get_string('coursescompletionhistorysubhead', 'totara_plan');
    }
}

if (!$report = reportbuilder_get_embedded_report($shortname, $data, false, $sid)) {
    print_error('error:couldnotgenerateembeddedreport', 'totara_reportbuilder');
}

$logurl = $PAGE->url->out_as_local_url();
if ($format != '') {
    add_to_log(SITEID, 'rbembedded', 'record export', $logurl, $report->fullname);
    $report->export_data($format);
    die;
}

add_to_log(SITEID, 'rbembedded', 'record view', $logurl, $report->fullname);

$report->include_js();

///
/// Display the page
///

$PAGE->navbar->add(get_string('mylearning', 'totara_core'), new moodle_url('/my/'));
$PAGE->navbar->add($strheading, new moodle_url('/totara/plan/record/index.php'));
$PAGE->navbar->add($strsubheading);
$PAGE->set_title($strheading);
$PAGE->set_button($report->edit_button());
$PAGE->set_heading($strheading);

$ownplan = $USER->id == $userid;

$usertype = ($ownplan) ? 'learner' : 'manager';
$menuitem = ($ownplan) ? 'recordoflearning' : 'myteam';
$PAGE->set_totara_menu_selected($menuitem);

echo $OUTPUT->header();

echo dp_display_plans_menu($userid, 0, $usertype, 'courses', $rolstatus);

echo $OUTPUT->container_start('', 'dp-plan-content');

echo $OUTPUT->heading($strheading.' : '.$strsubheading, 1);

$currenttab = 'courses';

dp_print_rol_tabs($rolstatus, $currenttab, $userid);

$countfiltered = $report->get_filtered_count();
$countall = $report->get_full_count();

$heading = $renderer->print_result_count_string($countfiltered, $countall);
echo $OUTPUT->heading($heading);

echo $renderer->print_description($report->description, $report->_id);

$report->display_search();
$report->display_sidebar_search();

// Print saved search buttons if appropriate.
echo $report->display_saved_search_options();

echo $renderer->showhide_button($report->_id, $report->shortname);

$report->display_table();

// Export button.
$renderer->export_select($report->_id, $sid);

echo $OUTPUT->container_end();

echo $OUTPUT->footer();
