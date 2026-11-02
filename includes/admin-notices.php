<?php
/**
 * Activation notice and requirement summary.
 *
 * @package SuchisSimpleBackup
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Queue a one-time notice after plugin activation.
 *
 * @return void
 */
function Ssbhf_Set_Activation_notice(): void
{
    set_transient('ssbhf_activation_notice', 1, 5 * MINUTE_IN_SECONDS);
}

/**
 * Show the requirement checklist after plugin activation.
 *
 * @return void
 */
function Ssbhf_Show_Activation_notice(): void
{
    if (!current_user_can('manage_options') || !get_transient('ssbhf_activation_notice')) {
        return;
    }

    delete_transient('ssbhf_activation_notice');
    $checks = Ssbhf_Get_System_checks();
    $ready = Ssbhf_System_Checks_Passed($checks);
    $panel_id = 'ssbhf-activation-checks';

    echo '<div class="notice ' . ($ready ? 'notice-success' : 'notice-error') . ' ssb-checks-notice">';
    Ssbhf_Render_Checklist_Heading(
        $ready ? 'Suchis Simple Backup ist einsatzbereit.' : 'Suchis Simple Backup benötigt Aufmerksamkeit.',
        $panel_id,
        $ready
    );
    echo '<div id="' . esc_attr($panel_id) . '" class="ssb-checks__content"' . ($ready ? ' hidden' : '') . '>';
    Ssbhf_Render_System_Checklist($checks);
    echo '<p>Weitere Details findest du unter <strong>Werkzeuge &rarr; Suchis Simple Backup</strong>.</p>';
    echo '</div></div>';
}
