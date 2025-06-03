<?php
namespace local_automaticbadges;
defined('MOODLE_INTERNAL') || die();

class observer {
    public static function grade_updated(\core\event\grade_updated $event) {
        global $CFG, $DB;

        $data = $event->get_data();
        $courseid = $data['courseid'];

        if (!helper::is_enabled_course($courseid)) {
            return;
        }

        $students = helper::get_students_in_course($courseid);

        debugging('Students count: ' . count($students), DEBUG_DEVELOPER);

        // foreach ($students as $student) {
        //     require_once($CFG->dirroot . '/badges/lib.php');
        //     $badgeid = 1;
        //     $badge = new \badge($badgeid);
        //     $badge->issue($student->id);
        //     debugging('Awarded badge to ' . $student->id, DEBUG_DEVELOPER);
        // }
    }
}
