<?php
/*
 * auth.php - session + role based access control
 * Include this at the top of every protected page.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Page key => [label, file]
const PAGES = [
    'subjects'    => ['Subjects',    'subjects.php'],
    'term'        => ['Term',        'term.php'],
    'instructors' => ['Instructor',  'instructors.php'],
    'schoolyear'  => ['School Year', 'schoolyear.php'],
    'section'     => ['Section',     'section.php'],
    'yearlevel'   => ['Year Level',  'yearlevel.php'],
];

// Usertype => pages that usertype may open
const ACCESS = [
    'admin'      => ['subjects', 'term', 'instructors', 'schoolyear', 'section', 'yearlevel'], // all pages
    'registrar'  => ['subjects', 'term', 'instructors', 'schoolyear'],
    'dean'       => ['subjects', 'section', 'instructors', 'yearlevel'],
    'instructor' => ['subjects', 'section'],
    'student'    => ['subjects'],
];

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function current_usertype()
{
    return $_SESSION['USERTYPE'] ?? '';
}

function require_login()
{
    if (!isset($_SESSION['USERNAME']) || !isset($_SESSION['USERTYPE'])) {
        header("Location: login.php");
        exit();
    }
}

function can_access($pageKey)
{
    $allowed = ACCESS[current_usertype()] ?? [];
    return in_array($pageKey, $allowed, true);
}

// Block the page if the logged-in usertype is not allowed to open it
function require_access($pageKey)
{
    require_login();

    if (!can_access($pageKey)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><title>Access Denied</title></head>'
            . '<body style="font-family:Arial;text-align:center;margin-top:100px">'
            . '<h1>403 - Access Denied</h1>'
            . '<p>Your usertype (' . h(current_usertype()) . ') is not allowed to open this page.</p>'
            . '<a href="dashboard.php">Back to Dashboard</a></body></html>';
        exit();
    }
}

// Students can only view; everyone else with page access can add/edit/delete
function can_edit()
{
    return current_usertype() !== 'student';
}

// Students are not allowed to use the search feature
function can_search()
{
    return current_usertype() !== 'student';
}

// ---- CSRF protection for forms ----
function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check()
{
    $sent = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(400);
        exit('Invalid request token.');
    }
}
