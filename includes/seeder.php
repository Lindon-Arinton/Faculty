<?php
/**
 * Builds a realistic sample university in the database.
 *
 * Everything is derived, not hand-set:
 *  - students are split into blocks of 40 or fewer, and each block takes its program's 2nd-semester courses
 *  - a scheduler gives every section times and rooms with no clashes for the block, the instructor or the room
 *  - grades come from each student's ability, the instructor's strictness and the course difficulty
 *  - "at risk" comes from those grades; submission status comes from how many final grades are encoded
 *  - faculty ratings are averages of individual anonymous responses
 *  - applicants move through exam -> requirements -> enrollment based on their dates
 *
 * The data is a snapshot as of SEED_AS_OF. Run `php database/seed.php` to rebuild it.
 */
require_once __DIR__ . '/catalog.php';

const SEED_AS_OF = '2025-05-27 09:30:00';
const SEED_RANDOM = 2025;
const SEED_BLOCK_SIZE = 40;

const SEED_TERMS = [
    // name, starts, ends, grade deadline, current
    ['1st Semester 2024–2025', '2024-08-12', '2024-12-14', '2024-12-23', 0],
    ['2nd Semester 2024–2025', '2025-01-13', '2025-05-17', '2025-06-02', 1],
];

const SEED_COLLEGES = [
    'Computing'       => ['CCS',  'College of Computing Studies',        'CCS Building'],
    'Engineering'     => ['COE',  'College of Engineering',              'Engineering Building'],
    'Business'        => ['CBA',  'College of Business and Accountancy', 'Business Building'],
    'Arts & Sciences' => ['CAS',  'College of Arts and Sciences',        'Arts and Sciences Building'],
    'Education'       => ['COED', 'College of Education',                'Education Building'],
    'Health Sciences' => ['CHS',  'College of Health Sciences',          'Health Sciences Building'],
];

// code => [college, name, enrolled students, share of applicants (%), share of women]
const SEED_PROGRAMS = [
    'BSCS'      => ['Computing',       'BS Computer Science',             520, 9,  0.30],
    'BSIT'      => ['Computing',       'BS Information Technology',       600, 11, 0.35],
    'BSCE'      => ['Engineering',     'BS Civil Engineering',            330, 6,  0.25],
    'BSCpE'     => ['Engineering',     'BS Computer Engineering',         290, 4,  0.20],
    'BSEE'      => ['Engineering',     'BS Electrical Engineering',       214, 2,  0.12],
    'BSME'      => ['Engineering',     'BS Mechanical Engineering',       230, 2,  0.08],
    'BSBA'      => ['Business',        'BS Business Administration',      420, 8,  0.58],
    'BSA'       => ['Business',        'BS Accountancy',                  336, 9,  0.68],
    'BSHM'      => ['Business',        'BS Hospitality Management',       230, 4,  0.55],
    'BA Comm'   => ['Arts & Sciences', 'BA Communication',                300, 3,  0.62],
    'BS Psych'  => ['Arts & Sciences', 'BS Psychology',                   420, 9,  0.75],
    'BA PolSci' => ['Arts & Sciences', 'BA Political Science',            214, 2,  0.50],
    'BSEd'      => ['Education',       'Bachelor of Secondary Education', 520, 6,  0.72],
    'BEEd'      => ['Education',       'Bachelor of Elementary Education', 392, 5, 0.85],
    'BSN'       => ['Health Sciences', 'BS Nursing',                      516, 14, 0.80],
    'BSMT'      => ['Health Sciences', 'BS Medical Technology',           310, 6,  0.75],
];

// Year-level share of each program (later years are smaller after shifting and dropping out)
const SEED_YEAR_SHARE = [1 => 0.30, 2 => 0.26, 3 => 0.23, 4 => 0.21];

// General education taken by every program, by year level
const SEED_GE = [1 => ['GE 102', 'GE 104', 'PE 102', 'NSTP 2'], 2 => ['GE 106', 'GE 108', 'PE 104'], 3 => ['GE 110'], 4 => []];

// Which college offers (and staffs) a course, by code prefix
const SEED_PREFIX_COLLEGE = [
    'CS' => 'Computing', 'IT' => 'Computing',
    'ENG' => 'Engineering', 'CE' => 'Engineering', 'CPE' => 'Engineering', 'EE' => 'Engineering', 'ME' => 'Engineering', 'MATH' => 'Engineering', 'PHYS' => 'Engineering',
    'BA' => 'Business', 'ACC' => 'Business', 'HM' => 'Business', 'ECON' => 'Business', 'STAT' => 'Business',
    'GE' => 'Arts & Sciences', 'PE' => 'Arts & Sciences', 'NSTP' => 'Arts & Sciences', 'COMM' => 'Arts & Sciences', 'PSY' => 'Arts & Sciences', 'POLS' => 'Arts & Sciences', 'SOC' => 'Arts & Sciences',
    'EDU' => 'Education', 'EED' => 'Education', 'ENGL' => 'Education',
    'NCM' => 'Health Sciences', 'MT' => 'Health Sciences', 'CHEM' => 'Health Sciences',
];

