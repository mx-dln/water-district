<?php

/**
 * CSC daily attendance computation for a fixed two-session government schedule.
 * All quantities are integer minutes; no automatic rounding is performed.
 */

function cscTimeToMinutes(?string $value): ?int
{
    if (!$value) return null;
    $timestamp = strtotime($value);
    if ($timestamp === false) return null;
    return ((int) date('H', $timestamp) * 60) + (int) date('i', $timestamp);
}

function cscSettingMinutes(string $key, string $default): int
{
    $value = (string) getSetting($key, $default);
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', $value, $matches)) {
        $value = $default;
        preg_match('/^(\d{1,2}):(\d{2})$/', $value, $matches);
    }
    return ((int) $matches[1] * 60) + (int) $matches[2];
}

function cscFormatMinutes(int $minutes): string
{
    $minutes = max(0, $minutes);
    return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
}

function cscMinutesToHours(int $minutes): string
{
    return number_format(max(0, $minutes) / 60, 2);
}

function cscComputeDaily(array $record, array $schedule = []): array
{
    $amStart = $schedule['am_start'] ?? cscSettingMinutes('csc_am_start', '08:00');
    $amEnd = $schedule['am_end'] ?? cscSettingMinutes('csc_am_end', '12:00');
    $pmStart = $schedule['pm_start'] ?? cscSettingMinutes('csc_pm_start', '13:00');
    $pmEnd = $schedule['pm_end'] ?? cscSettingMinutes('csc_pm_end', '17:00');
    $grace = max(0, (int) ($schedule['grace_minutes'] ?? getSetting('csc_grace_minutes', '0')));

    $required = max(0, $amEnd - $amStart) + max(0, $pmEnd - $pmStart);
    $timeIn = cscTimeToMinutes($record['time_in'] ?? null);
    $breakOut = cscTimeToMinutes($record['break_out'] ?? null);
    $breakIn = cscTimeToMinutes($record['break_in'] ?? null);
    $timeOut = cscTimeToMinutes($record['time_out'] ?? null);

    if ($timeIn === null && $timeOut === null && $breakOut === null && $breakIn === null) {
        return [
            'required_minutes' => $required, 'worked_minutes' => 0,
            'tardiness_minutes' => 0, 'undertime_minutes' => 0,
            'deficiency_minutes' => $required, 'overtime_minutes' => 0,
            'status' => 'Absent', 'is_complete' => true,
        ];
    }

    $amWorked = 0;
    $pmWorked = 0;
    if ($timeIn !== null) {
        $amExit = $breakOut ?? (($timeOut !== null && $timeOut <= $pmStart) ? $timeOut : null);
        if ($timeIn < $amEnd && $amExit !== null) {
            $amWorked = max(0, min($amExit, $amEnd) - max($timeIn, $amStart));
        }
    }
    if ($timeOut !== null) {
        $pmEntry = $breakIn ?? (($timeIn !== null && $timeIn >= $amEnd) ? $timeIn : null);
        if ($pmEntry !== null && $timeOut > $pmStart) {
            $pmWorked = max(0, min($timeOut, $pmEnd) - max($pmEntry, $pmStart));
        }
    }

    $worked = $amWorked + $pmWorked;
    $amDuration = max(0, $amEnd - $amStart);
    $pmDuration = max(0, $pmEnd - $pmStart);
    $pmArrival = $breakIn ?? (($timeIn !== null && $timeIn >= $amEnd) ? $timeIn : null);
    $morningLate = $timeIn === null ? $amDuration : min($amDuration, max(0, $timeIn - ($amStart + $grace)));
    $afternoonLate = $pmArrival === null ? $pmDuration : min($pmDuration, max(0, $pmArrival - ($pmStart + $grace)));
    $morningUndertime = $breakOut === null ? 0 : max(0, $amEnd - $breakOut);
    $afternoonUndertime = $timeOut === null ? $pmDuration : min($pmDuration, max(0, $pmEnd - $timeOut));
    $tardiness = min($required, $morningLate + $afternoonLate);
    $undertime = min($required, $morningUndertime + $afternoonUndertime);
    $deficiency = max(0, $required - $worked);
    $isComplete = $timeIn !== null && $breakOut !== null && $breakIn !== null && $timeOut !== null;

    $status = 'Present';
    if (!$isComplete) $status = 'Incomplete punches';
    elseif ($worked === 0) $status = 'Absent';
    elseif ($timeIn >= $pmStart) $status = 'Half-day / tardy';
    elseif ($timeOut <= $pmStart) $status = 'Half-day / undertime';
    elseif ($tardiness > 0 && $undertime > 0) $status = 'Tardy and undertime';
    elseif ($tardiness > 0) $status = 'Tardy';
    elseif ($undertime > 0) $status = 'Undertime';

    return [
        'required_minutes' => $required,
        'worked_minutes' => $worked,
        'tardiness_minutes' => $tardiness,
        'undertime_minutes' => $undertime,
        'deficiency_minutes' => $deficiency,
        'overtime_minutes' => max(0, $worked - $required),
        'status' => $status,
        'is_complete' => $isComplete,
    ];
}

function cscAttachComputations(array $records): array
{
    foreach ($records as &$record) {
        $record['csc'] = cscComputeDaily($record);
    }
    unset($record);
    return $records;
}

function cscAttendanceComputedColumnsAvailable(): bool
{
    static $available = null;
    if ($available !== null) return $available;

    global $db;
    try {
        $stmt = $db->query("SHOW COLUMNS FROM attendance LIKE 'tardiness_minutes'");
        $available = (bool) $stmt->fetch();
    } catch (Throwable $e) {
        $available = false;
    }
    return $available;
}

function cscPersistAttendanceComputation(int $attendanceId): void
{
    if (!cscAttendanceComputedColumnsAvailable()) return;

    global $db;
    $stmt = $db->prepare("SELECT * FROM attendance WHERE id = ?");
    $stmt->execute([$attendanceId]);
    $record = $stmt->fetch();
    if (!$record) return;

    $computed = cscComputeDaily($record);
    $remarks = trim((string) ($record['remarks'] ?? ''));
    $autoRemark = sprintf(
        'CSC computation: worked %s, tardiness %s, undertime %s, deficiency %s, status %s.',
        cscFormatMinutes($computed['worked_minutes']),
        cscFormatMinutes($computed['tardiness_minutes']),
        cscFormatMinutes($computed['undertime_minutes']),
        cscFormatMinutes($computed['deficiency_minutes']),
        $computed['status']
    );

    $remarks = preg_replace('/\n?CSC computation:.*$/s', '', $remarks);
    $remarks = trim($remarks);
    $remarks = $remarks ? $remarks . "\n" . $autoRemark : $autoRemark;

    $update = $db->prepare("
        UPDATE attendance
        SET worked_minutes = ?,
            tardiness_minutes = ?,
            undertime_minutes = ?,
            deficiency_minutes = ?,
            overtime_minutes = ?,
            csc_status = ?,
            remarks = ?,
            computed_at = NOW()
        WHERE id = ?
    ");
    $update->execute([
        $computed['worked_minutes'],
        $computed['tardiness_minutes'],
        $computed['undertime_minutes'],
        $computed['deficiency_minutes'],
        $computed['overtime_minutes'],
        $computed['status'],
        $remarks,
        $attendanceId,
    ]);
}
