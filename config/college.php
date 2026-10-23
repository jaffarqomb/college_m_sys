<?php

return [

    /*
    | Academic thresholds used by automated alerts and reports.
    */
    'attendance' => [
        // Attendance percentage below which students receive an alert mail.
        'alert_threshold' => env('ATTENDANCE_ALERT_THRESHOLD', 75.0),
    ],

    /*
    | Database snapshot backups. Written to the configured filesystem disk
    | ("backups" locally, switch to "s3" via BACKUP_DISK without code changes).
    */
    'backup' => [
        'disk' => env('BACKUP_DISK', 'backups'),
        // How many recent backups to retain on the disk.
        'keep' => (int) env('BACKUP_KEEP', 14),
    ],

];
