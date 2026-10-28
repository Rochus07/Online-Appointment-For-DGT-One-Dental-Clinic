<?php
/**
 * Role-filtered navigation. Expects $me (current user) and optional $ACTIVE key.
 */
declare(strict_types=1);

$ACTIVE = $ACTIVE ?? '';
$role   = $me['role'];
$M      = '/dgt_dental_clinic/modules/';

/** [key, label, href] grouped by role. The marker icon is keyed off $key below. */
$navByRole = [
    'admin' => [
        ['dashboard',    'Dashboard',        $M . 'admin/dashboard'],
        ['appointments', 'Appointments',     $M . 'admin/appointments'],
        ['services',     'Services',          $M . 'admin/services'],
        ['users',        'User Accounts',     $M . 'admin/users'],
        ['reports',      'Revenue Reports',   $M . 'admin/reports'],
        ['voids',        'Voided Receipts',   $M . 'admin/voids', 'billing'],
        ['logs',         'Audit Logs',        $M . 'admin/logs'],
        ['settings',     'Settings',          $M . 'admin/settings'],
    ],
    'dentist' => [
        ['dashboard', 'My Schedule',        $M . 'dentist/myschedule', 'schedule'],
        ['records',   'Patient Records',     $M . 'dentist/records'],
        ['calendar',  'Calendar',            $M . 'dentist/calendar'],
    ],
    'receptionist' => [
        ['dashboard', 'Appointments',    $M . 'receptionist/appointments', 'appointments'],
        ['records',   'Patient Records', $M . 'receptionist/records'],
        ['calendar',  'Calendar',        $M . 'receptionist/calendar'],
    ],
    'dental_assistant' => [
        ['dashboard', 'Today\'s Patients', $M . 'dental_assistant/dashboard', 'schedule'],
        ['records',   'Patient Records',   $M . 'dental_assistant/records'],
    ],
    'cashier' => [
        ['transactions', 'Transaction',     $M . 'cashier/transactions'],
        ['or_setup',     'OR / Invoice Setup', $M . 'cashier/or_setup', 'billing'],
        ['reports',      'Revenue Reports',  $M . 'admin/reports'],
        ['calendar',     'Calendar',         $M . 'cashier/calendar'],
    ],
    'patient' => [
        ['appointments', 'Appointments',  $M . 'patient/appointments'],
        ['calendar',     'Calendar',      $M . 'patient/calendar'],
        ['billing',      'Treatment & Transactions', $M . 'patient/dashboard'],
        ['records',      'Dental Records', $M . 'patient/dental_chart'],
        ['services',     'Services',      $M . 'patient/services'],
    ],
];

// In-House dentists set their own hours (2026-10-19); roaming ones have none.
if ($role === 'dentist' && ($me['doctor_type'] ?? '') === 'owner') {
    array_splice($navByRole['dentist'], 1, 0, [['availability', 'My Availability', $M . 'dentist/availability', 'schedules']]);
}
$items = $navByRole[$role] ?? [];

/**
 * Sidebar sections (2026-10-19): a small heading above each group of links so
 * it is clear which part of the system a page belongs to. Links are grouped in
 * the order of the section list; within a section they keep their order above.
 */
$navSections = [
    'admin' => [
        'Overview'      => ['dashboard'],
        'Clinic'        => ['appointments', 'services'],
        'Management'    => ['users'],
        'Reports'       => ['reports', 'voids', 'logs'],
        'System'        => ['settings'],
    ],
    'dentist' => [
        'Today'         => ['dashboard'],
        'Working Hours' => ['availability'],
        'Schedule'      => ['calendar'],
        'Patients'      => ['records'],
    ],
    'receptionist' => [
        'Front Desk'    => ['dashboard', 'calendar'],
        'Patients'      => ['records'],
    ],
    'dental_assistant' => [
        'Today'         => ['dashboard'],
        'Patients'      => ['records'],
    ],
    'cashier' => [
        'Billing'       => ['transactions', 'or_setup', 'reports'],
        'Schedule'      => ['calendar'],
    ],
    'patient' => [
        'Booking'       => ['appointments', 'calendar'],
        'My Records'    => ['billing', 'records'],
        'Clinic'        => ['services'],
    ],
];
$navGroups = [];
$placed = [];
foreach ($navSections[$role] ?? [] as $secLabel => $keys) {
    $group = [];
    foreach ($keys as $k) {
        foreach ($items as $it) {
            if ($it[0] === $k && !isset($placed[$k])) { $group[] = $it; $placed[$k] = true; }
        }
    }
    if ($group) $navGroups[] = [$secLabel, $group];
}
$rest = array_values(array_filter($items, fn($it) => !isset($placed[$it[0]])));
if ($rest) $navGroups[] = [$navGroups ? 'More' : '', $rest];

