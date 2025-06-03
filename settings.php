<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_automaticbadges',
        get_string('pluginname', 'local_automaticbadges'));

    // Checkbox general de activación
    $settings->add(new admin_setting_configcheckbox(
        'local_automaticbadges/enabled',
        get_string('enableall', 'local_automaticbadges'),
        get_string('enablealldesc', 'local_automaticbadges'),
        0
    ));

    $ADMIN->add('localplugins', $settings);
}
