<?php

declare(strict_types=1);

session_start();

const ADMIN_EMAIL = 'admin@lagospadelclub.com';
const MAX_PHOTO_SIZE = 5 * 1024 * 1024;
const ZEPTOMAIL_ENDPOINT = 'https://api.zeptomail.com/v1.1/email';
const ZEPTOMAIL_FROM_EMAIL = 'noreply@lagospadelclub.com';
const ZEPTOMAIL_FROM_NAME = 'Lagos Padel Club';

define('ZEPTOMAIL_AUTH_HEADER',  '');

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}


function field(string $name): string
{
    return escape(trim((string) ($_POST[$name] ?? '')));
}

function rawField(string $name): string
{
    return trim((string) ($_POST[$name] ?? ''));
}

function selected(string $name, string $value): string
{
    return rawField($name) === $value ? ' selected' : '';
}

function checked(string $name, string $value = '1'): string
{
    $current = $_POST[$name] ?? null;

    if (is_array($current)) {
        return in_array($value, $current, true) ? ' checked' : '';
    }

    return (string) $current === $value ? ' checked' : '';
}

function formatLabel(string $value): string
{
    return ucwords(str_replace('_', ' ', $value));
}

function validDate(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date !== false && $date->format('Y-m-d') === $value;
}

function validPhone(string $value): bool
{
    $digits = preg_replace('/\D+/', '', $value);

    return preg_match('/^\+?[0-9\s().-]+$/', $value) === 1
        && strlen((string) $digits) >= 7
        && strlen((string) $digits) <= 15;
}

function validSignatureData(string $value): bool
{
    if (!preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', $value)) {
        return false;
    }

    $decoded = base64_decode(substr($value, strlen('data:image/png;base64,')), true);

    return $decoded !== false && strlen($decoded) > 500 && strlen($decoded) < 800000;
}

function addError(array &$errors, array &$fieldErrors, string $field, string $message): void
{
    $errors[] = $message;
    $fieldErrors[$field] = $message;
}

function detailsRows(array $details): string
{
    $rows = '';

    foreach ($details as $section => $items) {
        $rows .= '<tr><td colspan="2" style="padding:22px 18px 8px;color:#06163a;font-size:18px;font-weight:800;">'
            . escape($section)
            . '</td></tr>';

        foreach ($items as $label => $value) {
            $rows .= '<tr>'
                . '<td style="width:38%;padding:10px 18px;border-bottom:1px solid #e8ebf0;color:#667085;font-weight:700;">'
                . escape($label)
                . '</td>'
                . '<td style="padding:10px 18px;border-bottom:1px solid #e8ebf0;color:#101828;">'
                . escape($value !== '' ? $value : 'Not provided')
                . '</td>'
                . '</tr>';
        }
    }

    return $rows;
}

function emailShell(string $eyebrow, string $title, string $body, string $rows = ''): string
{
    $html = '<!doctype html><html><body style="margin:0;background:#eef2f6;font-family:Arial,sans-serif;color:#101828;">'
        . '<div style="max-width:680px;margin:0 auto;padding:30px 16px;">'
        . '<div style="background:#06163a;padding:28px;text-align:center;border-radius:18px 18px 0 0;">'
        . '<img src="https://lagospadelclub.com/logo.png" width="130" alt="Lagos Padel Club" style="display:block;margin:0 auto 14px;">'
        . '<div style="color:#ffd400;font-size:13px;font-weight:800;letter-spacing:2px;text-transform:uppercase;">' . escape($eyebrow) . '</div>'
        . '<h1 style="margin:8px 0 0;color:#fff;font-size:28px;">' . escape($title) . '</h1>'
        . '</div>'
        . '<div style="background:#fff;padding:24px 18px;border-radius:0 0 18px 18px;">'
        . '<p style="margin:0 18px 14px;line-height:1.7;color:#475467;">' . $body . '</p>';

    if ($rows !== '') {
        $html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">'
            . $rows
            . '</table>';
    }

    return $html
        . '<p style="margin:24px 18px 4px;color:#667085;font-size:13px;line-height:1.6;">Lagos Padel Club</p>'
        . '</div></div></body></html>';
}

function pdfText(string $value): string
{
    $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);

    if ($converted === false) {
        $converted = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? $value;
    }

    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $converted);
}

function pdfCommand(float $x, float $y, int $size, string $text): string
{
    return sprintf("BT /F1 %d Tf %.2F %.2F Td (%s) Tj ET\n", $size, $x, $y, pdfText($text));
}

function buildRegistrationPdf(array $details): string
{
    $pages = [];
    $content = '';
    $y = 780.0;

    $addPage = static function () use (&$pages, &$content, &$y): void {
        if ($content !== '') {
            $pages[] = $content;
        }

        $content = pdfCommand(50, 800, 18, 'Lagos Padel Club')
            . pdfCommand(50, 778, 13, 'Elite Membership Registration')
            . pdfCommand(50, 760, 9, 'Generated on ' . date('j M Y, g:ia'));
        $y = 730.0;
    };

    $addPage();

    foreach ($details as $section => $items) {
        if ($y < 90) {
            $addPage();
        }

        $content .= pdfCommand(50, $y, 13, strtoupper((string) $section));
        $y -= 18;

        foreach ($items as $label => $value) {
            if ($y < 60) {
                $addPage();
            }

            $text = $label . ': ' . ($value !== '' ? $value : 'Not provided');
            $wrapped = wordwrap($text, 88, "\n", true);

            foreach (explode("\n", $wrapped) as $line) {
                if ($y < 60) {
                    $addPage();
                }

                $content .= pdfCommand(62, $y, 10, $line);
                $y -= 14;
            }
        }

        $y -= 10;
    }

    $pages[] = $content;

    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    $pageObjectNumbers = [];

    foreach ($pages as $pageContent) {
        $pageNumber = count($objects) + 1;
        $contentNumber = $pageNumber + 1;
        $pageObjectNumbers[] = $pageNumber;
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentNumber} 0 R >>";
        $objects[] = "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}endstream";
    }

    $objects[1] = '<< /Type /Pages /Kids [' . implode(' ', array_map(static fn(int $number): string => "{$number} 0 R", $pageObjectNumbers)) . '] /Count ' . count($pageObjectNumbers) . ' >>';

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $number = $index + 1;
        $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";

    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }

    return $pdf
        . "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n"
        . "startxref\n{$xref}\n%%EOF";
}

