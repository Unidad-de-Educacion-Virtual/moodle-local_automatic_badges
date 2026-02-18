<?php
// local/automatic_badges/course_criteria.php
require_once(__DIR__ . '/../../config.php');
$courseid = required_param('id', PARAM_INT);
redirect(new moodle_url('/local/automatic_badges/course_settings.php', ['id' => $courseid, 'tab' => 'rules']));
