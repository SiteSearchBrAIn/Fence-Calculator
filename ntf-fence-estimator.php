<?php
/**
 * Plugin Name: Privacy Fence Estimator (New Tampa Fence)
 * Description: Adds a [ntf_fence_estimator] shortcode with a lead-gated fence-cost calculator. All pricing and content is edited in the WordPress dashboard under Fence Estimator; submitted leads and quote requests are stored and emailed.
 * Version: 3.2.1
 * Author: Steve Scott SEO
 */

if (!defined('ABSPATH')) { exit; }

define('NTF_FENCE_VERSION', '3.2.1');
define('NTF_FENCE_OPTION', 'ntf_fence_rates_v3');
// Where estimator leads go when no notify address is saved. Never the WP admin email (that is the agency).
define('NTF_FENCE_FALLBACK_EMAIL', 'newtampafence@gmail.com');
define('NTF_FENCE_HEIGHTS', array('4', '5', '6', '8'));

// Update checks and one-click installs from GitHub releases (see README).
require_once __DIR__ . '/updater.php';
NTF_Fence_Updater::init(__FILE__);

/** ---------- Defaults (from fence_pricing.xlsx, Sep 2026) ---------- */
function ntf_fence_default_rates() {
    return array(
        'business' => array(
            'name'        => get_bloginfo('name') ?: 'New Tampa Fence, Inc.',
            'phone'       => '813-423-2383',
            'notifyEmail' => NTF_FENCE_FALLBACK_EMAIL,
        ),
        'fence' => array(
            'wood' => array(
                'label' => 'Wood Privacy', 'swatch' => '#6B4426',
                'heights' => array(
                    '4' => array('low' => 20, 'high' => 25),
                    '5' => array('low' => 21, 'high' => 35),
                    '6' => array('low' => 21, 'high' => 35),
                    '8' => array('low' => 30, 'high' => 40),
                ),
            ),
            'vinylPrivacy' => array(
                'label' => 'Vinyl Privacy', 'swatch' => '#EDE9DD',
                'heights' => array(
                    '4' => array('low' => 22, 'high' => 32),
                    '5' => array('low' => 23, 'high' => 33),
                    '6' => array('low' => 23, 'high' => 33),
                    '8' => array('low' => 42, 'high' => 52),
                ),
            ),
            // Not in the Sep 2026 pricing sheet — carried forward from the prior
            // height-adjustment model (-15%/-5%/base/+20% on the old $23/$28 base)
            // and flagged for review in the admin screen.
            'vinylPicket' => array(
                'label' => 'Vinyl Picket Fence', 'swatch' => '#F4F1E7',
                'heights' => array(
                    '4' => array('low' => 20, 'high' => 24),
                    '5' => array('low' => 22, 'high' => 27),
                    '6' => array('low' => 23, 'high' => 28),
                    '8' => array('low' => 28, 'high' => 34),
                ),
            ),
            'aluminum' => array(
                'label' => 'Aluminum Residential Grade', 'swatch' => '#3B4046',
                'heights' => array(
                    '4' => array('low' => 25, 'high' => 35),
                    '5' => array('low' => 27, 'high' => 37),
                    '6' => array('low' => 29, 'high' => 39),
                    '8' => array('low' => 38, 'high' => 50),
                ),
            ),
            'chainlink' => array(
                'label' => 'Chain Link Galvanized Residential Grade', 'swatch' => '#9AA1A6',
                'heights' => array(
                    '4' => array('low' => 15, 'high' => 25),
                    '5' => array('low' => 16, 'high' => 26),
                    '6' => array('low' => 17, 'high' => 27),
                    '8' => array('low' => 25, 'high' => 35),
                ),
            ),
            'metalDura' => array(
                'label' => 'Metal Dura Fence', 'swatch' => '#2B2F33',
                'heights' => array(
                    '4' => array('low' => 29, 'high' => 40),
                    '5' => array('low' => 29, 'high' => 40),
                    '6' => array('low' => 29, 'high' => 40),
                    // no '8' entry — not offered at 8 ft, matches the pricing sheet
                ),
            ),
        ),
        'gates' => array(
            'single'    => array('label' => 'Single walk gate (3–4 ft)',    'low' => 300,  'high' => 500),
            'double'    => array('label' => 'Double drive gate (10–16 ft)', 'low' => 700,  'high' => 1500),
            'oversized' => array('label' => 'Oversized / custom gate',      'low' => 1500, 'high' => 3000),
        ),
        'gateMaterialAdjust' => array(
            'wood' => 0, 'vinylPrivacy' => 0, 'vinylPicket' => 0, 'aluminum' => 0, 'chainlink' => -15, 'metalDura' => 0,
        ),
        'modifiers' => array(
            'removalOldFence' => array('label' => 'Old fence removal', 'low' => 3, 'high' => 6),
        ),
    );
}

