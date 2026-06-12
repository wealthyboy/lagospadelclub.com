<?php
declare(strict_types=1);

session_start();

const ADMIN_EMAIL = 'admin@lagospadelclub.com';
const MAX_PHOTO_SIZE = 5 * 1024 * 1024;

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

function buildEmail(array $details, array $photo, string $boundary): string
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

    $html = '<!doctype html><html><body style="margin:0;background:#eef2f6;font-family:Arial,sans-serif;color:#101828;">'
        . '<div style="max-width:680px;margin:0 auto;padding:30px 16px;">'
        . '<div style="background:#06163a;padding:28px;text-align:center;border-radius:18px 18px 0 0;">'
        . '<img src="https://lagospadelclub.com/logo.png" width="130" alt="Lagos Padel Club" style="display:block;margin:0 auto 14px;">'
        . '<div style="color:#ffd400;font-size:13px;font-weight:800;letter-spacing:2px;text-transform:uppercase;">Elite Membership</div>'
        . '<h1 style="margin:8px 0 0;color:#fff;font-size:28px;">Registration received</h1>'
        . '</div>'
        . '<div style="background:#fff;padding:24px 18px;border-radius:0 0 18px 18px;">'
        . '<p style="margin:0 18px 14px;line-height:1.7;color:#475467;">Thank you for registering with Lagos Padel Club. A copy of your submitted information is below. Your photograph is attached to this email.</p>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">'
        . $rows
        . '</table>'
        . '<p style="margin:24px 18px 4px;color:#667085;font-size:13px;line-height:1.6;">The club team will review this application and contact the applicant with the next steps.</p>'
        . '</div></div></body></html>';

    $attachment = chunk_split(base64_encode((string) file_get_contents($photo['tmp_name'])));
    $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $photo['name']) ?: 'member-photo.jpg';

    return "--{$boundary}\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $html . "\r\n"
        . "--{$boundary}\r\n"
        . "Content-Type: {$photo['mime']}; name=\"{$filename}\"\r\n"
        . "Content-Transfer-Encoding: base64\r\n"
        . "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n"
        . $attachment . "\r\n"
        . "--{$boundary}--";
}

