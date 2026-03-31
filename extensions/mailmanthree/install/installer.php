<?php

class mailmanthree_installer extends extension_installer_base {

    public function __construct() {
        parent::__construct();
    }

    public function install() {
        global $app;

        $app->log('Installing mailmanthree extension', LOGLEVEL_DEBUG);
        $app->uses('extension_installer');

        // Create database tables
        $sql_file = $this->extension_basedir . '/mailmanthree/install/sql/mailmanthree.sql';
        if (file_exists($sql_file)) {
            $sql = file_get_contents($sql_file);
            $statements = explode(';', $sql);
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $app->db->query($statement);
                }
            }
            $app->log('mailmanthree: database tables created', LOGLEVEL_DEBUG);
        }

        // Enable server plugin
        $plugin_src = $this->ispconfig_dir . '/server/plugins-available/mailmanthree_plugin.inc.php';
        $plugin_dst = $this->ispconfig_dir . '/server/plugins-enabled/mailmanthree_plugin.inc.php';
        if (file_exists($plugin_src) && !file_exists($plugin_dst)) {
            symlink($plugin_src, $plugin_dst);
            $app->log('mailmanthree: server plugin enabled', LOGLEVEL_DEBUG);
        }

        // Make Postorius user script executable
        $script = '/usr/local/bin/manage_postorius_user.py';
        if (file_exists($script)) {
            chmod($script, 0755);
        }

        parent::install();
    }

    public function update() {
        parent::update();
    }

    public function uninstall() {
        global $app;

        $app->log('Uninstalling mailmanthree extension', LOGLEVEL_DEBUG);
        $app->uses('extension_installer');

        // Disable extension (removes symlinks)
        $this->disable();

        // Remove server plugin symlink
        $plugin_link = $this->ispconfig_dir . '/server/plugins-enabled/mailmanthree_plugin.inc.php';
        if (is_link($plugin_link)) {
            unlink($plugin_link);
        }

        // Remove extension directory
        $ext_dir = $this->extension_basedir . '/mailmanthree';
        if (!empty($ext_dir) && is_dir($ext_dir)) {
            exec('rm -rf ' . escapeshellarg($ext_dir));
        }

        // Note: database tables are NOT dropped on uninstall (preserve data)
        $app->log('mailmanthree: uninstalled (database tables preserved)', LOGLEVEL_DEBUG);

        parent::uninstall();
    }

    public function enable() {
        global $app;

        $app->log('Enabling mailmanthree extension', LOGLEVEL_DEBUG);
        $app->uses('extension_installer');
        $app->extension_installer->enable_files('mailmanthree');

        // Enable server plugin
        $plugin_src = $this->ispconfig_dir . '/server/plugins-available/mailmanthree_plugin.inc.php';
        $plugin_dst = $this->ispconfig_dir . '/server/plugins-enabled/mailmanthree_plugin.inc.php';
        if (file_exists($plugin_src) && !file_exists($plugin_dst)) {
            symlink($plugin_src, $plugin_dst);
        }

        parent::enable();
    }

    public function disable() {
        global $app;

        $app->log('Disabling mailmanthree extension', LOGLEVEL_DEBUG);
        $app->uses('extension_installer');
        $app->extension_installer->disable_files('mailmanthree');

        // Disable server plugin
        $plugin_link = $this->ispconfig_dir . '/server/plugins-enabled/mailmanthree_plugin.inc.php';
        if (is_link($plugin_link)) {
            unlink($plugin_link);
        }

        parent::disable();
    }
}