/** ---------- Activation: create leads table ---------- */
register_activation_hook(__FILE__, 'ntf_fence_activate');
function ntf_fence_activate() {
    global $wpdb;
    $table = $wpdb->prefix . 'ntf_fence_leads';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        stage VARCHAR(20) NOT NULL DEFAULT 'lead',
        name VARCHAR(190) NOT NULL,
        email VARCHAR(190) NOT NULL,
        phone VARCHAR(60) NOT NULL,
        fence_type VARCHAR(160) NOT NULL DEFAULT '',
        height VARCHAR(10) NOT NULL DEFAULT '',
        feet VARCHAR(20) NOT NULL DEFAULT '',
        gates_summary VARCHAR(255) NOT NULL DEFAULT '',
        estimate_low VARCHAR(20) NOT NULL DEFAULT '',
        estimate_high VARCHAR(20) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

/** ---------- REST API (read-only pricing for the front-end + public lead capture) ---------- */
add_action('rest_api_init', function () {
    register_rest_route('ntf-fence/v1', '/rates', array(
        array('methods' => 'GET', 'callback' => 'ntf_fence_get_rates', 'permission_callback' => '__return_true'),
    ));
    register_rest_route('ntf-fence/v1', '/leads', array(
        array('methods' => 'POST', 'callback' => 'ntf_fence_save_lead', 'permission_callback' => '__return_true'),
    ));
});

function ntf_fence_get_rates() {
    $rates = get_option(NTF_FENCE_OPTION);
    if (!$rates) { $rates = ntf_fence_default_rates(); }
    return rest_ensure_response($rates);
}

function ntf_fence_save_lead(WP_REST_Request $request) {
    $data = $request->get_json_params();
    $stage = (isset($data['stage']) && $data['stage'] === 'quote') ? 'quote' : 'lead';
    $name  = isset($data['name'])  ? sanitize_text_field($data['name'])  : '';
    $email = isset($data['email']) ? sanitize_email($data['email'])      : '';
    $phone = isset($data['phone']) ? sanitize_text_field($data['phone']) : '';
    $type  = isset($data['fenceType']) ? sanitize_text_field($data['fenceType']) : '';
    $height = isset($data['height']) ? sanitize_text_field($data['height']) : '';
    $feet   = isset($data['feet']) ? sanitize_text_field($data['feet']) : '';
    $gates  = isset($data['gatesSummary']) ? sanitize_text_field($data['gatesSummary']) : '';
    $estLow  = isset($data['estimateLow']) ? sanitize_text_field($data['estimateLow']) : '';
    $estHigh = isset($data['estimateHigh']) ? sanitize_text_field($data['estimateHigh']) : '';

    if (empty($name) || empty($phone) || !is_email($email)) {
        return new WP_Error('ntf_fence_bad_lead', 'Name, a valid email, and phone are required.', array('status' => 400));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ntf_fence_leads';
    $wpdb->insert($table, array(
        'stage'         => $stage,
        'name'          => $name,
        'email'         => $email,
        'phone'         => $phone,
        'fence_type'    => $type,
        'height'        => $height,
        'feet'          => $feet,
        'gates_summary' => $gates,
        'estimate_low'  => $estLow,
        'estimate_high' => $estHigh,
        'created_at'    => current_time('mysql'),
    ));

    $rates = get_option(NTF_FENCE_OPTION);
    $notify = ($rates && !empty($rates['business']['notifyEmail'])) ? $rates['business']['notifyEmail'] : NTF_FENCE_FALLBACK_EMAIL;
    if ($notify) {
        if ($stage === 'quote') {
            $subject = 'Quote request: ' . $name . ' — $' . $estLow . '–$' . $estHigh;
            $body =
                "A visitor completed the fence estimator and requested a quote.\n\n" .
                "Name: $name\nEmail: $email\nPhone: $phone\n\n" .
                "Fence type: $type\nHeight: $height ft\nLength: $feet ft\nGates: $gates\n" .
                "Estimated range: \$$estLow – \$$estHigh\n";
        } else {
            $subject = 'New fence estimator lead: ' . $name;
            $body =
                "A new visitor started the fence estimator and requested their estimate.\n\n" .
                "Name: $name\nEmail: $email\nPhone: $phone\nInterested in: $type\n";
        }
        wp_mail($notify, $subject, $body);
    }

    // Queue the JobNimbus push. Runs from WP-Cron so the visitor never waits on the CRM API
    // and a JobNimbus outage can never break the estimate or the notification email.
    wp_schedule_single_event(time(), 'ntf_fence_jobnimbus_push', array(array(
        'stage' => $stage, 'name' => $name, 'email' => $email, 'phone' => $phone, 'type' => $type,
        'height' => $height, 'feet' => $feet, 'gates' => $gates, 'low' => $estLow, 'high' => $estHigh,
    )));
    if (function_exists('spawn_cron')) { spawn_cron(); }

    return rest_ensure_response(array('ok' => true));
}

/** ---------- JobNimbus push (uses the job-nimbus-client plugin, same API token as the Gravity Forms hook) ---------- */
add_action('ntf_fence_jobnimbus_push', 'ntf_fence_jobnimbus_push');

function ntf_fence_jn_log($msg) {
    $log = get_option('ntf_fence_jn_log', array());
    if (!is_array($log)) { $log = array(); }
    $log[] = current_time('mysql') . ' ' . $msg;
    update_option('ntf_fence_jn_log', array_slice($log, -50), false);
}

function ntf_fence_jobnimbus_push($lead) {
    if (!function_exists('jnc_jobnimbus_client')) { ntf_fence_jn_log('SKIP job-nimbus-client plugin not active'); return; }
    $email = isset($lead['email']) ? $lead['email'] : '';
    $name  = isset($lead['name']) ? trim($lead['name']) : '';
    $stage = (isset($lead['stage']) && $lead['stage'] === 'quote') ? 'quote' : 'lead';
    $parts = preg_split('/\s+/', $name, 2);
    $first = $parts[0];
    $last  = isset($parts[1]) ? $parts[1] : '';

    $desc = ($stage === 'quote' ? "Fence estimator: visitor completed the estimate and requested a quote.\n\n"
                                : "Fence estimator: visitor started the estimator and requested their estimate.\n\n");
    $desc .= 'Interested in: ' . $lead['type'] . "\n";
    if ($lead['height'] !== '') { $desc .= 'Height: ' . $lead['height'] . " ft\n"; }
    if ($lead['feet'] !== '')   { $desc .= 'Length: ' . $lead['feet'] . " ft\n"; }
    if ($lead['gates'] !== '')  { $desc .= 'Gates: ' . $lead['gates'] . "\n"; }
    if ($lead['low'] !== '' && $lead['high'] !== '') { $desc .= 'Estimated range: $' . $lead['low'] . ' - $' . $lead['high'] . "\n"; }
    $desc .= 'Phone: ' . $lead['phone'] . "\nEmail: " . $email;

    $contact_payload = array('type' => 'contact', 'name' => $name, 'first_name' => $first, 'email' => $email, 'mobile_phone' => $lead['phone']);
    if ($last !== '') { $contact_payload['last_name'] = $last; }
    $job_name = ($stage === 'quote' ? 'Fence Estimate Quote - ' : 'Fence Estimator Lead - ') . $name;
    $job_payload = array(
        'type' => 'job', 'name' => $job_name, 'description' => $desc,
        'state_text' => defined('JNGI_DEFAULT_STATE_TEXT') ? JNGI_DEFAULT_STATE_TEXT : 'FL',
        'source_name' => 'Web Search',
    );

    // Dry run for testing: log what would be sent, touch nothing.
    if (apply_filters('ntf_fence_jobnimbus_dry_run', false)) {
        ntf_fence_jn_log('DRY-RUN ' . $stage . ' contact=' . wp_json_encode($contact_payload) . ' job=' . wp_json_encode($job_payload));
        return;
    }

    $client = jnc_jobnimbus_client();
    $key    = 'ntf_jn_job_' . md5(strtolower($email));

    // Completing the estimate updates the job the gate step already created instead of adding a second one.
    if ($stage === 'quote') {
        $prev = get_transient($key);
        if ($prev) {
            $upd = $client->request('PUT', 'jobs/' . rawurlencode($prev), array('body' => array('name' => $job_name, 'description' => $desc)));
            if (!is_wp_error($upd)) { ntf_fence_jn_log('OK quote updated job ' . $prev . ' for ' . $email); return; }
            ntf_fence_jn_log('WARN job update failed (' . $upd->get_error_message() . '), creating a new job');
        }
    }

    $contact = $client->find_contact_by_email($email);
    if (is_wp_error($contact)) { ntf_fence_jn_log('WARN contact search failed: ' . $contact->get_error_message()); $contact = null; }
    if (!is_array($contact) || empty($contact['jnid'])) {
        $contact = $client->create_contact($contact_payload);
        if (is_wp_error($contact) || !is_array($contact) || empty($contact['jnid'])) {
            ntf_fence_jn_log('FAIL contact create for ' . $email . ': ' . (is_wp_error($contact) ? $contact->get_error_message() : 'unexpected response'));
            return;
        }
    }
    $job_payload['primary'] = array('id' => $contact['jnid'], 'type' => 'contact', 'name' => $name);
    $job = $client->create_job($job_payload);
    if (is_wp_error($job) || !is_array($job) || empty($job['jnid'])) {
        ntf_fence_jn_log('FAIL job create for ' . $email . ': ' . (is_wp_error($job) ? $job->get_error_message() : 'unexpected response'));
        return;
    }
    set_transient($key, $job['jnid'], 14 * DAY_IN_SECONDS);
    ntf_fence_jn_log('OK ' . $stage . ' contact ' . $contact['jnid'] . ' job ' . $job['jnid'] . ' for ' . $email);
}

/** ---------- Admin menu: Fence Estimator > Pricing, Leads ---------- */
add_action('admin_menu', function () {
    add_menu_page('Fence Pricing', 'Fence Estimator', 'manage_options', 'ntf-fence-pricing', 'ntf_fence_render_pricing_page', 'dashicons-fence', 26);
    add_submenu_page('ntf-fence-pricing', 'Fence Pricing', 'Pricing', 'manage_options', 'ntf-fence-pricing', 'ntf_fence_render_pricing_page');
    add_submenu_page('ntf-fence-pricing', 'Fence Leads', 'Leads', 'manage_options', 'ntf-fence-leads', 'ntf_fence_render_leads_page');
});

/** ---------- Admin: Pricing settings page (saved directly to the database, never exposed on the front end) ---------- */
add_action('admin_post_ntf_fence_save_pricing', 'ntf_fence_handle_save_pricing');
function ntf_fence_handle_save_pricing() {
    if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }
    check_admin_referer('ntf_fence_save_pricing');

    $defaults = ntf_fence_default_rates();
    $current  = get_option(NTF_FENCE_OPTION);
    if (!$current) { $current = $defaults; }

    $t = function ($n) { return isset($_POST[$n]) ? sanitize_text_field(wp_unslash($_POST[$n])) : ''; };
    // Raw-string reader so we can tell "left blank" (= not offered) apart from "0".
    $raw = function ($n) { return isset($_POST[$n]) ? trim(wp_unslash($_POST[$n])) : ''; };

    $rates = $current;
    $rates['business']['name']        = $t('biz_name');
    $rates['business']['phone']       = $t('biz_phone');
    $rates['business']['notifyEmail'] = sanitize_email($_POST['biz_notify_email'] ?? '');

    foreach ($defaults['fence'] as $key => $row) {
        $label  = $t('fence_' . $key . '_label');
        $rates['fence'][$key]['label']  = $label !== '' ? $label : ($current['fence'][$key]['label'] ?? $row['label']);
        $rates['fence'][$key]['swatch'] = $current['fence'][$key]['swatch'] ?? $row['swatch'];

        $heights = array();
        foreach (NTF_FENCE_HEIGHTS as $h) {
            $lowRaw  = $raw('fence_' . $key . '_' . $h . '_low');
            $highRaw = $raw('fence_' . $key . '_' . $h . '_high');
            if ($lowRaw === '' && $highRaw === '') {
                continue; // left blank on purpose — this height isn't offered for this material
            }
            $heights[$h] = array(
                'low'  => $lowRaw !== '' ? floatval($lowRaw) : 0,
                'high' => $highRaw !== '' ? floatval($highRaw) : 0,
            );
        }
        $rates['fence'][$key]['heights'] = $heights;
    }

    foreach ($defaults['gates'] as $key => $row) {
        $label = $t('gate_' . $key . '_label');
        $rates['gates'][$key]['label'] = $label !== '' ? $label : ($current['gates'][$key]['label'] ?? $row['label']);
        $rates['gates'][$key]['low']   = floatval($raw('gate_' . $key . '_low'));
        $rates['gates'][$key]['high']  = floatval($raw('gate_' . $key . '_high'));
    }

    foreach ($defaults['fence'] as $key => $row) {
        $rates['gateMaterialAdjust'][$key] = floatval($raw('gatemat_' . $key));
    }

    $removalLabel = $t('removal_label');
    $rates['modifiers']['removalOldFence']['label'] = $removalLabel !== '' ? $removalLabel : 'Old fence removal';
    $rates['modifiers']['removalOldFence']['low']   = floatval($raw('removal_low'));
    $rates['modifiers']['removalOldFence']['high']  = floatval($raw('removal_high'));

    update_option(NTF_FENCE_OPTION, $rates);

    wp_safe_redirect(add_query_arg(array('page' => 'ntf-fence-pricing', 'saved' => '1'), admin_url('admin.php')));
    exit;
}

add_action('admin_post_ntf_fence_reset_pricing', function () {
    if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }
    check_admin_referer('ntf_fence_reset_pricing');
    update_option(NTF_FENCE_OPTION, ntf_fence_default_rates());
    wp_safe_redirect(add_query_arg(array('page' => 'ntf-fence-pricing', 'reset' => '1'), admin_url('admin.php')));
    exit;
});

