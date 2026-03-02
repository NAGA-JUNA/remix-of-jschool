<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="sample-students.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['admission_no','name','father_name','mother_name','dob','gender','class','section','roll_no','phone','email','address','blood_group','category','aadhar_no','status','admission_date']);
fputcsv($out, ['ADM001','Rahul Kumar','Ramesh Kumar','Sunita Devi','2010-05-15','male','6','A','1','9876543210','rahul@example.com','123 Main Street, Delhi','B+','General','1234-5678-9012','active','2024-04-01']);
fputcsv($out, ['ADM002','Priya Sharma','Suresh Sharma','Meena Sharma','2011-08-22','female','5','B','2','9876543211','priya@example.com','456 Park Road, Mumbai','A+','OBC','9876-5432-1098','active','2024-04-01']);
fclose($out);
exit;