function sendZeptoMail(array $payload, ?string &$error = null): bool
{
    if (ZEPTOMAIL_AUTH_HEADER === '') {
        $error = 'ZEPTOMAIL_TOKEN is not configured on the server.';
        return false;
    }

    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($encoded === false) {
        $error = 'Could not prepare the email payload.';
        return false;
    }

    $ch = curl_init(ZEPTOMAIL_ENDPOINT);

    if ($ch === false) {
        $error = 'Could not initialize the email request.';
        return false;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: ' . ZEPTOMAIL_AUTH_HEADER,
        ],
        CURLOPT_POSTFIELDS => $encoded,
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    if ($response === false || $status < 200 || $status >= 300) {
        $error = curl_error($ch) ?: 'ZeptoMail returned HTTP ' . $status . ': ' . (string) $response;
        curl_close($ch);
        return false;
    }

    curl_close($ch);
    return true;
}

function zeptoAddress(string $email, string $name = ''): array
{
    return [
        'email_address' => [
            'address' => $email,
            'name' => $name !== '' ? $name : $email,
        ],
    ];
}

function zeptoBasePayload(string $toEmail, string $toName, string $subject, string $html): array
{
    return [
        'from' => [
            'address' => ZEPTOMAIL_FROM_EMAIL,
            'name' => ZEPTOMAIL_FROM_NAME,
        ],
        'to' => [
            zeptoAddress($toEmail, $toName),
        ],
        'subject' => $subject,
        'htmlbody' => $html,
    ];
}

