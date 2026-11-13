<?php
// Database settings (XAMPP defaults). Change these for production.
const DB_HOST = '127.0.0.1';
const DB_NAME = 'veritas_portal';
const DB_USER = 'root';
const DB_PASS = '';

const APP_NAME = 'BSU';
const APP_TAGLINE = 'Faculty Portal';

// Account created automatically the first time the users table is empty.
const DEFAULT_USER_NAME  = 'Faculty Office';
const DEFAULT_USER_EMAIL = 'facultyfaculty';
const DEFAULT_USER_PASS  = '12345';
const DEFAULT_USER_ROLE  = 'University-wide access';

// Login throttling
const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_SECONDS    = 300;

date_default_timezone_set('Asia/Manila');
