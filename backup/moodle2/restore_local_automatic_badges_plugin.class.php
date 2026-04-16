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
 * Restore plugin for local_automatic_badges.
 *
 * Reads the plugin XML written by backup_local_automatic_badges_plugin and
 * reinserts all plugin data into the target course, remapping all foreign-key
 * IDs (course, badge, user, course module, course section) as required.
 *
 * @package    local_automatic_badges
 * @author     Daniela Alexandra Patiño Dávila
 * @author     Cristian Julian Lamus Lamus
 * @copyright  2026 Daniela Alexandra Patiño Dávila, Cristian Julian Lamus Lamus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore plugin class for local_automatic_badges.
 *
 * All process_xxx() methods receive the parsed XML data for one record and are
 * responsible for remapping foreign-key IDs and inserting / updating the row.
 */
class restore_local_automatic_badges_plugin extends restore_local_plugin {
    /**
     * Returns the restore path elements for this plugin at the course level.
     *
     * @return restore_path_element[]
     */
    public function define_course_plugin_structure() {

        $paths = [];

        // Course configuration (single row per course).
        $paths[] = new restore_path_element(
            $this->get_namefor('coursecfg'),
            $this->get_pathfor('/automatic_badges_coursecfg')
        );

        // Award rules.
        $paths[] = new restore_path_element(
            $this->get_namefor('rule'),
            $this->get_pathfor('/automatic_badges_rules/automatic_badges_rule')
        );

        // Per-course key/value settings.
        $paths[] = new restore_path_element(
            $this->get_namefor('setting'),
            $this->get_pathfor('/automatic_badges_settings/automatic_badges_setting')
        );

        // Award history log (included only when user data was backed up).
        $paths[] = new restore_path_element(
            $this->get_namefor('log_entry'),
            $this->get_pathfor('/automatic_badges_log_entries/automatic_badges_log_entry')
        );

        // Legacy criteria.
        $paths[] = new restore_path_element(
            $this->get_namefor('criteria_entry'),
            $this->get_pathfor('/automatic_badges_criteria_entries/automatic_badges_criteria_entry')
        );

        return $paths;
    }

    /**
     * Restores the course enable/disable configuration record.
     *
     * Uses upsert semantics: if the target course already has a config row
     * (e.g. a merge restore), it is updated; otherwise a new row is inserted.
     *
     * @param array|object $data Parsed XML data for one coursecfg record.
     */
    public function process_local_automatic_badges_coursecfg($data) {
        global $DB;

        $data   = (object) $data;
        $oldid  = $data->id;
        $newcourseid = $this->get_task()->get_courseid();

        $data->courseid     = $newcourseid;
        $data->timemodified = time();

        $existing = $DB->get_record(
            'local_automatic_badges_coursecfg',
            ['courseid' => $newcourseid]
        );

        if ($existing) {
            $data->id = $existing->id;
            $DB->update_record('local_automatic_badges_coursecfg', $data);
            $newid = $existing->id;
        } else {
            unset($data->id);
            $data->timecreated = $data->timecreated ?? time();
            $newid = $DB->insert_record('local_automatic_badges_coursecfg', $data);
        }

        $this->set_mapping($this->get_namefor('coursecfg'), $oldid, $newid);
    }

    /**
     * Restores one award rule, remapping all foreign-key references.
     *
     * Skips the rule if the referenced badge cannot be mapped (badge not
     * present in the restore archive).
     *
     * @param array|object $data Parsed XML data for one rule record.
     */
    public function process_local_automatic_badges_rule($data) {
        global $DB;

        $data  = (object) $data;
        $oldid = $data->id;

        // Remap course.
        $data->courseid = $this->get_task()->get_courseid();

        // Remap badge.
        $newbadgeid = $this->get_mappingid('badge', $data->badgeid);
        if (!$newbadgeid) {
            // Badge not restored — skip this rule to avoid a broken reference.
            return;
        }
        $data->badgeid = $newbadgeid;

        // Remap activity (course module).
        if (!empty($data->activityid)) {
            $data->activityid = $this->get_mappingid(
                'course_module',
                $data->activityid,
                null
            );
        }

        // Remap bonus target activity.
        if (!empty($data->bonus_target_activityid)) {
            $data->bonus_target_activityid = $this->get_mappingid(
                'course_module',
                $data->bonus_target_activityid,
                null
            );
        }

        // Remap section.
        if (!empty($data->section_id)) {
            $data->section_id = $this->get_mappingid(
                'course_section',
                $data->section_id,
                null
            );
        }

        // Timestamps.
        $data->timecreated = $data->timecreated ?? time();
        $data->timemodified = time();

        unset($data->id);
        $newid = $DB->insert_record('local_automatic_badges_rules', $data);
        $this->set_mapping($this->get_namefor('rule'), $oldid, $newid);
    }

    /**
     * Restores one per-course key/value setting row.
     *
     * @param array|object $data Parsed XML data for one setting record.
     */
    public function process_local_automatic_badges_setting($data) {
        global $DB;

        $data  = (object) $data;
        $oldid = $data->id;

        $data->courseid = $this->get_task()->get_courseid();

        unset($data->id);
        $newid = $DB->insert_record('local_automatic_badges_settings', $data);
        $this->set_mapping($this->get_namefor('setting'), $oldid, $newid);
    }

    /**
     * Restores one award history log entry, remapping user, badge, and rule.
     *
     * Skips entries whose user or badge cannot be mapped (restore without
     * user data, or badge not in archive).
     *
     * @param array|object $data Parsed XML data for one log entry record.
     */
    public function process_local_automatic_badges_log_entry($data) {
        global $DB;

        $data  = (object) $data;
        $oldid = $data->id;

        // Remap course.
        $data->courseid = $this->get_task()->get_courseid();

        // Remap user.
        $newuserid = $this->get_mappingid('user', $data->userid);
        if (!$newuserid) {
            // User not in restore archive — skip this log entry.
            return;
        }
        $data->userid = $newuserid;

        // Remap badge.
        $newbadgeid = $this->get_mappingid('badge', $data->badgeid);
        if (!$newbadgeid) {
            return;
        }
        $data->badgeid = $newbadgeid;

        // Remap rule using the mapping stored during rule processing.
        if (!empty($data->ruleid)) {
            $data->ruleid = $this->get_mappingid(
                $this->get_namefor('rule'),
                $data->ruleid,
                $data->ruleid
            );
        }

        unset($data->id);
        $newid = $DB->insert_record('local_automatic_badges_log', $data);
        $this->set_mapping($this->get_namefor('log_entry'), $oldid, $newid);
    }

    /**
     * Restores one legacy criteria entry, remapping badge reference.
     *
     * Skips entries whose badge cannot be mapped.
     *
     * @param array|object $data Parsed XML data for one criteria entry record.
     */
    public function process_local_automatic_badges_criteria_entry($data) {
        global $DB;

        $data  = (object) $data;
        $oldid = $data->id;

        // Remap course.
        $data->courseid = $this->get_task()->get_courseid();

        // Remap badge.
        $newbadgeid = $this->get_mappingid('badge', $data->badgeid);
        if (!$newbadgeid) {
            return;
        }
        $data->badgeid = $newbadgeid;

        // Timestamps.
        $data->timecreated = $data->timecreated ?? time();
        $data->timemodified = $data->timemodified ?? time();

        unset($data->id);
        $newid = $DB->insert_record('local_automatic_badges_criteria', $data);
        $this->set_mapping($this->get_namefor('criteria_entry'), $oldid, $newid);
    }
}
