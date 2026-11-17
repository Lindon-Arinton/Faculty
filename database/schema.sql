-- Veritas Academic Administration Portal: institutional records
-- MySQL 5.7+ / MariaDB 10.3+. Creating tables only; database/seed.php fills them.

CREATE TABLE IF NOT EXISTS meta (
    k VARCHAR(50)  NOT NULL PRIMARY KEY,
    v VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS terms (
    id             TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(40) NOT NULL UNIQUE,
    starts_on      DATE NOT NULL,
    ends_on        DATE NOT NULL,
    grade_deadline DATE NOT NULL,
    is_current     TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS colleges (
    id         TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code       VARCHAR(10)  NOT NULL UNIQUE,
    name       VARCHAR(100) NOT NULL,
    short_name VARCHAR(40)  NOT NULL UNIQUE,
    building   VARCHAR(80)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS programs (
    id         SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    college_id TINYINT UNSIGNED NOT NULL,
    code       VARCHAR(20)  NOT NULL UNIQUE,
    name       VARCHAR(120) NOT NULL,
    FOREIGN KEY (college_id) REFERENCES colleges (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rooms (
    id         SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    college_id TINYINT UNSIGNED NOT NULL,
    name       VARCHAR(40) NOT NULL UNIQUE,
    type       ENUM('Lecture', 'Laboratory', 'Gym') NOT NULL,
    capacity   SMALLINT UNSIGNED NOT NULL,
    FOREIGN KEY (college_id) REFERENCES colleges (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS faculty (
    id         SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    college_id TINYINT UNSIGNED NOT NULL,
    title      VARCHAR(10)  NOT NULL,
    first_name VARCHAR(60)  NOT NULL,
    last_name  VARCHAR(60)  NOT NULL,
    `rank`     VARCHAR(40)  NOT NULL,
    employment ENUM('Full-time', 'Part-time') NOT NULL,
    email      VARCHAR(120) NOT NULL UNIQUE,
    FOREIGN KEY (college_id) REFERENCES colleges (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS courses (
    id         SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    college_id TINYINT UNSIGNED NOT NULL,
    code       VARCHAR(20)  NOT NULL UNIQUE,
    title      VARCHAR(120) NOT NULL,
    units      TINYINT UNSIGNED NOT NULL,
    kind       ENUM('Lecture', 'Lab', 'PE', 'NSTP', 'Practicum') NOT NULL,
    FOREIGN KEY (college_id) REFERENCES colleges (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Courses each program takes in the current semester, by year level
CREATE TABLE IF NOT EXISTS curriculum (
    program_id SMALLINT UNSIGNED NOT NULL,
    course_id  SMALLINT UNSIGNED NOT NULL,
    year_level TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (program_id, course_id),
    FOREIGN KEY (program_id) REFERENCES programs (id),
    FOREIGN KEY (course_id) REFERENCES courses (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS students (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_no    VARCHAR(12)  NOT NULL UNIQUE,
    first_name    VARCHAR(60)  NOT NULL,
    middle_name   VARCHAR(60)  NULL,
    last_name     VARCHAR(60)  NOT NULL,
    sex           CHAR(1)      NOT NULL,
    birthdate     DATE         NOT NULL,
    email         VARCHAR(120) NOT NULL UNIQUE,
    program_id    SMALLINT UNSIGNED NOT NULL,
    year_level    TINYINT UNSIGNED NOT NULL,
    block         CHAR(1)      NOT NULL,
    status        ENUM('Regular', 'Irregular', 'On leave') NOT NULL,
    back_subjects TINYINT UNSIGNED NOT NULL DEFAULT 0,
    adviser_id    SMALLINT UNSIGNED NULL,
    enrolled_at   DATETIME     NULL,
    KEY idx_students_program (program_id, year_level, block),
    KEY idx_students_name (last_name, first_name),
    FOREIGN KEY (program_id) REFERENCES programs (id),
    FOREIGN KEY (adviser_id) REFERENCES faculty (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One class section = one course taught to one block (e.g. CS 311 for BSCS 3A)
CREATE TABLE IF NOT EXISTS sections (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term_id             TINYINT UNSIGNED NOT NULL,
    course_id           SMALLINT UNSIGNED NOT NULL,
    program_id          SMALLINT UNSIGNED NOT NULL,
    year_level          TINYINT UNSIGNED NOT NULL,
    block               CHAR(1) NOT NULL,
    faculty_id          SMALLINT UNSIGNED NULL,
    mode                ENUM('Onsite', 'Online', 'Off-campus') NOT NULL DEFAULT 'Onsite',
    platform            VARCHAR(30) NULL,
    grades_updated_at   DATETIME NULL,
    grades_submitted_at DATETIME NULL,
    UNIQUE KEY uq_section (term_id, course_id, program_id, year_level, block),
    KEY idx_sections_faculty (faculty_id),
    FOREIGN KEY (term_id) REFERENCES terms (id),
    FOREIGN KEY (course_id) REFERENCES courses (id),
    FOREIGN KEY (program_id) REFERENCES programs (id),
    FOREIGN KEY (faculty_id) REFERENCES faculty (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS meetings (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_id INT UNSIGNED NOT NULL,
    day        ENUM('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat') NOT NULL,
    start_time TIME NOT NULL,
    end_time   TIME NOT NULL,
    room_id    SMALLINT UNSIGNED NULL,
    KEY idx_meetings_room (room_id, day),
    KEY idx_meetings_section (section_id),
    FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Grades use the Philippine scale: 1.00 (highest) to 3.00 (passing), 5.00 failed, INC, DRP
CREATE TABLE IF NOT EXISTS enrollments (
    section_id    INT UNSIGNED NOT NULL,
    student_id    INT UNSIGNED NOT NULL,
    midterm_grade VARCHAR(4) NULL,
    final_grade   VARCHAR(4) NULL,
    PRIMARY KEY (section_id, student_id),
    KEY idx_enrollments_student (student_id),
    FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS interventions (
    student_id      INT UNSIGNED NOT NULL PRIMARY KEY,
    status          ENUM('Contacted', 'Counseling scheduled', 'Improving') NOT NULL,
    last_contact_at DATETIME NOT NULL,
    notes           VARCHAR(255) NULL,
    FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Month-end counts of at-risk students, for the trend chart
CREATE TABLE IF NOT EXISTS risk_snapshots (
    month      DATE NOT NULL,
    college_id TINYINT UNSIGNED NOT NULL,
    at_risk    SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (month, college_id),
    FOREIGN KEY (college_id) REFERENCES colleges (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Anonymous: responses link to a class section, never to a student
CREATE TABLE IF NOT EXISTS evaluation_responses (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_id    INT UNSIGNED NOT NULL,
    submitted_at  DATETIME NOT NULL,
    teaching      TINYINT UNSIGNED NOT NULL,
    organization  TINYINT UNSIGNED NOT NULL,
    communication TINYINT UNSIGNED NOT NULL,
    fairness      TINYINT UNSIGNED NOT NULL,
    punctuality   TINYINT UNSIGNED NOT NULL,
    KEY idx_eval_section (section_id),
    FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS faculty_rating_history (
    faculty_id SMALLINT UNSIGNED NOT NULL,
    term_id    TINYINT UNSIGNED NOT NULL,
    rating     DECIMAL(3, 2) NOT NULL,
    responses  SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (faculty_id, term_id),
    FOREIGN KEY (faculty_id) REFERENCES faculty (id),
    FOREIGN KEY (term_id) REFERENCES terms (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS applicants (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    applicant_no VARCHAR(16)  NOT NULL UNIQUE,
    first_name   VARCHAR(60)  NOT NULL,
    middle_name  VARCHAR(60)  NULL,
    last_name    VARCHAR(60)  NOT NULL,
    sex          CHAR(1)      NOT NULL,
    email        VARCHAR(120) NOT NULL,
    program_id   SMALLINT UNSIGNED NOT NULL,
    type         ENUM('Freshman', 'Transferee') NOT NULL,
    shs_strand   VARCHAR(10)  NULL,
    prior_gwa    DECIMAL(5, 2) NULL,  -- SHS average (75-100) or college GWA for transferees
    exam_date    DATE NULL,
    exam_score   TINYINT UNSIGNED NULL,
    status       ENUM('For review', 'Not qualified', 'Qualified', 'Pending documents', 'Enrolled') NOT NULL,
    submitted_at DATETIME NOT NULL,
    updated_at   DATETIME NOT NULL,
    KEY idx_applicants_status (status),
    KEY idx_applicants_updated (updated_at),
    FOREIGN KEY (program_id) REFERENCES programs (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS applicant_requirements (
    applicant_id INT UNSIGNED NOT NULL,
    requirement  VARCHAR(40) NOT NULL,
    submitted_at DATETIME NULL,
    PRIMARY KEY (applicant_id, requirement),
    FOREIGN KEY (applicant_id) REFERENCES applicants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
