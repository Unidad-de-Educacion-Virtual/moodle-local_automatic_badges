<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_automaticbadges\task\award_badges_task',
        'blocking'  => 0,
        'minute'    => 'R',
        'hour'      => 'R',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*'
    ],
];
