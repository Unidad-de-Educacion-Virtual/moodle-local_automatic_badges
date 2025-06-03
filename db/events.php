<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\grade_updated',
        'callback'  => 'local_automaticbadges\observer::grade_updated',
    ],
];