// code => [title, kind]
const SEED_COURSES = [
    'GE 102' => ['Mathematics in the Modern World', 'Lecture'], 'GE 104' => ['Purposive Communication', 'Lecture'],
    'GE 106' => ['Art Appreciation', 'Lecture'], 'GE 108' => ['Ethics', 'Lecture'], 'GE 110' => ['The Life and Works of Rizal', 'Lecture'],
    'PE 102' => ['Rhythmic Activities', 'PE'], 'PE 104' => ['Team Sports', 'PE'], 'NSTP 2' => ['Civic Welfare Training Service 2', 'NSTP'],
    // Computing
    'CS 102' => ['Computer Programming 2', 'Lab'], 'CS 104' => ['Discrete Structures 1', 'Lecture'], 'CS 106' => ['Introduction to Human-Computer Interaction', 'Lecture'],
    'CS 202' => ['Data Structures and Algorithms', 'Lab'], 'CS 204' => ['Object-Oriented Programming', 'Lab'], 'CS 206' => ['Discrete Structures 2', 'Lecture'], 'CS 208' => ['Linear Algebra for Computing', 'Lecture'],
    'CS 311' => ['Software Engineering', 'Lab'], 'CS 312' => ['Automata Theory and Formal Languages', 'Lecture'], 'CS 314' => ['Operating Systems', 'Lab'], 'CS 316' => ['Information Assurance and Security', 'Lecture'], 'CS 318' => ['Programming Languages', 'Lecture'],
    'CS 402' => ['CS Thesis 2', 'Lecture'], 'CS 404' => ['Social Issues and Professional Practice', 'Lecture'], 'CS 406' => ['Parallel and Distributed Computing', 'Lab'], 'CS 408' => ['Machine Learning', 'Lab'],
    'IT 102' => ['Computer Programming 2', 'Lab'], 'IT 104' => ['Introduction to Human-Computer Interaction', 'Lecture'], 'IT 106' => ['Discrete Mathematics', 'Lecture'],
    'IT 202' => ['Data Structures and Algorithms', 'Lab'], 'IT 204' => ['Information Management', 'Lab'], 'IT 206' => ['Networking 1', 'Lab'], 'IT 208' => ['Quantitative Methods', 'Lecture'],
    'IT 302' => ['Integrative Programming and Technologies', 'Lab'], 'IT 304' => ['Systems Integration and Architecture', 'Lecture'], 'IT 306' => ['Networking 2', 'Lab'], 'IT 308' => ['Web Systems and Technologies', 'Lab'], 'IT 310' => ['Information Assurance and Security 1', 'Lecture'],
    'IT 402' => ['Capstone Project 2', 'Lecture'], 'IT 404' => ['IT Practicum', 'Practicum'], 'IT 406' => ['Systems Administration and Maintenance', 'Lab'],
    // Engineering
    'MATH 102' => ['Calculus 2', 'Lecture'], 'PHYS 102' => ['Physics for Engineers', 'Lab'], 'MATH 204' => ['Differential Equations', 'Lecture'],
    'ENG 204' => ['Engineering Mechanics', 'Lecture'], 'ENG 302' => ['Engineering Economics', 'Lecture'], 'ENG 102' => ['Computer-Aided Drafting', 'Lab'],
    'CE 202' => ['Fundamentals of Surveying', 'Lab'], 'CE 204' => ['Engineering Geology', 'Lecture'], 'CE 302' => ['Structural Theory 1', 'Lecture'], 'CE 304' => ['Hydraulics', 'Lab'],
    'CE 306' => ['Geotechnical Engineering 1', 'Lab'], 'CE 308' => ['Construction Materials and Testing', 'Lab'], 'CE 402' => ['Principles of Reinforced Concrete', 'Lecture'],
    'CE 404' => ['Construction Methods and Project Management', 'Lecture'], 'CE 406' => ['Highway and Railroad Engineering', 'Lecture'], 'CE 408' => ['CE Project 2', 'Lecture'],
    'CPE 102' => ['Computer Programming 2', 'Lab'], 'CPE 202' => ['Data Structures and Algorithms', 'Lab'], 'CPE 204' => ['Fundamentals of Electrical Circuits', 'Lab'],
    'CPE 302' => ['Microprocessors', 'Lab'], 'CPE 304' => ['Computer Networks and Security', 'Lab'], 'CPE 306' => ['Logic Circuits and Design', 'Lab'], 'CPE 308' => ['Embedded Systems', 'Lab'],
    'CPE 402' => ['CpE Design Project 2', 'Lecture'], 'CPE 404' => ['Digital Signal Processing', 'Lab'], 'CPE 406' => ['Computer Architecture and Organization', 'Lecture'], 'CPE 408' => ['CpE Laws and Professional Practice', 'Lecture'],
    'EE 102' => ['Engineering Drawing', 'Lab'], 'EE 202' => ['Electrical Circuits 1', 'Lab'], 'EE 204' => ['Electromagnetics', 'Lecture'],
    'EE 302' => ['Electrical Machines 1', 'Lab'], 'EE 304' => ['Power System Analysis', 'Lecture'], 'EE 306' => ['Electronic Circuits', 'Lab'], 'EE 308' => ['Feedback Control Systems', 'Lecture'],
    'EE 402' => ['Power Plant Engineering', 'Lecture'], 'EE 404' => ['Illumination Engineering Design', 'Lab'], 'EE 406' => ['EE Design Project 2', 'Lecture'], 'EE 408' => ['EE Laws, Contracts and Ethics', 'Lecture'],
    'ME 102' => ['Workshop Theory and Practice', 'Lab'], 'ME 202' => ['Thermodynamics 1', 'Lecture'], 'ME 204' => ['Mechanics of Deformable Bodies', 'Lecture'],
    'ME 302' => ['Machine Design 1', 'Lecture'], 'ME 304' => ['Fluid Mechanics', 'Lab'], 'ME 306' => ['Heat Transfer', 'Lecture'], 'ME 308' => ['Machine Elements', 'Lecture'],
    'ME 402' => ['Power Plant Design', 'Lecture'], 'ME 404' => ['Refrigeration Systems', 'Lab'], 'ME 406' => ['ME Project Study 2', 'Lecture'], 'ME 408' => ['Industrial Plant Engineering', 'Lecture'],
    // Business
    'BA 102' => ['Principles of Marketing', 'Lecture'], 'ECON 102' => ['Basic Microeconomics', 'Lecture'], 'ACC 102' => ['Financial Accounting and Reporting', 'Lecture'],
    'BA 202' => ['Human Resource Management', 'Lecture'], 'BA 204' => ['Business Law and Taxation', 'Lecture'], 'BA 206' => ['Operations Management', 'Lecture'], 'STAT 202' => ['Business Statistics', 'Lecture'],
    'BA 302' => ['Marketing Research', 'Lecture'], 'BA 304' => ['Consumer Behavior', 'Lecture'], 'BA 306' => ['Strategic Management', 'Lecture'], 'BA 308' => ['International Business and Trade', 'Lecture'], 'BA 310' => ['Feasibility Study', 'Lecture'],
    'BA 402' => ['Business Research 2', 'Lecture'], 'BA 404' => ['Business Practicum', 'Practicum'], 'BA 406' => ['Corporate Social Responsibility', 'Lecture'],
    'ACC 104' => ['Conceptual Framework and Accounting Standards', 'Lecture'], 'ACC 202' => ['Intermediate Accounting 2', 'Lecture'], 'ACC 204' => ['Cost Accounting and Control', 'Lecture'], 'ACC 206' => ['Income Taxation', 'Lecture'],
    'ACC 302' => ['Advanced Financial Accounting', 'Lecture'], 'ACC 304' => ['Auditing and Assurance Principles', 'Lecture'], 'ACC 306' => ['Accounting Information Systems', 'Lab'], 'ACC 308' => ['Strategic Cost Management', 'Lecture'],
    'ACC 310' => ['Governance, Business Ethics and Risk Management', 'Lecture'], 'ACC 402' => ['Auditing Theory', 'Lecture'], 'ACC 404' => ['Accounting Research', 'Lecture'], 'ACC 406' => ['Financial Accounting Review', 'Lecture'], 'ACC 408' => ['Regulatory Framework for Business Transactions', 'Lecture'],
    'HM 102' => ['Kitchen Essentials and Basic Food Preparation', 'Lab'], 'HM 104' => ['Risk Management in Safety, Security and Sanitation', 'Lecture'], 'HM 106' => ['Philippine Tourism, Geography and Culture', 'Lecture'],
    'HM 202' => ['Fundamentals in Lodging Operations', 'Lab'], 'HM 204' => ['Food and Beverage Service Procedures', 'Lab'], 'HM 206' => ['Supply Chain Management in Hospitality', 'Lecture'],
    'HM 302' => ['Bar and Beverage Management', 'Lab'], 'HM 304' => ['Asian Cuisine', 'Lab'], 'HM 306' => ['Events Management', 'Lecture'], 'HM 308' => ['Ergonomics and Facilities Planning', 'Lecture'], 'HM 310' => ['Research in Hospitality 1', 'Lecture'],
    'HM 402' => ['Practicum: Hotel Operations', 'Practicum'], 'HM 404' => ['Research in Hospitality 2', 'Lecture'],
    // Arts & Sciences
    'SOC 102' => ['Introduction to Sociology', 'Lecture'],
    'COMM 102' => ['Introduction to Communication Media', 'Lecture'], 'COMM 104' => ['Communication Theory', 'Lecture'],
    'COMM 202' => ['Writing for Print Media', 'Lecture'], 'COMM 204' => ['Photojournalism', 'Lab'], 'COMM 206' => ['Media Ethics and Law', 'Lecture'], 'COMM 208' => ['Speech Communication', 'Lecture'],
    'COMM 302' => ['Broadcast Journalism', 'Lab'], 'COMM 304' => ['Development Communication', 'Lecture'], 'COMM 306' => ['Advertising Principles', 'Lecture'], 'COMM 308' => ['Communication Research 1', 'Lecture'], 'COMM 310' => ['Film Analysis', 'Lecture'],
    'COMM 402' => ['Communication Thesis 2', 'Lecture'], 'COMM 404' => ['Communication Internship', 'Practicum'], 'COMM 406' => ['Media Management', 'Lecture'],
    'PSY 102' => ['Introduction to Psychology', 'Lecture'], 'PSY 104' => ['Psychological Statistics', 'Lecture'],
    'PSY 202' => ['Developmental Psychology', 'Lecture'], 'PSY 204' => ['Theories of Personality', 'Lecture'], 'PSY 206' => ['Experimental Psychology', 'Lab'], 'PSY 208' => ['Cognitive Psychology', 'Lecture'],
    'PSY 302' => ['Abnormal Psychology', 'Lecture'], 'PSY 304' => ['Psychological Assessment', 'Lab'], 'PSY 306' => ['Industrial-Organizational Psychology', 'Lecture'], 'PSY 308' => ['Research in Psychology 1', 'Lecture'], 'PSY 310' => ['Social Psychology', 'Lecture'],
    'PSY 402' => ['Research in Psychology 2', 'Lecture'], 'PSY 404' => ['Practicum in Psychology', 'Practicum'], 'PSY 406' => ['Counseling Psychology', 'Lecture'],
    'POLS 102' => ['Introduction to Political Science', 'Lecture'], 'POLS 104' => ['Philippine Government and Constitution', 'Lecture'],
    'POLS 202' => ['Political Theory', 'Lecture'], 'POLS 204' => ['Public Administration', 'Lecture'], 'POLS 206' => ['Comparative Politics', 'Lecture'], 'POLS 208' => ['Politics and Governance in Southeast Asia', 'Lecture'],
    'POLS 302' => ['International Relations', 'Lecture'], 'POLS 304' => ['Philippine Foreign Policy', 'Lecture'], 'POLS 306' => ['Legislative Process', 'Lecture'], 'POLS 308' => ['Research Methods in Political Science', 'Lecture'], 'POLS 310' => ['Local Government Administration', 'Lecture'],
    'POLS 402' => ['Political Science Thesis 2', 'Lecture'], 'POLS 404' => ['Legislative Internship', 'Practicum'], 'POLS 406' => ['Human Rights and Governance', 'Lecture'],
    // Education
    'EDU 102' => ['The Child and Adolescent Learner', 'Lecture'], 'EDU 104' => ['The Teaching Profession', 'Lecture'], 'ENGL 102' => ['Introduction to Linguistics', 'Lecture'], 'EED 102' => ['Teaching Music in the Elementary Grades', 'Lecture'],
    'EDU 202' => ['Facilitating Learner-Centered Teaching', 'Lecture'], 'EDU 215' => ['Learning Assessment', 'Lecture'], 'EDU 206' => ['Technology for Teaching and Learning 1', 'Lab'],
    'ENGL 202' => ['Survey of Philippine Literature in English', 'Lecture'], 'EED 202' => ['Teaching Science in the Elementary Grades', 'Lab'],
    'EDU 302' => ['The Teacher and the School Community', 'Lecture'], 'EDU 304' => ['Curriculum Development', 'Lecture'], 'EDU 306' => ['Field Study 2', 'Lecture'],
    'ENGL 302' => ['Teaching and Assessment of Literature Studies', 'Lecture'], 'ENGL 304' => ['Language Research', 'Lecture'],
    'EED 302' => ['Teaching Math in the Intermediate Grades', 'Lecture'], 'EED 304' => ['Content and Pedagogy for the Mother Tongue', 'Lecture'],
    'EDU 402' => ['Teaching Internship', 'Practicum'], 'EDU 404' => ['Teacher Education Review', 'Lecture'],
    // Health Sciences
    'NCM 102' => ['Health Education', 'Lecture'], 'NCM 104' => ['Anatomy and Physiology', 'Lab'], 'CHEM 102' => ['Biochemistry', 'Lab'],
    'NCM 202' => ['Health Assessment', 'Lab'], 'NCM 204' => ['Pharmacology', 'Lecture'], 'NCM 206' => ['Nutrition and Diet Therapy', 'Lecture'], 'NCM 208' => ['Community Health Nursing 1', 'Lecture'],
    'NCM 302' => ['Care of Mother and Child at Risk', 'Lab'], 'NCM 304' => ['Care of Clients with Problems in Oxygenation', 'Lab'], 'NCM 306' => ['Nursing Research 1', 'Lecture'], 'NCM 308' => ['Nursing Informatics', 'Lab'], 'NCM 310' => ['Related Learning Experience', 'Practicum'],
    'NCM 402' => ['Nursing Research 2', 'Lecture'], 'NCM 404' => ['Intensive Nursing Practicum', 'Practicum'], 'NCM 406' => ['Nursing Leadership and Management', 'Lecture'],
    'MT 102' => ['Introduction to Medical Technology', 'Lecture'], 'MT 104' => ['Human Anatomy and Physiology with Pathophysiology', 'Lab'],
    'MT 202' => ['Clinical Chemistry 1', 'Lab'], 'MT 204' => ['Hematology 1', 'Lab'], 'MT 206' => ['Clinical Bacteriology', 'Lab'], 'MT 208' => ['Community and Public Health for MT', 'Lecture'],
    'MT 302' => ['Clinical Chemistry 2', 'Lab'], 'MT 304' => ['Immunohematology and Blood Banking', 'Lab'], 'MT 306' => ['Histopathologic Techniques', 'Lab'], 'MT 308' => ['Research Paper 1', 'Lecture'], 'MT 310' => ['Laboratory Management', 'Lecture'],
    'MT 402' => ['Clinical Internship', 'Practicum'], 'MT 404' => ['Seminars in Medical Technology', 'Lecture'],
];

