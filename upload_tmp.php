<?php
$k = $_POST['k'] ?? '';
if ($k !== 'arti_img_2026') { http_response_code(403); exit; }
$n = preg_replace('/[^a-z0-9\-]/', '', $_POST['n'] ?? '');
$c = base64_decode($_POST['c'] ?? '');
if (!$n || !$c) { http_response_code(400); exit; }
file_put_contents(__DIR__ . '/assets/' . $n . '.webp', $c);
echo 'OK:' . strlen($c);