function ntf_fence_render_pricing_page() {
    $rates = get_option(NTF_FENCE_OPTION);
    if (!$rates) { $rates = ntf_fence_default_rates(); }
    $heights = NTF_FENCE_HEIGHTS;
    ?>
    <div class="wrap">
        <h1>Fence Estimator &mdash; Pricing</h1>
        <p>Every number and label here powers the public calculator. This page is only visible in your WordPress dashboard &mdash; it never appears on the site itself.</p>

        <?php if (isset($_GET['saved'])) : ?>
            <div class="notice notice-success is-dismissible"><p>Saved &mdash; live on the estimator now.</p></div>
        <?php elseif (isset($_GET['reset'])) : ?>
            <div class="notice notice-success is-dismissible"><p>Reset to recommended defaults.</p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('ntf_fence_save_pricing'); ?>
            <input type="hidden" name="action" value="ntf_fence_save_pricing">

            <h2 class="title">Business contact info</h2>
            <table class="form-table">
                <tr>
                    <th><label for="biz_name">Business name</label></th>
                    <td><input class="regular-text" type="text" id="biz_name" name="biz_name" value="<?php echo esc_attr($rates['business']['name']); ?>"></td>
                </tr>
                <tr>
                    <th><label for="biz_phone">Phone number (used on the Call button)</label></th>
                    <td><input class="regular-text" type="text" id="biz_phone" name="biz_phone" value="<?php echo esc_attr($rates['business']['phone']); ?>"></td>
                </tr>
                <tr>
                    <th><label for="biz_notify_email">Email address for quotes &amp; leads</label></th>
                    <td>
                        <input class="regular-text" type="email" id="biz_notify_email" name="biz_notify_email" value="<?php echo esc_attr($rates['business']['notifyEmail']); ?>">
                        <p class="description">Sent here the moment someone submits the "See My Estimate" form, and again when they request a final quote.</p>
                    </td>
                </tr>
            </table>

            <h2 class="title">Fence materials &amp; pricing by height</h2>
            <p class="description">Price per linear foot, low and high, at each height. Leave both fields blank for a height your business doesn't offer for that material &mdash; it will be hidden on the calculator (like Metal Dura Fence at 8 ft).</p>

            <?php foreach ($rates['fence'] as $key => $m) : ?>
                <table class="widefat" style="max-width:640px;margin-bottom:26px;">
                    <thead>
                        <tr>
                            <th style="width:55%;">
                                <input type="text" class="regular-text" name="fence_<?php echo esc_attr($key); ?>_label" value="<?php echo esc_attr($m['label']); ?>" style="font-weight:600;">
                            </th>
                            <th>Low $/ft</th>
                            <th>High $/ft</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($heights as $h) :
                        $hv = isset($m['heights'][$h]) ? $m['heights'][$h] : null; ?>
                        <tr>
                            <td><?php echo esc_html($h); ?> ft</td>
                            <td><input type="number" step="0.5" name="fence_<?php echo esc_attr($key); ?>_<?php echo esc_attr($h); ?>_low" value="<?php echo $hv ? esc_attr($hv['low']) : ''; ?>" placeholder="not offered" style="width:110px;"></td>
                            <td><input type="number" step="0.5" name="fence_<?php echo esc_attr($key); ?>_<?php echo esc_attr($h); ?>_high" value="<?php echo $hv ? esc_attr($hv['high']) : ''; ?>" placeholder="not offered" style="width:110px;"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>

            <h2 class="title">Gate rates &mdash; price per gate</h2>
            <table class="widefat" style="max-width:700px;">
                <thead><tr><th>Gate type</th><th>Low $</th><th>High $</th></tr></thead>
                <tbody>
                <?php foreach ($rates['gates'] as $key => $g) : ?>
                    <tr>
                        <td><input type="text" class="regular-text" name="gate_<?php echo esc_attr($key); ?>_label" value="<?php echo esc_attr($g['label']); ?>"></td>
                        <td><input type="number" step="10" name="gate_<?php echo esc_attr($key); ?>_low" value="<?php echo esc_attr($g['low']); ?>" style="width:100px;"></td>
                        <td><input type="number" step="10" name="gate_<?php echo esc_attr($key); ?>_high" value="<?php echo esc_attr($g['high']); ?>" style="width:100px;"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2 class="title">Gate price adjustment by fence material (%)</h2>
            <table class="widefat" style="max-width:450px;">
                <thead><tr><th>Material</th><th>Adjustment %</th></tr></thead>
                <tbody>
                <?php foreach ($rates['fence'] as $key => $m) : ?>
                    <tr>
                        <td><?php echo esc_html($m['label']); ?></td>
                        <td><input type="number" step="1" name="gatemat_<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($rates['gateMaterialAdjust'][$key] ?? 0); ?>" style="width:100px;"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2 class="title">Site condition add-ons</h2>
            <table class="widefat" style="max-width:600px;">
                <tbody>
                    <tr>
                        <td><input type="text" class="regular-text" name="removal_label" value="<?php echo esc_attr($rates['modifiers']['removalOldFence']['label'] ?? 'Old fence removal'); ?>"></td>
                        <td>Low $/ft <input type="number" step="0.5" name="removal_low" value="<?php echo esc_attr($rates['modifiers']['removalOldFence']['low']); ?>" style="width:100px;"></td>
                        <td>High $/ft <input type="number" step="0.5" name="removal_high" value="<?php echo esc_attr($rates['modifiers']['removalOldFence']['high']); ?>" style="width:100px;"></td>
                    </tr>
                </tbody>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">Save pricing</button>
            </p>
        </form>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Reset all pricing and labels to the recommended defaults?');">
            <?php wp_nonce_field('ntf_fence_reset_pricing'); ?>
            <input type="hidden" name="action" value="ntf_fence_reset_pricing">
            <button type="submit" class="button">Reset to recommended defaults</button>
        </form>
    </div>
    <?php
}

