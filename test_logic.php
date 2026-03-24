<?php
// This file is part of local_automatic_badges - https://moodle.org/.
//
// local_automatic_badges is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// local_automatic_badges is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with local_automatic_badges.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Diagnostic tool for automatic badges rule logic.
 *
 * @package    local_automatic_badges
 * @author     Daniela Alexandra Patiño Dávila
 * @author     Cristian Julian Lamus Lamus
 * @copyright  2026 Daniela Alexandra Patiño Dávila, Cristian Julian Lamus Lamus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/badgeslib.php');
require_once($CFG->dirroot . '/grade/querylib.php');

// Security check.
$courseid = optional_param('id', 2, PARAM_INT);
require_login($courseid);
$context = context_course::instance($courseid);
require_capability('moodle/site:config', context_system::instance());

$ruleid   = optional_param('ruleid', 0, PARAM_INT);
$userid   = optional_param('userid', 0, PARAM_INT);
$action   = optional_param('action', '', PARAM_ALPHA);

$PAGE->set_url('/local/automatic_badges/test_logic.php', ['id' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Test Awarding Logic');
$PAGE->set_heading('Test Awarding Logic');

echo $OUTPUT->header();
echo $OUTPUT->heading('Diagnostic Tool: Automatic Badges Rule Logic');

/**
 * Helper function to get the grade for a user in a course module.
 *
 * @param int $courseid The course ID.
 * @param int $userid The user ID.
 * @param object $cm The course module object.
 * @return string The formatted grade or '-' if not available.
 */
function get_test_grade($courseid, $userid, $cm) {
    global $DB;
    $gradeitem = \grade_item::fetch([
        'courseid' => $courseid, 'itemtype' => 'mod',
        'itemmodule' => $cm->modname, 'iteminstance' => $cm->instance,
        'itemnumber' => 0,
    ]);

    if (!$gradeitem) {
        return '-';
    }

    $grade = $gradeitem->get_final($userid);
    return ($grade && $grade->finalgrade !== null) ? format_float($grade->finalgrade, 2) : '-';
}

// Action: retroactive check for specific rule.
if ($action === 'retroactive' && $ruleid && confirm_sesskey()) {
    $rule = $DB->get_record('local_automatic_badges_rules', ['id' => $ruleid]);
    if ($rule) {
        echo $OUTPUT->heading("Retroactive Check for Rule #{$ruleid}", 3);
        $users = get_enrolled_users($context);
        $awarded = 0;
        foreach ($users as $u) {
            if (\local_automatic_badges\rule_engine::check_rule($rule, $u->id)) {
                $badge = new badge($rule->badgeid);
                if (!$badge->is_issued($u->id)) {
                    $badge->issue($u->id);
                    echo "<div>Awarded to: " . fullname($u) . "</div>";
                    $awarded++;
                }
            }
        }
        echo $OUTPUT->notification("Retroactive check complete. Awarded {$awarded} badges.", 'success');
    }
}

// Select user form.
echo '<form method="get" class="mb-3 form-inline">';
echo '<input type="hidden" name="id" value="' . $courseid . '">';
echo '<label class="mr-2">Select User to Test:</label>';
$users = get_enrolled_users($context);
echo '<select name="userid" onchange="this.form.submit()" class="custom-select mr-2">';
echo '<option value="0">-- Select User --</option>';
foreach ($users as $u) {
    $selected = ($u->id == $userid) ? 'selected' : '';
    echo "<option value='{$u->id}' {$selected}>" . fullname($u) . "</option>";
}
echo '</select>';
if ($userid) {
    echo '<noscript><input type="submit" value="Select" class="btn btn-primary"></noscript>';
}
echo '</form>';

// Load rules.
$rules = $DB->get_records('local_automatic_badges_rules', ['courseid' => $courseid]);

if ($userid) {
    echo $OUTPUT->heading('Evaluating Rules for User: ' . fullname($users[$userid]), 3);

    echo '<table class="table table-bordered table-striped">';
    echo '<thead><tr><th>Rule ID</th><th>Activity</th><th>Ref Badge</th>';
    echo '<th>Criteria</th><th>Current Grade</th><th>Passes Rule?</th>';
    echo '<th>Badge Issued?</th><th>Action</th></tr></thead>';
    echo '<tbody>';

    foreach ($rules as $rule) {
        $cm = get_coursemodule_from_id(null, $rule->activityid, $courseid, false, IGNORE_MISSING);
        $activityname = $cm ? $cm->name : "Unknown (ID: {$rule->activityid})";

        // Get real grade.
        $gradeinfo = get_test_grade($courseid, $userid, $cm);
        if ($rule->criterion_type === 'grade') {
             $gradeinfo .= " (Min: {$rule->grade_min})";
        } else if ($rule->criterion_type === 'forum') {
             $gradeinfo .= " (Posts)";
        }

        // Logic check.
        $meetsrule = \local_automatic_badges\rule_engine::check_rule($rule, $userid);
        $logicresult = $meetsrule
            ? '<span class="badge badge-success">YES</span>'
            : '<span class="badge badge-danger">NO</span>';

        // Badge issued check.
        $badge = new badge($rule->badgeid);
        $isissued = $badge->is_issued($userid);
        $issuedstatus = $isissued
            ? '<span class="badge badge-success">ISSUED</span>'
            : '<span class="badge badge-warning">NOT ISSUED</span>';

        // Action button.
        $btn = '';
        if (!$isissued && $meetsrule) {
            $forceurl = new moodle_url('/local/automatic_badges/test_logic.php', [
                'id' => $courseid, 'userid' => $userid,
                'ruleid' => $rule->id, 'action' => 'force_award',
                'sesskey' => sesskey(),
            ]);
            $btn = html_writer::link($forceurl, 'Force Award', ['class' => 'btn btn-sm btn-primary']);
        }

        echo "<tr>";
        echo "<td>{$rule->id}</td>";
        echo "<td>{$activityname}</td>";
        $badgeurl = new moodle_url('/badges/overview.php', ['id' => $rule->badgeid]);
        echo "<td><a href='{$badgeurl}' target='_blank'>{$badge->name}</a></td>";
        echo "<td>{$rule->criterion_type}</td>";
        echo "<td>{$gradeinfo}</td>";
        echo "<td>{$logicresult}</td>";
        echo "<td>{$issuedstatus}</td>";
        echo "<td>{$btn}</td>";
        echo "</tr>";
    }
    echo '</tbody></table>';

    // Handle force award.
    if ($action === 'force_award' && $ruleid && confirm_sesskey()) {
        $rule = $DB->get_record('local_automatic_badges_rules', ['id' => $ruleid]);
        if ($rule && \local_automatic_badges\rule_engine::check_rule($rule, $userid)) {
            $badge = new badge($rule->badgeid);
            if (!$badge->is_issued($userid)) {
                $badge->issue($userid);
                echo $OUTPUT->notification("Badge manually awarded!", 'success');
                // Refresh page.
                redirect(new moodle_url(
                    '/local/automatic_badges/test_logic.php',
                    ['id' => $courseid, 'userid' => $userid]
                ));
            }
        }
    }
} else {
    // Show retroactive check options if no user selected.
    echo $OUTPUT->heading('All Course Rules', 3);
    echo '<table class="table table-bordered table-striped">';
    echo '<thead><tr><th>Rule ID</th><th>Activity</th>';
    echo '<th>Badge</th><th>Retroactive Check</th></tr></thead>';
    echo '<tbody>';
    foreach ($rules as $rule) {
        $cm = get_coursemodule_from_id(null, $rule->activityid, $courseid, false, IGNORE_MISSING);
        $activityname = $cm ? $cm->name : "Unknown";
        $badge = new badge($rule->badgeid);

        $retrourl = new moodle_url('/local/automatic_badges/test_logic.php', [
            'id' => $courseid, 'ruleid' => $rule->id,
            'action' => 'retroactive', 'sesskey' => sesskey(),
        ]);

        echo "<tr>";
        echo "<td>{$rule->id}</td>";
        echo "<td>{$activityname}</td>";
        echo "<td>{$badge->name}</td>";
        echo "<td>" . html_writer::link(
            $retrourl,
            'Check ALL Users & Award',
            ['class' => 'btn btn-sm btn-info']
        ) . "</td>";
        echo "</tr>";
    }
    echo '</tbody></table>';
}

echo $OUTPUT->footer();
