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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

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

// Pass course ID and all translatable JS strings to the AMD module.
$jscfg = [
    'courseid' => $courseid,
    'strings' => [
        'layershape'    => get_string('designer_layer_shape', 'local_automatic_badges'),
        'layericon'     => get_string('designer_layer_icon', 'local_automatic_badges'),
        'layertext'     => get_string('designer_layer_text', 'local_automatic_badges'),
        'layerimage'    => get_string('designer_layer_image', 'local_automatic_badges'),
        'layerribbon'   => get_string('designer_layer_ribbon', 'local_automatic_badges'),
        'layersunburst' => get_string('designer_layer_sunburst', 'local_automatic_badges'),
        'layerwings'    => get_string('designer_layer_wings', 'local_automatic_badges'),
        'layercrown'    => get_string('designer_layer_crown', 'local_automatic_badges'),
        'layerlaurels'  => get_string('designer_layer_laurels', 'local_automatic_badges'),
        'layerstars'    => get_string('designer_layer_stars', 'local_automatic_badges'),
        'layerdots'     => get_string('designer_layer_dots', 'local_automatic_badges'),
        'visibility'    => get_string('designer_layer_visibility', 'local_automatic_badges'),
        'deletelayer'   => get_string('designer_layer_delete', 'local_automatic_badges'),
        'imageonly'     => get_string('designer_image_only', 'local_automatic_badges'),
        'imageadded'    => get_string('designer_image_added', 'local_automatic_badges', '{filename}'),
        'nameRequired'  => get_string('designer_save_error_noname', 'local_automatic_badges'),
        'saving'        => get_string('designer_save_loading', 'local_automatic_badges'),
        'saveErrorConn' => get_string('designer_save_error_conn', 'local_automatic_badges'),
        'saveErrorImg'  => get_string('designer_save_error_image', 'local_automatic_badges'),
        'saveBtnLabel'  => get_string('designer_save_btn_label', 'local_automatic_badges'),
    ],
];
$PAGE->requires->js_call_amd('local_automatic_badges/badge_designer', 'init', [$jscfg]);

echo $OUTPUT->footer();
