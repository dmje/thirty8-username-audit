<?php
/**
 * Plugin Name: Thirty8 Duplicate Users
 * Description: READ-ONLY diagnostic tool. Finds users with duplicate email addresses across the network. No data is modified.
 * Version: 2.2.0
 * Author: Thirty8 Digital
 * Network: true
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( is_network_admin() ) {
    add_action( 'network_admin_menu', 't8ua_add_network_menu' );
    add_action( 'admin_init', 't8ua_maybe_export_csv' );
}

function t8ua_maybe_export_csv() {
    if (
        isset( $_GET['page'] ) && $_GET['page'] === 'thirty8-username-audit' &&
        isset( $_GET['export'] ) && $_GET['export'] === 'csv' &&
        current_user_can( 'manage_network_users' )
    ) {
        $groups = t8ua_get_duplicate_users();
        t8ua_export_csv( $groups );
        exit;
    }
}

function t8ua_add_network_menu() {
    add_menu_page(
        'Duplicate Users',
        'Duplicate Users',
        'manage_network_users',
        'thirty8-username-audit',
        't8ua_render_page',
        'dashicons-groups',
        99
    );
}

function t8ua_get_user_post_count( $user_id ) {
    $sites = get_sites( [ 'number' => 0 ] );
    $total = 0;
    foreach ( $sites as $site ) {
        switch_to_blog( $site->blog_id );
        $total += (int) count_user_posts( $user_id, [ 'post', 'page' ], true );
        restore_current_blog();
    }
    return $total;
}

function t8ua_get_duplicate_users() {
    global $wpdb;

    // Find all emails that appear more than once - read only SELECT
    $duplicate_emails = $wpdb->get_col(
        "SELECT user_email
         FROM {$wpdb->users}
         GROUP BY user_email
         HAVING COUNT(*) > 1
         ORDER BY user_email ASC"
    );

    if ( empty( $duplicate_emails ) ) {
        return [];
    }

    $groups = [];

    foreach ( $duplicate_emails as $email ) {
        $users = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID, user_login, user_email, user_registered
             FROM {$wpdb->users}
             WHERE user_email = %s
             ORDER BY user_registered ASC",
            $email
        ), ARRAY_A );

        $accounts = [];
        foreach ( $users as $user ) {
            $sites = get_blogs_of_user( $user['ID'] );
            $site_list = [];
            foreach ( $sites as $site ) {
                $site_list[] = [
                    'label'     => $site->domain . $site->path,
                    'admin_url' => 'https://' . $site->domain . $site->path . 'wp-admin/users.php?s=' . rawurlencode( $user['user_login'] ),
                ];
            }

            $accounts[] = [
                'id'         => $user['ID'],
                'login'      => $user['user_login'],
                'registered' => $user['user_registered'],
                'sites'      => $site_list,
                'post_count' => t8ua_get_user_post_count( $user['ID'] ),
            ];
        }

        $groups[] = [
            'email'    => $email,
            'count'    => count( $accounts ),
            'accounts' => $accounts,
        ];
    }

    return $groups;
}

function t8ua_render_page() {
    $groups = t8ua_get_duplicate_users();
    $group_count = count( $groups );
    $account_count = array_sum( array_column( $groups, 'count' ) );


    ?>
    <div class="wrap">
        <h1>Duplicate Users <span style="font-size:13px;font-weight:400;color:#666;">— read-only diagnostic</span></h1>

        <div class="t8ua-summary">
            <div class="t8ua-stat t8ua-stat--alert">
                <span class="t8ua-stat-number"><?php echo esc_html( $group_count ); ?></span>
                <span class="t8ua-stat-label">Duplicate email groups</span>
            </div>
            <div class="t8ua-stat t8ua-stat--alert">
                <span class="t8ua-stat-number"><?php echo esc_html( $account_count ); ?></span>
                <span class="t8ua-stat-label">Accounts to consolidate</span>
            </div>
        </div>

        <?php if ( $group_count === 0 ) : ?>
            <div class="notice notice-success"><p>✅ No duplicate email addresses found.</p></div>
        <?php else : ?>
            <p>
                <a href="<?php echo esc_url( add_query_arg( 'export', 'csv' ) ); ?>" class="button button-secondary">
                    ⬇ Export as CSV
                </a>
            </p>

            <?php foreach ( $groups as $group ) : ?>
                <div class="t8ua-group">
                    <div class="t8ua-group-header">
                        <span class="t8ua-email"><?php echo esc_html( $group['email'] ); ?></span>
                        <span class="t8ua-badge"><?php echo esc_html( $group['count'] ); ?> accounts</span>
                    </div>

                    <table class="wp-list-table widefat fixed striped t8ua-table">
                        <thead>
                            <tr>
                                <th style="width:80px">User ID</th>
                                <th style="width:200px">Username</th>
                                <th style="width:160px">Registered</th>
                                <th style="width:100px">Posts/Pages</th>
                                <th>Sites</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $group['accounts'] as $i => $account ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $account['id'] ); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url( network_admin_url( 'users.php?s=' . urlencode( $group['email'] ) ) ); ?>" target="_blank" class="t8ua-username-link">
                                            <code class="t8ua-username"><?php echo esc_html( $account['login'] ); ?></code>
                                        </a>
                                        <?php if ( $i === 0 ) : ?>
                                            <span class="t8ua-oldest-label">oldest</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( substr( $account['registered'], 0, 10 ) ); ?></td>
                                    <td><?php echo esc_html( $account['post_count'] ); ?></td>
                                    <td>
                                        <?php if ( ! empty( $account['sites'] ) ) : ?>
                                            <?php foreach ( $account['sites'] as $site ) : ?>
                                                <a href="<?php echo esc_url( $site['admin_url'] ); ?>" target="_blank" class="t8ua-site t8ua-site-link"><?php echo esc_html( $site['label'] ); ?></a>
                                            <?php endforeach; ?>
                                        <?php else : ?>
                                            <em style="color:#999">No sites</em>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <style>
        .t8ua-summary {
            display: flex;
            gap: 16px;
            margin: 20px 0;
        }
        .t8ua-stat {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 16px 24px;
            min-width: 140px;
            text-align: center;
        }
        .t8ua-stat--alert {
            border-color: #d63638;
            background: #fff5f5;
        }
        .t8ua-stat-number {
            display: block;
            font-size: 32px;
            font-weight: 600;
            line-height: 1.2;
            color: #1d2327;
        }
        .t8ua-stat--alert .t8ua-stat-number {
            color: #d63638;
        }
        .t8ua-stat-label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        .t8ua-notice {
            background: #fff8e5;
            border-left: 4px solid #dba617;
            padding: 10px 14px;
            margin: 16px 0;
        }
        .t8ua-group {
            margin-bottom: 24px;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
        }
        .t8ua-group-header {
            background: #f6f7f7;
            border-bottom: 1px solid #ddd;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .t8ua-email {
            font-weight: 600;
            font-size: 14px;
        }
        .t8ua-badge {
            background: #d63638;
            color: #fff;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 10px;
        }
        .t8ua-username-link {
            text-decoration: none;
        }
        .t8ua-username-link:hover .t8ua-username {
            background: #c0392b;
            color: #fff;
        }
        .t8ua-username {
            font-size: 13px;
            transition: background 0.15s, color 0.15s;
        }
        .t8ua-oldest-label {
            display: inline-block;
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
            font-size: 10px;
            padding: 1px 6px;
            border-radius: 3px;
            margin-left: 6px;
            vertical-align: middle;
        }
        .t8ua-site {
            display: inline-block;
            background: #f0f0f1;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 11px;
            padding: 1px 6px;
            margin: 1px 3px 1px 0;
        }
        a.t8ua-site-link {
            color: #2271b1;
            text-decoration: none;
        }
        a.t8ua-site-link:hover {
            background: #2271b1;
            border-color: #2271b1;
            color: #fff;
        }
        .t8ua-table {
            border: none !important;
            border-radius: 0 !important;
        }
    </style>
    <?php
}

function t8ua_export_csv( $groups ) {
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="duplicate-users-' . date( 'Y-m-d' ) . '.csv"' );

    $output = fopen( 'php://output', 'w' );
    fputcsv( $output, [ 'Email', 'User ID', 'Username', 'Registered', 'Posts/Pages', 'Sites' ] );

    foreach ( $groups as $group ) {
        foreach ( $group['accounts'] as $account ) {
            fputcsv( $output, [
                $group['email'],
                $account['id'],
                $account['login'],
                substr( $account['registered'], 0, 10 ),
                $account['post_count'],
                implode( ', ', array_column( $account['sites'], 'label' ) ),
            ] );
        }
        // blank row between groups for readability
        fputcsv( $output, [] );
    }

    fclose( $output );
}