if (empty($_SESSION['registration_token'])) {
    $_SESSION['registration_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$fieldErrors = [];
$success = isset($_GET['submitted']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = (string) ($_POST['_token'] ?? '');

    if (!hash_equals((string) $_SESSION['registration_token'], $token)) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }

    if (rawField('website') !== '') {
        $errors[] = 'We could not process this registration.';
    }

    $required = [
        'full_name' => 'Full name',
        'preferred_name' => 'Preferred name',
        'mobile_number' => 'Mobile number',
        'whatsapp' => 'WhatsApp number',
        'linkedin' => 'LinkedIn profile',
        'instagram' => 'Instagram / Snapchat',
        'email' => 'Email address',
        'occupation' => 'Occupation or industry',
        'company_name' => 'Company or business name',
        'position' => 'Position or role',
        'date_of_birth' => 'Date of birth',
        'playing_level' => 'Playing level',
        'emergency_name' => 'Emergency contact name',
        'emergency_relationship' => 'Emergency contact relationship',
        'emergency_phone' => 'Emergency contact phone',
        'media_consent' => 'Media consent',
        'admission_pathway' => 'Admission pathway',
        'signature_data' => 'Signature',
        'declaration_date' => 'Declaration date',
    ];

    foreach ($required as $name => $label) {
        if (rawField($name) === '') {
            addError($errors, $fieldErrors, $name, "{$label} is required.");
        }
    }

    $email = filter_var(rawField('email'), FILTER_VALIDATE_EMAIL);

    if ($email === false || preg_match('/[\r\n]/', rawField('email'))) {
        addError($errors, $fieldErrors, 'email', 'Enter a valid email address.');
    }

    foreach (
        [
            'full_name' => 120,
            'preferred_name' => 80,
            'email' => 190,
            'mobile_number' => 30,
            'whatsapp' => 30,
            'linkedin' => 255,
            'instagram' => 80,
            'occupation' => 120,
            'company_name' => 120,
            'position' => 120,
            'emergency_name' => 120,
            'emergency_relationship' => 80,
            'emergency_phone' => 30,
            'inviting_member' => 120,
            'supporting_member_1' => 120,
            'supporting_member_2' => 120,
            'supporting_member_3' => 120,
        ] as $name => $maximum
    ) {
        if (mb_strlen(rawField($name)) > $maximum) {
            addError($errors, $fieldErrors, $name, formatLabel($name) . " must not exceed {$maximum} characters.");
        }
    }

    foreach (
        [
            'full_name' => 'Full name',
            'emergency_name' => 'Emergency contact name',
        ] as $name => $label
    ) {
        $value = rawField($name);

        if ($value !== '' && preg_match("/^[\\p{L}\\p{M}][\\p{L}\\p{M} .'-]{1,119}$/u", $value) !== 1) {
            addError($errors, $fieldErrors, $name, "{$label} may contain only letters, spaces, apostrophes, hyphens and periods.");
        }
    }

    foreach (
        [
            'mobile_number' => 'Mobile number',
            'whatsapp' => 'WhatsApp number',
            'emergency_phone' => 'Emergency contact phone',
        ] as $name => $label
    ) {
        $value = rawField($name);

        if ($value !== '' && !validPhone($value)) {
            addError($errors, $fieldErrors, $name, "Enter a valid {$label}.");
        }
    }

    $linkedin = rawField('linkedin');

    if ($linkedin !== '' && filter_var($linkedin, FILTER_VALIDATE_URL) === false) {
        addError($errors, $fieldErrors, 'linkedin', 'Enter a complete LinkedIn URL beginning with http:// or https://.');
    }

    $dateOfBirth = rawField('date_of_birth');

    if ($dateOfBirth !== '' && (!validDate($dateOfBirth) || $dateOfBirth >= date('Y-m-d'))) {
        addError($errors, $fieldErrors, 'date_of_birth', 'Enter a valid date of birth in the past.');
    }

    $declarationDate = rawField('declaration_date');

    if ($declarationDate !== '' && (!validDate($declarationDate) || $declarationDate > date('Y-m-d'))) {
        addError($errors, $fieldErrors, 'declaration_date', 'Declaration date cannot be in the future.');
    }

    $allowedLevels = ['beginner', 'intermediate', 'advanced'];
    $allowedConsent = ['yes', 'no'];
    $allowedPathways = ['standard_admission', 'curated_entry'];

    if (!in_array(rawField('playing_level'), $allowedLevels, true)) {
        addError($errors, $fieldErrors, 'playing_level', 'Choose a valid playing level.');
    }

    if (!in_array(rawField('media_consent'), $allowedConsent, true)) {
        addError($errors, $fieldErrors, 'media_consent', 'Choose a valid media consent option.');
    }

    if (!in_array(rawField('admission_pathway'), $allowedPathways, true)) {
        addError($errors, $fieldErrors, 'admission_pathway', 'Choose a valid admission pathway.');
    }

    foreach (
        [
            'inviting_member' => 'Inviting member',
            'supporting_member_1' => 'Supporting member 1',
            'supporting_member_2' => 'Supporting member 2',
            'supporting_member_3' => 'Supporting member 3',
        ] as $name => $label
    ) {
        if (rawField($name) === '') {
            addError($errors, $fieldErrors, $name, "{$label} is required.");
        }
    }

    $engagement = array_values(array_intersect(
        (array) ($_POST['club_engagement'] ?? []),
        ['social_play', 'competitive_matches', 'tournaments', 'club_events']
    ));

    if ($engagement === []) {
        addError($errors, $fieldErrors, 'club_engagement', 'Select at least one club engagement option.');
    }

    foreach (['fitness_declaration', 'sport_acknowledgement', 'liability_release', 'club_declaration'] as $declaration) {
        if (rawField($declaration) !== '1') {
            addError($errors, $fieldErrors, $declaration, 'All health, liability and club declarations must be accepted.');
            break;
        }
    }

    if (!validSignatureData(rawField('signature_data'))) {
        addError($errors, $fieldErrors, 'signature_data', 'Please sign inside the signature box.');
    }

    $photo = $_FILES['member_photo'] ?? null;
    $photoData = null;

    if (!$photo || (int) $photo['error'] !== UPLOAD_ERR_OK) {
        addError($errors, $fieldErrors, 'member_photo', 'Upload a clear photograph of the applicant.');
    } elseif ((int) $photo['size'] > MAX_PHOTO_SIZE) {
        addError($errors, $fieldErrors, 'member_photo', 'The applicant photograph must be 5 MB or smaller.');
    } elseif (!is_uploaded_file($photo['tmp_name'])) {
        addError($errors, $fieldErrors, 'member_photo', 'The applicant photograph upload is invalid.');
    } else {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($photo['tmp_name']);
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($mime, $allowedTypes, true) || getimagesize($photo['tmp_name']) === false) {
            addError($errors, $fieldErrors, 'member_photo', 'The applicant photograph must be a valid JPG, PNG or WebP image.');
        } else {
            $photoData = [
                'tmp_name' => $photo['tmp_name'],
                'name' => $photo['name'],
                'mime' => $mime,
            ];
        }
    }

    if (!$errors && $email && $photoData) {
        $engagement = array_map(
            static fn(string $item): string => formatLabel($item),
            $engagement
        );

        $details = [
            'Personal information' => [
                'Full name' => rawField('full_name'),
                'Preferred name' => rawField('preferred_name'),
                'Mobile number' => rawField('mobile_number'),
                'WhatsApp' => rawField('whatsapp'),
                'LinkedIn' => rawField('linkedin'),
                'Instagram / Snapchat' => rawField('instagram'),
                'Email address' => rawField('email'),
                'Photo filename' => (string) $photoData['name'],
            ],
            'Background details' => [
                'Occupation / Industry' => rawField('occupation'),
                'Company / Business name' => rawField('company_name'),
                'Position / Role' => rawField('position'),
                'Date of birth' => rawField('date_of_birth'),
            ],
            'Padel profile' => [
                'Playing level' => formatLabel(rawField('playing_level')),
                'Club engagement' => $engagement ? implode(', ', $engagement) : 'Not selected',
            ],
            'Emergency contact' => [
                'Name' => rawField('emergency_name'),
                'Relationship' => rawField('emergency_relationship'),
                'Phone' => rawField('emergency_phone'),
            ],
            'Membership details' => [
                'Media consent' => formatLabel(rawField('media_consent')),
                'Admission pathway' => formatLabel(rawField('admission_pathway')),
                'Inviting member' => rawField('inviting_member'),
                'Supporting member 1' => rawField('supporting_member_1'),
                'Supporting member 2' => rawField('supporting_member_2'),
                'Supporting member 3' => rawField('supporting_member_3'),
                'Signature' => 'Submitted electronically',
                'Declaration date' => rawField('declaration_date'),
            ],
        ];

        $safeName = preg_replace('/[^A-Za-z0-9-]+/', '-', rawField('full_name')) ?: 'member';
        $pdfName = 'lagos-padel-registration-' . trim($safeName, '-') . '.pdf';
        $pdf = buildRegistrationPdf($details);

        $adminSubject = 'New Lagos Padel Club registration - ' . rawField('full_name');
        $adminHtml = emailShell(
            'New membership application',
            'Registration received',
            'A new Lagos Padel Club membership application has been submitted. The completed registration summary is attached as a PDF.',
            detailsRows($details)
        );
        $adminPayload = zeptoBasePayload(ADMIN_EMAIL, 'Lagos Padel Club Admin', $adminSubject, $adminHtml);
        $adminPayload['attachments'] = [
            [
                'name' => $pdfName,
                'content' => base64_encode($pdf),
                'mime_type' => 'application/pdf',
            ],
            [
                'name' => 'signature-' . trim($safeName, '-') . '.png',
                'content' => substr(rawField('signature_data'), strlen('data:image/png;base64,')),
                'mime_type' => 'image/png',
            ],
        ];

        $welcomeName = rawField('preferred_name') !== '' ? rawField('preferred_name') : rawField('full_name');
        $welcomeSubject = 'Welcome to Lagos Padel Club';
        $welcomeHtml = emailShell(
            'Elite membership',
            'Welcome to Lagos Padel Club',
            'Hello ' . escape($welcomeName) . ',<br><br>'
                . 'Thank you for submitting your Lagos Padel Club membership registration. We have received your details and our membership team will review your application shortly.<br><br>'
                . 'We are excited to welcome you into a community built around padel, competition, lifestyle and connection. Please keep an eye on your email for the next steps.',
        );
        $welcomePayload = zeptoBasePayload((string) $email, rawField('full_name'), $welcomeSubject, $welcomeHtml);

        $mailError = null;

        if (sendZeptoMail($adminPayload, $mailError) && sendZeptoMail($welcomePayload, $mailError)) {
            $_SESSION['registration_token'] = bin2hex(random_bytes(32));
            header('Location: /register?submitted=1');
            exit;
        }

        $errors[] = 'We could not send your registration right now. Please try again shortly.';
        error_log('Lagos Padel Club registration email failed: ' . (string) $mailError);
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#06163a">
    <meta name="description" content="Apply for elite membership at Lagos Padel Club.">
    <title>Membership Registration | Lagos Padel Club</title>
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
    <style>
        :root {
            --navy: #06163a;
            --navy-light: #0c295b;
            --yellow: #ffd400;
            --blue: #25a8ee;
            --white: #fff;
            --ink: #111827;
            --muted: #667085;
            --line: #d9e0ea;
            --surface: #f5f7fa;
            --danger: #b42318;
            --success: #067647;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-width: 320px;
            margin: 0;
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at 90% 4%, rgba(37, 168, 238, .12), transparent 24rem),
                var(--surface);
        }

        a {
            color: inherit;
        }

        .topbar {
            display: flex;
            padding: 1rem clamp(1.2rem, 5vw, 4rem);
            color: var(--white);
            background: var(--navy);
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: .85rem;
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: .18em;
            text-decoration: none;
            text-transform: uppercase;
        }

        .brand img {
            width: 58px;
            height: 58px;
            object-fit: contain;
        }

        .back {
            color: rgba(255, 255, 255, .72);
            font-size: .82rem;
            font-weight: 700;
            text-decoration: none;
        }

        .hero {
            padding: clamp(3rem, 8vw, 6rem) 1.25rem clamp(6rem, 11vw, 9rem);
            color: var(--white);
            text-align: center;
            background:
                radial-gradient(circle at 20% 20%, rgba(255, 212, 0, .14), transparent 22rem),
                linear-gradient(135deg, var(--navy), var(--navy-light));
        }

        .hero p {
            max-width: 660px;
            margin: 1rem auto 0;
            color: rgba(255, 255, 255, .72);
            font-size: 1.02rem;
            line-height: 1.7;
        }

        .eyebrow {
            color: var(--yellow) !important;
            font-size: .72rem !important;
            font-weight: 800;
            letter-spacing: .2em;
            text-transform: uppercase;
        }

        h1 {
            margin: .8rem 0 0;
            font-size: clamp(2.4rem, 6vw, 4.8rem);
            letter-spacing: -.055em;
            line-height: .98;
        }

        .form-shell {
            width: min(100% - 2rem, 940px);
            margin: clamp(-4rem, -7vw, -5.5rem) auto 4rem;
            padding: clamp(1.25rem, 4vw, 3rem);
            border: 1px solid rgba(6, 22, 58, .08);
            border-radius: 24px;
            background: var(--white);
            box-shadow: 0 28px 75px rgba(6, 22, 58, .13);
        }

        .notice {
            margin-bottom: 1.5rem;
            padding: 1rem 1.1rem;
            border-radius: 10px;
            line-height: 1.55;
        }

        .notice.error {
            border: 1px solid #fecdca;
            color: var(--danger);
            background: #fef3f2;
        }

        .notice.success {
            border: 1px solid #abefc6;
            color: var(--success);
            background: #ecfdf3;
        }

        .notice ul {
            margin: .5rem 0 0;
            padding-left: 1.2rem;
        }

        fieldset {
            margin: 0;
            padding: 2rem 0;
            border: 0;
            border-bottom: 1px solid var(--line);
        }

        fieldset:first-of-type {
            padding-top: 0;
        }

        fieldset:last-of-type {
            border-bottom: 0;
        }

        legend {
            display: block;
            width: 100%;
            margin-bottom: 1.25rem;
            color: var(--navy);
            font-size: 1.2rem;
            font-weight: 800;
        }

        .section-number {
            display: inline-grid;
            width: 2rem;
            height: 2rem;
            margin-right: .55rem;
            border-radius: 50%;
            color: var(--navy);
            background: var(--yellow);
            place-items: center;
            font-size: .86rem;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }

        .span-2 {
            grid-column: 1 / -1;
        }

        label,
        .label {
            display: block;
            margin-bottom: .45rem;
            color: #344054;
            font-size: .86rem;
            font-weight: 700;
        }

        .required {
            color: #d92d20;
        }

        input,
        select {
            width: 100%;
            min-height: 50px;
            padding: .78rem .9rem;
            border: 1px solid #cbd3df;
            border-radius: 9px;
            color: var(--ink);
            background: var(--white);
            font: inherit;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        input:focus,
        select:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 4px rgba(37, 168, 238, .12);
        }

        input.is-invalid,
        select.is-invalid {
            border-color: var(--danger);
            background: #fffafa;
            box-shadow: 0 0 0 3px rgba(180, 35, 24, .08);
        }

        .field-error {
            margin: .4rem 0 0;
            color: var(--danger);
            font-size: .78rem;
            font-weight: 700;
            line-height: 1.4;
        }

        .choice-grid.is-invalid,
        .declarations.is-invalid {
            padding: .6rem;
            border: 1px solid #fda29b;
            border-radius: 10px;
            background: #fffafa;
        }

        .signature-pad {
            padding: .75rem;
            border: 1px solid #cbd3df;
            border-radius: 12px;
            background: #fff;
            transition: border-color .2s, box-shadow .2s;
        }

        .signature-pad.is-invalid {
            border-color: var(--danger);
            background: #fffafa;
            box-shadow: 0 0 0 3px rgba(180, 35, 24, .08);
        }

        .signature-canvas {
            display: block;
            width: 100%;
            height: 190px;
            border-radius: 9px;
            background:
                linear-gradient(transparent calc(100% - 42px), rgba(6, 22, 58, .15) calc(100% - 42px), rgba(6, 22, 58, .15) calc(100% - 40px), transparent calc(100% - 40px)),
                #fbfcfe;
            touch-action: none;
            cursor: crosshair;
        }

        .signature-actions {
            display: flex;
            margin-top: .75rem;
            align-items: center;
            justify-content: space-between;
            gap: .8rem;
        }

        .signature-clear {
            border: 0;
            color: var(--navy);
            background: transparent;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
            text-decoration: underline;
        }

        input[type="file"] {
            padding: .6rem;
            background: #f8fafc;
        }

        .choice-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
        }

        .choice {
            display: flex;
            min-height: 52px;
            margin: 0;
            padding: .8rem;
            border: 1px solid var(--line);
            border-radius: 9px;
            align-items: center;
            gap: .7rem;
            cursor: pointer;
            font-weight: 700;
        }

        .choice:has(input:checked) {
            border-color: var(--blue);
            color: var(--navy);
            background: #eff9ff;
        }

        .choice input {
            width: 18px;
            min-height: 18px;
            margin: 0;
            flex: 0 0 auto;
            accent-color: var(--navy);
        }

        .declarations {
            display: grid;
            gap: .8rem;
        }

        .declaration {
            display: flex;
            margin: 0;
            align-items: flex-start;
            gap: .7rem;
            font-size: .92rem;
            font-weight: 600;
            line-height: 1.5;
        }

        .declaration input {
            width: 18px;
            min-height: 18px;
            margin-top: 2px;
            flex: 0 0 auto;
            accent-color: var(--navy);
        }

        .hint {
            margin: .45rem 0 0;
            color: var(--muted);
            font-size: .78rem;
            line-height: 1.5;
        }

        .honeypot {
            position: absolute !important;
            left: -9999px !important;
        }

        .submit {
            width: 100%;
            min-height: 58px;
            margin-top: 1.6rem;
            border: 0;
            border-radius: 999px;
            color: var(--navy);
            background: var(--yellow);
            font-size: 1rem;
            font-weight: 900;
            letter-spacing: .05em;
            cursor: pointer;
            text-transform: uppercase;
            transition: transform .2s, box-shadow .2s;
        }

        .submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(255, 212, 0, .28);
        }

        .privacy {
            margin: 1rem 0 0;
            color: var(--muted);
            font-size: .76rem;
            line-height: 1.5;
            text-align: center;
        }

        @media (max-width: 680px) {

            .grid,
            .choice-grid {
                grid-template-columns: 1fr;
            }

            .span-2 {
                grid-column: auto;
            }

            .topbar {
                padding: .75rem 1rem;
            }

            .brand {
                letter-spacing: .1em;
            }

            .brand img {
                width: 48px;
                height: 48px;
            }

            .form-shell {
                width: min(100% - 1rem, 940px);
                border-radius: 18px;
            }
        }
    </style>
