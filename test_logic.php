<?php
// local/automatic_badges/test_logic.php
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/badgeslib.php');
require_once($CFG->dirroot . '/grade/querylib.php');

// Security check
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

// Helper to get grade
function get_test_grade($courseid, $userid, $cm) {
    global $DB;
    $grade_item = \grade_item::fetch([
        'courseid' => $courseid,
        'itemtype' => 'mod',
        'itemmodule' => $cm->modname,
        'iteminstance' => $cm->instance,
        'itemnumber' => 0
    ]);

    if (!$grade_item) return '-';
    
    $grade = $grade_item->get_final($userid);
    return ($grade && $grade->finalgrade !== null) ? format_float($grade->finalgrade, 2) : '-';
}

// ACTION: Retroactive Check for specific rule
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

// 1. Select User
echo '<form method="get" class="mb-3 form-inline">';
echo '<input type="hidden" name="id" value="'.$courseid.'">';
echo '<label class="mr-2">Select User to Test:</label>';
$users = get_enrolled_users($context);
echo '<select name="userid" onchange="this.form.submit()" class="custom-select mr-2">';
echo '<option value="0">-- Select User --</option>';
foreach ($users as $u) {
    $selected = ($u->id == $userid) ? 'selected' : '';
    echo "<option value='{$u->id}' {$selected}>" . fullname($u) . "</option>";
}
echo '</select>';
if ($userid) echo '<noscript><input type="submit" value="Select" class="btn btn-primary"></noscript>';
echo '</form>';

// 2. Load Rules
$rules = $DB->get_records('local_automatic_badges_rules', ['courseid' => $courseid]);

if ($userid) {
    echo $OUTPUT->heading('Evaluating Rules for User: ' . fullname($users[$userid]), 3);

    echo '<table class="table table-bordered table-striped">';
    echo '<thead><tr><th>Rule ID</th><th>Activity</th><th>Ref Badge</th><th>Criteria</th><th>Current Grade</th><th>Passes Rule?</th><th>Badge Issued?</th><th>Action</th></tr></thead>';
    echo '<tbody>';

    foreach ($rules as $rule) {
        $cm = get_coursemodule_from_id(null, $rule->activityid, $courseid, false, IGNORE_MISSING);
        $activityName = $cm ? $cm->name : "Unknown (ID: {$rule->activityid})";

        // Get Real Grade
        $gradeInfo = get_test_grade($courseid, $userid, $cm);
        if ($rule->criterion_type === 'grade') {
             $gradeInfo .= " (Min: {$rule->grade_min})";
        } elseif ($rule->criterion_type === 'forum') {
             $gradeInfo .= " (Posts)";
        }

        // Logic Check
        $meetsRule = \local_automatic_badges\rule_engine::check_rule($rule, $userid);
        $logicResult = $meetsRule ? '<span class="badge badge-success">YES</span>' : '<span class="badge badge-danger">NO</span>';

        // Badge Issued Check
        $badge = new badge($rule->badgeid);
        $isIssued = $badge->is_issued($userid);
        $issuedStatus = $isIssued ? '<span class="badge badge-success">ISSUED</span>' : '<span class="badge badge-warning">NOT ISSUED</span>';

        // Action Button
        $btn = '';
        if (!$isIssued && $meetsRule) {
            $forceUrl = new moodle_url('/local/automatic_badges/test_logic.php', [
                'id' => $courseid, 'userid' => $userid, 'ruleid' => $rule->id, 'action' => 'force_award', 'sesskey' => sesskey()
            ]);
            $btn = html_writer::link($forceUrl, 'Force Award', ['class' => 'btn btn-sm btn-primary']);
        }

        echo "<tr>";
        echo "<td>{$rule->id}</td>";
        echo "<td>{$activityName}</td>";
        echo "<td><a href='" . new moodle_url('/badges/overview.php', ['id' => $rule->badgeid]) . "' target='_blank'>{$badge->name}</a></td>";
        echo "<td>{$rule->criterion_type}</td>";
        echo "<td>{$gradeInfo}</td>";
        echo "<td>{$logicResult}</td>";
        echo "<td>{$issuedStatus}</td>";
        echo "<td>{$btn}</td>";
        echo "</tr>";
    }
    echo '</tbody></table>';

    // Handle Force Award
    if ($action === 'force_award' && $ruleid && confirm_sesskey()) {
        $rule = $DB->get_record('local_automatic_badges_rules', ['id' => $ruleid]);
        if ($rule && \local_automatic_badges\rule_engine::check_rule($rule, $userid)) {
            $badge = new badge($rule->badgeid);
            if (!$badge->is_issued($userid)) {
                $badge->issue($userid);
                echo $OUTPUT->notification("Badge manually awarded!", 'success');
                // Refresh
                redirect(new moodle_url('/local/automatic_badges/test_logic.php', ['id' => $courseid, 'userid' => $userid]));
            }
        }
    }
} else {
    // Show Retroactive Check options if no user selected
    echo $OUTPUT->heading('All Course Rules', 3);
    echo '<table class="table table-bordered table-striped">';
    echo '<thead><tr><th>Rule ID</th><th>Activity</th><th>Badge</th><th>Retroactive Check</th></tr></thead>';
    echo '<tbody>';
    foreach ($rules as $rule) {
        $cm = get_coursemodule_from_id(null, $rule->activityid, $courseid, false, IGNORE_MISSING);
        $activityName = $cm ? $cm->name : "Unknown";
        $badge = new badge($rule->badgeid);
        
        $retroUrl = new moodle_url('/local/automatic_badges/test_logic.php', [
            'id' => $courseid, 'ruleid' => $rule->id, 'action' => 'retroactive', 'sesskey' => sesskey()
        ]);
        
        echo "<tr>";
        echo "<td>{$rule->id}</td>";
        echo "<td>{$activityName}</td>";
        echo "<td>{$badge->name}</td>";
        echo "<td>" . html_writer::link($retroUrl, 'Check ALL Users & Award', ['class' => 'btn btn-sm btn-info']) . "</td>";
        echo "</tr>";
    }
    echo '</tbody></table>';
}

echo $OUTPUT->footer();
