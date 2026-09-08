<?php
/**
 * Shared validation and access-control helpers.
 * Include this after session_start() and database.php in any page that needs it.
 */

/**
 * True if $phone is a valid PH mobile number: exactly 11 digits, starting with "09".
 */
function isValidPhone(string $phone): bool
{
  return (bool) preg_match('/^09\d{9}$/', $phone);
}

/**
 * Validates the registration / delivery-profile fields used by register.php
 * and account.php (both the "Create Account" form and checkout).
 *
 * Returns an empty string when everything is valid, or a human-readable
 * error message describing the first problem found.
 */
function validateRegistrationFields(string $name, string $email, string $password, string $address, string $location, string $zip, string $phone, bool $requirePassword = true): string
{
  if ($name === '') {
    return 'Please enter your name.';
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return 'Please enter a valid email address.';
  }
  if ($requirePassword && strlen($password) < 6) {
    return 'Password must be at least 6 characters.';
  }
  if ($address === '' || $location === '' || $zip === '' || $phone === '') {
    return 'Complete your name, email, password, address, location, ZIP code, and phone number.';
  }
  if (!isValidPhone($phone)) {
    return 'Phone number must be 11 digits and start with 09 (e.g. 09171234567).';
  }
  return '';
}

/**
 * Validates just the delivery fields (used at checkout, where the user is
 * already logged in so email/password aren't part of the form).
 */
function validateDeliveryFields(string $address, string $location, string $zip, string $phone): string
{
  if ($address === '' || $location === '' || $zip === '' || $phone === '') {
    return 'Please add your delivery address, location, ZIP code, and phone number.';
  }
  if (!isValidPhone($phone)) {
    return 'Phone number must be 11 digits and start with 09 (e.g. 09171234567).';
  }
  return '';
}

/**
 * True if a client (either a regular user or an admin) is currently logged in.
 */
function isLoggedIn(): bool
{
  return !empty($_SESSION['user_id']) || !empty($_SESSION['is_admin']);
}

/**
 * True if the current session belongs to an admin.
 */
function isAdmin(): bool
{
  return !empty($_SESSION['is_admin']);
}

/**
 * Redirects to login.php and stops execution unless the session is an admin.
 * Use at the top of admin-only pages, after session_start().
 */
function requireAdmin(): void
{
  if (!isAdmin()) {
    header('Location: login.php');
    exit;
  }
}

/**
 * Redirects to login.php and stops execution unless someone is logged in.
 * Use on pages that should never be reachable by a guest.
 */
function requireLogin(): void
{
  if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
  }
}