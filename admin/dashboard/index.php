<?php
require_once dirname(__DIR__, 2) . '/config/database.php';

// Redirect aman ke dashboard admin
header("Location: " . BASE_URL . "admin/index.php");
exit;