// Major courses per program and year level (2nd semester)
const SEED_CURRICULUM = [
    'BSCS'      => [1 => ['CS 102', 'CS 104', 'CS 106'], 2 => ['CS 202', 'CS 204', 'CS 206', 'CS 208'], 3 => ['CS 311', 'CS 312', 'CS 314', 'CS 316', 'CS 318'], 4 => ['CS 402', 'CS 404', 'CS 406', 'CS 408']],
    'BSIT'      => [1 => ['IT 102', 'IT 104', 'IT 106'], 2 => ['IT 202', 'IT 204', 'IT 206', 'IT 208'], 3 => ['IT 302', 'IT 304', 'IT 306', 'IT 308', 'IT 310'], 4 => ['IT 402', 'IT 404', 'IT 406']],
    'BSCE'      => [1 => ['MATH 102', 'PHYS 102', 'ENG 102'], 2 => ['ENG 204', 'MATH 204', 'CE 202', 'CE 204'], 3 => ['CE 302', 'CE 304', 'CE 306', 'CE 308', 'ENG 302'], 4 => ['CE 402', 'CE 404', 'CE 406', 'CE 408']],
    'BSCpE'     => [1 => ['MATH 102', 'PHYS 102', 'CPE 102'], 2 => ['ENG 204', 'MATH 204', 'CPE 202', 'CPE 204'], 3 => ['CPE 302', 'CPE 304', 'CPE 306', 'CPE 308', 'ENG 302'], 4 => ['CPE 402', 'CPE 404', 'CPE 406', 'CPE 408']],
    'BSEE'      => [1 => ['MATH 102', 'PHYS 102', 'EE 102'], 2 => ['ENG 204', 'MATH 204', 'EE 202', 'EE 204'], 3 => ['EE 302', 'EE 304', 'EE 306', 'EE 308', 'ENG 302'], 4 => ['EE 402', 'EE 404', 'EE 406', 'EE 408']],
    'BSME'      => [1 => ['MATH 102', 'PHYS 102', 'ME 102'], 2 => ['ENG 204', 'MATH 204', 'ME 202', 'ME 204'], 3 => ['ME 302', 'ME 304', 'ME 306', 'ME 308', 'ENG 302'], 4 => ['ME 402', 'ME 404', 'ME 406', 'ME 408']],
    'BSBA'      => [1 => ['BA 102', 'ECON 102', 'ACC 102'], 2 => ['BA 202', 'BA 204', 'BA 206', 'STAT 202'], 3 => ['BA 302', 'BA 304', 'BA 306', 'BA 308', 'BA 310'], 4 => ['BA 402', 'BA 404', 'BA 406']],
    'BSA'       => [1 => ['ACC 102', 'ECON 102', 'ACC 104'], 2 => ['ACC 202', 'ACC 204', 'ACC 206', 'BA 204'], 3 => ['ACC 302', 'ACC 304', 'ACC 306', 'ACC 308', 'ACC 310'], 4 => ['ACC 402', 'ACC 404', 'ACC 406', 'ACC 408']],
    'BSHM'      => [1 => ['HM 102', 'HM 104', 'HM 106'], 2 => ['HM 202', 'HM 204', 'HM 206', 'BA 102'], 3 => ['HM 302', 'HM 304', 'HM 306', 'HM 308', 'HM 310'], 4 => ['HM 402', 'HM 404']],
    'BA Comm'   => [1 => ['COMM 102', 'COMM 104', 'SOC 102'], 2 => ['COMM 202', 'COMM 204', 'COMM 206', 'COMM 208'], 3 => ['COMM 302', 'COMM 304', 'COMM 306', 'COMM 308', 'COMM 310'], 4 => ['COMM 402', 'COMM 404', 'COMM 406']],
    'BS Psych'  => [1 => ['PSY 102', 'PSY 104', 'SOC 102'], 2 => ['PSY 202', 'PSY 204', 'PSY 206', 'PSY 208'], 3 => ['PSY 302', 'PSY 304', 'PSY 306', 'PSY 308', 'PSY 310'], 4 => ['PSY 402', 'PSY 404', 'PSY 406']],
    'BA PolSci' => [1 => ['POLS 102', 'POLS 104', 'SOC 102'], 2 => ['POLS 202', 'POLS 204', 'POLS 206', 'POLS 208'], 3 => ['POLS 302', 'POLS 304', 'POLS 306', 'POLS 308', 'POLS 310'], 4 => ['POLS 402', 'POLS 404', 'POLS 406']],
    'BSEd'      => [1 => ['EDU 102', 'EDU 104', 'ENGL 102'], 2 => ['EDU 202', 'EDU 215', 'EDU 206', 'ENGL 202'], 3 => ['EDU 302', 'EDU 304', 'ENGL 302', 'ENGL 304', 'EDU 306'], 4 => ['EDU 402', 'EDU 404']],
    'BEEd'      => [1 => ['EDU 102', 'EDU 104', 'EED 102'], 2 => ['EDU 202', 'EDU 215', 'EDU 206', 'EED 202'], 3 => ['EDU 302', 'EDU 304', 'EED 302', 'EED 304', 'EDU 306'], 4 => ['EDU 402', 'EDU 404']],
    'BSN'       => [1 => ['NCM 102', 'NCM 104', 'CHEM 102'], 2 => ['NCM 202', 'NCM 204', 'NCM 206', 'NCM 208'], 3 => ['NCM 302', 'NCM 304', 'NCM 306', 'NCM 308', 'NCM 310'], 4 => ['NCM 402', 'NCM 404', 'NCM 406']],
    'BSMT'      => [1 => ['MT 102', 'CHEM 102', 'MT 104'], 2 => ['MT 202', 'MT 204', 'MT 206', 'MT 208'], 3 => ['MT 302', 'MT 304', 'MT 306', 'MT 308', 'MT 310'], 4 => ['MT 402', 'MT 404']],
];

const SEED_UNITS = ['Lecture' => 3, 'Lab' => 3, 'PE' => 2, 'NSTP' => 3, 'Practicum' => 6];

