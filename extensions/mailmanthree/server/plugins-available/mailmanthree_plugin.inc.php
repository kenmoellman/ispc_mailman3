<?php

/**
 * Mailman3 Server Plugin for ISPConfig3
 *
 * Ensures Postfix transport_maps includes Mailman3's LMTP transport map
 * after ISPConfig regenerates Postfix configuration (including upgrades).
 */

class mailmanthree_plugin {

    var $plugin_name        = 'mailmanthree_plugin';
    var $class_name         = 'mailmanthree_plugin';

    private $mailmanthree_transport = 'hash:/var/lib/mailmanthree/data/postfix_lmtp';

    function onInstall() {
        global $conf;
        return true;
    }

    function onLoad() {
        global $app;
        $app->plugins->registerEvent('server_update', $this->plugin_name, 'postfix_check');
        $app->plugins->registerEvent('server_insert', $this->plugin_name, 'postfix_check');
    }

    /**
     * After ISPConfig updates server config, ensure Mailman3 transport is in transport_maps.
     */
    function postfix_check($event_name, $data) {
        global $app;

        // Only act if Mailman3 transport map file exists
        if (!file_exists('/var/lib/mailmanthree/data/postfix_lmtp')) {
            return;
        }

        // Check current transport_maps
        $output = [];
        exec('postconf -h transport_maps 2>/dev/null', $output);
        $current = isset($output[0]) ? trim($output[0]) : '';

        if (strpos($current, $this->mailmanthree_transport) === false) {
            // Mailman3 transport is missing — re-add it
            $new_value = $this->mailmanthree_transport . ', ' . $current;
            exec("postconf -e " . escapeshellarg("transport_maps = {$new_value}"));
            exec('postfix reload 2>/dev/null');
            $app->log('mailmanthree_plugin: re-added Mailman3 transport_maps entry after ISPConfig config change', LOGLEVEL_WARN);
        }
    }
}
