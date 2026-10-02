<?php
require_once '../config/database.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    header("Location: " . BASE_URL . "index.php"); exit;
}
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
header("Location: index.php?view=ujian&id=" . $id);
exit;
