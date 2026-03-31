<?php

// Mailman3 Mailing Lists — Menu items for the Email module
// Injected via mail/lib/menu.d/
// Reorders nav to place Mailman3 after the existing Mailing List section
// and Mailman3 Admin before Server Settings

// Build our nav sections
$mm3_items = array();
$mm3_items[] = array(
    'title'   => 'Mailing Lists (Mailman3)',
    'target'  => 'content',
    'link'    => 'mail/mailmanthree_list_list.php',
    'html_id' => 'mailmanthree_list_list',
);
$mm3_items[] = array(
    'title'   => 'Add Mailing List',
    'target'  => 'content',
    'link'    => 'mail/mailmanthree_list_edit.php',
    'html_id' => 'mailmanthree_list_edit',
);
$mm3_nav = array(
    'title' => 'Mailing Lists (Mailman3)',
    'open'  => 1,
    'items' => $mm3_items,
);
unset($mm3_items);

// Admin settings section
$mm3_admin_nav = null;
if($app->auth->is_admin()) {
    $mm3_admin_items = array();
    $mm3_admin_items[] = array(
        'title'   => 'Mailman3 Settings',
        'target'  => 'content',
        'link'    => 'mail/mailmanthree_config_edit.php',
        'html_id' => 'mailmanthree_config_edit',
    );
    $mm3_admin_nav = array(
        'title' => 'Mailman3 Admin',
        'open'  => 1,
        'items' => $mm3_admin_items,
    );
    unset($mm3_admin_items);
}

// Insert into existing nav at the right positions
// Find "Mailing List" section and insert after it
$new_nav = array();
$inserted_list = false;
$inserted_admin = false;

foreach($module['nav'] as $nav_section) {
    $new_nav[] = $nav_section;

    // Insert Mailman3 lists right after existing Mailing List section
    if(!$inserted_list && isset($nav_section['title']) && strpos($nav_section['title'], 'Mailing List') !== false) {
        $new_nav[] = $mm3_nav;
        $inserted_list = true;
    }

    // Insert admin settings right before Server Settings
    if(!$inserted_admin && $mm3_admin_nav && isset($nav_section['title']) && strpos($nav_section['title'], 'Server Settings') !== false) {
        // Remove the Server Settings we just added, insert admin before it
        array_pop($new_nav);
        $new_nav[] = $mm3_admin_nav;
        $new_nav[] = $nav_section;
        $inserted_admin = true;
    }
}

// If sections weren't found, append at end
if(!$inserted_list) {
    $new_nav[] = $mm3_nav;
}
if(!$inserted_admin && $mm3_admin_nav) {
    $new_nav[] = $mm3_admin_nav;
}

$module['nav'] = $new_nav;
unset($new_nav, $mm3_nav, $mm3_admin_nav, $inserted_list, $inserted_admin);
