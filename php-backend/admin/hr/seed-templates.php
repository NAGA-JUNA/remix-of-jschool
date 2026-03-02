<?php
/**
 * Default letter templates + seeding logic.
 * getDefaultTemplates() is used by both seeding and "Reset to Default".
 */
function getDefaultTemplates() {
    return [
        'appointment' => '<div style="font-family:\'Times New Roman\',serif;max-width:800px;margin:0 auto;padding:40px;">
<div style="text-align:center;margin-bottom:30px;">
    <img src="{{hr_logo}}" style="height:80px;margin-bottom:10px;" alt="Logo">
    <h2 style="margin:0;color:#1e40af;">{{school_name}}</h2>
    <p style="margin:5px 0;color:#666;font-size:14px;">{{school_address}}</p>
    <hr style="border:2px solid #1e40af;margin:15px 0;">
</div>
<p style="text-align:right;"><strong>Ref:</strong> {{reference_no}}<br><strong>Date:</strong> {{issue_date}}</p>
<h3 style="text-align:center;text-decoration:underline;margin:30px 0;">APPOINTMENT LETTER</h3>
<p>Dear <strong>{{employee_name}}</strong>,</p>
<p>We are pleased to inform you that you have been selected for the position of <strong>{{designation}}</strong> in the <strong>{{department}}</strong> department at {{school_name}}.</p>
<p><strong>Date of Joining:</strong> {{date_of_joining}}</p>
<p><strong>Monthly Salary:</strong> ₹{{salary_new}}</p>
<p><strong>Probation Period:</strong> {{probation_months}} months</p>
<p><strong>Reporting To:</strong> {{reporting_to}}</p>
<h4 style="margin-top:25px;">Terms & Conditions:</h4>
<ol style="line-height:2;">
    <li>You will be on probation for a period of {{probation_months}} months from the date of joining.</li>
    <li>Working hours: 8:00 AM to 4:00 PM, Monday to Saturday.</li>
    <li>You are entitled to casual leave (12 days), sick leave (6 days), and earned leave (15 days) per year.</li>
    <li>Either party may terminate employment with one month\'s written notice during probation.</li>
    <li>You shall maintain confidentiality regarding all institutional matters.</li>
</ol>
<p>We look forward to your valuable contribution to our institution.</p>
<div style="margin-top:60px;">
    <div style="float:right;text-align:center;">
        {{digital_signature}}
        <div style="border-top:1px solid #333;padding-top:5px;width:200px;">
            <strong>Principal / HR Manager</strong><br>
            {{school_name}}
        </div>
    </div>
    <div style="clear:both;"></div>
</div>
</div>',

        'joining' => '<div style="font-family:\'Times New Roman\',serif;max-width:800px;margin:0 auto;padding:40px;">
<div style="text-align:center;margin-bottom:30px;">
    <img src="{{hr_logo}}" style="height:80px;margin-bottom:10px;" alt="Logo">
    <h2 style="margin:0;color:#1e40af;">{{school_name}}</h2>
    <p style="margin:5px 0;color:#666;font-size:14px;">{{school_address}}</p>
    <hr style="border:2px solid #1e40af;margin:15px 0;">
</div>
<p style="text-align:right;"><strong>Ref:</strong> {{reference_no}}<br><strong>Date:</strong> {{issue_date}}</p>
<h3 style="text-align:center;text-decoration:underline;margin:30px 0;">JOINING CONFIRMATION LETTER</h3>
<p>Dear <strong>{{employee_name}}</strong>,</p>
<p>We are pleased to confirm that your probation period has been successfully completed. You are hereby confirmed as a permanent employee of <strong>{{school_name}}</strong> effective from <strong>{{effective_date}}</strong>.</p>
<p><strong>Employee ID:</strong> {{employee_id}}</p>
<p><strong>Designation:</strong> {{designation}}</p>
<p><strong>Department:</strong> {{department}}</p>
<p><strong>Revised Monthly Salary:</strong> ₹{{salary_new}}</p>
<p><strong>Reporting Manager:</strong> {{reporting_to}}</p>
<p>All other terms and conditions as per your appointment letter remain unchanged.</p>
<p>We appreciate your dedication and look forward to your continued contribution.</p>
<div style="margin-top:60px;">
    <div style="float:right;text-align:center;">
        {{digital_signature}}
        <div style="border-top:1px solid #333;padding-top:5px;width:200px;">
            <strong>Principal / HR Manager</strong><br>
            {{school_name}}
        </div>
    </div>
    <div style="clear:both;"></div>
</div>
</div>',

        'resignation' => '<div style="font-family:\'Times New Roman\',serif;max-width:800px;margin:0 auto;padding:40px;">
<div style="text-align:center;margin-bottom:30px;">
    <img src="{{hr_logo}}" style="height:80px;margin-bottom:10px;" alt="Logo">
    <h2 style="margin:0;color:#1e40af;">{{school_name}}</h2>
    <p style="margin:5px 0;color:#666;font-size:14px;">{{school_address}}</p>
    <hr style="border:2px solid #1e40af;margin:15px 0;">
</div>
<p style="text-align:right;"><strong>Ref:</strong> {{reference_no}}<br><strong>Date:</strong> {{issue_date}}</p>
<h3 style="text-align:center;text-decoration:underline;margin:30px 0;">RESIGNATION ACCEPTANCE LETTER</h3>
<p>Dear <strong>{{employee_name}}</strong>,</p>
<p>This is to acknowledge and accept your resignation from the position of <strong>{{designation}}</strong> in the <strong>{{department}}</strong> department.</p>
<p><strong>Last Working Date:</strong> {{last_working_date}}</p>
<p><strong>Notice Period:</strong> {{notice_period}}</p>
<p>Please ensure the following before your last working day:</p>
<ul style="line-height:2;">
    <li>Complete all pending work and hand over responsibilities.</li>
    <li>Return all institutional property including ID card, keys, and equipment.</li>
    <li>Clear any outstanding dues.</li>
    <li>Obtain clearance from all departments.</li>
</ul>
<p>Your final settlement will be processed after completion of the clearance procedure.</p>
<p>We wish you all the best in your future endeavours.</p>
<div style="margin-top:60px;">
    <div style="float:right;text-align:center;">
        {{digital_signature}}
        <div style="border-top:1px solid #333;padding-top:5px;width:200px;">
            <strong>Principal / HR Manager</strong><br>
            {{school_name}}
        </div>
    </div>
    <div style="clear:both;"></div>
</div>
</div>',

        'hike' => '<div style="font-family:\'Times New Roman\',serif;max-width:800px;margin:0 auto;padding:40px;">
<div style="text-align:center;margin-bottom:30px;">
    <img src="{{hr_logo}}" style="height:80px;margin-bottom:10px;" alt="Logo">
    <h2 style="margin:0;color:#1e40af;">{{school_name}}</h2>
    <p style="margin:5px 0;color:#666;font-size:14px;">{{school_address}}</p>
    <hr style="border:2px solid #1e40af;margin:15px 0;">
</div>
<p style="text-align:right;"><strong>Ref:</strong> {{reference_no}}<br><strong>Date:</strong> {{issue_date}}</p>
<h3 style="text-align:center;text-decoration:underline;margin:30px 0;">SALARY REVISION / INCREMENT LETTER</h3>
<p>Dear <strong>{{employee_name}}</strong>,</p>
<p>We are pleased to inform you that based on your performance and contribution, the management has decided to revise your salary effective from <strong>{{effective_date}}</strong>.</p>
<table style="width:100%;border-collapse:collapse;margin:20px 0;" border="1" cellpadding="10">
    <tr style="background:#f0f4ff;">
        <td><strong>Previous Salary</strong></td>
        <td style="text-align:right;">₹{{salary_old}} /month</td>
    </tr>
    <tr style="background:#e8ffe8;">
        <td><strong>Revised Salary</strong></td>
        <td style="text-align:right;font-size:1.1em;color:#16a34a;"><strong>₹{{salary_new}} /month</strong></td>
    </tr>
    <tr>
        <td><strong>Increment</strong></td>
        <td style="text-align:right;">{{increment_pct}}%</td>
    </tr>
</table>
<p><strong>Reason:</strong> {{reason}}</p>
<p>All other terms and conditions of your employment remain unchanged.</p>
<p>We appreciate your hard work and look forward to your continued excellence.</p>
<div style="margin-top:60px;">
    <div style="float:right;text-align:center;">
        {{digital_signature}}
        <div style="border-top:1px solid #333;padding-top:5px;width:200px;">
            <strong>Principal / HR Manager</strong><br>
            {{school_name}}
        </div>
    </div>
    <div style="clear:both;"></div>
</div>
</div>',
    ];
}

/**
 * Seed default letter templates into letter_templates table.
 * Uses INSERT IGNORE per type so missing types get seeded even if some already exist.
 */
function seedLetterTemplates($db) {
    $templates = getDefaultTemplates();
    $stmt = $db->prepare("INSERT IGNORE INTO letter_templates (letter_type, template_content, status) VALUES (?, ?, 'active')");
    foreach ($templates as $type => $content) {
        $stmt->execute([$type, $content]);
    }
}