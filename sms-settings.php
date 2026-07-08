<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

if (is_file(__DIR__ . '/config.php')) {
    require_auth();
    Access::requireAdminHtml();
}

redirect_to('index.php#sms-settings');
