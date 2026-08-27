<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Keep the CI3 session display name in sync with staff.fname/lname
 * (same source as the new Staff Portal top bar).
 */
function refresh_session_staff_identity()
{
    $CI =& get_instance();
    if (! isset($CI->session) || ! isset($CI->db)) {
        return;
    }

    $user = $CI->session->userdata('user');
    if (! is_object($user)) {
        return;
    }

    $staffId = (int) ($user->staff_id ?? $user->auth_staff_id ?? 0);
    if ($staffId < 1) {
        return;
    }

    $staff = $CI->db->select('fname, lname, title, oname, photo, work_email')
        ->from('staff')
        ->where('staff_id', $staffId)
        ->limit(1)
        ->get()
        ->row();
    if (! $staff) {
        return;
    }

    $name = trim((string) $staff->fname . ' ' . (string) $staff->lname);
    if ($name === '') {
        return;
    }

    if (
        (string) ($user->name ?? '') === $name
        && (string) ($user->fname ?? '') === (string) $staff->fname
        && (string) ($user->lname ?? '') === (string) $staff->lname
    ) {
        return;
    }

    $user->fname = $staff->fname;
    $user->lname = $staff->lname;
    $user->title = $staff->title;
    $user->oname = $staff->oname;
    $user->photo = $staff->photo;
    $user->name = $name;
    $CI->session->set_userdata('user', $user);
}
