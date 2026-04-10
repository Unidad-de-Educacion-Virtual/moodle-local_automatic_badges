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
 * External service to save a badge design from the badge designer.
 *
 * @package    local_automatic_badges
 * @author     Daniela Alexandra Patiño Dávila
 * @author     Cristian Julian Lamus Lamus
 * @copyright  2026 Daniela Alexandra Patiño Dávila, Cristian Julian Lamus Lamus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_automatic_badges\external;

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

/**
 * External function to save a badge design image and create the badge record.
 */
class save_badge_design extends external_api {
    /**
     * Defines the parameters accepted by this external function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'name' => new external_value(PARAM_TEXT, 'Badge name'),
            'imagedata' => new external_value(PARAM_RAW, 'Base64 encoded image data URL'),
            'description' => new external_value(PARAM_TEXT, 'Badge description', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Saves a badge design: decodes the image, creates the badge record and activates it.
     *
     * @param int $courseid Course ID.
     * @param string $name Badge name.
     * @param string $imagedata Base64 image data URL.
     * @param string $description Badge description.
     * @return array
     */
    public static function execute(int $courseid, string $name, string $imagedata, string $description = ''): array {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'    => $courseid,
            'name'        => $name,
            'imagedata'   => $imagedata,
            'description' => $description,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/badges:createbadge', $context);

        require_once($CFG->libdir . '/badgeslib.php');
        require_once($CFG->dirroot . '/badges/criteria/award_criteria.php');

        // Step 1: Decode the Base64 image.
        // Data URL format: "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...".
        $imagedata = $params['imagedata'];
        if (preg_match('/^data:image\/(\w+);base64,/', $imagedata, $type)) {
            $imagedata = substr($imagedata, strpos($imagedata, ',') + 1);
            $type = strtolower($type[1]);

            if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
                throw new \invalid_parameter_exception('Invalid image type');
            }
            $imagedata = base64_decode($imagedata);

            if ($imagedata === false) {
                throw new \invalid_parameter_exception('Base64 decode failed');
            }
        } else {
            throw new \invalid_parameter_exception('Did not match data URI with image data');
        }

        // Step 2: Create the badge record.
        $badge = new \stdClass();
        $badge->name = $params['name'];
        $badge->description = $params['description'] ?: $params['name'];
        $badge->timecreated = time();
        $badge->timemodified = time();
        $badge->usercreated = $USER->id;
        $badge->usermodified = $USER->id;
        $badge->issuername = fullname($USER);
        $badge->issuerurl = $CFG->wwwroot;
        $badge->issuercontact = $USER->email;
        $badge->expiredate = null;
        $badge->expireperiod = null;
        $badge->type = BADGE_TYPE_COURSE;
        $badge->courseid = $params['courseid'];
        $badge->version = '1.0';
        $language = current_language();
        $languages = get_string_manager()->get_list_of_languages();
        if (!isset($languages[$language])) {
            $language = get_parent_language($language) ?: 'en';
        }
        $badge->language = $language;
        $badge->imageauthorname = get_string('pluginname', 'local_automatic_badges');
        $badge->imageauthoremail = $USER->email;
        $urlparts = parse_url($CFG->wwwroot);
        $badge->imageauthorurl = $urlparts['scheme'] . '://' . $urlparts['host'];
        $badge->messagesubject = get_string('messagesubject', 'badges');
        $badge->message = get_string('messagebody', 'badges');
        $badge->imagefile = 'f1.png';
        $badge->attachment = 1;
        $badge->notification = 0;
        $badge->status = BADGE_STATUS_INACTIVE;
        $badge->nextcron = null;

        $badgeid = $DB->insert_record('badge', $badge);

        // Step 3: Save the image in Moodle file storage.
        $tempdir = make_temp_directory('badges');
        $tempfile = $tempdir . '/' . md5(time() . $USER->id) . '.png';
        file_put_contents($tempfile, $imagedata);

        $badgeobj = new \badge($badgeid);
        badges_process_badge_image($badgeobj, $tempfile);

        // Step 4: Add award criteria (OVERALL + MANUAL).
        $overall = new \stdClass();
        $overall->badgeid = $badgeid;
        $overall->criteriatype = BADGE_CRITERIA_TYPE_OVERALL;
        $overall->method = BADGE_CRITERIA_AGGREGATION_ANY;
        $overall->description = '';
        $overall->descriptionformat = FORMAT_HTML;
        $DB->insert_record('badge_criteria', $overall);

        $manual = new \stdClass();
        $manual->badgeid = $badgeid;
        $manual->criteriatype = BADGE_CRITERIA_TYPE_MANUAL;
        $manual->method = BADGE_CRITERIA_AGGREGATION_ANY;
        $manual->description = '';
        $manual->descriptionformat = FORMAT_HTML;
        $manualid = $DB->insert_record('badge_criteria', $manual);

        $roles = get_roles_with_capability('moodle/badges:awardbadge', CAP_ALLOW, $context);
        foreach ($roles as $role) {
            $param = new \stdClass();
            $param->critid = $manualid;
            $param->name = 'role_' . $role->id;
            $param->value = $role->id;
            $DB->insert_record('badge_criteria_param', $param);
        }

        // Step 5: Activate the badge.
        $badgeobj = new \badge($badgeid);
        $badgeobj->set_status(BADGE_STATUS_ACTIVE);

        return [
            'success' => true,
            'badgeid' => $badgeid,
            'message' => 'Badge created and activated successfully!',
        ];
    }

    /**
     * Defines the return structure of this external function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'badgeid' => new external_value(PARAM_INT, 'New badge ID', VALUE_OPTIONAL),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
        ]);
    }
}