function ntf_fence_render_leads_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'ntf_fence_leads';
    $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 500");
    ?>
    <div class="wrap">
        <h1>Fence Estimator Leads</h1>
        <p>
            <a class="button button-primary" href="<?php echo esc_url(admin_url('admin-post.php?action=ntf_fence_export_leads')); ?>">Download CSV</a>
        </p>
        <table class="widefat striped">
            <thead><tr><th>Date</th><th>Stage</th><th>Name</th><th>Email</th><th>Phone</th><th>Fence Type</th><th>Height</th><th>Feet</th><th>Gates</th><th>Estimate</th></tr></thead>
            <tbody>
            <?php if ($rows) : foreach ($rows as $r) : ?>
                <tr>
                    <td><?php echo esc_html($r->created_at); ?></td>
                    <td><?php echo $r->stage === 'quote' ? '<strong>Quote request</strong>' : 'Lead'; ?></td>
                    <td><?php echo esc_html($r->name); ?></td>
                    <td><?php echo esc_html($r->email); ?></td>
                    <td><?php echo esc_html($r->phone); ?></td>
                    <td><?php echo esc_html($r->fence_type); ?></td>
                    <td><?php echo esc_html($r->height); ?></td>
                    <td><?php echo esc_html($r->feet); ?></td>
                    <td><?php echo esc_html($r->gates_summary); ?></td>
                    <td><?php echo ($r->estimate_low !== '') ? esc_html('$' . $r->estimate_low . '–$' . $r->estimate_high) : ''; ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="10">No leads yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

