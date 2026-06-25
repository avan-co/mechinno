<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

Auth::logout();
redirect_to('login.php');