// [type, name pattern, numbers, capacity]
const SEED_ROOMS = [
    'Computing'       => [['Lecture', 'CCS %d', [201, 202, 203, 204, 205, 206, 301, 302], 45], ['Laboratory', 'Computer Lab %d', [1, 2, 3, 4, 5, 6, 7, 8], 40]],
    'Engineering'     => [['Lecture', 'ENG %d', [101, 102, 103, 104, 105, 106, 107, 108, 201, 202, 203, 204, 205, 206, 207, 208], 45],
                          ['Laboratory', '%s', ['Physics Lab', 'Drafting Room', 'Surveying Lab', 'Hydraulics Lab', 'Materials Testing Lab', 'CpE Lab 1', 'CpE Lab 2', 'Electrical Lab', 'Electronics Lab', 'Machine Shop'], 40]],
    'Business'        => [['Lecture', 'BUS %d', [101, 102, 103, 104, 105, 106, 201, 202, 203, 204, 205, 206], 45],
                          ['Laboratory', '%s', ['Hot Kitchen', 'Bar and Beverage Lab', 'Mock Hotel Suite', 'AIS Lab'], 40]],
    'Arts & Sciences' => [['Lecture', 'AS %d', [101, 102, 103, 104, 105, 106, 107, 108, 201, 202, 203, 204, 205, 206, 207, 208, 301, 302, 303, 304, 305, 306], 50],
                          ['Laboratory', '%s', ['Psychology Lab', 'Media Studio', 'Radio Booth'], 40],
                          ['Gym', '%s', ['Gym A', 'Gym B', 'Covered Court', 'Dance Studio'], 60]],
    'Education'       => [['Lecture', 'EDU %d', [101, 102, 103, 104, 105, 106, 201, 202, 203, 204], 45],
                          ['Laboratory', '%s', ['Science Lab', 'Educational Technology Lab'], 40]],
    'Health Sciences' => [['Lecture', 'HS %d', [101, 102, 103, 104, 105, 106, 201, 202], 50],
                          ['Laboratory', '%s', ['Nursing Skills Lab 1', 'Nursing Skills Lab 2', 'Nursing Skills Lab 3', 'Anatomy Lab', 'Chemistry Lab', 'Hematology Lab', 'Microbiology Lab', 'Histopathology Lab'], 40]],
];

const SEED_FIRST_MALE = [
    'Juan', 'Jose', 'Mark', 'John Paul', 'Christian', 'Joshua', 'Miguel', 'Rafael', 'Paolo', 'Carlo', 'Kevin', 'Jerome', 'Francis', 'Dominic',
    'Gabriel', 'Nathan', 'Aaron', 'Marco', 'Tristan', 'Enzo', 'Vincent', 'Adrian', 'Ramon', 'Luis', 'Joaquin', 'Angelo', 'Bryan', 'Cedric',
    'Daniel', 'Emmanuel', 'Gerald', 'Harold', 'Ivan', 'Jayson', 'Jericho', 'Kenneth', 'Lance', 'Lorenzo', 'Matthew', 'Neil', 'Oliver',
    'Patrick', 'Raphael', 'Renz', 'Ryan', 'Sean', 'Timothy', 'Vince', 'Warren', 'Xavier', 'Elijah', 'Gian', 'Kyle', 'Jhon Rey',
    'Mark Anthony', 'John Lloyd', 'Rodel', 'Arvin', 'Carl', 'Miko',
];
const SEED_FIRST_FEMALE = [
    'Maria', 'Angela', 'Andrea', 'Camille', 'Kyla', 'Danica', 'Bea', 'Patricia', 'Nicole', 'Trisha', 'Alyssa', 'Sophia', 'Hannah', 'Isabel',
    'Jasmine', 'Erika', 'Lea', 'Clarisse', 'Bianca', 'Reina', 'Janelle', 'Pia', 'Shaira', 'Angelica', 'Abigail', 'Bernadette', 'Charmaine',
    'Christine', 'Denise', 'Ella', 'Frances', 'Gwyneth', 'Hazel', 'Ivy', 'Jenny', 'Joanna', 'Kathleen', 'Kimberly', 'Lorraine', 'Mariel',
    'Michelle', 'Nathalie', 'Princess', 'Rachelle', 'Rica', 'Samantha', 'Sheena', 'Stephanie', 'Theresa', 'Vanessa', 'Ysabel', 'Angela Mae',
    'Mary Grace', 'Kristine', 'Jillian', 'Precious', 'Czarina', 'Aira', 'Louise', 'Margarette',
];
const SEED_SURNAMES = [
    'Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Tomas', 'Andrada', 'Castillo', 'Flores', 'Villanueva',
    'Ramos', 'Castro', 'Rivera', 'Aquino', 'Navarro', 'Salazar', 'Mercado', 'Aguilar', 'Domingo', 'Gonzales', 'Lopez', 'Dela Cruz', 'Pascual',
    'Soriano', 'Valdez', 'Manalo', 'Alvarez', 'Fernandez', 'De Leon', 'Lim', 'Tan', 'Gomez', 'Morales', 'Delos Santos', 'Panganiban', 'Ilagan',
    'Velasco', 'Robles', 'Sison', 'Santiago', 'Del Rosario', 'Francisco', 'Marquez', 'Hernandez', 'Perez', 'Rodriguez', 'Gutierrez', 'Jimenez',
    'Diaz', 'Romero', 'Javier', 'Dizon', 'Sarmiento', 'Manalastas', 'Galang', 'Pangilinan', 'Tolentino', 'Bernardo', 'Evangelista', 'Agustin',
    'Valencia', 'Mallari', 'Cabrera', 'Lacson', 'Samson', 'Concepcion', 'Natividad', 'Padilla', 'Cortez', 'Mariano', 'Esguerra', 'Rosales',
    'Medina', 'Salvador', 'Vergara', 'Abad', 'Abella', 'Alonzo', 'Ancheta', 'Angeles', 'Aragon', 'Asuncion', 'Bacani', 'Baltazar', 'Belmonte',
    'Buenaventura', 'Caballero', 'Calderon', 'Carpio', 'Cuevas', 'Dimaculangan', 'Enriquez', 'Espiritu', 'Estrada', 'Fajardo', 'Guevarra',
    'Ignacio', 'Lagman', 'Legaspi', 'Macaraeg', 'Magbanua', 'Manansala', 'Nepomuceno', 'Ong', 'Quiambao', 'Quizon', 'Roxas', 'Sy', 'Tiongson',
    'Umali', 'Yap', 'Zamora', 'Co', 'Chua', 'Go', 'Bello', 'Tuazon', 'Pineda', 'Cordero', 'Lualhati',
];
const SEED_FACULTY_FIRST = [
    'Roberto', 'Marites', 'Antonio', 'Cecilia', 'Fernando', 'Lourdes', 'Eduardo', 'Teresa', 'Ricardo', 'Gloria', 'Manuel', 'Rosario', 'Victor',
    'Carmela', 'Ernesto', 'Imelda', 'Alfredo', 'Corazon', 'Danilo', 'Leticia', 'Rogelio', 'Maricel', 'Benjamin', 'Aurora', 'Jaime', 'Norma',
    'Arturo', 'Josefina', 'Oscar', 'Melinda', 'Noel', 'Estela', 'Rolando', 'Divina', 'Wilfredo', 'Remedios', 'Jonathan', 'Michelle', 'Ramil',
    'Marivic', 'Dennis', 'Rowena', 'Edgardo', 'Analyn', 'Reynaldo', 'Jocelyn', 'Ferdinand', 'Liza', 'Gregorio', 'Cristina',
];
// Kept from the original dashboard design
const SEED_NAMED_FACULTY = [
    'Computing'   => ['Dr.', 'Elena', 'Morales', 'Professor'],
    'Engineering' => ['Prof.', 'Adrian', 'Reyes', 'Associate Professor'],
    'Business'    => ['Dr.', 'Sofia', 'Lim', 'Associate Professor'],
    'Education'   => ['Prof.', 'Julia', 'Torres', 'Assistant Professor'],
];

/* ------------------------------------------------------------------ */
/* Random helpers                                                       */
/* ------------------------------------------------------------------ */

function rng(int $seed): Random\Randomizer
{
    return new Random\Randomizer(new Random\Engine\Mt19937($seed));
}

/** PHP 8.2-compatible random float in the half-open interval [min, max). */
function seed_float(Random\Randomizer $r, float $min, float $max): float
{
    return $min + ($max - $min) * ($r->getInt(0, 2147483646) / 2147483647);
}
function pick(Random\Randomizer $r, array $items): mixed
{
    $items = array_values($items);
    return $items[$r->getInt(0, count($items) - 1)];
}

/** Picks a key from [key => weight]. */
function weighted(Random\Randomizer $r, array $weights): string|int
{
    $x = seed_float($r, 0, array_sum($weights));
    foreach ($weights as $key => $w) {
        $x -= $w;
        if ($x <= 0) {
            return $key;
        }
    }
    return array_key_last($weights);
}

function chance(Random\Randomizer $r, float $p): bool
{
    return seed_float($r, 0, 1) < $p;
}

function gauss(Random\Randomizer $r, float $mean = 0, float $sd = 1): float
{
    $u = max(seed_float($r, 0, 1), 1e-9);
    return $mean + $sd * sqrt(-2 * log($u)) * cos(2 * M_PI * seed_float($r, 0, 1));
}