add_action('admin_post_ntf_fence_export_leads', function () {
    if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }
    global $wpdb;
    $table = $wpdb->prefix . 'ntf_fence_leads';
    $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="fence-estimator-leads.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array('Date', 'Stage', 'Name', 'Email', 'Phone', 'Fence Type', 'Height', 'Feet', 'Gates', 'Estimate Low', 'Estimate High'));
    foreach ($rows as $r) {
        fputcsv($out, array($r['created_at'], $r['stage'], $r['name'], $r['email'], $r['phone'], $r['fence_type'], $r['height'], $r['feet'], $r['gates_summary'], $r['estimate_low'], $r['estimate_high']));
    }
    fclose($out);
    exit;
});

/** ---------- Shortcode: [ntf_fence_estimator] ---------- */
function ntf_fence_shortcode() {
    wp_enqueue_style(
        'ntf-fence-fonts',
        'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap',
        array(),
        null
    );
    wp_enqueue_style('ntf-fence-css', plugins_url('assets/calculator.css', __FILE__), array(), NTF_FENCE_VERSION);
    wp_enqueue_script('ntf-fence-js', plugins_url('assets/calculator.js', __FILE__), array(), NTF_FENCE_VERSION, true);

    wp_localize_script('ntf-fence-js', 'NTF_FENCE_CONFIG', array(
        'restUrl'  => esc_url_raw(rest_url('ntf-fence/v1/rates')),
        'leadsUrl' => esc_url_raw(rest_url('ntf-fence/v1/leads')),
        'logoUrl'  => plugins_url('assets/logo.png', __FILE__),
        'defaults' => ntf_fence_default_rates(),
    ));

    ob_start();
    include plugin_dir_path(__FILE__) . 'template.php';
    return ob_get_clean();
}
add_shortcode('ntf_fence_estimator', 'ntf_fence_shortcode');
