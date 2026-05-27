<?php

function requireLogin() {
    if (empty($_SESSION['admin_id'])) {
        header('Location: ' . BASE_URL . '/../index.php');
        exit();
    }
}
