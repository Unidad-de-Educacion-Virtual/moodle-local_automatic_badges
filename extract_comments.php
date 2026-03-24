<?php
$files = glob("C:/xampp/htdocs/moodle/local/automatic_badges/classes/*.php");
foreach ($files as $file) {
    if (basename($file) === 'bonus_manager.php') continue;
    $content = file_get_contents($file);
    preg_match_all('/\/\*\*.*?\*\//s', $content, $matches);
    echo "--- " . basename($file) . " ---\n";
    foreach ($matches[0] as $match) {
        if (strpos($match, '*/') !== false) {
             $lines = explode("\n", $match);
             $summary = isset($lines[1]) ? trim($lines[1]) : '';
             if (!empty($summary) && strpos($summary, '@') === false) {
                 echo $summary . "\n";
             }
        }
    }
}
