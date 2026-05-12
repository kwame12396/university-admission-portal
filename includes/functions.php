<?php
// ============================================================
// includes/functions.php — Shared utility functions
// ============================================================

/**
 * Convert letter grade to numeric points.
 * A*=8, A=7, B=6, C=5, D=4, E=3, U=0
 */
function gradeToPoints(string $grade): int {
    $map = ['A*' => 8, 'A' => 7, 'B' => 6, 'C' => 5, 'D' => 4, 'E' => 3, 'U' => 0];
    return $map[$grade] ?? 0;
}

/**
 * Calculate the total score for an application.
 * Rule: Sum the 6 highest subject point values. Maximum = 48 (6 × 8).
 * If fewer than 6 subjects exist, sum all of them.
 */
function getTotalPoints(PDO $pdo, int $applicationId): int {
    $stmt = $pdo->prepare(
        "SELECT points FROM academic_qualifications
         WHERE application_id = ?
         ORDER BY points DESC
         LIMIT 6"
    );
    $stmt->execute([$applicationId]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return array_sum($rows);
}

/**
 * Get all qualifications for an application.
 */
function getQualifications(PDO $pdo, int $applicationId): array {
    $stmt = $pdo->prepare(
        "SELECT * FROM academic_qualifications
         WHERE application_id = ?
         ORDER BY points DESC"
    );
    $stmt->execute([$applicationId]);
    return $stmt->fetchAll();
}

/**
 * Get all documents for an application.
 */
function getDocuments(PDO $pdo, int $applicationId): array {
    $stmt = $pdo->prepare("SELECT * FROM documents WHERE application_id = ?");
    $stmt->execute([$applicationId]);
    return $stmt->fetchAll();
}

/**
 * Handle file upload. Returns ['path'=>string,'original'=>string] on success,
 * or a string error message on failure.
 */
function handleFileUpload(array $file, int $applicationId, string $docType): string|array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return "File upload error for '{$docType}'.";
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        return "File '{$file['name']}' exceeds the 5 MB size limit.";
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        return "Invalid file type for '{$file['name']}'. Allowed: PDF, JPG, PNG.";
    }
    $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_',
                    pathinfo($file['name'], PATHINFO_FILENAME));
    $fileName = "app_{$applicationId}_" . time() . "_" . $safeName . ".{$ext}";
    $destPath = UPLOAD_DIR . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return "Could not save '{$file['name']}'. Check server upload permissions.";
    }
    return ['path' => 'uploads/' . $fileName, 'original' => $file['name']];
}

/**
 * Render a simple HTML alert box.
 */
function alert(string $message, string $type = 'error'): string {
    $icon = $type === 'success' ? '✔' : ($type === 'info' ? 'ℹ' : '✖');
    return "<div class='alert alert-{$type}'><span class='alert-icon'>{$icon}</span> "
         . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</div>";
}

/**
 * Fetch the single application record for a student.
 */