</head>

<body>
    <header class="topbar">
        <a class="brand" href="/">
            <img src="logo.png" alt="">
            <span>Lagos Padel Club</span>
        </a>
        <a class="back" href="/">&larr; Back home</a>
    </header>

    <section class="hero">
        <p class="eyebrow">Elite membership</p>
        <h1>Join the club.</h1>
        <p>Tell us about yourself, your padel experience and how you would like to engage with the Lagos Padel Club community.</p>
    </section>

    <main class="form-shell">
        <?php if ($success): ?>
            <div class="notice success">
                <strong>Registration submitted.</strong><br>
                A copy has been sent to your email and to the Lagos Padel Club membership team.
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="notice error">
                <strong>Please correct the following:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form id="registration-form" method="post" enctype="multipart/form-data" action="/register" novalidate>
            <input type="hidden" name="_token" value="<?= escape($_SESSION['registration_token']) ?>">
            <div class="honeypot" aria-hidden="true">
                <label for="website">Website</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <fieldset>
                <legend><span class="section-number">1</span>Personal information</legend>
                <div class="grid">
                    <div class="span-2">
                        <label for="full_name">Full name <span class="required">*</span></label>
                        <input id="full_name" name="full_name" value="<?= field('full_name') ?>" autocomplete="name" maxlength="120">
                    </div>
                    <div>
                        <label for="preferred_name">Preferred name <span class="required">*</span></label>
                        <input id="preferred_name" name="preferred_name" value="<?= field('preferred_name') ?>" maxlength="80">
                    </div>
                    <div>
                        <label for="email">Email address <span class="required">*</span></label>
                        <input id="email" name="email" type="email" value="<?= field('email') ?>" autocomplete="email" maxlength="190">
                    </div>
                    <div>
                        <label for="mobile_number">Mobile number <span class="required">*</span></label>
                        <input id="mobile_number" name="mobile_number" type="tel" value="<?= field('mobile_number') ?>" autocomplete="tel" inputmode="tel" maxlength="30">
                    </div>
                    <div>
                        <label for="whatsapp">WhatsApp number <span class="required">*</span></label>
                        <input id="whatsapp" name="whatsapp" type="tel" value="<?= field('whatsapp') ?>" inputmode="tel" maxlength="30">
                    </div>
                    <div>
                        <label for="linkedin">LinkedIn profile <span class="required">*</span></label>
                        <input id="linkedin" name="linkedin" type="url" value="<?= field('linkedin') ?>" maxlength="255" placeholder="https://linkedin.com/in/...">
                    </div>
                    <div>
                        <label for="instagram">Instagram / Snapchat <span class="required">*</span></label>
                        <input id="instagram" name="instagram" value="<?= field('instagram') ?>" maxlength="80" placeholder="@username">
                    </div>
                    <div class="span-2">
                        <label for="member_photo">Clear applicant photograph <span class="required">*</span></label>
                        <input id="member_photo" name="member_photo" type="file" accept="image/jpeg,image/png,image/webp">
                        <p class="hint">JPG, PNG or WebP, up to 5 MB. This photograph will be attached to the registration email.</p>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend><span class="section-number">2</span>Background details</legend>
                <div class="grid">
                    <div class="span-2">
                        <label for="occupation">Occupation / Industry <span class="required">*</span></label>
                        <input id="occupation" name="occupation" value="<?= field('occupation') ?>" maxlength="120">
                    </div>
                    <div>
                        <label for="company_name">Company / Business name <span class="required">*</span></label>
                        <input id="company_name" name="company_name" value="<?= field('company_name') ?>" maxlength="120">
                    </div>
                    <div>
                        <label for="position">Position / Role <span class="required">*</span></label>
                        <input id="position" name="position" value="<?= field('position') ?>" maxlength="120">
                    </div>
                    <div class="span-2">
                        <label for="date_of_birth">Date of birth <span class="required">*</span></label>
                        <input id="date_of_birth" name="date_of_birth" type="date" value="<?= field('date_of_birth') ?>" max="<?= date('Y-m-d', strtotime('-1 day')) ?>">
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend><span class="section-number">3</span>Padel profile</legend>
                <span class="label">Playing level <span class="required">*</span></span>
                <div class="choice-grid">
                    <label class="choice"><input type="radio" name="playing_level" value="beginner" <?= checked('playing_level', 'beginner') ?>> Beginner</label>
                    <label class="choice"><input type="radio" name="playing_level" value="intermediate" <?= checked('playing_level', 'intermediate') ?>> Intermediate</label>
                    <label class="choice"><input type="radio" name="playing_level" value="advanced" <?= checked('playing_level', 'advanced') ?>> Advanced</label>
                </div>

                <span class="label" style="margin-top:1.5rem;">Club engagement <span class="required">*</span></span>
                <div class="choice-grid">
                    <label class="choice"><input type="checkbox" name="club_engagement[]" value="social_play" <?= checked('club_engagement', 'social_play') ?>> Social play</label>
                    <label class="choice"><input type="checkbox" name="club_engagement[]" value="competitive_matches" <?= checked('club_engagement', 'competitive_matches') ?>> Competitive matches</label>
                    <label class="choice"><input type="checkbox" name="club_engagement[]" value="tournaments" <?= checked('club_engagement', 'tournaments') ?>> Tournaments</label>
                    <label class="choice"><input type="checkbox" name="club_engagement[]" value="club_events" <?= checked('club_engagement', 'club_events') ?>> Club events</label>
                </div>
            </fieldset>

            <fieldset>
                <legend><span class="section-number">4</span>Health &amp; liability declaration</legend>
                <div class="declarations">
                    <label class="declaration"><input type="checkbox" name="fitness_declaration" value="1" <?= checked('fitness_declaration') ?>> I am physically fit to participate in padel activities.</label>
                    <label class="declaration"><input type="checkbox" name="sport_acknowledgement" value="1" <?= checked('sport_acknowledgement') ?>> I understand that padel is a physically demanding sport.</label>
                    <label class="declaration"><input type="checkbox" name="liability_release" value="1" <?= checked('liability_release') ?>> I release Lagos Padel Club from liability for injuries sustained during play.</label>
                </div>
            </fieldset>

            <fieldset>
                <legend><span class="section-number">5</span>Emergency contact</legend>
                <div class="grid">
                    <div>
                        <label for="emergency_name">Name <span class="required">*</span></label>
                        <input id="emergency_name" name="emergency_name" value="<?= field('emergency_name') ?>" maxlength="120">
                    </div>
                    <div>
                        <label for="emergency_relationship">Relationship <span class="required">*</span></label>
                        <input id="emergency_relationship" name="emergency_relationship" value="<?= field('emergency_relationship') ?>" maxlength="80">
                    </div>
                    <div class="span-2">
                        <label for="emergency_phone">Phone <span class="required">*</span></label>
                        <input id="emergency_phone" name="emergency_phone" type="tel" value="<?= field('emergency_phone') ?>" inputmode="tel" maxlength="30">
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend><span class="section-number">6</span>Membership details</legend>
                <span class="label">Media consent <span class="required">*</span></span>
                <div class="choice-grid">
                    <label class="choice"><input type="radio" name="media_consent" value="yes" <?= checked('media_consent', 'yes') ?>> Yes</label>
                    <label class="choice"><input type="radio" name="media_consent" value="no" <?= checked('media_consent', 'no') ?>> No</label>
                </div>

                <span class="label" style="margin-top:1.5rem;">Admission pathway <span class="required">*</span></span>
                <div class="choice-grid">
                    <label class="choice"><input type="radio" name="admission_pathway" value="standard_admission" <?= checked('admission_pathway', 'standard_admission') ?>> Standard admission</label>
                    <label class="choice"><input type="radio" name="admission_pathway" value="curated_entry" <?= checked('admission_pathway', 'curated_entry') ?>> Curated entry</label>
                </div>

                <div class="grid" style="margin-top:1.5rem;">
                    <div class="span-2">
                        <label for="inviting_member">Inviting member <span class="required">*</span></label>
                        <input id="inviting_member" name="inviting_member" value="<?= field('inviting_member') ?>" maxlength="120">
                    </div>
                    <div>
                        <label for="supporting_member_1">Supporting member 1 <span class="required">*</span></label>
                        <input id="supporting_member_1" name="supporting_member_1" value="<?= field('supporting_member_1') ?>" maxlength="120">
                    </div>
                    <div>
                        <label for="supporting_member_2">Supporting member 2 <span class="required">*</span></label>
                        <input id="supporting_member_2" name="supporting_member_2" value="<?= field('supporting_member_2') ?>" maxlength="120">
                    </div>
                    <div class="span-2">
                        <label for="supporting_member_3">Supporting member 3 <span class="required">*</span></label>
                        <input id="supporting_member_3" name="supporting_member_3" value="<?= field('supporting_member_3') ?>" maxlength="120">
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend><span class="section-number">7</span>Declaration</legend>
                <label class="declaration">
                    <input type="checkbox" name="club_declaration" value="1" <?= checked('club_declaration') ?>>
                    I agree to uphold the standards, culture and integrity of Lagos Padel Club. I understand that membership is selective and may be revoked if club standards are not maintained.
                </label>
                <div class="grid" style="margin-top:1.5rem;">
                    <div class="span-2">
                        <span class="label">Signature <span class="required">*</span></span>
                        <div class="signature-pad" id="signature-pad">
                            <canvas class="signature-canvas" id="signature-canvas" aria-label="Signature pad" tabindex="0"></canvas>
                            <div class="signature-actions">
                                <p class="hint">Sign with your finger, mouse or trackpad.</p>
                                <button class="signature-clear" type="button" id="signature-clear">Clear</button>
                            </div>
                        </div>
                        <input id="signature_data" name="signature_data" type="hidden" value="<?= field('signature_data') ?>">
                    </div>
                    <div class="span-2">
                        <label for="declaration_date">Date <span class="required">*</span></label>
                        <input id="declaration_date" name="declaration_date" type="date" value="<?= field('declaration_date') ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                </div>
            </fieldset>

            <button class="submit" type="submit">Submit registration</button>
            <p class="privacy">Your details and photograph are used only to process your Lagos Padel Club membership application.</p>
        </form>
    </main>
    <script>
        (() => {
            const form = document.getElementById('registration-form');
            const serverErrors = <?= json_encode($fieldErrors, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const canvas = document.getElementById('signature-canvas');
            const pad = document.getElementById('signature-pad');
            const signatureInput = document.getElementById('signature_data');
            const clearSignature = document.getElementById('signature-clear');
            const context = canvas.getContext('2d');
            let hasSignature = signatureInput.value !== '';
            let isDrawing = false;
            let lastPoint = null;

            const controlsFor = (name) => Array.from(form.querySelectorAll(
                `[name="${CSS.escape(name)}"], [name="${CSS.escape(name)}[]"]`
            ));

            const errorHost = (control) => {
                if (control.name === 'signature_data') {
                    return pad.parentElement;
                }

                if (control.type === 'radio' || control.type === 'checkbox') {
                    return control.closest('.choice-grid, .declarations') || control.closest('fieldset');
                }

                return control.parentElement;
            };

            const clearError = (name) => {
                controlsFor(name).forEach((control) => {
                    control.classList.remove('is-invalid');
                    control.removeAttribute('aria-invalid');
                    const group = control.closest('.choice-grid, .declarations');
                    if (group) group.classList.remove('is-invalid');
                });

                if (name === 'signature_data') {
                    pad.classList.remove('is-invalid');
                    signatureInput.removeAttribute('aria-invalid');
                }

                form.querySelectorAll(`[data-error-for="${CSS.escape(name)}"]`).forEach((error) => error.remove());
            };

            const showError = (name, message) => {
                const controls = controlsFor(name);
                if (!controls.length) return;

                clearError(name);
                controls.forEach((control) => {
                    control.classList.add('is-invalid');
                    control.setAttribute('aria-invalid', 'true');
                    const group = control.closest('.choice-grid, .declarations');
                    if (group) group.classList.add('is-invalid');
                });

                if (name === 'signature_data') {
                    pad.classList.add('is-invalid');
                    signatureInput.setAttribute('aria-invalid', 'true');
                }

                const error = document.createElement('p');
                error.className = 'field-error';
                error.dataset.errorFor = name;
                error.textContent = message;
                errorHost(controls[0]).appendChild(error);
            };

            const resizeCanvas = () => {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const rect = canvas.getBoundingClientRect();
                const existing = signatureInput.value;

                canvas.width = Math.floor(rect.width * ratio);
                canvas.height = Math.floor(rect.height * ratio);
                context.setTransform(ratio, 0, 0, ratio, 0, 0);
                context.lineWidth = 2.5;
                context.lineCap = 'round';
                context.lineJoin = 'round';
                context.strokeStyle = '#06163a';

                if (existing) {
                    const image = new Image();
                    image.onload = () => context.drawImage(image, 0, 0, rect.width, rect.height);
                    image.src = existing;
                }
            };

            const pointFromEvent = (event) => {
                const rect = canvas.getBoundingClientRect();

                return {
                    x: event.clientX - rect.left,
                    y: event.clientY - rect.top
                };
            };

            const saveSignature = () => {
                signatureInput.value = canvas.toDataURL('image/png');
                hasSignature = true;
                clearError('signature_data');
            };

            const clearSignatureCanvas = () => {
                context.clearRect(0, 0, canvas.width, canvas.height);
                signatureInput.value = '';
                hasSignature = false;
                clearError('signature_data');
            };

            const startDrawing = (event) => {
                event.preventDefault();
                isDrawing = true;
                lastPoint = pointFromEvent(event);
                canvas.setPointerCapture?.(event.pointerId);
            };

            const draw = (event) => {
                if (!isDrawing || !lastPoint) return;

                event.preventDefault();
                const point = pointFromEvent(event);
                context.beginPath();
                context.moveTo(lastPoint.x, lastPoint.y);
                context.lineTo(point.x, point.y);
                context.stroke();
                lastPoint = point;
                saveSignature();
            };

            const stopDrawing = () => {
                isDrawing = false;
                lastPoint = null;
            };

            const requiredFields = {
                full_name: 'Full name',
                preferred_name: 'Preferred name',
                email: 'Email address',
                mobile_number: 'Mobile number',
                whatsapp: 'WhatsApp number',
                linkedin: 'LinkedIn profile',
                instagram: 'Instagram / Snapchat',
                occupation: 'Occupation / Industry',
                company_name: 'Company / Business name',
                position: 'Position / Role',
                date_of_birth: 'Date of birth',
                emergency_name: 'Emergency contact name',
                emergency_relationship: 'Emergency contact relationship',
                emergency_phone: 'Emergency contact phone',
                inviting_member: 'Inviting member',
                supporting_member_1: 'Supporting member 1',
                supporting_member_2: 'Supporting member 2',
                supporting_member_3: 'Supporting member 3',
                declaration_date: 'Declaration date'
            };

            const clearClientErrors = () => Object.keys(requiredFields)
                .concat([
                    'member_photo', 'playing_level', 'club_engagement', 'media_consent',
                    'admission_pathway', 'fitness_declaration', 'sport_acknowledgement',
                    'liability_release', 'club_declaration', 'signature_data'
                ])
                .forEach(clearError);

            const validPhone = (value) => /^\+?[0-9\s().-]{7,30}$/.test(value);
            const validUrl = (value) => {
                try {
                    const url = new URL(value);
                    return url.protocol === 'http:' || url.protocol === 'https:';
                } catch {
                    return false;
                }
            };
            const validPastDate = (value) => value !== '' && new Date(`${value}T00:00:00`) < new Date(new Date().setHours(0, 0, 0, 0));
            const validTodayOrEarlier = (value) => value !== '' && new Date(`${value}T00:00:00`) <= new Date(new Date().setHours(0, 0, 0, 0));

            const validateForm = () => {
                clearClientErrors();
                let firstInvalid = null;
                const invalidate = (name, message, focusTarget = null) => {
                    showError(name, message);
                    firstInvalid ||= focusTarget || controlsFor(name)[0];
                };

                Object.entries(requiredFields).forEach(([name, label]) => {
                    if (String(form.elements[name]?.value || '').trim() === '') {
                        invalidate(name, `${label} is required.`);
                    }
                });

                const email = String(form.elements.email.value || '').trim();
                if (email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    invalidate('email', 'Enter a valid email address.');
                }

                ['mobile_number', 'whatsapp', 'emergency_phone'].forEach((name) => {
                    const value = String(form.elements[name].value || '').trim();
                    if (value !== '' && !validPhone(value)) {
                        invalidate(name, 'Enter a valid phone number.');
                    }
                });

                const linkedin = String(form.elements.linkedin.value || '').trim();
                if (linkedin !== '' && !validUrl(linkedin)) {
                    invalidate('linkedin', 'Enter a complete LinkedIn URL beginning with http:// or https://.');
                }

                if (form.elements.date_of_birth.value && !validPastDate(form.elements.date_of_birth.value)) {
                    invalidate('date_of_birth', 'Enter a valid date of birth in the past.');
                }
                if (form.elements.declaration_date.value && !validTodayOrEarlier(form.elements.declaration_date.value)) {
                    invalidate('declaration_date', 'Declaration date cannot be in the future.');
                }

                ['playing_level', 'media_consent', 'admission_pathway'].forEach((name) => {
                    if (!form.querySelector(`[name="${name}"]:checked`)) {
                        invalidate(name, `Select a ${name.replace(/_/g, ' ')} option.`);
                    }
                });

                if (!form.querySelector('[name="club_engagement[]"]:checked')) {
                    invalidate('club_engagement', 'Select at least one club engagement option.');
                }

                ['fitness_declaration', 'sport_acknowledgement', 'liability_release', 'club_declaration'].forEach((name) => {
                    if (!form.elements[name].checked) {
                        invalidate(name, 'This declaration must be accepted.');
                    }
                });

                const photo = form.elements.member_photo;
                if (!photo.files.length) {
                    invalidate('member_photo', 'Upload a clear photograph of the applicant.');
                } else if (photo.files[0].size > 5 * 1024 * 1024) {
                    invalidate('member_photo', 'The applicant photograph must be 5 MB or smaller.');
                }

                if (!hasSignature || !signatureInput.value) {
                    invalidate('signature_data', 'Please sign inside the signature box.', canvas);
                }

                return firstInvalid;
            };

            resizeCanvas();
            Object.entries(serverErrors).forEach(([name, message]) => showError(name, message));
            window.addEventListener('resize', resizeCanvas);
            canvas.addEventListener('pointerdown', startDrawing);
            canvas.addEventListener('pointermove', draw);
            canvas.addEventListener('pointerup', stopDrawing);
            canvas.addEventListener('pointercancel', stopDrawing);
            canvas.addEventListener('pointerleave', stopDrawing);
            clearSignature.addEventListener('click', clearSignatureCanvas);

            form.addEventListener('change', (event) => {
                if (event.target.name) clearError(event.target.name.replace('[]', ''));
            });

            form.addEventListener('input', (event) => {
                if (event.target.name) clearError(event.target.name.replace('[]', ''));
            });

            form.addEventListener('submit', (event) => {
                const firstInvalid = validateForm();

                if (firstInvalid) {
                    event.preventDefault();
                    firstInvalid.focus({
                        preventScroll: true
                    });
                    firstInvalid.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
            });
        })();
    </script>
</body>

</html>