function clamp(float $v, float $lo, float $hi): float
{
    return max($lo, min($hi, $v));
}

/** Random moment between two timestamps; office hours only when $officeHours. */
function seed_time_between(Random\Randomizer $r, int $from, int $to, bool $officeHours = true): int
{
    if ($to <= $from) {
        return $from;
    }
    for ($i = 0; $i < 20; $i++) {
        $ts = $r->getInt($from, $to);
        $h = (int) date('G', $ts);
        if (!$officeHours || ($h >= 8 && $h < 18 && date('N', $ts) < 7)) {
            return $ts;
        }
    }
    return $r->getInt($from, $to);
}

function seed_slug(string $s): string
{
    return strtolower(preg_replace('/[^a-z]/i', '', $s));
}

function seed_insert(PDO $pdo, string $table, array $columns, array $rows, int $batch = 800): void
{
    $cols = implode(', ', array_map(fn($c) => "`$c`", $columns));
    $one = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
    foreach (array_chunk($rows, $batch) as $chunk) {
        $sql = "INSERT INTO `$table` ($cols) VALUES " . implode(', ', array_fill(0, count($chunk), $one));
        $pdo->prepare($sql)->execute(array_merge(...array_map('array_values', $chunk)));
    }
}

/* ------------------------------------------------------------------ */
/* Schema                                                               */
/* ------------------------------------------------------------------ */

const SEED_TABLES = [
    'applicant_requirements', 'applicants', 'faculty_rating_history', 'evaluation_responses', 'risk_snapshots', 'interventions',
    'enrollments', 'meetings', 'sections', 'students', 'curriculum', 'courses', 'faculty', 'rooms', 'programs', 'colleges', 'terms', 'meta',
];