function getApplicationByUser(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Return the label for a qualification type, including the custom "Other" value.
 */
function qualTypeLabel(string $type, ?string $other): string {
    if ($type === 'Other' && $other) {
        return 'Other — ' . htmlspecialchars($other, ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
}

// ============================================================
// PROGRAMMES LIST (expanded)
// ============================================================
function getPrograms(): array {
    return [
        // --- Faculty of Science ---
        'BSc General',
        'BSc Computer Science',
        'BSc Computer Science with Statistics',
        'BSc Information Technology',
        'BSc Mathematics',
        'BSc Mathematics with Statistics',
        'BSc Statistics',
        'BSc Physics',
        'BSc Chemistry',
        'BSc Biology',
        'BSc Biochemistry',
        'BSc Environmental Science',
        'BSc Geology',
        'BSc Food Science & Technology',
        'BSc Forensic Science',
        'BSc Actuarial Science',

        // --- Faculty of Engineering & Technology ---
        'BEng Civil Engineering',
        'BEng Electrical & Electronics Engineering',
        'BEng Mechanical Engineering',
        'BEng Mining Engineering',
        'BEng Chemical Engineering',
        'BEng Computer Engineering',
        'BEng Environmental Engineering',
        'BTech Information Systems',
        'BTech Telecommunication Engineering',

        // --- Faculty of Business ---
        'BBA (Bachelor of Business Administration)',
        'BBA Accounting',
        'BBA Finance',
        'BBA Marketing',
        'BBA Human Resources Management',
        'BBA Entrepreneurship',
        'BBA Supply Chain Management',
        'BCom (Bachelor of Commerce)',
        'BCom Accounting',
        'BCom Economics',
        'BCom Banking & Finance',
        'BSc Economics',

        // --- Faculty of Humanities ---
        'BA English',
        'BA Setswana',
        'BA French',
        'BA Sociology',
        'BA Psychology',
        'BA History',
        'BA Philosophy',
        'BA Media Studies',
        'BA Political Science',
        'BA Development Studies',
        'BA Gender Studies',
        'BA Library & Information Studies',
        'BA Social Work',

        // --- Faculty of Education ---
        'BEd Primary Education',
        'BEd Secondary Education (Sciences)',
        'BEd Secondary Education (Humanities)',
        'BEd Special Education',
        'BEd Physical Education & Sport',
        'BEd Early Childhood Care & Education',
        'BEd Adult Education',
        'BEd Home Economics',
        'BEd Technical & Vocational Education',

        // --- Faculty of Health Sciences ---
        'MBBS (Bachelor of Medicine & Surgery)',
        'BNS (Bachelor of Nursing Science)',
        'BPharm (Bachelor of Pharmacy)',
        'BDS (Bachelor of Dental Surgery)',
        'BSc Medical Laboratory Sciences',
        'BSc Physiotherapy',
        'BSc Radiography',
        'BSc Public Health',
        'BSc Occupational Therapy',
        'BSc Optometry',

        // --- Faculty of Law ---
        'LLB (Bachelor of Laws)',
        'LLB with Business Law',

        // --- Faculty of Social Sciences ---
        'BSc Social Sciences',
        'BSocSci (Community Development)',
        'BSocSci (Urban & Regional Planning)',
        'BSocSci (Population Studies)',

        // --- Faculty of Architecture & Planning ---
        'BArch (Bachelor of Architecture)',
        'BSc Quantity Surveying',
        'BSc Construction Management',
        'BSc Land Management',

        // --- Faculty of Agriculture ---
        'BSc Agriculture',
        'BSc Agricultural Economics',
        'BSc Animal Science',
        'BSc Crop Science',
        'BSc Agricultural Engineering',
        'BSc Agribusiness Management',
        'BSc Horticulture',
        'BSc Natural Resources Management',

        // --- Postgraduate (for reference) ---
        'Postgraduate Diploma in Education',
        'MSc Computer Science',
        'MSc Data Science',
        'MBA (Master of Business Administration)',
    ];
}

// ============================================================
// SUBJECTS (combined list covering BGCSE / IGCSE / IB / Matric)
// ============================================================
function getSubjectsByQualType(string $qualType): array {
    $bgcse = [
        'English Language','Setswana','Mathematics','Combined Science',
        'Physics','Chemistry','Biology','Agriculture','Accounting',
        'Business Studies','Economics','History','Geography',
        'Religious Education','Art & Design','Physical Education',
        'Computer Studies','Home Management','Design & Technology','French',
    ];
    $igcse = [
        'English Language','English Literature','Mathematics (Core)',
        'Mathematics (Extended)','Additional Mathematics','Biology',
        'Chemistry','Physics','Combined Science','Co-ordinated Sciences',
        'Accounting','Business Studies','Economics','Geography','History',
        'Sociology','Travel & Tourism','Computer Science','ICT',
        'Art & Design','Music','Physical Education','French',
        'Afrikaans','Portuguese','Setswana',
    ];
    $ib = [
        'English A: Language & Literature','English A: Literature',
        'French B','Spanish B','Mathematics: Analysis & Approaches',
        'Mathematics: Applications & Interpretation','Biology','Chemistry',
        'Physics','Environmental Systems & Societies','Economics',
        'Business Management','History','Geography','Psychology',
        'Philosophy','Computer Science','Film','Music','Visual Arts',
        'Theory of Knowledge (TOK)',
    ];
    $matric = [
        'English Home Language','English First Additional Language',
        'Afrikaans Home Language','Afrikaans First Additional Language',
        'Setswana Home Language','Sesotho Home Language',
        'Mathematics','Mathematical Literacy','Technical Mathematics',
        'Physical Sciences','Life Sciences','Technical Sciences',
        'Geography','History','Business Studies','Economics','Accounting',
        'Consumer Studies','Computer Applications Technology (CAT)',
        'Information Technology (IT)','Engineering Graphics & Design',
        'Electrical Technology','Civil Technology','Mechanical Technology',
        'Agricultural Sciences','Agricultural Technology',
        'Life Orientation','Religion Studies',
    ];
    $common = [
        'English Language','Mathematics','Biology','Chemistry','Physics',
        'History','Geography','Economics','Business Studies','Computer Science',
        'Art & Design','Physical Education','French','Sociology','Psychology',
    ];

    return match ($qualType) {
        'BGCSE'  => $bgcse,
        'IGCSE'  => $igcse,
        'IB'     => $ib,
        'Matric' => $matric,
        default  => $common,
    };
}

/**
 * All subjects combined (used as fallback when qual type not yet selected).
 */
function getAllSubjects(): array {
    return array_values(array_unique(array_merge(
        getSubjectsByQualType('BGCSE'),
        getSubjectsByQualType('IGCSE'),
        getSubjectsByQualType('IB'),
        getSubjectsByQualType('Matric'),
    )));
}

/**
 * Document types keyed to qualification types.
 */
function getDocTypes(string $qualType = ''): array {
    $base = ['Omang/ID', 'Transcript', 'Birth Certificate', 'Other'];
    $cert = match($qualType) {
        'IGCSE'  => 'IGCSE Certificate',
        'IB'     => 'IB Certificate',
        'Matric' => 'Matric Certificate',
        default  => 'BGCSE Certificate',
    };
    array_splice($base, 1, 0, [$cert]);
    return $base;
}
