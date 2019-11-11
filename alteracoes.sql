UPDATE job_activity SET counter = 0 WHERE id = 2;

UPDATE task SET reopened = 0 
WHERE job_activity_id = 2;