<?php
header('Content-Type: application/json');

// This file simply returns a success response to indicate the server is running
echo json_encode([
    'ok' => true,
    'server_running' => true,
    'timestamp' => date('Y-m-d H:i:s')
]);