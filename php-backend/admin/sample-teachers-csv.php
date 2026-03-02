<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="sample-teachers.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['employee_id','name','email','phone','subject','qualification','experience_years','dob','gender','address','joining_date','status']);
fputcsv($out, ['EMP001','Anil Verma','anil@example.com','9876543210','Mathematics','M.Sc, B.Ed','10','1985-03-20','male','789 School Lane, Delhi','2020-07-01','active']);
fputcsv($out, ['EMP002','Sita Rani','sita@example.com','9876543211','English','M.A, B.Ed','8','1988-11-05','female','321 College Road, Mumbai','2021-04-15','active']);
fclose($out);
exit;
