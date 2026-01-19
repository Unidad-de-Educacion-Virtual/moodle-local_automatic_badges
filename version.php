<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_automatic_badges';
$plugin->version = 2026011702; // YYYYMMDDXX - Added coursecfg and criteria tables, refactoring
$plugin->requires = 2022041900; // Moodle 4.0+
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = '0.3.0';

$plugin->settings  = 'settings.php';

