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
 * Badge designer page.
 *
 * Renders the interactive badge editor using a Mustache template and AMD module.
 *
 * @package    local_automatic_badges
 * @author     Daniela Alexandra Patiño Dávila
 * @author     Cristian Julian Lamus Lamus
 * @copyright  2026 Daniela Alexandra Patiño Dávila, Cristian Julian Lamus Lamus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

$courseid = required_param('id', PARAM_INT);
require_login($courseid);

$context = context_course::instance($courseid);
require_capability('moodle/badges:createbadge', $context);

$PAGE->set_url(new moodle_url('/local/automatic_badges/pages/badge_designer.php', ['id' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('badgedesigner', 'local_automatic_badges'));
$PAGE->set_heading(format_string($COURSE->fullname));
$PAGE->set_pagelayout('course');

// Load Font Awesome stylesheet.
$PAGE->requires->css(new moodle_url('/local/automatic_badges/css/fontawesome.min.css'));

// Load Fabric.js and Sortable.js into the page <head> so they are available as
// window.fabric and window.Sortable before RequireJS initialises.
$PAGE->requires->js(new moodle_url('/local/automatic_badges/js/fabric.min.js'), true);
$PAGE->requires->js(new moodle_url('/local/automatic_badges/js/Sortable.min.js'), true);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('badgedesigner', 'local_automatic_badges'));

$cancelurl = new moodle_url('/local/automatic_badges/pages/course_settings.php', [
    'id' => $courseid,
    'tab' => 'badges',
]);

$templatecontext = [
    'cancelurl' => $cancelurl->out(false),
];

echo $OUTPUT->render_from_template('local_automatic_badges/badge_designer', $templatecontext);

$PAGE->requires->js_call_amd('local_automatic_badges/badge_designer', 'init', [$courseid]);

echo $OUTPUT->footer();