if (empty($_SESSION['registration_token'])) {
    $_SESSION['registration_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = isset($_GET['submitted']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['_token'] ?? '');

    if (!hash_equals((string) $_SESSION['registration_token'], $token)) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }

    if (rawField('website') !== '') {
        $errors[] = 'We could not process this registration.';
    }

    $required = [
        'full_name' => 'Full name',
        'mobile_number' => 'Mobile number',
        'email' => 'Email address',
        'occupation' => 'Occupation or industry',
        'date_of_birth' => 'Date of birth',
        'playing_level' => 'Playing level',
        'emergency_name' => 'Emergency contact name',
        'emergency_relationship' => 'Emergency contact relationship',
        'emergency_phone' => 'Emergency contact phone',
        'media_consent' => 'Media consent',
        'admission_pathway' => 'Admission pathway',
        'signature' => 'Signature',
        'declaration_date' => 'Declaration date',
    ];

    foreach ($required as $name => $label) {
        if (rawField($name) === '') {
            $errors[] = "{$label} is required.";
        }
    }

    $email = filter_var(rawField('email'), FILTER_VALIDATE_EMAIL);

    if ($email === false || preg_match('/[\r\n]/', rawField('email'))) {
        $errors[] = 'Enter a valid email address.';
    }

    $allowedLevels = ['beginner', 'intermediate', 'advanced'];
    $allowedConsent = ['yes', 'no'];
    $allowedPathways = ['standard_admission', 'curated_entry'];

    if (!in_array(rawField('playing_level'), $allowedLevels, true)) {
        $errors[] = 'Choose a valid playing level.';
    }

    if (!in_array(rawField('media_consent'), $allowedConsent, true)) {
        $errors[] = 'Choose a valid media consent option.';
    }

    if (!in_array(rawField('admission_pathway'), $allowedPathways, true)) {
        $errors[] = 'Choose a valid admission pathway.';
    }

    foreach (['fitness_declaration', 'sport_acknowledgement', 'liability_release', 'club_declaration'] as $declaration) {
        if (rawField($declaration) !== '1') {
            $errors[] = 'All health, liability and club declarations must be accepted.';
            break;
        }
    }

    $photo = $_FILES['member_photo'] ?? null;
    $photoData = null;

    if (!$photo || (int) $photo['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Upload a clear photograph of the applicant.';
    } elseif ((int) $photo['size'] > MAX_PHOTO_SIZE) {
        $errors[] = 'The applicant photograph must be 5 MB or smaller.';
    } else {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($photo['tmp_name']);
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($mime, $allowedTypes, true)) {
            $errors[] = 'The applicant photograph must be a JPG, PNG or WebP image.';
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
            static fn (string $item): string => formatLabel($item),
            array_values(array_intersect(
                (array) ($_POST['club_engagement'] ?? []),
                ['social_play', 'competitive_matches', 'tournaments', 'club_events']
            ))
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
                'Signed by' => rawField('signature'),
                'Declaration date' => rawField('declaration_date'),
            ],
        ];

        $boundary = '=_LPC_' . bin2hex(random_bytes(16));
        $subject = 'Lagos Padel Club membership registration - ' . rawField('full_name');
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = [
            'MIME-Version: 1.0',
            "Content-Type: multipart/mixed; boundary=\"{$boundary}\"",
            'From: Lagos Padel Club <' . ADMIN_EMAIL . '>',
            'Reply-To: ' . rawField('email'),
            'X-Mailer: PHP/' . PHP_VERSION,
        ];
        $recipients = rawField('email') . ', ' . ADMIN_EMAIL;
        $message = buildEmail($details, $photoData, $boundary);

        if (mail($recipients, $encodedSubject, $message, implode("\r\n", $headers))) {
            $_SESSION['registration_token'] = bin2hex(random_bytes(32));
            header('Location: register.php?submitted=1');
            exit;
        }

        $errors[] = 'We could not send your registration right now. Please try again shortly.';
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

        * { box-sizing: border-box; }

        body {
            min-width: 320px;
            margin: 0;
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at 90% 4%, rgba(37, 168, 238, .12), transparent 24rem),
                var(--surface);
        }

        a { color: inherit; }

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

        fieldset:first-of-type { padding-top: 0; }
        fieldset:last-of-type { border-bottom: 0; }

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

        .span-2 { grid-column: 1 / -1; }

        label, .label {
            display: block;
            margin-bottom: .45rem;
            color: #344054;
            font-size: .86rem;
            font-weight: 700;
        }

        .required { color: #d92d20; }

        input, select {
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

        input:focus, select:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 4px rgba(37, 168, 238, .12);
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
            .grid, .choice-grid { grid-template-columns: 1fr; }
            .span-2 { grid-column: auto; }
            .topbar { padding: .75rem 1rem; }
            .brand { letter-spacing: .1em; }
            .brand img { width: 48px; height: 48px; }
            .form-shell { width: min(100% - 1rem, 940px); border-radius: 18px; }
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

        <form method="post" enctype="multipart/form-data" action="register.php">
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
                        <input id="full_name" name="full_name" value="<?= field('full_name') ?>" autocomplete="name" required>
                    </div>
                    <div>
                        <label for="preferred_name">Preferred name</label>
                        <input id="preferred_name" name="preferred_name" value="<?= field('preferred_name') ?>">
                    </div>
                    <div>
                        <label for="email">Email address <span class="required">*</span></label>
                        <input id="email" name="email" type="email" value="<?= field('email') ?>" autocomplete="email" required>
                    </div>
                    <div>
                        <label for="mobile_number">Mobile number <span class="required">*</span></label>
                        <input id="mobile_number" name="mobile_number" type="tel" value="<?= field('mobile_number') ?>" autocomplete="tel" required>
                    </div>
                    <div>
                        <label for="whatsapp">WhatsApp number</label>
                        <input id="whatsapp" name="whatsapp" type="tel" value="<?= field('whatsapp') ?>">
                    </div>
                    <div>
                        <label for="linkedin">LinkedIn profile</label>
                        <input id="linkedin" name="linkedin" value="<?= field('linkedin') ?>" placeholder="https://linkedin.com/in/...">
                    </div>
                    <div>
                        <label for="instagram">Instagram / Snapchat</label>
                        <input id="instagram" name="instagram" value="<?= field('instagram') ?>" placeholder="@username">
                    </div>
                    <div class="span-2">
                        <label for="member_photo">Clear applicant photograph <span class="required">*</span></label>
                        <input id="member_photo" name="member_photo" type="file" accept="image/jpeg,image/png,image/webp" required>
                        <p class="hint">JPG, PNG or WebP, up to 5 MB. This photograph will be attached to the registration email.</p>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend><span class="section-number">2</span>Background details</legend>
                <div class="grid">
                    <div class="span-2">
                        <label for="occupation">Occupation / Industry <span class="required">*</span></label>
                        <input id="occupation" name="occupation" value="<?= field('occupation') ?>" required>
                    </div>
                    <div>
                        <label for="company_name">Company / Business name</label>
                        <input id="company_name" name="company_name" value="<?= field('company_name') ?>">
                    </div>
                    <div>
                        <label for="position">Position / Role</label>
                        <input id="position" name="position" value="<?= field('position') ?>">
                    </div>
                    <div class="span-2">
                        <label for="date_of_birth">Date of birth <span class="required">*</span></label>
                        <input id="date_of_birth" name="date_of_birth" type="date" value="<?= field('date_of_birth') ?>" required>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend><span class="section-number">3</span>Padel profile</legend>
                <span class="label">Playing level <span class="required">*</span></span>
                <div class="choice-grid">
                    <label class="choice"><input type="radio" name="playing_level" value="beginner"<?= checked('playing_level', 'beginner') ?> required> Beginner</label>
                    <label class="choice"><input type="radio" name="playing_level" value="intermediate"<?= checked('playing_level', 'intermediate') ?>> Intermediate</label>
                    <label class="choice"><input type="radio" name="playing_level" value="advanced"<?= checked('playing_level', 'advanced') ?>> Advanced</label>
                </div>

                <span class="label" style="margin-top:1.5rem;">Club engagement</span>
                <div class="choice-grid">
                    <label class="choice"><input type="checkbox" name="club_engagement[]" value="social_play"<?= checked('club_engagement', 'social_play') ?>> Social play</label>
                    <label class="choice"><input type="checkbox" name="club_engagement[]" value="competitive_matches"<?= checked('club_engagement', 'competitive_matches') ?>> Competitive matches</label>
                    <label class="choice"><input type="checkbox" name="club_engagement[]" value="tournaments"<?= checked('club_engagement', 'tournaments') ?>> Tournaments</label>
                    <label class="choice"><input type="checkbox" name="club_engagement[]" value="club_events"<?= checked('club_engagement', 'club_events') ?>> Club events</label>
                </div>
            </fieldset>

            <fieldset>
                <legend><span class="section-number">4</span>Health &amp; liability declaration</legend>
                <div class="declarations">
                    <label class="declaration"><input type="checkbox" name="fitness_declaration" value="1"<?= checked('fitness_declaration') ?> required> I am physically fit to participate in padel activities.</label>
                    <label class="declaration"><input type="checkbox" name="sport_acknowledgement" value="1"<?= checked('sport_acknowledgement') ?> required> I understand that padel is a physically demanding sport.</label>
                    <label class="declaration"><input type="checkbox" name="liability_release" value="1"<?= checked('liability_release') ?> required> I release Lagos Padel Club from liability for injuries sustained during play.</label>
                </div>
            </fieldset>

            <fieldset>
                <legend><span class="section-number">5</span>Emergency contact</legend>
                <div class="grid">
                    <div>
                        <label for="emergency_name">Name <span class="required">*</span></label>
                        <input id="emergency_name" name="emergency_name" value="<?= field('emergency_name') ?>" required>
                    </div>
                    <div>
                        <label for="emergency_relationship">Relationship <span class="required">*</span></label>
                        <input id="emergency_relationship" name="emergency_relationship" value="<?= field('emergency_relationship') ?>" required>
                    </div>
                    <div class="span-2">
                        <label for="emergency_phone">Phone <span class="required">*</span></label>
                        <input id="emergency_phone" name="emergency_phone" type="tel" value="<?= field('emergency_phone') ?>" required>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend><span class="section-number">6</span>Membership details</legend>
                <span class="label">Media consent <span class="required">*</span></span>
                <div class="choice-grid">
                    <label class="choice"><input type="radio" name="media_consent" value="yes"<?= checked('media_consent', 'yes') ?> required> Yes</label>
                    <label class="choice"><input type="radio" name="media_consent" value="no"<?= checked('media_consent', 'no') ?>> No</label>
                </div>

                <span class="label" style="margin-top:1.5rem;">Admission pathway <span class="required">*</span></span>
                <div class="choice-grid">
                    <label class="choice"><input type="radio" name="admission_pathway" value="standard_admission"<?= checked('admission_pathway', 'standard_admission') ?> required> Standard admission</label>
                    <label class="choice"><input type="radio" name="admission_pathway" value="curated_entry"<?= checked('admission_pathway', 'curated_entry') ?>> Curated entry</label>
                </div>

                <div class="grid" style="margin-top:1.5rem;">
                    <div class="span-2">
                        <label for="inviting_member">Inviting member <span class="hint">(for standard admission)</span></label>
                        <input id="inviting_member" name="inviting_member" value="<?= field('inviting_member') ?>">
                    </div>
                    <div>
                        <label for="supporting_member_1">Supporting member 1</label>
                        <input id="supporting_member_1" name="supporting_member_1" value="<?= field('supporting_member_1') ?>">
                    </div>
                    <div>
                        <label for="supporting_member_2">Supporting member 2</label>
                        <input id="supporting_member_2" name="supporting_member_2" value="<?= field('supporting_member_2') ?>">
                    </div>
                    <div class="span-2">
                        <label for="supporting_member_3">Supporting member 3</label>
                        <input id="supporting_member_3" name="supporting_member_3" value="<?= field('supporting_member_3') ?>">
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend><span class="section-number">7</span>Declaration</legend>
                <label class="declaration">
                    <input type="checkbox" name="club_declaration" value="1"<?= checked('club_declaration') ?> required>
                    I agree to uphold the standards, culture and integrity of Lagos Padel Club. I understand that membership is selective and may be revoked if club standards are not maintained.
                </label>
                <div class="grid" style="margin-top:1.5rem;">
                    <div>
                        <label for="signature">Type your full name as signature <span class="required">*</span></label>
                        <input id="signature" name="signature" value="<?= field('signature') ?>" required>
                    </div>
                    <div>
                        <label for="declaration_date">Date <span class="required">*</span></label>
                        <input id="declaration_date" name="declaration_date" type="date" value="<?= field('declaration_date') ?>" required>
                    </div>
                </div>
            </fieldset>

            <button class="submit" type="submit">Submit registration</button>
            <p class="privacy">Your details and photograph are used only to process your Lagos Padel Club membership application.</p>
        </form>
    </main>
</body>
</html>
