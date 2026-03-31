<?php

require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';

//* Check permissions for module
$app->auth->check_module_permissions('mail');

// Clear session sync timestamp to force a fresh sync
unset($_SESSION['mailmanthree_last_sync']);

// Redirect to list page with sync flag
header('Location: /mail/mailmanthree_list_list.php?sync=1');
exit;
