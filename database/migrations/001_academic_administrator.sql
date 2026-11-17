-- Existing academic portal: preserve records and existing login credentials.
ALTER TABLE users ALTER COLUMN role SET DEFAULT 'Authorized Academic Administrator';
UPDATE users
SET role = 'Authorized Academic Administrator', name = 'Academic Administration Office'
WHERE email = 'facultyfaculty' AND name = 'Faculty Office'
  AND role IN ('Faculty', 'University-wide access');
