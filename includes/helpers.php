<?php
//a one-time message to show on the next page
function setFlash($type, $message) {
    $_SESSION['flash_type']    = $type;
    $_SESSION['flash_message'] = $message;
}

// Get and clear the flash message
function getFlash() {
    if (empty($_SESSION['flash_message'])) {
        return null;
    }

    $flash = [
        'type'    => $_SESSION['flash_type'],
        'message' => $_SESSION['flash_message']
    ];

    unset($_SESSION['flash_type'], $_SESSION['flash_message']);

    return $flash;
}
