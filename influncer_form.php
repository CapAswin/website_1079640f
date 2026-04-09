<?php
$message = '';
$messageType = '';
// db.php must be in the same directory as this file (use / so path does not concatenate as one filename)
$db_file = __DIR__ . DIRECTORY_SEPARATOR . 'db.php';
if (!is_file($db_file)) {
    $db_file = dirname(__FILE__) . '/db.php';
}
require_once $db_file;

// Use connection only if it exists and is connected (db.php sets $conn = null on failure)
$db_ok = false;
if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    try {
        @$conn->ping();
        $db_ok = true;
    } catch (Throwable $e) {
        $db_ok = false;
    }
}
if (!$db_ok) {
    $message = 'Database is temporarily unavailable. Please try again later.';
    $messageType = 'error';
}

// Load active platforms from social_platform for form (checkboxes + blocks)
$platforms = [];
if ($db_ok) {
    $r = @$conn->query("SELECT platform_id, platform_name FROM social_platform WHERE status = 1 AND (deleted = 0 OR deleted IS NULL) ORDER BY platform_id");
    if ($r && $r->num_rows > 0) {
        $slugMap = ['Instagram' => 'instagram', 'YouTube' => 'youtube', 'TikTok' => 'tiktok', 'Facebook' => 'facebook', 'Twitter' => 'twitter'];
        while ($row = $r->fetch_assoc()) {
            $platforms[] = [
                'id'   => (int)$row['platform_id'],
                'name' => $row['platform_name'],
                'slug' => $slugMap[$row['platform_name']] ?? strtolower(preg_replace('/\s+/', '_', $row['platform_name'])),
            ];
        }
        $r->free();
    }
}

