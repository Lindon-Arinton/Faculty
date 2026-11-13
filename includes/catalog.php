<?php
/**
 * Fixed lists shared by the seeder and the pages.
 */

const REQUIREMENTS = ['Form 138 / Transcript', 'PSA birth certificate', 'Good moral certificate', 'Medical clearance', '2x2 ID photos'];

const RISK_TYPES = ['Failing', 'INC', 'Behind curriculum'];

// Irregular students with at least this many back subjects count as behind curriculum.
const RISK_BACK_SUBJECTS = 3;

const INTERVENTION_STATUSES = ['Not contacted', 'Contacted', 'Counseling scheduled', 'Improving'];

// Column in evaluation_responses => label
const EVAL_CATEGORIES = [
    'teaching'      => 'Teaching effectiveness',
    'organization'  => 'Course organization',
    'communication' => 'Communication',
    'fairness'      => 'Fairness in grading',
    'punctuality'   => 'Punctuality and availability',
];

const WEEK_DAYS = ['Mon' => 'Monday', 'Tue' => 'Tuesday', 'Wed' => 'Wednesday', 'Thu' => 'Thursday', 'Fri' => 'Friday', 'Sat' => 'Saturday'];
