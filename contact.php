<?php
/**
 * Arti Pierre — réception des demandes de devis
 * Envoie le lead par email. Aucun service tiers.
 */
$TO   = 'favreau3499@gmail.com, aymeric@cleanibat.fr';
// Expéditeur : cleanibat.fr (SPF + DKIM valides sur o2switch) pour délivrabilité garantie.
// artipierre.fr n'a pas encore de DKIM (DNS chez Squarespace).
$FROM = 'no-reply@cleanibat.fr';
$SITE = 'Arti Pierre';

header('Content-Type: application/json; charset=utf-8');

function out($ok, $msg = '') {
    echo json_encode(['success' => $ok, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(false, 'Méthode non autorisée.');
}

// Anti-spam honeypot
if (!empty($_POST['botcheck'])) {
    out(true);
}

function clean($k) {
    return isset($_POST[$k]) ? trim(strip_tags($_POST[$k])) : '';
}
$prenom      = clean('prenom');
$telephone   = clean('telephone');
$email       = clean('email');
$type_projet = clean('type_projet');
$ville       = clean('ville');
$message     = clean('message');

if ($prenom === '' || $telephone === '' || $email === '' || $ville === '') {
    http_response_code(422);
    out(false, 'Merci de remplir les champs obligatoires.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    out(false, 'Adresse email invalide.');
}

$ip   = $_SERVER['REMOTE_ADDR'] ?? '';
$body = "Nouvelle demande de devis depuis le site Arti Pierre\n";
$body .= "------------------------------------------------------\n\n";
$body .= "Prénom     : $prenom\n";
$body .= "Téléphone  : $telephone\n";
$body .= "Email      : $email\n";
$body .= "Type       : " . ($type_projet !== '' ? $type_projet : '(non précisé)') . "\n";
$body .= "Ville      : $ville\n\n";
$body .= "Message :\n" . ($message !== '' ? $message : '(non précisé)') . "\n\n";
$body .= "------------------------------------------------------\n";
$body .= "IP : $ip\n";

$subject = "Nouveau devis Arti Pierre — $prenom · $ville";
$enc     = '=?UTF-8?B?' . base64_encode($subject) . '?=';

$headers  = "From: $SITE <$FROM>\r\n";
$headers .= "Reply-To: $prenom <$email>\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: base64\r\n";

$sent = @mail($TO, $enc, chunk_split(base64_encode($body)), $headers, "-f$FROM");

if ($sent) {
    out(true);
} else {
    http_response_code(500);
    out(false, "L'envoi a échoué. Appelez-nous au 07 69 88 64 54.");
}