if ($db_ok && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');

    if (empty($email) || empty($password) || empty($full_name)) {
        $message = 'Email, password and full name are required.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email format.';
        $messageType = 'error';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters.';
        $messageType = 'error';
    } elseif (isset($_POST['confirm_password']) && $password !== $_POST['confirm_password']) {
        $message = 'Passwords do not match.';
        $messageType = 'error';
    } else {
        $chk = $conn->prepare("SELECT influencer_id FROM influencer WHERE email = ?");
        $chk->bind_param("s", $email);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $chk->close();
            $message = 'This email is already registered.';
            $messageType = 'error';
        } else {
            $chk->close();
            $username = preg_replace('/[^a-zA-Z0-9_]/', '', explode('@', $email)[0]) ?: 'user';
            $username = substr($username . '_' . rand(100, 999), 0, 50);
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $profile_image = null;
            if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/uploads/influencers';
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0755, true);
                }
                $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION)) ?: 'jpg';
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) $ext = 'jpg';
                $filename = 'profile_' . time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '', $username) . '.' . $ext;
                $path = $upload_dir . '/' . $filename;
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $path)) {
                    $profile_image = 'uploads/influencers/' . $filename;
                }
            }

            $phone = trim($_POST['phone'] ?? '');
            $country_id = !empty($_POST['country_id']) ? (int)$_POST['country_id'] : null;
            $province_id = !empty($_POST['province_id']) ? (int)$_POST['province_id'] : null;
            $city_name = trim($_POST['city_name'] ?? '') ?: null;
            $date_of_birth = trim($_POST['date_of_birth'] ?? '') ?: null;
            $gender = isset($_POST['gender']) && $_POST['gender'] !== '' ? (int)$_POST['gender'] : null;
            $influencer_type = isset($_POST['influencer_type']) && $_POST['influencer_type'] !== '' ? (int)$_POST['influencer_type'] : 0;
            $bio = trim($_POST['bio'] ?? '') ?: null;
            $experience_since = !empty($_POST['experience_since']) ? (int)$_POST['experience_since'] : null;
            $past_brands_raw = trim($_POST['past_brands'] ?? '');
            $primary_niche_id = !empty($_POST['primary_niche_id']) ? (int)$_POST['primary_niche_id'] : null;
            $secondary_niche_id = !empty($_POST['secondary_niche_id']) ? (int)$_POST['secondary_niche_id'] : null;

            // influencer table has no past_brands column; it is stored in content_pricing as JSON
            $stmt = $conn->prepare("
                INSERT INTO influencer (username, full_name, email, password_hash, phone, country_id, province_id, city_name, date_of_birth, gender, influencer_type, bio, profile_image, experience_since, primary_niche_id, secondary_niche_id, email_verified, account_status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, NOW(), NOW())
            ");
            $stmt->bind_param("sssssiissiissiii", $username, $full_name, $email, $password_hash, $phone, $country_id, $province_id, $city_name, $date_of_birth, $gender, $influencer_type, $bio, $profile_image, $experience_since, $primary_niche_id, $secondary_niche_id);

            if ($stmt->execute()) {
                $influencer_id = $conn->insert_id;
                $stmt->close();

                $conn->query("INSERT INTO wallet (influencer_id, current_balance_points, balance_currency) VALUES ($influencer_id, 0, 0)");

                if ($country_id && $city_name) {
                    $city_country = $country_id;
                    $city_province = $province_id;
                    $city_stmt = $conn->prepare("INSERT INTO influncer_city (influencer_id, country_id, province_id, city_name) VALUES (?, ?, ?, ?)");
                    $city_stmt->bind_param("iiis", $influencer_id, $city_country, $city_province, $city_name);
                    $city_stmt->execute();
                    $city_stmt->close();
                }

                $platform_instagram = !empty($_POST['platform_instagram']);
                $ig_user = trim($_POST['instagram_username'] ?? '');
                $ig_link = trim($_POST['instagram_profile_link'] ?? '');
                if ($platform_instagram && $ig_user !== '' && $ig_link !== '') {
                    $ins = $conn->prepare("INSERT INTO influencer_social_account (influencer_id, platform_id, username, profile_link, is_verified) VALUES (?, 1, ?, ?, 0)");
                    $ins->bind_param("iss", $influencer_id, $ig_user, $ig_link);
                    $ins->execute();
                    $ins->close();
                    $ig_f = !empty($_POST['instagram_followers']) ? (int)$_POST['instagram_followers'] : 0;
                    $ig_eng = !empty($_POST['instagram_engagement']) ? (float)$_POST['instagram_engagement'] : null;
                    if ($ig_eng !== null) { $ig_eng = $ig_eng > 1 ? $ig_eng / 100 : $ig_eng; $ig_eng = min(1.0, max(0.0, (float)$ig_eng)); }
                    $pm = $conn->prepare("INSERT INTO platform_metrics (influencer_id, platform_id, followers_count, average_reach, engagement_rate) VALUES (?, 1, ?, NULL, ?)");
                    $pm->bind_param("iid", $influencer_id, $ig_f, $ig_eng);
                    $pm->execute();
                    $pm->close();
                }
                $platform_youtube = !empty($_POST['platform_youtube']);
                $yt_user = trim($_POST['youtube_username'] ?? '');
                $yt_link = trim($_POST['youtube_profile_link'] ?? '');
                if ($platform_youtube && $yt_user !== '' && $yt_link !== '') {
                    $ins = $conn->prepare("INSERT INTO influencer_social_account (influencer_id, platform_id, username, profile_link, is_verified) VALUES (?, 2, ?, ?, 0)");
                    $ins->bind_param("iss", $influencer_id, $yt_user, $yt_link);
                    $ins->execute();
                    $ins->close();
                    $yt_s = !empty($_POST['youtube_subscribers']) ? (int)$_POST['youtube_subscribers'] : 0;
                    $yt_v = !empty($_POST['youtube_views']) ? (int)$_POST['youtube_views'] : null;
                    $pm = $conn->prepare("INSERT INTO platform_metrics (influencer_id, platform_id, followers_count, average_views, engagement_rate) VALUES (?, 2, ?, ?, NULL)");
                    $pm->bind_param("iii", $influencer_id, $yt_s, $yt_v);
                    $pm->execute();
                    $pm->close();
                }
                $platform_tiktok = !empty($_POST['platform_tiktok']);
                $tk_user = trim($_POST['tiktok_username'] ?? '');
                $tk_link = trim($_POST['tiktok_profile_link'] ?? '');
                if ($platform_tiktok && $tk_user !== '' && $tk_link !== '') {
                    $ins = $conn->prepare("INSERT INTO influencer_social_account (influencer_id, platform_id, username, profile_link, is_verified) VALUES (?, 3, ?, ?, 0)");
                    $ins->bind_param("iss", $influencer_id, $tk_user, $tk_link);
                    $ins->execute();
                    $ins->close();
                    $tk_f = !empty($_POST['tiktok_followers']) ? (int)$_POST['tiktok_followers'] : 0;
                    $tk_v = !empty($_POST['tiktok_views']) ? (int)$_POST['tiktok_views'] : null;
                    $pm = $conn->prepare("INSERT INTO platform_metrics (influencer_id, platform_id, followers_count, average_views) VALUES (?, 3, ?, ?)");
                    $pm->bind_param("iii", $influencer_id, $tk_f, $tk_v);
                    $pm->execute();
                    $pm->close();
                }
                $platform_facebook = !empty($_POST['platform_facebook']);
                $fb_user = trim($_POST['facebook_username'] ?? '');
                $fb_link = trim($_POST['facebook_profile_link'] ?? '');
                if ($platform_facebook && $fb_user !== '' && $fb_link !== '') {
                    $ins = $conn->prepare("INSERT INTO influencer_social_account (influencer_id, platform_id, username, profile_link, is_verified) VALUES (?, 4, ?, ?, 0)");
                    $ins->bind_param("iss", $influencer_id, $fb_user, $fb_link);
                    $ins->execute();
                    $ins->close();
                    $fb_f = !empty($_POST['facebook_followers']) ? (int)$_POST['facebook_followers'] : 0;
                    $fb_eng = !empty($_POST['facebook_engagement']) ? (float)$_POST['facebook_engagement'] : null;
                    if ($fb_eng !== null) { $fb_eng = $fb_eng > 1 ? $fb_eng / 100 : $fb_eng; $fb_eng = min(1.0, max(0.0, (float)$fb_eng)); }
                    $pm = $conn->prepare("INSERT INTO platform_metrics (influencer_id, platform_id, followers_count, average_reach, engagement_rate) VALUES (?, 4, ?, NULL, ?)");
                    $pm->bind_param("iid", $influencer_id, $fb_f, $fb_eng);
                    $pm->execute();
                    $pm->close();
                }
                $platform_twitter = !empty($_POST['platform_twitter']);
                $tw_user = trim($_POST['twitter_username'] ?? '');
                $tw_link = trim($_POST['twitter_profile_link'] ?? '');
                if ($platform_twitter && $tw_user !== '' && $tw_link !== '') {
                    $ins = $conn->prepare("INSERT INTO influencer_social_account (influencer_id, platform_id, username, profile_link, is_verified) VALUES (?, 5, ?, ?, 0)");
                    $ins->bind_param("iss", $influencer_id, $tw_user, $tw_link);
                    $ins->execute();
                    $ins->close();
                    $tw_f = !empty($_POST['twitter_followers']) ? (int)$_POST['twitter_followers'] : 0;
                    $tw_eng = !empty($_POST['twitter_engagement']) ? (float)$_POST['twitter_engagement'] : null;
                    if ($tw_eng !== null) { $tw_eng = $tw_eng > 1 ? $tw_eng / 100 : $tw_eng; $tw_eng = min(1.0, max(0.0, (float)$tw_eng)); }
                    $pm = $conn->prepare("INSERT INTO platform_metrics (influencer_id, platform_id, followers_count, average_reach, engagement_rate) VALUES (?, 5, ?, NULL, ?)");
                    $pm->bind_param("iid", $influencer_id, $tw_f, $tw_eng);
                    $pm->execute();
                    $pm->close();
                }

                $top_countries = trim($_POST['top_countries'] ?? '');
                if ($top_countries !== '') {
                    $arr = array_map('trim', explode(',', $top_countries));
                    $top_json = $conn->real_escape_string(json_encode(array_slice($arr, 0, 5)));
                    $conn->query("INSERT INTO audience_demographics (influencer_id, top_countries, gender_split, age_groups) VALUES ($influencer_id, '$top_json', '{}', '{}')");
                }

                $price_per_post = isset($_POST['price_per_post']) && $_POST['price_per_post'] !== '' ? (float)$_POST['price_per_post'] : 0.00;
                $is_negotiable = isset($_POST['is_negotiable']) ? (int)$_POST['is_negotiable'] : 1;
                $availability_status = isset($_POST['availability_status']) ? (int)$_POST['availability_status'] : 1;
                $open_to_brand_collab = isset($_POST['open_to_brand_collab']) ? (int)$_POST['open_to_brand_collab'] : 1;
                $collab = trim($_POST['collaboration_type'] ?? '') ?: 'paid';
                $past_brands_json = $past_brands_raw !== '' ? json_encode(array_map('trim', explode(',', $past_brands_raw))) : null;
                $preferred_platforms_raw = isset($_POST['preferred_platforms']) && is_array($_POST['preferred_platforms']) ? $_POST['preferred_platforms'] : [];
                $preferred_platforms_ids = array_values(array_filter(array_map('intval', $preferred_platforms_raw)));
                $preferred_platforms_json = json_encode($preferred_platforms_ids);
                $currency = 'USD';
                $pricing = $conn->prepare("INSERT INTO content_pricing (influencer_id, collaboration_type, preferred_platforms, past_brands, price_per_post, currency, is_negotiable, availability_status, open_to_brand_collab) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $pricing->bind_param("isssdsiii", $influencer_id, $collab, $preferred_platforms_json, $past_brands_json, $price_per_post, $currency, $is_negotiable, $availability_status, $open_to_brand_collab);
                $pricing->execute();
                $pricing->close();

                $message = 'Submitted successfully.';
                $messageType = 'success';
            } else {
                $stmt->close();
                $message = 'Registration failed. Please try again.';
                $messageType = 'error';
            }
        }
    }
}
if (!$db_ok && $message === '') {
    $message = 'Database connection failed.';
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- FAVICONS ICON -->
    <link rel="icon" href="assets/img/favicon.png" type="icon">
    <title>Influencer Form</title>

    <!-- libraries CSS -->
    <link rel="stylesheet" href="assets/font/flaticon_jio_-_influencer.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendor/swiper/swiper-bundle.min.css">
    <link rel="stylesheet" href="assets/vendor/splide/splide.min.css">
    <link rel="stylesheet" href="assets/vendor/plyr/plyr.css">
    <link rel="stylesheet" href="assets/vendor/slim-select/slimselect.css">
    <link rel="stylesheet" href="assets/vendor/no-ui-slider/nouislider.min.css">

    <!-- custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .form-section .option-group-label,
        .form-section .platform-block-title,
        .form-section label:not(.option-btn):not(.platform-btn) { color: #fff; }
        .form-section .app-select,
        .form-section input,
        .form-section textarea { color: #fff; background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.2); }
        .form-section .app-select option { background: #1a1a2e; color: #fff; }
        .form-section .option-group-status { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 1rem; }
        .form-section .option-group-status .option-group-label { margin-right: 0.5rem; flex: 0 0 auto; }
        .form-section .option-btn-pill { padding: 0.5rem 1rem; border-radius: 2rem; border: 1px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.05); cursor: pointer; transition: background 0.2s, border-color 0.2s; }
        .form-section .option-btn-pill:hover { background: rgba(255,255,255,0.1); }
        .form-section .option-btn-pill input:checked + * { font-weight: 600; }
        .form-section .option-btn-pill:has(input:checked) { border-color: var(--jo-theme, #e94560); background: rgba(233,69,96,0.15); }
        .form-section .platform-btn { color: #fff; }
        .form-section .checkbox-grid { display: flex; flex-wrap: wrap; gap: 0.5rem 1rem; margin-bottom: 1rem; }
        .form-section .platform-metrics-block { margin-top: 1rem; padding: 1.25rem; border-radius: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); }
        .form-section .platform-metrics-block + .platform-metrics-block { margin-top: 1rem; }
        .form-section .platform-block-title { margin: 0 0 0.75rem; font-size: 1rem; color: #fff; }
        .form-section .platform-inputs.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.75rem 1rem; }
        .form-section .form-step .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.75rem 1rem; }
        .form-section .step-hint { color: rgba(255,255,255,0.8); font-size: 0.9rem; margin-bottom: 1rem; }
        .form-section .full-width { grid-column: 1 / -1; }
        /* Verification & Documents: same height and width for all inputs */
        .form-section .verification-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0.75rem 1rem; align-items: start; }
        .form-section .verification-field { min-width: 0; }
        .form-section .verification-field label:first-child { display: block; margin-bottom: 0.35rem; color: #fff; font-size: 0.9rem; }
        .form-section .verification-field .app-select,
        .form-section .verification-field .app-file-label { min-height: 44px; height: 44px; width: 100%; max-width: 100%; box-sizing: border-box; border-radius: 6px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.08); color: #fff; }
        .form-section .verification-field .app-select { padding: 0.5rem 0.75rem; display: block; }
        .form-section .verification-field .app-file-label { display: flex; align-items: center; justify-content: center; padding: 0 1rem; cursor: pointer; gap: 0.5rem; }
        .form-section .verification-field .app-file-input { position: absolute; width: 0; height: 0; opacity: 0; }
        /* Snackbar: simple overlay on top of page */
        .snackbar { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); padding: 12px 24px; border-radius: 8px; color: #fff; font-size: 0.95rem; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.25); opacity: 0; visibility: hidden; transition: opacity 0.3s, visibility 0.3s; }
        .snackbar.show { opacity: 1; visibility: visible; }
        .snackbar.snackbar-success { background: #2e7d32; }
        .snackbar.snackbar-error { background: #c62828; }
        .snackbar.snackbar-info { background: #1565c0; }
    </style>
</head>

<body>
    <!-- sidebar -->
    <div class="jo-sidebar">
        <div>
            <div class="jo-sidebar__heading d-flex justify-content-between align-items-center">
                <a href="#"><img src="assets/img/logo.png" alt="logo" class="logo"></a>
                <button type="button" class="jo-sidebar-close-btn"><i class="flaticon-add-plus-button"></i></button>
            </div>
            <div class="jo-header-nav-in-mobile"></div>
        </div>
        <div>
            <a href="about.html" class="jo-btn"><i class="flaticon-add-plus-button"></i>Follow Me</a>
            <div class="tt-footer-top__socials jo-footer-top__socials jo-sidebar-socials justify-content-start justify-content-sm-end">
                <a href="#"><i class="flaticon-facebook-1"></i></a>
                <a href="#"><i class="flaticon-twitter"></i></a>
                <a href="#"><i class="flaticon-social-media"></i></a>
                <a href="#"><i class="flaticon-youtube-1"></i></a>
            </div>
        </div>
    </div>

    <!-- HEADER SECTION START -->
    <header class="jo-header">
        <div class="logo">
            <a href="index.html"><img src="assets/img/logo.png" alt="logo"></a>
        </div>
        <div class="jo-header-right">
            <div class="jo-header-nav">
                <div class="to-go-to-sidebar-in-mobile">
                    <nav>
                        <a href="index.html">Home</a>
                        <a href="about.html">About Us</a>
                        <a href="for-brands.html">For Brands</a>
                        <a href="for-influencers.html">For Influencers</a>
                        <a href="service.html">Services</a>
                        <a href="blog.html">Blogs</a>
                        <a href="contact.html">Contact</a>
                    </nav>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="about.html" class="jo-btn"><i class="flaticon-add-plus-button"></i>Follow Me</a>
                <button class="jo-header-sidebar-open-btn jo-btn px-3 d-inline-block d-lg-none"><i class="flaticon-menu"></i></button>
            </div>
        </div>
    </header>
    <!-- HEADER SECTION END -->

    <main>
        <!-- BREADCRUMB SECTION START -->
        <section class="jo-breadcrumb">
            <div class="container">
                <h1 class="jo-page-title jo-section-title">Influencers Data</h1>
                <ul class="jo-breadcrumb-nav">
                    <li><a href="#">Home</a></li>
                    <li><span>/</span></li>
                    <li class="current-page">Influencers Data</li>
                </ul>
                <div class="jo-circle-box">
                    <span class="circle-1"><img src="assets/img/social-icon-1.png" alt="Social Media Icon"></span>
                    <span class="circle-2"><img src="assets/img/social-icon-2.png" alt="Social Media Icon"></span>
                    <span class="circle-3"><img src="assets/img/social-icon-3.png" alt="Social Media Icon"></span>
                    <span class="circle-4"><img src="assets/img/social-icon-4.png" alt="Social Media Icon"></span>
                </div>
            </div>
        </section>
        <!-- BREADCRUMB SECTION END -->

        <section class="form-section" id="frm">
            <div class="form-wrapper">
                <h1>Influencer Master Data</h1>
                <p class="subtitle">
                    Please fill all details carefully. This data will be used for verification,
                    tier assignment, and brand collaborations.
                </p>

                <div id="messageBox" class="form-message" style="display:none;"></div>

                <!-- Step progress indicator -->
                <div class="step-progress">
                    <div class="step-progress-bar" role="progressbar"></div>
                    <div class="step-dots">
                        <span class="step-dot active" data-step="1" title="Basic Info"></span>
                        <span class="step-dot" data-step="2" title="Status"></span>
                        <span class="step-dot" data-step="3" title="Platforms"></span>
                        <span class="step-dot" data-step="4" title="Audience"></span>
                        <span class="step-dot" data-step="5" title="Content"></span>
                        <span class="step-dot" data-step="6" title="Collaboration"></span>
                        <span class="step-dot" data-step="7" title="Pricing"></span>
                        <span class="step-dot" data-step="8" title="Verification"></span>
                    </div>
                    <div class="step-counter">Step <span id="current-step-num">1</span> of 8</div>
                </div>

                <form class="step-form" id="influencer-form" method="post" action="" enctype="multipart/form-data" novalidate>
                    <!-- STEP 1: BASIC INFORMATION -->
                    <div class="form-step active" data-step="1">
                        <h2>Basic Information</h2>
                        <div class="grid">
                            <input type="text" placeholder="Enter your full name" name="full_name" required>
                            <input type="email" placeholder="you@example.com" name="email" required>
                            <input type="password" placeholder="Password (min 8 characters)" name="password" required>
                            <input type="password" placeholder="Confirm Password" name="confirm_password" required>
                            <input type="text" placeholder="Phone Number" name="phone">
                            <select name="country_id" id="country_id" class="app-select">
                                <option value="">Loading countries…</option>
                            </select>
                            <select name="province_id" id="province_id" class="app-select" disabled>
                                <option value="">Select country first</option>
                            </select>
                            <input type="text" placeholder="Enter your city" name="city_name">
                            <div class="field-with-helper">
                                <input type="date" name="date_of_birth" id="date_of_birth" placeholder="Select your date of birth">
                                <span class="helper-text" id="age-display">Age: -- years</span>
                            </div>
                            <input type="number" placeholder="Age" name="age" id="age_input" min="1" max="120" style="display:none;">
                            <div class="option-group option-group-status full-width" role="group" aria-label="Gender">
                                <span class="option-group-label">Gender</span>
                                <label class="option-btn option-btn-pill"><input type="radio" name="gender" value="0"> Male</label>
                                <label class="option-btn option-btn-pill"><input type="radio" name="gender" value="1"> Female</label>
                                <label class="option-btn option-btn-pill"><input type="radio" name="gender" value="2"> Other</label>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2: INFLUENCER STATUS -->
                    <div class="form-step" data-step="2">
                        <h2>Influencer Status</h2>
                        <div class="grid">
                            <div class="file-group app-upload-zone">
                                <label for="profile_photo">Profile Photo</label>
                                <label class="app-file-label" for="profile_photo">
                                    <span class="app-file-icon">📷</span>
                                    <span class="app-file-text">Tap to upload photo</span>
                                    <input type="file" id="profile_photo" name="profile_photo" accept="image/*" class="app-file-input">
                                </label>
                            </div>
                            <div class="option-group option-group-status full-width" role="group" aria-label="Influencer Status">
                                <span class="option-group-label">Influencer type</span>
                                <label class="option-btn option-btn-pill"><input type="radio" name="influencer_type" value="0"> Full time</label>
                                <label class="option-btn option-btn-pill"><input type="radio" name="influencer_type" value="1"> Part time</label>
                                <label class="option-btn option-btn-pill"><input type="radio" name="influencer_type" value="2"> Beginner</label>
                            </div>
                            <input type="number" placeholder="Experience (year started e.g. 2020)" name="experience_since" min="2000" max="2030">
                            <input type="text" placeholder="Past Collaborations / Past Brands" name="past_brands">
                            <textarea placeholder="Bio" name="bio" rows="3"></textarea>
                        </div>
                    </div>

                    <!-- STEP 3: PLATFORMS (from social_platform table; only show inputs for ticked platforms) -->
                    <div class="form-step" data-step="3">
                        <h2>Platforms (Active)</h2>
                        <p class="step-hint">Select the platforms you use; only those sections will appear below.</p>
                        <div class="checkbox-grid app-platform-grid">
                            <?php foreach ($platforms as $p): ?>
                            <label class="platform-btn"><input type="checkbox" name="platform_<?php echo htmlspecialchars($p['slug']); ?>" value="1" data-platform="<?php echo htmlspecialchars($p['slug']); ?>"> <?php echo htmlspecialchars($p['name']); ?></label>
                            <?php endforeach; ?>
                        </div>
                        <?php foreach ($platforms as $p):
                            $slug = $p['slug'];
                            $name = $p['name'];
                        ?>
                        <div id="platform-<?php echo htmlspecialchars($slug); ?>-block" class="platform-metrics-block" style="display:none;">
                            <h3 class="platform-block-title"><?php echo htmlspecialchars($name); ?></h3>
                            <div class="platform-inputs grid">
                                <input type="text" placeholder="<?php echo htmlspecialchars($name); ?> Username" name="<?php echo $slug; ?>_username">
                                <input type="url" placeholder="<?php echo htmlspecialchars($name); ?> Profile URL" name="<?php echo $slug; ?>_profile_link">
                                <?php if ($slug === 'youtube'): ?>
                                <input type="number" placeholder="<?php echo htmlspecialchars($name); ?> Subscribers" name="youtube_subscribers" min="0">
                                <input type="number" placeholder="<?php echo htmlspecialchars($name); ?> Average Views" name="youtube_views" min="0">
                                <?php elseif ($slug === 'tiktok'): ?>
                                <input type="number" placeholder="<?php echo htmlspecialchars($name); ?> Followers" name="tiktok_followers" min="0">
                                <input type="number" placeholder="<?php echo htmlspecialchars($name); ?> Average Views" name="tiktok_views" min="0">
                                <?php elseif ($slug === 'instagram'): ?>
                                <input type="number" placeholder="<?php echo htmlspecialchars($name); ?> Followers" name="instagram_followers" min="0">
                                <input type="number" placeholder="<?php echo htmlspecialchars($name); ?> Average Reach" name="instagram_reach" min="0">
                                <input type="number" step="0.01" placeholder="<?php echo htmlspecialchars($name); ?> Engagement Rate (%)" name="instagram_engagement" min="0">
                                <?php else: ?>
                                <input type="number" placeholder="<?php echo htmlspecialchars($name); ?> Followers" name="<?php echo $slug; ?>_followers" min="0">
                                <input type="number" step="0.01" placeholder="<?php echo htmlspecialchars($name); ?> Engagement Rate (%)" name="<?php echo $slug; ?>_engagement" min="0">
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- STEP 4: AUDIENCE DEMOGRAPHICS -->
                    <div class="form-step" data-step="4">
                        <h2>Audience Demographics</h2>
                        <div class="grid">
                            <input type="text" placeholder="Audience Countries (Top 3, comma separated)" name="top_countries">
                            <input type="text" placeholder="Audience Gender Split" name="audience_gender_split">
                            <input type="text" placeholder="Audience Age Groups" name="audience_age_groups">
                        </div>
                    </div>

                    <!-- STEP 5: CONTENT & NICHE (Category & Niche from API) -->
                    <div class="form-step" data-step="5">
                        <h2>Content & Niche</h2>
                        <div class="grid">
                            <div class="field-with-helper">
                                <label for="primary_category_id">Primary Category</label>
                                <select id="primary_category_id" class="app-select">
                                    <option value="">Loading categories…</option>
                                </select>
                            </div>
                            <div class="field-with-helper">
                                <label for="primary_niche_id">Primary Niche</label>
                                <select name="primary_niche_id" id="primary_niche_id" class="app-select" disabled>
                                    <option value="">Select category first</option>
                                </select>
                            </div>
                            <div class="field-with-helper">
                                <label for="secondary_category_id">Secondary Category</label>
                                <select id="secondary_category_id" class="app-select">
                                    <option value="">Loading categories…</option>
                                </select>
                            </div>
                            <div class="field-with-helper">
                                <label for="secondary_niche_id">Secondary Niche</label>
                                <select name="secondary_niche_id" id="secondary_niche_id" class="app-select" disabled>
                                    <option value="">Select category first</option>
                                </select>
                            </div>
                            <input type="text" placeholder="Content Category Tags" name="content_tags">
                        </div>
                    </div>

                    <!-- STEP 6: COLLABORATION PREFERENCES -->
                    <div class="form-step" data-step="6">
                        <h2>Collaboration Preferences</h2>
                        <div class="grid">
                            <div class="field-with-helper full-width">
                                <label for="collaboration_type">Collaboration type</label>
                                <select name="collaboration_type" id="collaboration_type" class="app-select">
                                    <option value="paid">Paid</option>
                                    <option value="affiliate">Affiliate</option>
                                </select>
                            </div>
                            <div class="field-with-helper full-width">
                                <span class="option-group-label">Preferred Platforms</span>
                                <div id="preferred_platforms" class="checkbox-grid app-platform-grid" role="group" aria-label="Preferred platforms">
                                    <span class="helper-text">Loading platforms…</span>
                                </div>
                            </div>
                            <div class="option-group full-width option-group-status" role="group" aria-label="Open to brand collaboration">
                                <span class="option-group-label">Open to brand collaboration</span>
                                <label class="option-btn option-btn-pill"><input type="radio" name="open_to_brand_collab" value="1" checked> Yes</label>
                                <label class="option-btn option-btn-pill"><input type="radio" name="open_to_brand_collab" value="0"> No</label>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 7: PRICING & AVAILABILITY -->
                    <div class="form-step" data-step="7">
                        <h2>Pricing & Availability</h2>
                        <div class="grid">
                            <input type="number" step="0.01" placeholder="Price per post (e.g. 1500.00)" name="price_per_post" min="0">
                            <div class="field-with-helper full-width">
                                <label for="is_negotiable">Price negotiable</label>
                                <select name="is_negotiable" id="is_negotiable" class="app-select">
                                    <option value="1" selected>Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                            <select name="availability_status" class="app-select">
                                <option value="1">Available</option>
                                <option value="0">Unavailable</option>
                            </select>
                        </div>
                    </div>

                    <!-- STEP 8: VERIFICATION (documents only; no admin fields) -->
                    <div class="form-step" data-step="8">
                        <h2>Verification & Documents</h2>
                        <div class="grid verification-grid">
                            <div class="file-group verification-field">
                                <label for="document_type">Document type</label>
                                <select name="document_type" id="document_type" class="app-select">
                                    <option value="">Select document type</option>
                                    <option value="license">License</option>
                                    <option value="media_kit">Media Kit</option>
                                    <option value="analytics">Analytics</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="file-group app-upload-zone verification-field">
                                <label>Document file</label>
                                <label class="app-file-label" for="document_file">
                                    <span class="app-file-icon">☁</span>
                                    <span class="app-file-text">Tap to upload document</span>
                                    <input type="file" id="document_file" name="document_file" accept=".pdf,.doc,.docx,.jpg,.png" class="app-file-input">
                                </label>
                            </div>
                            <div class="file-group app-upload-zone verification-field">
                                <label for="mediaKit">Media Kit (optional)</label>
                                <label class="app-file-label" for="mediaKit">
                                    <span class="app-file-icon">☁</span>
                                    <span class="app-file-text">Tap to upload</span>
                                    <input type="file" id="mediaKit" name="mediaKit" accept=".pdf,.doc,.docx,.jpg,.png" class="app-file-input">
                                </label>
                            </div>
                            <div class="file-group app-upload-zone verification-field">
                                <label for="analyticsScreenshot">Analytics Screenshot (optional)</label>
                                <label class="app-file-label" for="analyticsScreenshot">
                                    <span class="app-file-icon">☁</span>
                                    <span class="app-file-text">Tap to upload</span>
                                    <input type="file" id="analyticsScreenshot" name="analyticsScreenshot" accept="image/*" class="app-file-input">
                                </label>
                            </div>
                            <div class="field-with-helper verification-field">
                                <label for="verified_status">Verified status</label>
                                <select name="verified_status" id="verified_status" class="app-select">
                                    <option value="">Not set</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation buttons -->
                    <div class="form-step-nav">
                        <button type="button" class="step-btn step-prev" aria-label="Previous step">← Previous</button>
                        <button type="button" class="step-btn step-next" aria-label="Next step">Next →</button>
                        <button type="submit" class="step-btn step-submit" style="display:none;">Submit Influencer Data</button>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <!-- FOOTER SECTION START -->
    <footer class="jo-footer">
        <div class="jo-container">
            <div class="jo-footer-top">
                <div class="row gx-4 gy-sm-5 gy-4 align-items-center">
                    <div class="col-lg-3 col-6 col-xxs-12">
                        <a href="index.html"><img src="assets/img/logo1.png" alt="logo" class="logo"></a>
                    </div>
                    <div class="col-lg-6 order-lg-1 order-2">
                        <form action="#" class="jo-footer-top__nwsltr">
                            <input type="email" name="jo-nwsltr-email" id="jo-nwsltr-email" placeholder="Email Address">
                            <button class="jo-btn" type="submit">Get Newsletter</button>
                        </form>
                    </div>
                    <div class="col-lg-3 col-6 col-xxs-12 order-lg-2 order-1">
                        <div class="tt-footer-top__socials jo-footer-top__socials justify-content-start justify-content-sm-end">
                            <a href="#"><i class="flaticon-facebook-1"></i></a>
                            <a href="#"><i class="flaticon-twitter"></i></a>
                            <a href="#"><i class="flaticon-social-media"></i></a>
                            <a href="#"><i class="flaticon-youtube-1"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="jo-footer-middle">
                <div class="row g-4">
                    <div class="col-xl-4 col-md-6">
                        <div class="jo-footer-widget jo-footer-contact">
                            <h5 class="jo-footer-widget__title">Get in Touch</h5>
                            <a href="mailto:contact.me@gmail.com">contact.me@gmail.com</a>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6">
                        <div class="jo-footer-widget jo-footer-links">
                            <h5 class="jo-footer-widget__title">Browse Categories</h5>
                            <div class="links">
                                <a href="#">Music</a>
                                <a href="#">Sports</a>
                                <a href="#">Gaming</a>
                                <a href="#">Fashion</a>
                                <a href="#">Art</a>
                                <a href="#">Photography</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6">
                        <div class="jo-footer-widget jo-footer-gallery">
                            <h5 class="jo-footer-widget__title">Instagram feed</h5>
                            <div class="imgs flex-wrap">
                                <img src="assets/img/footer-img-1.jpg" alt="Footer Image">
                                <img src="assets/img/footer-img-2.jpg" alt="Footer Image">
                                <img src="assets/img/footer-img-3.jpg" alt="Footer Image">
                                <img src="assets/img/footer-img-4.jpg" alt="Footer Image">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tt-footer-bottom text-center">
                <p class="mb-0">Copyright ©2024 Developed by&nbsp;Influtics</p>
            </div>
        </div>
    </footer>
    <!-- FOOTER SECTION END -->

    <!-- Snackbar: shown via JS from PHP message or on submit -->
    <div id="snackbar" class="snackbar" role="status" aria-live="polite"
        data-message="<?php echo $message ? htmlspecialchars($message, ENT_QUOTES, 'UTF-8') : ''; ?>"
        data-type="<?php echo $messageType === 'success' ? 'success' : ($messageType === 'error' ? 'error' : 'info'); ?>"></div>

    <!-- libraries JS -->
    <script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
    <script src="assets/vendor/splide/splide.min.js"></script>
    <script src="assets/vendor/splide/splide-extension-auto-scroll.min.js"></script>
    <script src="assets/vendor/slim-select/slimselect.min.js"></script>
    <script src="assets/vendor/plyr/plyr.polyfilled.js"></script>
    <script src="assets/vendor/no-ui-slider/nouislider.min.js"></script>
    <script src="assets/vendor/fs-lightbox/fslightbox.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/accordion.js"></script>
    <script>
      window.FORM_API_BASE = '<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/"); ?>';
    </script>
    <script>
      (function() {
        const form = document.getElementById('influencer-form');
        if (!form) return;
        
        // Add novalidate attribute to disable browser validation
        form.setAttribute('novalidate', 'novalidate');
        
        const steps = form.querySelectorAll('.form-step');
        const total = steps.length;
        const prevBtn = form.querySelector('.step-prev');
        const nextBtn = form.querySelector('.step-next');
        const submitBtn = form.querySelector('.step-submit');
        const counterEl = document.getElementById('current-step-num');
        const dots = form.closest('.form-wrapper').querySelectorAll('.step-dot');
        const progressBar = form.closest('.form-wrapper').querySelector('.step-progress-bar');
        const messageBox = document.getElementById('messageBox');

        let current = 1;

        // Validate current step
        function validateStep(stepNum) {
            const step = document.querySelector(`.form-step[data-step="${stepNum}"]`);
            const required = step.querySelectorAll('[required]');
            let valid = true;
            
            // Remove any existing error styles
            step.querySelectorAll('.error').forEach(field => {
                field.classList.remove('error');
            });
            
            required.forEach(field => {
                if (!field.value.trim()) {
                    valid = false;
                    field.classList.add('error');
                    
                    // Show error message
                    if (messageBox) {
                        messageBox.textContent = `Please fill in all required fields in this step.`;
                        messageBox.className = 'form-message form-message-error';
                        messageBox.style.display = 'block';
                    }
                    
                    // Focus on first invalid field
                    if (field === required[0]) {
                        field.focus();
                    }
                }
            });
            
            // Additional validation for specific fields
            if (stepNum === 1) {
                const email = step.querySelector('[name="email"]');
                const password = step.querySelector('[name="password"]');
                const confirm = step.querySelector('[name="confirm_password"]');
                
                if (email && email.value && !isValidEmail(email.value)) {
                    valid = false;
                    email.classList.add('error');
                    showMessage('Please enter a valid email address.', 'error');
                }
                
                if (password && password.value && password.value.length < 8) {
                    valid = false;
                    password.classList.add('error');
                    showMessage('Password must be at least 8 characters long.', 'error');
                }
                
                if (password && confirm && password.value !== confirm.value) {
                    valid = false;
                    confirm.classList.add('error');
                    showMessage('Passwords do not match.', 'error');
                }
            }
            
            return valid;
        }
        
        // Email validation helper
        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
        
        // Show message
        function showMessage(text, type) {
            if (messageBox) {
                messageBox.textContent = text;
                messageBox.className = `form-message form-message-${type}`;
                messageBox.style.display = 'block';
                
                // Auto hide after 5 seconds for success messages
                if (type === 'success') {
                    setTimeout(() => {
                        messageBox.style.display = 'none';
                    }, 5000);
                }
            }
        }
        
        // Hide message
        function hideMessage() {
            if (messageBox) {
                messageBox.style.display = 'none';
            }
        }

        function goTo(step) {
            if (step < 1 || step > total) return;
            current = step;
            
            steps.forEach(function(s, i) { 
                s.classList.toggle('active', i + 1 === current); 
            });
            
            dots.forEach(function(d, i) {
                d.classList.toggle('active', i + 1 === current);
                d.classList.toggle('completed', i + 1 < current);
            });
            
            if (counterEl) counterEl.textContent = current;
            if (progressBar) progressBar.style.setProperty('--step-progress', ((current - 1) / (total - 1)) * 100 + '%');
            
            prevBtn.style.display = current === 1 ? 'none' : 'inline-block';
            nextBtn.style.display = current === total ? 'none' : 'inline-block';
            submitBtn.style.display = current === total ? 'inline-block' : 'none';
            
            // Hide message when changing steps
            hideMessage();
            
            // Remove error styles when leaving a step
            steps.forEach(step => {
                step.querySelectorAll('.error').forEach(field => {
                    field.classList.remove('error');
                });
            });
        }

        // Previous button click
        prevBtn.addEventListener('click', function() { 
            goTo(current - 1); 
        });
        
        // Next button click
        nextBtn.addEventListener('click', function() {
            if (validateStep(current)) {
                hideMessage();
                goTo(current + 1);
            }
        });

        // Dot click navigation
        dots.forEach((dot, index) => {
            dot.addEventListener('click', function() {
                const stepNum = parseInt(this.dataset.step);
                // Only allow going to completed steps or next step
                if (stepNum <= current + 1 || this.classList.contains('completed')) {
                    goTo(stepNum);
                }
            });
        });

        // Form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate all steps
            let allValid = true;
            let firstInvalidStep = null;
            
            for (let i = 1; i <= total; i++) {
                if (!validateStep(i)) {
                    allValid = false;
                    if (!firstInvalidStep) {
                        firstInvalidStep = i;
                    }
                    break;
                }
            }
            
            if (allValid) {
                showSnackbar('Submitting…', 'info');
                submitBtn.disabled = true;
                form.submit();
            } else {
                if (firstInvalidStep) goTo(firstInvalidStep);
                showSnackbar('Please fill all required fields correctly before submitting.', 'error');
            }
        });

        // Initialize first step
        goTo(1);

        // —— API dropdowns (separate endpoints: countries, provinces, categories, niches) ——
        const API_BASE = (typeof window.FORM_API_BASE !== 'undefined' && window.FORM_API_BASE)
            ? window.FORM_API_BASE
            : window.location.pathname.replace(/\/[^/]*$/, '');
        function fetchOptions(type, param, signal) {
            let url;
            if (type === 'countries') url = API_BASE + '/countries.php';
            else if (type === 'provinces') url = API_BASE + '/provinces.php' + (param && param.value ? '?country_id=' + encodeURIComponent(param.value) : '');
            else if (type === 'categories') url = API_BASE + '/categories.php';
            else if (type === 'niches') url = API_BASE + '/niches.php' + (param && param.value ? '?category_id=' + encodeURIComponent(param.value) : '');
            else if (type === 'platforms') url = API_BASE + '/platforms.php';
            else return Promise.reject(new Error('Unknown type'));
            const opts = signal ? { signal } : {};
            return fetch(url, opts).then(function(r) {
                if (!r.ok) throw new Error('API ' + r.status);
                return r.json();
            });
        }
        function fillSelect(selectEl, items, placeholder, valueKey, labelKey) {
            if (!selectEl) return;
            valueKey = valueKey || 'id';
            labelKey = labelKey || 'name';
            selectEl.innerHTML = '';
            const opt0 = document.createElement('option');
            opt0.value = '';
            opt0.textContent = placeholder || 'Select…';
            selectEl.appendChild(opt0);
            (items || []).forEach(function(item) {
                const o = document.createElement('option');
                o.value = item[valueKey];
                o.textContent = item[labelKey];
                selectEl.appendChild(o);
            });
        }
        function setSelectLoading(selectEl, placeholder) {
            if (!selectEl) return;
            selectEl.disabled = true;
            fillSelect(selectEl, [], placeholder || 'Loading…');
        }
        const countryEl = document.getElementById('country_id');
        const provinceEl = document.getElementById('province_id');
        const primaryCat = document.getElementById('primary_category_id');
        const primaryNiche = document.getElementById('primary_niche_id');
        const secondaryCat = document.getElementById('secondary_category_id');
        const secondaryNiche = document.getElementById('secondary_niche_id');

        // Load countries and categories in parallel for faster first paint
        Promise.all([
            fetchOptions('countries').then(function(res) {
                if (res && res.success && Array.isArray(res.data)) {
                    fillSelect(countryEl, res.data, 'Select country');
                } else {
                    fillSelect(countryEl, [], 'Could not load countries');
                }
            }).catch(function() {
                if (countryEl) fillSelect(countryEl, [], 'Could not load countries');
                if (typeof showSnackbar === 'function') showSnackbar('Could not load countries. Please refresh.', 'error');
            }),
            fetchOptions('categories').then(function(res) {
                const data = (res && res.success && Array.isArray(res.data)) ? res.data : [];
                fillSelect(primaryCat, data, 'Select category');
                fillSelect(secondaryCat, data, 'Select category');
            }).catch(function() {
                if (primaryCat) fillSelect(primaryCat, [], 'Could not load categories');
                if (secondaryCat) fillSelect(secondaryCat, [], 'Could not load categories');
                if (typeof showSnackbar === 'function') showSnackbar('Could not load categories. Please refresh.', 'error');
            })
        ]).catch(function() {});

        // Provinces: abort previous request when country changes for smooth, correct results
        let provinceAbort = null;
        if (countryEl && provinceEl) {
            countryEl.addEventListener('change', function() {
                const cid = countryEl.value;
                provinceEl.value = '';
                if (!cid) {
                    setSelectLoading(provinceEl, 'Select country first');
                    return;
                }
                if (provinceAbort) provinceAbort.abort();
                provinceAbort = new AbortController();
                setSelectLoading(provinceEl, 'Loading…');
                fetchOptions('provinces', { key: 'country_id', value: cid }, provinceAbort.signal).then(function(res) {
                    if (res && res.success && Array.isArray(res.data)) {
                        fillSelect(provinceEl, res.data, 'Select province');
                    } else {
                        fillSelect(provinceEl, [], 'Could not load provinces');
                    }
                    provinceEl.disabled = false;
                }).catch(function(err) {
                    if (err && err.name === 'AbortError') return;
                    fillSelect(provinceEl, [], 'Could not load provinces');
                    provinceEl.disabled = false;
                });
            });
        }

        // Niches: abort previous request when category changes
        let nicheAbort = null;
        function loadNiches(selectEl, categoryId, placeholder) {
            if (!selectEl) return;
            selectEl.value = '';
            if (!categoryId) {
                setSelectLoading(selectEl, 'Select category first');
                return;
            }
            if (nicheAbort) nicheAbort.abort();
            nicheAbort = new AbortController();
            const sig = nicheAbort.signal;
            setSelectLoading(selectEl, 'Loading…');
            fetchOptions('niches', { key: 'category_id', value: categoryId }, sig).then(function(res) {
                if (res && res.success && Array.isArray(res.data)) {
                    fillSelect(selectEl, res.data, placeholder || 'Select niche');
                } else {
                    fillSelect(selectEl, [], 'Could not load niches');
                }
                selectEl.disabled = false;
            }).catch(function(err) {
                if (err && err.name === 'AbortError') return;
                fillSelect(selectEl, [], 'Could not load niches');
                selectEl.disabled = false;
            });
        }
        if (primaryCat && primaryNiche) {
            primaryCat.addEventListener('change', function() { loadNiches(primaryNiche, primaryCat.value, 'Select niche'); });
        }
        if (secondaryCat && secondaryNiche) {
            secondaryCat.addEventListener('change', function() { loadNiches(secondaryNiche, secondaryCat.value, 'Select niche'); });
        }

        // Preferred platforms: load from platforms.php and render as checkboxes
        var preferredPlatformsEl = document.getElementById('preferred_platforms');
        if (preferredPlatformsEl) {
            preferredPlatformsEl.innerHTML = '<span class="helper-text">Loading platforms…</span>';
            fetchOptions('platforms').then(function(res) {
                if (!res || !res.success || !Array.isArray(res.data)) {
                    preferredPlatformsEl.innerHTML = '<span class="helper-text">Could not load platforms</span>';
                    return;
                }
                preferredPlatformsEl.innerHTML = '';
                res.data.forEach(function(p) {
                    var label = document.createElement('label');
                    label.className = 'platform-btn';
                    var cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.name = 'preferred_platforms[]';
                    cb.value = p.id;
                    label.appendChild(cb);
                    label.appendChild(document.createTextNode(' ' + p.name));
                    preferredPlatformsEl.appendChild(label);
                });
            }).catch(function() {
                preferredPlatformsEl.innerHTML = '<span class="helper-text">Could not load platforms</span>';
            });
        }

        // —— Platforms: show only blocks for ticked platforms (from social_platform; id = platform-{slug}-block) ——
        function togglePlatformBlocks() {
            form.querySelectorAll('input[data-platform]').forEach(function(cb) {
                var slug = cb.getAttribute('data-platform');
                if (!slug) return;
                var block = document.getElementById('platform-' + slug + '-block');
                if (block) block.style.display = cb.checked ? 'block' : 'none';
            });
        }
        form.querySelectorAll('input[data-platform]').forEach(function(cb) {
            cb.addEventListener('change', togglePlatformBlocks);
        });
        togglePlatformBlocks();

        // —— Snackbar ——
        var snackbar = document.getElementById('snackbar');
        function showSnackbar(message, type) {
            if (!snackbar) return;
            snackbar.textContent = message;
            snackbar.className = 'snackbar snackbar-' + (type || 'info') + ' show';
            clearTimeout(snackbar._hideTimer);
            snackbar._hideTimer = setTimeout(function() {
                snackbar.classList.remove('show');
            }, 4500);
        }
        var initialMessage = snackbar && snackbar.getAttribute('data-message');
        if (initialMessage) {
            showSnackbar(initialMessage, snackbar.getAttribute('data-type') || 'info');
        }

        // Age from Date of Birth (app-style helper)
        var dobInput = document.getElementById('date_of_birth');
        var ageDisplay = document.getElementById('age-display');
        var ageInput = document.getElementById('age_input');
        if (dobInput && ageDisplay) {
          function updateAge() {
            var val = dobInput.value;
            if (!val) { ageDisplay.textContent = 'Age: -- years'; if (ageInput) ageInput.value = ''; return; }
            var birth = new Date(val);
            var today = new Date();
            var age = today.getFullYear() - birth.getFullYear();
            var m = today.getMonth() - birth.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
            ageDisplay.textContent = 'Age: ' + age + ' years';
            if (ageInput) ageInput.value = age > 0 ? age : '';
          }
          dobInput.addEventListener('change', updateAge);
          dobInput.addEventListener('input', updateAge);
          updateAge();
        }

        // Add input event listeners to remove error class on input
        form.addEventListener('input', function(e) {
            if (e.target.classList.contains('error')) {
                e.target.classList.remove('error');
                hideMessage();
            }
        });
        
        // Add change event for selects
        form.addEventListener('change', function(e) {
            if (e.target.classList.contains('error')) {
                e.target.classList.remove('error');
                hideMessage();
            }
        });
      })();
    </script>
</body>
</html>