function seed_schema(PDO $pdo, bool $fresh): void
{
    if ($fresh) {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (SEED_TABLES as $t) {
            $pdo->exec("DROP TABLE IF EXISTS `$t`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
    $sql = (string) file_get_contents(__DIR__ . '/../database/schema.sql');
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $pdo->exec($statement);
    }
}

/* ------------------------------------------------------------------ */
/* Seeder                                                               */
/* ------------------------------------------------------------------ */

function seed_database(PDO $pdo, ?callable $log = null): array
{
    $log ??= fn(string $m) => null;
    $r = rng(SEED_RANDOM);
    $asOf = strtotime(SEED_AS_OF);
    $stats = [];

    seed_schema($pdo, true);
    $pdo->beginTransaction();

    // ---- Terms, colleges, programs, rooms, courses ---------------------------
    $termIds = [];
    foreach (SEED_TERMS as $i => $t) {
        $termIds[$t[0]] = $i + 1;
    }
    seed_insert($pdo, 'terms', ['id', 'name', 'starts_on', 'ends_on', 'grade_deadline', 'is_current'],
        array_map(fn($t, $i) => [$i + 1, ...$t], SEED_TERMS, array_keys(SEED_TERMS)));
    $currentTerm = 2;
    $previousTerm = 1;

    $collegeId = [];
    $rows = [];
    foreach (array_keys(SEED_COLLEGES) as $i => $name) {
        $collegeId[$name] = $i + 1;
        [$code, $full, $building] = SEED_COLLEGES[$name];
        $rows[] = [$i + 1, $code, $full, $name, $building];
    }
    seed_insert($pdo, 'colleges', ['id', 'code', 'name', 'short_name', 'building'], $rows);

    $programId = [];
    $rows = [];
    foreach (array_keys(SEED_PROGRAMS) as $i => $code) {
        $programId[$code] = $i + 1;
        $rows[] = [$i + 1, $collegeId[SEED_PROGRAMS[$code][0]], $code, SEED_PROGRAMS[$code][1]];
    }
    seed_insert($pdo, 'programs', ['id', 'college_id', 'code', 'name'], $rows);

    $rooms = [];      // id => [college, type, name, capacity]
    $roomsBy = [];    // type => college => [ids]
    $rows = [];
    $id = 0;
    foreach (SEED_ROOMS as $college => $groups) {
        foreach ($groups as [$type, $pattern, $numbers, $cap]) {
            foreach ($numbers as $n) {
                $id++;
                $name = sprintf($pattern, $n);
                $rooms[$id] = [$college, $type, $name, $cap];
                $roomsBy[$type][$college][] = $id;
                $rows[] = [$id, $collegeId[$college], $name, $type, $cap];
            }
        }
    }
    seed_insert($pdo, 'rooms', ['id', 'college_id', 'name', 'type', 'capacity'], $rows);
    $stats['rooms'] = count($rooms);

    $courseId = [];
    $courseInfo = [];
    $rows = [];
    $id = 0;
    foreach (SEED_COURSES as $code => [$title, $kind]) {
        $id++;
        $prefix = strtok($code, ' ');
        $college = SEED_PREFIX_COLLEGE[$prefix];
        $courseId[$code] = $id;
        $courseInfo[$code] = ['college' => $college, 'kind' => $kind, 'units' => SEED_UNITS[$kind]];
        $rows[] = [$id, $collegeId[$college], $code, $title, SEED_UNITS[$kind], $kind];
    }
    seed_insert($pdo, 'courses', ['id', 'college_id', 'code', 'title', 'units', 'kind'], $rows);

    $plan = []; // program => year => [course codes]
    $rows = [];
    foreach (SEED_CURRICULUM as $prog => $years) {
        foreach ($years as $year => $codes) {
            $plan[$prog][$year] = array_merge(SEED_GE[$year], $codes);
            foreach ($plan[$prog][$year] as $code) {
                $rows[] = [$programId[$prog], $courseId[$code], $year];
            }
        }
    }
    seed_insert($pdo, 'curriculum', ['program_id', 'course_id', 'year_level'], $rows);

    // ---- Students and blocks --------------------------------------------------
    $students = [];   // index => row
    $blocks = [];     // key "PROG|year|A" => [student indexes]
    $usedEmails = [];
    foreach (SEED_PROGRAMS as $prog => [$college, , $total, , $women]) {
        $remaining = $total;
        foreach (SEED_YEAR_SHARE as $year => $share) {
            $n = $year === 4 ? $remaining : (int) round($total * $share);
            $remaining -= $n;
            $blockCount = (int) ceil($n / SEED_BLOCK_SIZE);
            for ($i = 0; $i < $n; $i++) {
                $sex = chance($r, $women) ? 'F' : 'M';
                $first = pick($r, $sex === 'F' ? SEED_FIRST_FEMALE : SEED_FIRST_MALE);
                $last = pick($r, SEED_SURNAMES);
                do {
                    $middle = pick($r, SEED_SURNAMES);
                } while ($middle === $last);
                $status = weighted($r, ['Regular' => 88, 'Irregular' => 8, 'On leave' => 4]);
                $block = chr(65 + $i % $blockCount);
                $email = seed_slug($first) . '.' . seed_slug($last);
                $k = 1;
                while (isset($usedEmails[$email . ($k > 1 ? $k : '')])) {
                    $k++;
                }
                $email .= $k > 1 ? $k : '';
                $usedEmails[$email] = true;
                $entry = 2025 - $year;
                $students[] = [
                    'program' => $prog, 'college' => $college, 'year' => $year, 'block' => $block, 'sex' => $sex,
                    'first' => $first, 'middle' => $middle, 'last' => $last, 'status' => $status,
                    'back' => $status === 'Irregular' ? (int) weighted($r, [1 => 38, 2 => 36, 3 => 14, 4 => 8, 5 => 4]) : 0,
                    'entry' => $entry,
                    'birthdate' => date('Y-m-d', strtotime(($entry - 18) . '-01-01') + $r->getInt(-200, 365) * 86400),
                    'email' => $email . '@student.veritas.edu.ph',
                    'ability' => gauss($r),
                    'enrolled_at' => $status === 'On leave' ? null
                        : seed_time_between($r, strtotime('2025-01-06 08:00'), strtotime(chance($r, 0.94) ? '2025-01-17 17:00' : '2025-01-24 17:00')),
                ];
                $blocks["$prog|$year|$block"][] = count($students) - 1;
            }
        }
    }
    // Student numbers: entry year + sequence, issued in random order within each batch
    $byEntry = [];
    foreach ($students as $i => $s) {
        $byEntry[$s['entry']][] = $i;
    }
    foreach ($byEntry as $entry => $list) {
        $seq = 10000;
        foreach ($r->shuffleArray($list) as $i) {
            $seq += $r->getInt(1, 3);
            $students[$i]['no'] = $entry . '-' . $seq;
        }
    }
    $stats['students'] = count($students);
    $log('Students: ' . count($students) . ' in ' . count($blocks) . ' blocks');

    // ---- Sections (one per block x course) ------------------------------------
    $sections = [];
    foreach ($blocks as $key => $members) {
        [$prog, $year, $block] = explode('|', $key);
        foreach ($plan[$prog][(int) $year] as $code) {
            $sections[] = ['code' => $code, 'prog' => $prog, 'year' => (int) $year, 'block' => $block, 'blockKey' => $key,
                'college' => $courseInfo[$code]['college'], 'kind' => $courseInfo[$code]['kind'], 'units' => $courseInfo[$code]['units'],
                'size' => count($members), 'faculty' => null, 'mode' => 'Onsite', 'platform' => null, 'meetings' => []];
        }
    }

    // ---- Faculty, sized to each college's teaching load ----------------------
    $unitsByCollege = [];
    foreach ($sections as $s) {
        $unitsByCollege[$s['college']] = ($unitsByCollege[$s['college']] ?? 0) + min(3, $s['units']);
    }
    $faculty = [];
    $usedNames = [];
    $fid = 0;
    foreach ($unitsByCollege as $college => $units) {
        $fullTime = (int) ceil($units * 0.78 / 21);
        $partTime = (int) ceil($units * 0.22 / 10);
        for ($i = 0; $i < $fullTime + $partTime; $i++) {
            $fid++;
            $isFt = $i < $fullTime;
            if ($i === 0 && isset(SEED_NAMED_FACULTY[$college])) {
                [$title, $first, $last, $rank] = SEED_NAMED_FACULTY[$college];
            } else {
                do {
                    $first = pick($r, chance($r, 0.6) ? SEED_FACULTY_FIRST : array_merge(SEED_FIRST_MALE, SEED_FIRST_FEMALE));
                    $last = pick($r, SEED_SURNAMES);
                } while (isset($usedNames["$first $last"]));
                $rank = $isFt ? weighted($r, ['Instructor' => 28, 'Assistant Professor' => 34, 'Associate Professor' => 24, 'Professor' => 14]) : 'Lecturer';
                $title = in_array($rank, ['Associate Professor', 'Professor'], true) && chance($r, 0.6) ? 'Dr.'
                    : ($college === 'Engineering' && chance($r, 0.6) ? 'Engr.' : 'Prof.');
            }
            $usedNames["$first $last"] = true;
            $faculty[$fid] = [
                'college' => $college, 'title' => $title, 'first' => $first, 'last' => $last, 'rank' => $rank,
                'employment' => $isFt ? 'Full-time' : 'Part-time', 'max' => $isFt ? 24 : 12, 'load' => 0,
                'email' => strtolower(substr(seed_slug($first), 0, 1) . seed_slug($last)) . $fid . '@veritas.edu.ph',
                'quality' => clamp(gauss($r, 4.35, 0.28), 3.1, 4.92),   // how students rate them
                'strictness' => gauss($r, 0, 0.12),                     // grading tendency
                'promptness' => seed_float($r, 0, 1),                     // how early they submit grades
            ];
        }
    }
    $facultyBy = [];
    foreach ($faculty as $id => $f) {
        $facultyBy[$f['college']][] = $id;
    }
    $stats['faculty'] = count($faculty);

    // ---- Timetable ------------------------------------------------------------
    $mwf = fn(int $s, int $len) => [['Mon', $s, $s + $len], ['Wed', $s, $s + $len], ['Fri', $s, $s + $len]];
    $tth = fn(int $s, int $len) => [['Tue', $s, $s + $len], ['Thu', $s, $s + $len]];
    $days6 = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $opts = ['lecture' => [], 'lablec' => [], 'lab' => [], 'pe' => [], 'nstp' => []];
    foreach (range(420, 1080, 60) as $s) {
        $opts['lecture'][] = $mwf($s, 60);
        $opts['lablec'][] = [['Mon', $s, $s + 60], ['Wed', $s, $s + 60]];
    }
    foreach ([450, 540, 630, 780, 870, 960, 1050] as $s) {
        $opts['lecture'][] = $tth($s, 90);
        $opts['lablec'][] = $tth($s, 60);
    }
    foreach ($days6 as $d) {
        foreach ([420, 600, 780, 960] as $s) $opts['lab'][] = [[$d, $s, $s + 180]];
        foreach ([420, 540, 780, 900, 1020] as $s) $opts['pe'][] = [[$d, $s, $s + 120]];
    }
    foreach ([420, 600, 780] as $s) $opts['nstp'][] = [['Sat', $s, $s + 180]];

    $parts = [
        'Lecture'   => [['lecture', 'Lecture']],
        'Lab'       => [['lablec', 'Lecture'], ['lab', 'Laboratory']],
        'PE'        => [['pe', 'Gym']],
        'NSTP'      => [['nstp', 'Lecture']],
        'Practicum' => [],
    ];

    $busy = [];
    $isFree = function (string $key, array $meetings) use (&$busy): bool {
        foreach ($meetings as [$d, $s, $e]) {
            foreach ($busy[$key][$d] ?? [] as [$bs, $be]) {
                if ($s < $be && $bs < $e) return false;
            }
        }
        return true;
    };
    $book = function (string $key, array $meetings) use (&$busy): void {
        foreach ($meetings as [$d, $s, $e]) $busy[$key][$d][] = [$s, $e];
    };
    $overlaps = function (array $a, array $b): bool {
        foreach ($a as [$d1, $s1, $e1]) foreach ($b as [$d2, $s2, $e2]) if ($d1 === $d2 && $s1 < $e2 && $s2 < $e1) return true;
        return false;
    };
    // Prefer daytime weekday slots
    $ordered = function (array $options, bool $saturdayOk) use ($r): array {
        $scored = [];
        foreach ($options as $o) {
            $start = $o[0][1];
            $pen = ($start >= 1020 ? 0.7 : 0) + ($start <= 420 ? 0.3 : 0) + (!$saturdayOk && $o[0][0] === 'Sat' ? 0.8 : 0);
            $scored[] = [seed_float($r, 0, 1) + $pen, $o];
        }
        usort($scored, fn($a, $b) => $a[0] <=> $b[0]);
        return array_column($scored, 1);
    };

    $unplaced = 0;
    $order = $r->shuffleArray(array_keys($sections));
    // Labs and PE first: they compete for fewer rooms
    usort($order, fn($a, $b) => (in_array($sections[$b]['kind'], ['Lab', 'PE', 'NSTP'], true) <=> in_array($sections[$a]['kind'], ['Lab', 'PE', 'NSTP'], true)));

    foreach ($order as $si) {
        $sec = &$sections[$si];
        $info = $courseInfo[$sec['code']];
        if ($sec['kind'] === 'Practicum') {
            $sec['mode'] = 'Off-campus';
        } elseif ($sec['kind'] === 'Lecture' && chance($r, 0.07)) {
            $sec['mode'] = 'Online';
            $sec['platform'] = chance($r, 0.7) ? 'Google Meet' : 'MS Teams';
        }

        $candidates = $facultyBy[$sec['college']];
        usort($candidates, fn($a, $b) => ($faculty[$a]['load'] + seed_float($r, 0, 4)) <=> ($faculty[$b]['load'] + seed_float($r, 0, 4)));
        $withRoom = array_filter($candidates, fn($f) => $faculty[$f]['load'] + min(3, $sec['units']) <= $faculty[$f]['max']);
        $tryList = array_merge(array_values($withRoom), $candidates);

        $placed = null;
        foreach (array_slice($tryList, 0, 14) as $f) {
            $chosen = [];
            $ok = true;
            foreach ($parts[$sec['kind']] as [$optKey, $roomType]) {
                $found = null;
                foreach ($ordered($opts[$optKey], $sec['kind'] === 'NSTP') as $opt) {
                    $pending = array_merge(...array_map(fn($c) => $c[0], $chosen ?: [[[]]]));
                    if ($pending && $overlaps($pending, $opt)) continue;
                    if (!$isFree('b' . $sec['blockKey'], $opt) || !$isFree('f' . $f, $opt)) continue;
                    if ($sec['mode'] === 'Online') {
                        $found = [$opt, null];
                        break;
                    }
                    $pool = $roomsBy[$roomType][$sec['college']] ?? [];
                    if ($roomType === 'Gym') $pool = $roomsBy['Gym']['Arts & Sciences'];
                    $others = [];
                    foreach ($roomsBy[$roomType] as $c => $ids) if ($c !== $sec['college']) $others = array_merge($others, $ids);
                    foreach (array_merge($r->shuffleArray($pool), $r->shuffleArray($others)) as $room) {
                        if ($rooms[$room][3] >= $sec['size'] && $isFree('r' . $room, $opt)) {
                            $found = [$opt, $room];
                            break 2;
                        }
                    }
                }
                if (!$found) {
                    $ok = false;
                    break;
                }
                $chosen[] = $found;
            }
            if ($ok) {
                $placed = [$f, $chosen];
                break;
            }
        }

        if (!$placed) {
            $unplaced++;
            $placed = [$tryList[0], []];
        }
        [$f, $chosen] = $placed;
        $sec['faculty'] = $f;
        $faculty[$f]['load'] += min(3, $sec['units']);
        foreach ($chosen as $p => [$opt, $room]) {
            $book('b' . $sec['blockKey'], $opt);
            $book('f' . $f, $opt);
            if ($room) $book('r' . $room, $opt);
            foreach ($opt as [$d, $s, $e]) {
                $sec['meetings'][] = [$d, $s, $e, $room, $p];
            }
        }
        unset($sec);
    }
    $stats['unscheduled_sections'] = $unplaced;

    // Two double-booked rooms, as happens with manual room changes
    $made = 0;
    foreach ($sections as $ai => $a) {
        if ($made >= 2) break;
        if ($a['mode'] !== 'Onsite' || $a['kind'] !== 'Lecture' || !$a['meetings'] || $r->getInt(0, 40) !== 0) continue;
        foreach ($sections as $bi => $b) {
            if ($bi <= $ai || $b['mode'] !== 'Onsite' || $b['kind'] !== 'Lecture' || $b['college'] !== $a['college'] || !$b['meetings']) continue;
            if ($b['meetings'][0][0] === $a['meetings'][0][0] && $b['meetings'][0][1] === $a['meetings'][0][1] && $b['meetings'][0][3] !== $a['meetings'][0][3]) {
                foreach ($sections[$bi]['meetings'] as &$m) $m[3] = $a['meetings'][0][3];
                unset($m);
                $made++;
                break;
            }
        }
    }

    // ---- Insert faculty, students, sections, meetings --------------------------
    seed_insert($pdo, 'faculty', ['id', 'college_id', 'title', 'first_name', 'last_name', 'rank', 'employment', 'email'],
        array_map(fn($id, $f) => [$id, $collegeId[$f['college']], $f['title'], $f['first'], $f['last'], $f['rank'], $f['employment'], $f['email']], array_keys($faculty), $faculty));

    // Block advisers: a full-time instructor of the program's college
    $advisers = [];
    foreach (array_keys($blocks) as $key) {
        $college = SEED_PROGRAMS[explode('|', $key)[0]][0];
        $ft = array_values(array_filter($facultyBy[$college], fn($f) => $faculty[$f]['employment'] === 'Full-time'));
        $advisers[$key] = pick($r, $ft);
    }

    $rows = [];
    foreach ($students as $i => $s) {
        $rows[] = [$i + 1, $s['no'], $s['first'], $s['middle'], $s['last'], $s['sex'], $s['birthdate'], $s['email'], $programId[$s['program']],
            $s['year'], $s['block'], $s['status'], $s['back'], $advisers[$s['program'] . '|' . $s['year'] . '|' . $s['block']],
            $s['enrolled_at'] ? date('Y-m-d H:i:s', $s['enrolled_at']) : null];
    }
    seed_insert($pdo, 'students', ['id', 'student_no', 'first_name', 'middle_name', 'last_name', 'sex', 'birthdate', 'email', 'program_id',
        'year_level', 'block', 'status', 'back_subjects', 'adviser_id', 'enrolled_at'], $rows);

    // ---- Enrollments and grades -----------------------------------------------
    $gradeSteps = ['1.00', '1.25', '1.50', '1.75', '2.00', '2.25', '2.50', '2.75', '3.00'];
    $toGrade = function (float $x) use ($gradeSteps): string {
        if ($x > 3.25) return '5.00';
        return $gradeSteps[(int) clamp(round(($x - 1) * 4), 0, 8)];
    };
    $difficulty = fn(array $s) => match ($s['kind']) {
        'PE' => -0.45, 'NSTP' => -0.5, 'Practicum' => -0.3, 'Lab' => 0.08,
        default => str_starts_with($s['code'], 'GE ') ? -0.1 : ($s['year'] >= 3 ? 0.1 : 0.0),
    };

    $deadline = strtotime(SEED_TERMS[1][3]);
    $termEnd = strtotime(SEED_TERMS[1][2] . ' 18:00');
    $enrollRows = [];
    $sectionRows = [];
    $meetingRows = [];
    $studentGrades = []; // student index => [grades used for risk]
    foreach ($sections as $si => $sec) {
        $sid = $si + 1;
        $f = $faculty[$sec['faculty']];
        // Final-grade encoding progress depends on the instructor
        $pSub = clamp(0.42 + 0.52 * $f['promptness'], 0, 0.97);
        $state = chance($r, $pSub) ? 'Submitted' : (chance($r, 0.7) ? 'In progress' : 'Not started');
        $encodedShare = $state === 'Submitted' ? 1 : ($state === 'In progress' ? seed_float($r, 0.15, 0.9) : 0);
        $submittedAt = $state === 'Submitted' ? seed_time_between($r, $termEnd, $asOf - 1800) : null;
        $updatedAt = match ($state) {
            'Submitted'   => $submittedAt,
            'In progress' => seed_time_between($r, $termEnd + 2 * 86400, $asOf - 600),
            default       => null,
        };
        $sectionRows[] = [$sid, $currentTerm, $courseId[$sec['code']], $programId[$sec['prog']], $sec['year'], $sec['block'], $sec['faculty'],
            $sec['mode'], $sec['platform'], $updatedAt ? date('Y-m-d H:i:s', $updatedAt) : null, $submittedAt ? date('Y-m-d H:i:s', $submittedAt) : null];
        foreach ($sec['meetings'] as [$d, $s, $e, $room]) {
            $meetingRows[] = [$sid, $d, sprintf('%02d:%02d:00', intdiv($s, 60), $s % 60), sprintf('%02d:%02d:00', intdiv($e, 60), $e % 60), $room];
        }

        foreach ($blocks[$sec['blockKey']] as $stu) {
            $st = $students[$stu];
            if ($st['status'] === 'On leave') continue;
            // Irregular students carry a reduced load from their block
            if ($st['status'] === 'Irregular' && $r->getInt(0, 9) < 3) continue;

            $inc = chance($r, 0.003 + ($st['ability'] < -1.3 ? 0.022 : 0));
            $drp = !$inc && chance($r, 0.003 + ($st['ability'] < -1.5 ? 0.01 : 0));
            $base = 2.02 + $difficulty($sec) + $f['strictness'] - 0.40 * $st['ability'] + gauss($r, 0, 0.22);
            $mid = $drp ? 'DRP' : $toGrade($base);
            $final = null;
            if (chance($r, $encodedShare)) {
                $final = $drp ? 'DRP' : ($inc ? 'INC' : $toGrade($base + gauss($r, 0, 0.18)));
            }
            $enrollRows[] = [$sid, $stu + 1, $mid, $final];
            $studentGrades[$stu][] = $final ?? $mid;
        }
    }
    seed_insert($pdo, 'sections', ['id', 'term_id', 'course_id', 'program_id', 'year_level', 'block', 'faculty_id', 'mode', 'platform', 'grades_updated_at', 'grades_submitted_at'], $sectionRows);
    seed_insert($pdo, 'meetings', ['section_id', 'day', 'start_time', 'end_time', 'room_id'], $meetingRows);
    seed_insert($pdo, 'enrollments', ['section_id', 'student_id', 'midterm_grade', 'final_grade'], $enrollRows, 1500);
    $stats['sections'] = count($sections);
    $stats['meetings'] = count($meetingRows);
    $stats['enrollments'] = count($enrollRows);
    $log('Sections: ' . count($sections) . ', meetings: ' . count($meetingRows) . ', enrollments: ' . count($enrollRows) . ", unscheduled: $unplaced");

    // ---- Interventions for students the grades flag as at risk ---------------
    $atRiskByCollege = array_fill_keys(array_keys(SEED_COLLEGES), 0);
    $rows = [];
    foreach ($students as $i => $s) {
        $grades = $studentGrades[$i] ?? [];
        $failing = count(array_keys($grades, '5.00', true));
        $incs = count(array_keys($grades, 'INC', true));
        $behind = $s['status'] === 'Irregular' && $s['back'] >= RISK_BACK_SUBJECTS;
        if (!$failing && !$incs && !$behind) continue;
        $atRiskByCollege[$s['college']]++;
        $urgent = $failing >= 2 || ($failing && $incs) || $s['back'] >= 5;
        $status = weighted($r, $urgent
            ? ['none' => 15, 'Contacted' => 35, 'Counseling scheduled' => 35, 'Improving' => 15]
            : ['none' => 35, 'Contacted' => 35, 'Counseling scheduled' => 15, 'Improving' => 15]);
        if ($status === 'none') continue;
        $notes = match ($status) {
            'Contacted'            => pick($r, ['Sent email; waiting for reply.', 'Talked after class about missing requirements.', 'Called guardian.', 'Messaged through LMS.']),
            'Counseling scheduled' => pick($r, ['Referred to Guidance Office.', 'Meeting with adviser set.', 'Academic counseling booked.']),
            default                => pick($r, ['Submitted missing work.', 'Attending tutorial sessions.', 'Removal exam scheduled.']),
        };
        $rows[] = [$i + 1, $status, date('Y-m-d H:i:s', seed_time_between($r, $asOf - 25 * 86400, $asOf - 3600)), $notes];
    }
    seed_insert($pdo, 'interventions', ['student_id', 'status', 'last_contact_at', 'notes'], $rows);
    $stats['at_risk'] = array_sum($atRiskByCollege);

    // Month-end history (earlier months had more students flagged)
    $rows = [];
    foreach (['2025-01-31' => 1.13, '2025-02-28' => 1.09, '2025-03-31' => 1.07, '2025-04-30' => 1.03] as $month => $factor) {
        foreach ($atRiskByCollege as $college => $n) {
            $rows[] = [$month, $collegeId[$college], (int) round($n * $factor * seed_float($r, 0.97, 1.03))];
        }
    }
    seed_insert($pdo, 'risk_snapshots', ['month', 'college_id', 'at_risk'], $rows);

    // ---- Faculty evaluations (anonymous responses per section) ---------------
    $offsets = ['teaching' => 0, 'organization' => -0.05, 'communication' => 0.03, 'fairness' => -0.1, 'punctuality' => 0.06];
    $rows = [];
    $perFaculty = [];
    $sizes = array_count_values(array_column($enrollRows, 0));
    $evalFrom = strtotime('2025-05-02 08:00');
    $evalTo = strtotime('2025-05-16 22:00');
    foreach ($sections as $si => $sec) {
        if ($sec['mode'] === 'Off-campus') continue;
        $size = $sizes[$si + 1] ?? 0;
        $n = (int) round($size * clamp(gauss($r, 0.82, 0.08), 0.45, 0.98));
        $q = $faculty[$sec['faculty']]['quality'];
        for ($k = 0; $k < $n; $k++) {
            $row = [$si + 1, date('Y-m-d H:i:s', seed_time_between($r, $evalFrom, $evalTo, false))];
            foreach ($offsets as $off) {
                $row[] = (int) clamp(round($q + $off + gauss($r, 0, 0.62)), 1, 5);
            }
            $rows[] = $row;
        }
        $perFaculty[$sec['faculty']] = ($perFaculty[$sec['faculty']] ?? 0) + $n;
    }
    seed_insert($pdo, 'evaluation_responses', ['section_id', 'submitted_at', 'teaching', 'organization', 'communication', 'fairness', 'punctuality'], $rows, 2000);
    $stats['evaluation_responses'] = count($rows);

    $rows = [];
    foreach ($faculty as $id => $f) {
        if (empty($perFaculty[$id])) continue;
        $rows[] = [$id, $previousTerm, round(clamp($f['quality'] + gauss($r, -0.04, 0.12), 2.5, 5), 2), (int) round($perFaculty[$id] * seed_float($r, 0.85, 1.1))];
    }
    seed_insert($pdo, 'faculty_rating_history', ['faculty_id', 'term_id', 'rating', 'responses'], $rows);

    // ---- Applicants for next academic year ------------------------------------
    $cutoff = ['Health Sciences' => 80, 'Engineering' => 78, 'Computing' => 76, 'Business' => 75, 'Education' => 74, 'Arts & Sciences' => 72];
    $monthWeights = ['2024-10' => 8, '2024-11' => 12, '2024-12' => 9, '2025-01' => 16, '2025-02' => 17, '2025-03' => 15, '2025-04' => 12, '2025-05' => 11];
    $programWeights = array_map(fn($p) => $p[3], SEED_PROGRAMS);
    $enrollmentOpens = strtotime('2025-05-05 08:00');
    $applicants = [];
    $total = 3240;
    for ($i = 0; $i < $total; $i++) {
        $month = weighted($r, $monthWeights);
        $from = strtotime("$month-01 07:00");
        $to = min($asOf - 1200, strtotime(date('Y-m-t 22:00', $from)));
        // Online applications arrive between 7 AM and 11 PM
        $submitted = seed_time_between($r, $from, $to, false);
        $hour = (int) date('G', $submitted);
        if ($hour < 7 || $hour >= 23) {
            $submitted = strtotime(date('Y-m-d', $submitted)) + $r->getInt(7 * 3600, 23 * 3600 - 1);
        }
        while ($submitted > $asOf - 1200) {
            $submitted -= 86400;
        }

        $prog = weighted($r, $programWeights);
        $college = SEED_PROGRAMS[$prog][0];
        $sex = chance($r, SEED_PROGRAMS[$prog][4]) ? 'F' : 'M';
        $type = chance($r, 0.1) ? 'Transferee' : 'Freshman';
        $prior = $type === 'Freshman' ? round(clamp(gauss($r, 88, 4.5), 75, 99), 1) : round(clamp(gauss($r, 2.1, 0.35), 1.2, 3), 2);
        $strand = $type === 'Freshman'
            ? weighted($r, in_array($college, ['Engineering', 'Computing', 'Health Sciences'], true)
                ? ['STEM' => 70, 'GAS' => 10, 'TVL' => 12, 'ABM' => 4, 'HUMSS' => 4]
                : ['STEM' => 20, 'ABM' => 30, 'HUMSS' => 30, 'GAS' => 15, 'TVL' => 5])
            : null;

        // Entrance exam: the first Saturday at least a week after applying; results 5 days later
        $exam = strtotime('next saturday', $submitted + 7 * 86400);
        $resultAt = $exam + 5 * 86400 + 10 * 3600;
        $tookExam = $resultAt <= $asOf && chance($r, 0.93);
        $score = null;
        $events = [$submitted];
        if ($tookExam) {
            $aptitude = $type === 'Freshman' ? ($prior - 88) / 4.5 : (2.1 - $prior) / 0.35;
            $score = (int) clamp(round(gauss($r, 81 + 4 * $aptitude, 7)), 40, 99);
            $events[] = $resultAt;
        }

        // Documents arrive over the weeks after applying
        $docs = [];
        $age = ($asOf - $submitted) / 86400;
        foreach (REQUIREMENTS as $k => $req) {
            $p = ($k < 2 ? 0.75 : 0.45) + 0.5 * min(1, $age / 75);
            $at = chance($r, min(0.99, $p)) ? seed_time_between($r, $submitted, min($asOf - 600, $submitted + (int) (min($age, 60) * 86400)), false) : null;
            $docs[$req] = $at;
            if ($at) $events[] = $at;
        }
        $complete = !in_array(null, $docs, true);

        if (!$tookExam) {
            $status = 'For review';
        } elseif ($score < $cutoff[$college]) {
            $status = 'Not qualified';
        } elseif (!$complete) {
            $status = 'Pending documents';
        } elseif ($resultAt < $asOf - 2 * 86400 && chance($r, 0.62)) {
            $status = 'Enrolled';
            $events[] = seed_time_between($r, max($enrollmentOpens, $resultAt, max($events)), $asOf - 600);
        } else {
            $status = 'Qualified';
        }

        $first = pick($r, $sex === 'F' ? SEED_FIRST_FEMALE : SEED_FIRST_MALE);
        $last = pick($r, SEED_SURNAMES);
        $applicants[] = [
            'submitted' => $submitted, 'first' => $first, 'middle' => pick($r, SEED_SURNAMES), 'last' => $last, 'sex' => $sex,
            'email' => seed_slug($first) . '.' . seed_slug($last) . $r->getInt(10, 99) . '@example.com',
            'program' => $programId[$prog], 'type' => $type, 'strand' => $strand, 'prior' => $prior,
            'exam' => $tookExam ? date('Y-m-d', $exam) : null, 'score' => $score, 'status' => $status,
            'updated' => min($asOf - 300, max($events)), 'docs' => $docs,
        ];
    }
    usort($applicants, fn($a, $b) => $a['submitted'] <=> $b['submitted']);
    $rows = [];
    $reqRows = [];
    foreach ($applicants as $i => $a) {
        $id = $i + 1;
        $rows[] = [$id, 'APP-2025-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT), $a['first'], $a['middle'], $a['last'], $a['sex'], $a['email'],
            $a['program'], $a['type'], $a['strand'], $a['prior'], $a['exam'], $a['score'], $a['status'],
            date('Y-m-d H:i:s', $a['submitted']), date('Y-m-d H:i:s', $a['updated'])];
        foreach ($a['docs'] as $req => $at) {
            $reqRows[] = [$id, $req, $at ? date('Y-m-d H:i:s', $at) : null];
        }
    }
    seed_insert($pdo, 'applicants', ['id', 'applicant_no', 'first_name', 'middle_name', 'last_name', 'sex', 'email', 'program_id', 'type',
        'shs_strand', 'prior_gwa', 'exam_date', 'exam_score', 'status', 'submitted_at', 'updated_at'], $rows);
    seed_insert($pdo, 'applicant_requirements', ['applicant_id', 'requirement', 'submitted_at'], $reqRows, 2000);
    $stats['applicants'] = count($applicants);

    // ---- Snapshot settings ----------------------------------------------------
    $enrolledNow = count(array_filter($students, fn($s) => $s['status'] !== 'On leave'));
    seed_insert($pdo, 'meta', ['k', 'v'], [
        ['as_of', SEED_AS_OF],
        ['intake_label', 'Academic year 2025–2026'],
        ['prev_intake_applications', (string) (int) round($total / 1.092)],
        ['prev_term_enrollment', (string) (int) round(count($students) / 1.064)],
        ['enrolled_active', (string) $enrolledNow],
        ['seeded_at', date('Y-m-d H:i:s')],
    ]);

    $pdo->commit();
    return $stats;
}