/**
 * Sidebar nav glyphs — Phosphor Icons (MIT), inlined as [outline, fill] SVG
 * path pairs. The outline shows at rest; the active row swaps to the fill
 * (see the <style> block below). viewBox is Phosphor's native 256.
 */
$NAV_ICON = [
    'dashboard'    => ['M104,40H56A16,16,0,0,0,40,56v48a16,16,0,0,0,16,16h48a16,16,0,0,0,16-16V56A16,16,0,0,0,104,40Zm0,64H56V56h48v48Zm96-64H152a16,16,0,0,0-16,16v48a16,16,0,0,0,16,16h48a16,16,0,0,0,16-16V56A16,16,0,0,0,200,40Zm0,64H152V56h48v48Zm-96,32H56a16,16,0,0,0-16,16v48a16,16,0,0,0,16,16h48a16,16,0,0,0,16-16V152A16,16,0,0,0,104,136Zm0,64H56V152h48v48Zm96-64H152a16,16,0,0,0-16,16v48a16,16,0,0,0,16,16h48a16,16,0,0,0,16-16V152A16,16,0,0,0,200,136Zm0,64H152V152h48v48Z', 'M120,56v48a16,16,0,0,1-16,16H56a16,16,0,0,1-16-16V56A16,16,0,0,1,56,40h48A16,16,0,0,1,120,56Zm80-16H152a16,16,0,0,0-16,16v48a16,16,0,0,0,16,16h48a16,16,0,0,0,16-16V56A16,16,0,0,0,200,40Zm-96,96H56a16,16,0,0,0-16,16v48a16,16,0,0,0,16,16h48a16,16,0,0,0,16-16V152A16,16,0,0,0,104,136Zm96,0H152a16,16,0,0,0-16,16v48a16,16,0,0,0,16,16h48a16,16,0,0,0,16-16V152A16,16,0,0,0,200,136Z'],   // squares-four (dashboard grid)
    'schedule'     => ['M208,32H184V24a8,8,0,0,0-16,0v8H88V24a8,8,0,0,0-16,0v8H48A16,16,0,0,0,32,48V208a16,16,0,0,0,16,16H208a16,16,0,0,0,16-16V48A16,16,0,0,0,208,32ZM72,48v8a8,8,0,0,0,16,0V48h80v8a8,8,0,0,0,16,0V48h24V80H48V48ZM208,208H48V96H208V208Zm-38.34-85.66a8,8,0,0,1,0,11.32l-48,48a8,8,0,0,1-11.32,0l-24-24a8,8,0,0,1,11.32-11.32L116,164.69l42.34-42.35A8,8,0,0,1,169.66,122.34Z', 'M208,32H184V24a8,8,0,0,0-16,0v8H88V24a8,8,0,0,0-16,0v8H48A16,16,0,0,0,32,48V208a16,16,0,0,0,16,16H208a16,16,0,0,0,16-16V48A16,16,0,0,0,208,32ZM169.66,133.66l-48,48a8,8,0,0,1-11.32,0l-24-24a8,8,0,0,1,11.32-11.32L116,164.69l42.34-42.35a8,8,0,0,1,11.32,11.32ZM48,80V48H72v8a8,8,0,0,0,16,0V48h80v8a8,8,0,0,0,16,0V48h24V80Z'],
    'appointments' => ['M224,128a8,8,0,0,1-8,8H128a8,8,0,0,1,0-16h88A8,8,0,0,1,224,128ZM128,72h88a8,8,0,0,0,0-16H128a8,8,0,0,0,0,16Zm88,112H128a8,8,0,0,0,0,16h88a8,8,0,0,0,0-16ZM82.34,42.34,56,68.69,45.66,58.34A8,8,0,0,0,34.34,69.66l16,16a8,8,0,0,0,11.32,0l32-32A8,8,0,0,0,82.34,42.34Zm0,64L56,132.69,45.66,122.34a8,8,0,0,0-11.32,11.32l16,16a8,8,0,0,0,11.32,0l32-32a8,8,0,0,0-11.32-11.32Zm0,64L56,196.69,45.66,186.34a8,8,0,0,0-11.32,11.32l16,16a8,8,0,0,0,11.32,0l32-32a8,8,0,0,0-11.32-11.32Z', 'M208,32H48A16,16,0,0,0,32,48V208a16,16,0,0,0,16,16H208a16,16,0,0,0,16-16V48A16,16,0,0,0,208,32ZM117.66,149.66l-32,32a8,8,0,0,1-11.32,0l-16-16a8,8,0,0,1,11.32-11.32L80,164.69l26.34-26.35a8,8,0,0,1,11.32,11.32Zm0-64-32,32a8,8,0,0,1-11.32,0l-16-16A8,8,0,0,1,69.66,90.34L80,100.69l26.34-26.35a8,8,0,0,1,11.32,11.32ZM192,168H144a8,8,0,0,1,0-16h48a8,8,0,0,1,0,16Zm0-64H144a8,8,0,0,1,0-16h48a8,8,0,0,1,0,16Z'],
    'schedules'    => ['M208,32H184V24a8,8,0,0,0-16,0v8H88V24a8,8,0,0,0-16,0v8H48A16,16,0,0,0,32,48V208a16,16,0,0,0,16,16H208a16,16,0,0,0,16-16V48A16,16,0,0,0,208,32ZM72,48v8a8,8,0,0,0,16,0V48h80v8a8,8,0,0,0,16,0V48h24V80H48V48ZM208,208H48V96H208V208Zm-48-56a8,8,0,0,1-8,8H136v16a8,8,0,0,1-16,0V160H104a8,8,0,0,1,0-16h16V128a8,8,0,0,1,16,0v16h16A8,8,0,0,1,160,152Z', 'M208,32H184V24a8,8,0,0,0-16,0v8H88V24a8,8,0,0,0-16,0v8H48A16,16,0,0,0,32,48V208a16,16,0,0,0,16,16H208a16,16,0,0,0,16-16V48A16,16,0,0,0,208,32ZM152,160H136v16a8,8,0,0,1-16,0V160H104a8,8,0,0,1,0-16h16V128a8,8,0,0,1,16,0v16h16a8,8,0,0,1,0,16ZM48,80V48H72v8a8,8,0,0,0,16,0V48h80v8a8,8,0,0,0,16,0V48h24V80Z'],
    'services'     => ['M171,71.42,149.54,80,171,88.57A8,8,0,1,1,165,103.42L128,88.61,91,103.42A8,8,0,1,1,85,88.57L106.46,80,85,71.42A8,8,0,1,1,91,56.57l37,14.81,37-14.81A8,8,0,1,1,171,71.42Zm53,8.33c0,42.72-8,75.4-14.69,95.28-8.73,25.8-20.63,45.49-32.65,54a15.69,15.69,0,0,1-15.95,1.41,16.09,16.09,0,0,1-9.18-13.36C150.68,205.58,146.48,168,128,168s-22.68,37.59-23.53,49.11a16.09,16.09,0,0,1-16,14.9,15.67,15.67,0,0,1-9.13-2.95c-12-8.53-23.92-28.22-32.65-54C40,155.15,32,122.47,32,79.75A56,56,0,0,1,88,24h80A56,56,0,0,1,224,79.75Zm-16,0A40,40,0,0,0,168,40H88A40,40,0,0,0,48,79.76c0,40.55,7.51,71.4,13.85,90.14,11.05,32.66,23,43.37,26.61,46C91.57,174.67,105.59,152,128,152s36.45,22.71,39.49,63.94h0c3.6-2.59,15.57-13.26,26.66-46C200.49,151.16,208,120.31,208,79.76Z', 'M168,24H88A56,56,0,0,0,32,79.75c0,42.72,8,75.4,14.7,95.28,8.72,25.8,20.62,45.49,32.64,54A15.67,15.67,0,0,0,88.47,232a16.09,16.09,0,0,0,16-14.9c.85-11.52,5-49.11,23.53-49.11s22.68,37.59,23.53,49.11a16.09,16.09,0,0,0,9.18,13.36,15.69,15.69,0,0,0,15.95-1.41c12-8.53,23.92-28.22,32.64-54C216,155.15,224,122.47,224,79.75A56,56,0,0,0,168,24Zm3,56.57A8,8,0,1,1,165,95.42L128,80.61,91,95.42A8,8,0,1,1,85,80.57L106.46,72,85,63.42A8,8,0,1,1,91,48.57l37,14.81,37-14.81A8,8,0,1,1,171,63.42L149.54,72Z'],
    'users'        => ['M117.25,157.92a60,60,0,1,0-66.5,0A95.83,95.83,0,0,0,3.53,195.63a8,8,0,1,0,13.4,8.74,80,80,0,0,1,134.14,0,8,8,0,0,0,13.4-8.74A95.83,95.83,0,0,0,117.25,157.92ZM40,108a44,44,0,1,1,44,44A44.05,44.05,0,0,1,40,108Zm210.14,98.7a8,8,0,0,1-11.07-2.33A79.83,79.83,0,0,0,172,168a8,8,0,0,1,0-16,44,44,0,1,0-16.34-84.87,8,8,0,1,1-5.94-14.85,60,60,0,0,1,55.53,105.64,95.83,95.83,0,0,1,47.22,37.71A8,8,0,0,1,250.14,206.7Z', 'M164.47,195.63a8,8,0,0,1-6.7,12.37H10.23a8,8,0,0,1-6.7-12.37,95.83,95.83,0,0,1,47.22-37.71,60,60,0,1,1,66.5,0A95.83,95.83,0,0,1,164.47,195.63Zm87.91-.15a95.87,95.87,0,0,0-47.13-37.56A60,60,0,0,0,144.7,54.59a4,4,0,0,0-1.33,6A75.83,75.83,0,0,1,147,150.53a4,4,0,0,0,1.07,5.53,112.32,112.32,0,0,1,29.85,30.83,23.92,23.92,0,0,1,3.65,16.47,4,4,0,0,0,3.95,4.64h60.3a8,8,0,0,0,7.73-5.93A8.22,8.22,0,0,0,252.38,195.48Z'],
    'reports'      => ['M232,208a8,8,0,0,1-8,8H32a8,8,0,0,1-8-8V48a8,8,0,0,1,16,0v94.37L90.73,98a8,8,0,0,1,10.07-.38l58.81,44.11L218.73,90a8,8,0,1,1,10.54,12l-64,56a8,8,0,0,1-10.07.38L96.39,114.29,40,163.63V200H224A8,8,0,0,1,232,208Z', 'M216,40H40A16,16,0,0,0,24,56V200a16,16,0,0,0,16,16H216a16,16,0,0,0,16-16V56A16,16,0,0,0,216,40ZM200,176a8,8,0,0,1,0,16H56a8,8,0,0,1-8-8V72a8,8,0,0,1,16,0v62.92l34.88-29.07a8,8,0,0,1,9.56-.51l43,28.69,43.41-36.18a8,8,0,0,1,10.24,12.3l-48,40a8,8,0,0,1-9.56.51l-43-28.69L64,155.75V176Z'],
    'logs'         => ['M136,80v43.47l36.12,21.67a8,8,0,0,1-8.24,13.72l-40-24A8,8,0,0,1,120,128V80a8,8,0,0,1,16,0Zm-8-48A95.44,95.44,0,0,0,60.08,60.15C52.81,67.51,46.35,74.59,40,82V64a8,8,0,0,0-16,0v40a8,8,0,0,0,8,8H72a8,8,0,0,0,0-16H49c7.15-8.42,14.27-16.35,22.39-24.57a80,80,0,1,1,1.66,114.75,8,8,0,1,0-11,11.64A96,96,0,1,0,128,32Z', 'M224,128A96,96,0,0,1,62.11,197.82a8,8,0,1,1,11-11.64A80,80,0,1,0,71.43,71.43C67.9,75,64.58,78.51,61.35,82L77.66,98.34A8,8,0,0,1,72,112H32a8,8,0,0,1-8-8V64a8,8,0,0,1,13.66-5.66L50,70.7c3.22-3.49,6.54-7,10.06-10.55A96,96,0,0,1,224,128ZM128,72a8,8,0,0,0-8,8v48a8,8,0,0,0,3.88,6.86l40,24a8,8,0,1,0,8.24-13.72L136,123.47V80A8,8,0,0,0,128,72Z'],
    'settings'     => ['M128,80a48,48,0,1,0,48,48A48.05,48.05,0,0,0,128,80Zm0,80a32,32,0,1,1,32-32A32,32,0,0,1,128,160Zm88-29.84q.06-2.16,0-4.32l14.92-18.64a8,8,0,0,0,1.48-7.06,107.21,107.21,0,0,0-10.88-26.25,8,8,0,0,0-6-3.93l-23.72-2.64q-1.48-1.56-3-3L186,40.54a8,8,0,0,0-3.94-6,107.71,107.71,0,0,0-26.25-10.87,8,8,0,0,0-7.06,1.49L130.16,40Q128,40,125.84,40L107.2,25.11a8,8,0,0,0-7.06-1.48A107.6,107.6,0,0,0,73.89,34.51a8,8,0,0,0-3.93,6L67.32,64.27q-1.56,1.49-3,3L40.54,70a8,8,0,0,0-6,3.94,107.71,107.71,0,0,0-10.87,26.25,8,8,0,0,0,1.49,7.06L40,125.84Q40,128,40,130.16L25.11,148.8a8,8,0,0,0-1.48,7.06,107.21,107.21,0,0,0,10.88,26.25,8,8,0,0,0,6,3.93l23.72,2.64q1.49,1.56,3,3L70,215.46a8,8,0,0,0,3.94,6,107.71,107.71,0,0,0,26.25,10.87,8,8,0,0,0,7.06-1.49L125.84,216q2.16.06,4.32,0l18.64,14.92a8,8,0,0,0,7.06,1.48,107.21,107.21,0,0,0,26.25-10.88,8,8,0,0,0,3.93-6l2.64-23.72q1.56-1.48,3-3L215.46,186a8,8,0,0,0,6-3.94,107.71,107.71,0,0,0,10.87-26.25,8,8,0,0,0-1.49-7.06Zm-16.1-6.5a73.93,73.93,0,0,1,0,8.68,8,8,0,0,0,1.74,5.48l14.19,17.73a91.57,91.57,0,0,1-6.23,15L187,173.11a8,8,0,0,0-5.1,2.64,74.11,74.11,0,0,1-6.14,6.14,8,8,0,0,0-2.64,5.1l-2.51,22.58a91.32,91.32,0,0,1-15,6.23l-17.74-14.19a8,8,0,0,0-5-1.75h-.48a73.93,73.93,0,0,1-8.68,0,8,8,0,0,0-5.48,1.74L100.45,215.8a91.57,91.57,0,0,1-15-6.23L82.89,187a8,8,0,0,0-2.64-5.1,74.11,74.11,0,0,1-6.14-6.14,8,8,0,0,0-5.1-2.64L46.43,170.6a91.32,91.32,0,0,1-6.23-15l14.19-17.74a8,8,0,0,0,1.74-5.48,73.93,73.93,0,0,1,0-8.68,8,8,0,0,0-1.74-5.48L40.2,100.45a91.57,91.57,0,0,1,6.23-15L69,82.89a8,8,0,0,0,5.1-2.64,74.11,74.11,0,0,1,6.14-6.14A8,8,0,0,0,82.89,69L85.4,46.43a91.32,91.32,0,0,1,15-6.23l17.74,14.19a8,8,0,0,0,5.48,1.74,73.93,73.93,0,0,1,8.68,0,8,8,0,0,0,5.48-1.74L155.55,40.2a91.57,91.57,0,0,1,15,6.23L173.11,69a8,8,0,0,0,2.64,5.1,74.11,74.11,0,0,1,6.14,6.14,8,8,0,0,0,5.1,2.64l22.58,2.51a91.32,91.32,0,0,1,6.23,15l-14.19,17.74A8,8,0,0,0,199.87,123.66Z', 'M216,130.16q.06-2.16,0-4.32l14.92-18.64a8,8,0,0,0,1.48-7.06,107.6,107.6,0,0,0-10.88-26.25,8,8,0,0,0-6-3.93l-23.72-2.64q-1.48-1.56-3-3L186,40.54a8,8,0,0,0-3.94-6,107.29,107.29,0,0,0-26.25-10.86,8,8,0,0,0-7.06,1.48L130.16,40Q128,40,125.84,40L107.2,25.11a8,8,0,0,0-7.06-1.48A107.6,107.6,0,0,0,73.89,34.51a8,8,0,0,0-3.93,6L67.32,64.27q-1.56,1.49-3,3L40.54,70a8,8,0,0,0-6,3.94,107.71,107.71,0,0,0-10.87,26.25,8,8,0,0,0,1.49,7.06L40,125.84Q40,128,40,130.16L25.11,148.8a8,8,0,0,0-1.48,7.06,107.6,107.6,0,0,0,10.88,26.25,8,8,0,0,0,6,3.93l23.72,2.64q1.49,1.56,3,3L70,215.46a8,8,0,0,0,3.94,6,107.71,107.71,0,0,0,26.25,10.87,8,8,0,0,0,7.06-1.49L125.84,216q2.16.06,4.32,0l18.64,14.92a8,8,0,0,0,7.06,1.48,107.21,107.21,0,0,0,26.25-10.88,8,8,0,0,0,3.93-6l2.64-23.72q1.56-1.48,3-3L215.46,186a8,8,0,0,0,6-3.94,107.71,107.71,0,0,0,10.87-26.25,8,8,0,0,0-1.49-7.06ZM128,168a40,40,0,1,1,40-40A40,40,0,0,1,128,168Z'],
    'transactions' => ['M239.76,158.06,217.28,68.12A16,16,0,0,0,201.75,56H136V40a16,16,0,0,0-16-16H80A16,16,0,0,0,64,40V56H54.25A16,16,0,0,0,38.72,68.12L16.24,158.06A7.93,7.93,0,0,0,16,160v32a16,16,0,0,0,16,16H224a16,16,0,0,0,16-16V160A7.93,7.93,0,0,0,239.76,158.06ZM80,40h40V56H80ZM54.25,72h147.5l20,80H34.25ZM32,192V168H224v24ZM64,96a8,8,0,0,1,8-8H88a8,8,0,0,1,0,16H72A8,8,0,0,1,64,96Zm48,0a8,8,0,0,1,8-8h16a8,8,0,0,1,0,16H120A8,8,0,0,1,112,96Zm48,0a8,8,0,0,1,8-8h16a8,8,0,0,1,0,16H168A8,8,0,0,1,160,96ZM64,128a8,8,0,0,1,8-8H88a8,8,0,0,1,0,16H72A8,8,0,0,1,64,128Zm48,0a8,8,0,0,1,8-8h16a8,8,0,0,1,0,16H120A8,8,0,0,1,112,128Zm48,0a8,8,0,0,1,8-8h16a8,8,0,0,1,0,16H168A8,8,0,0,1,160,128Z', 'M239.76,158.06,217.28,68.12A16,16,0,0,0,201.75,56H136V40a16,16,0,0,0-16-16H80A16,16,0,0,0,64,40V56H54.25A16,16,0,0,0,38.72,68.12L16.24,158.06A7.93,7.93,0,0,0,16,160v32a16,16,0,0,0,16,16H224a16,16,0,0,0,16-16V160A7.93,7.93,0,0,0,239.76,158.06ZM168,88h16a8,8,0,0,1,0,16H168a8,8,0,0,1,0-16Zm0,32h16a8,8,0,0,1,0,16H168a8,8,0,0,1,0-16ZM136,88a8,8,0,0,1,0,16H120a8,8,0,0,1,0-16Zm8,40a8,8,0,0,1-8,8H120a8,8,0,0,1,0-16h16A8,8,0,0,1,144,128ZM80,40h40V56H80ZM72,88H88a8,8,0,0,1,0,16H72a8,8,0,0,1,0-16Zm0,32H88a8,8,0,0,1,0,16H72a8,8,0,0,1,0-16Zm152,72H32V168H224Z'],
    'calendar'     => ['M208,32H184V24a8,8,0,0,0-16,0v8H88V24a8,8,0,0,0-16,0v8H48A16,16,0,0,0,32,48V208a16,16,0,0,0,16,16H208a16,16,0,0,0,16-16V48A16,16,0,0,0,208,32ZM72,48v8a8,8,0,0,0,16,0V48h80v8a8,8,0,0,0,16,0V48h24V80H48V48ZM208,208H48V96H208V208Zm-68-76a12,12,0,1,1-12-12A12,12,0,0,1,140,132Zm44,0a12,12,0,1,1-12-12A12,12,0,0,1,184,132ZM96,172a12,12,0,1,1-12-12A12,12,0,0,1,96,172Zm44,0a12,12,0,1,1-12-12A12,12,0,0,1,140,172Zm44,0a12,12,0,1,1-12-12A12,12,0,0,1,184,172Z', 'M208,32H184V24a8,8,0,0,0-16,0v8H88V24a8,8,0,0,0-16,0v8H48A16,16,0,0,0,32,48V208a16,16,0,0,0,16,16H208a16,16,0,0,0,16-16V48A16,16,0,0,0,208,32ZM84,184a12,12,0,1,1,12-12A12,12,0,0,1,84,184Zm44,0a12,12,0,1,1,12-12A12,12,0,0,1,128,184Zm0-40a12,12,0,1,1,12-12A12,12,0,0,1,128,144Zm44,40a12,12,0,1,1,12-12A12,12,0,0,1,172,184Zm0-40a12,12,0,1,1,12-12A12,12,0,0,1,172,144Zm36-64H48V48H72v8a8,8,0,0,0,16,0V48h80v8a8,8,0,0,0,16,0V48h24Z'],
    'records'      => ['M213.66,82.34l-56-56A8,8,0,0,0,152,24H56A16,16,0,0,0,40,40V216a16,16,0,0,0,16,16H200a16,16,0,0,0,16-16V88A8,8,0,0,0,213.66,82.34ZM160,51.31,188.69,80H160ZM200,216H56V40h88V88a8,8,0,0,0,8,8h48V216Zm-32-80a8,8,0,0,1-8,8H96a8,8,0,0,1,0-16h64A8,8,0,0,1,168,136Zm0,32a8,8,0,0,1-8,8H96a8,8,0,0,1,0-16h64A8,8,0,0,1,168,168Z', 'M213.66,82.34l-56-56A8,8,0,0,0,152,24H56A16,16,0,0,0,40,40V216a16,16,0,0,0,16,16H200a16,16,0,0,0,16-16V88A8,8,0,0,0,213.66,82.34ZM160,176H96a8,8,0,0,1,0-16h64a8,8,0,0,1,0,16Zm0-32H96a8,8,0,0,1,0-16h64a8,8,0,0,1,0,16Zm-8-56V44l44,44Z'],
    'billing'      => ['M72,104a8,8,0,0,1,8-8h96a8,8,0,0,1,0,16H80A8,8,0,0,1,72,104Zm8,40h96a8,8,0,0,0,0-16H80a8,8,0,0,0,0,16ZM232,56V208a8,8,0,0,1-11.58,7.15L192,200.94l-28.42,14.21a8,8,0,0,1-7.16,0L128,200.94,99.58,215.15a8,8,0,0,1-7.16,0L64,200.94,35.58,215.15A8,8,0,0,1,24,208V56A16,16,0,0,1,40,40H216A16,16,0,0,1,232,56Zm-16,0H40V195.06l20.42-10.22a8,8,0,0,1,7.16,0L96,199.06l28.42-14.22a8,8,0,0,1,7.16,0L160,199.06l28.42-14.22a8,8,0,0,1,7.16,0L216,195.06Z', 'M216,40H40A16,16,0,0,0,24,56V208a8,8,0,0,0,11.58,7.15L64,200.94l28.42,14.21a8,8,0,0,0,7.16,0L128,200.94l28.42,14.21a8,8,0,0,0,7.16,0L192,200.94l28.42,14.21A8,8,0,0,0,232,208V56A16,16,0,0,0,216,40ZM176,144H80a8,8,0,0,1,0-16h96a8,8,0,0,1,0,16Zm0-32H80a8,8,0,0,1,0-16h96a8,8,0,0,1,0,16Z'],
];
?>
<aside id="sidebar"
       class="fixed inset-y-0 left-0 z-40 flex w-[244px] flex-col bg-dgt-blue-800 text-white/70 transition-transform
              max-nav:-translate-x-full max-nav:[&.open]:translate-x-0 nav:translate-x-0 print:hidden">
    <style>
    /* Sidebar nav icons (Phosphor, inline SVG) — Twitter/X style: a dim outline
       glyph at rest; the active row swaps to the solid-fill glyph, same white,
       no background chip. */
    #sidebar .nav-ico { color: #fff; opacity: .55; transition: opacity .18s ease; }
    #sidebar .nav-ico .ico-fill { display: none; }
    #sidebar .nav-item:hover .nav-ico { opacity: .85; }
    #sidebar .nav-item.active .nav-ico { opacity: 1; }
    #sidebar .nav-item.active .nav-ico .ico-line { display: none; }
    #sidebar .nav-item.active .nav-ico .ico-fill { display: inline; }
    /* Section headings: a small label above each group; in the collapsed
       rail the text fades out and a short divider line takes its place. */
    #sidebar .nav-sec { position: relative; height: 22px; flex: none; display: flex; align-items: flex-end; padding: 0 12px 5px; }
    #sidebar .nav-scroll { scrollbar-width: thin; scrollbar-color: rgba(255,255,255,.2) transparent; }
    #sidebar .nav-sec::after { content: ''; position: absolute; left: 50%; bottom: 9px; width: 22px; margin-left: -11px; height: 1px; background: rgba(255,255,255,.18); opacity: 0; transition: opacity .16s ease; }
    @media (min-width: 1280px) { body.sb-collapsed #sidebar .nav-sec::after { opacity: 1; } }
    </style>
    <?php
    // Only patients can jump back to the public landing page from the portal;
    // staff roles get a plain, non-linked brand block.
    $brandIsLink = $role === 'patient';
    $brandTag    = $brandIsLink ? 'a' : 'div';
    $brandAttr   = $brandIsLink ? ' href="/dgt_dental_clinic/home" title="Clinic home page"' : '';
    $brandCls    = 'sidebar-brand flex items-center gap-3 bg-dgt-teal-600 px-[18px] py-5'
                 . ($brandIsLink ? ' hover:bg-dgt-teal-700' : '');
    ?>
    <div class="relative">
        <<?= $brandTag . $brandAttr ?> class="<?= $brandCls ?>">
            <span class="flex min-w-0 items-center gap-3">
                <!-- Doubles as the collapsed-state toggle: while collapsed, hovering
                     swaps the logo for the panel glyph and a click expands (wired in
                     app.js — it no-ops while expanded, so the logo just behaves like a
                     logo then). Matches ChatGPT's own "logo becomes the reopen control"
                     behaviour instead of a separate visible button in the rail. -->
                <span id="sbLogoToggle" class="sb-logo-badge relative grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-[10px] bg-white text-xs font-bold text-dgt-blue-900">
                    <img src="<?= e(asset('assets/img/dgt_logo.jpg')) ?>" alt="DGT One Dental Center"
                         class="sb-logo-img h-full w-full object-contain p-[3px]"
                         onerror="this.parentNode.textContent='DGT'">
                    <svg class="sb-logo-ico pointer-events-none absolute inset-0 m-auto h-5 w-5 text-dgt-blue-800 opacity-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="16" rx="2"/>
                        <line x1="9" y1="4" x2="9" y2="20"/>
                    </svg>
                </span>
                <span class="sb-label flex min-w-0 flex-col">
                    <span class="truncate text-sm font-bold text-white">DGT One Dental Center</span>
                    <span class="truncate text-[11px] text-white/70">Bulacan Dental Clinic</span>
                </span>
            </span>
        </<?= $brandTag ?>>
        <!-- Expanded-state toggle: overlaid inside the brand block's bottom-right corner,
             aligned with the short second line ("Bulacan Dental Clinic") instead of the
             long first line, so the full clinic name is never squeezed. The wrapping
             .relative div (not the fixed, full-height <aside>) is what this anchors to.
             Hides once collapsed — the logo alone keeps the header a fixed height either
             way, so collapsing never shifts the nav list below. -->
        <button type="button" aria-label="Collapse sidebar" title="Collapse sidebar"
                class="sb-toggle sb-toggle-header absolute bottom-2 right-3 hidden h-6 w-6 flex-none items-center justify-center rounded-md text-white/70 hover:bg-white/[0.12] hover:text-white nav:flex">
            <svg viewBox="0 0 24 24" class="h-[15px] w-[15px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="4" width="18" height="16" rx="2"/>
                <line x1="9" y1="4" x2="9" y2="20"/>
            </svg>
        </button>
    </div>

    <nav class="nav-scroll flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto overflow-x-hidden px-2.5 py-3" aria-label="Main">
        <?php foreach ($navGroups as $gi => [$secLabel, $groupItems]): ?>
        <?php if ($secLabel !== ''): ?>
            <div class="nav-sec<?= $gi > 0 ? ' mt-2' : '' ?>" role="presentation">
                <span class="sb-label text-[10.5px] font-semibold uppercase tracking-[0.14em] text-white/45"><?= e($secLabel) ?></span>
            </div>
        <?php endif; ?>
        <?php foreach ($groupItems as $it): [$key, $label, $href] = $it; $icoKey = $it[3] ?? $key; ?>
            <a href="<?= e($href) ?>" data-nav title="<?= e($label) ?>"
               class="nav-item group flex flex-none items-center gap-2.5 rounded-md px-3 py-2.5 text-[13px] font-medium text-white/70
                      hover:bg-white/[0.09] hover:text-white [&.active]:bg-dgt-blue-500 [&.active]:text-white<?= $ACTIVE === $key ? ' active' : '' ?>">
                <span class="nav-ico-wrap grid h-5 w-5 flex-none place-items-center">
                    <?php if (isset($NAV_ICON[$icoKey])): ?>
                        <svg class="nav-ico h-5 w-5" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true">
                            <path class="ico-line" d="<?= $NAV_ICON[$icoKey][0] ?>"/>
                            <path class="ico-fill" d="<?= $NAV_ICON[$icoKey][1] ?>"/>
                        </svg>
                    <?php else: ?>
                        <span class="nav-ico h-[7px] w-[7px] rounded-full bg-current"></span>
                    <?php endif; ?>
                </span><span class="sb-label"><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <?php $meAvatar = avatarUrl($me['avatar'] ?? null); ?>
    <div class="flex flex-col gap-2 border-t border-white/10 p-3">
        <a href="/dgt_dental_clinic/modules/shared/profile" data-nav title="View profile"
           class="sidebar-user flex items-center gap-2.5 rounded-md p-2 text-white hover:bg-white/[0.09]">
            <span class="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-full bg-white/10 text-sm font-bold text-white">
                <?php if ($meAvatar !== null): ?>
                    <img src="<?= e($meAvatar) ?>" alt="" class="h-full w-full object-cover">
                <?php else: ?>
                    <?= e(strtoupper(substr($me['full_name'], 0, 1))) ?>
                <?php endif; ?>
            </span>
            <span class="sb-label flex min-w-0 flex-col">
                <span class="truncate text-[13px] font-semibold text-white"><?= e(shortName($me)) ?></span>
                <span class="text-[10px] uppercase tracking-widest text-white/50"><?= e(roleLabel($role)) ?></span>
            </span>
        </a>
        <a href="/dgt_dental_clinic/logout" title="Logout"
           class="sidebar-logout group relative flex items-center justify-end rounded-[11px] border-2 border-danger px-4 py-2.5 text-xs font-semibold text-white transition-all duration-500 hover:bg-danger">
            <svg class="logout-ico absolute left-4 h-4 w-4 shrink-0 text-white transition-transform duration-500 group-hover:translate-x-[5px]"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            <span class="sb-label">Logout</span>
        </a>
    </div>
</aside>
