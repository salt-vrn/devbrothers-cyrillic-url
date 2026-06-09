<?php
/**
 * Uninstall DevBrothers Cyrillic URL
 *
 * @package DevBrothers_Cyrillic_Slugs
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('dbcs_settings');
delete_transient('dbcs_convert_total');

if (is_multisite()) {
    $dbcs_sites = get_sites(['number' => 999]);

    foreach ($dbcs_sites as $dbcs_site) {
        switch_to_blog($dbcs_site->blog_id);

        delete_option('dbcs_settings');
        delete_transient('dbcs_convert_total');

        restore_current_blog();
    }
}
