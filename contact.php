<?php
/**
 * Arti Pierre — réception des demandes de devis
 * Envoie le lead (+ photos en pièces jointes) par email. Aucun service tiers.
 *
 * ====== À CONFIGURER ======
 *  - $TO   : où recevoir les leads (ton adresse)
 *  - $FROM : DOIT être une adresse de TON domaine hébergé sur o2switch
 *            (ex : no-reply@artipierre.fr) pour passer le SPF et éviter le spam.
 *            Crée-la dans cPanel → "Comptes de messagerie" si besoin.
 */
$TO   = 'favreau3499@gmail.com';
// Expéditeur : on envoie via cleanibat.fr (SPF + DKIM déjà valides sur le même
// serveur o2switch) pour une délivrabilité garantie en boîte de réception.
// artipierre.fr n'a pas encore de DKIM (DNS chez Squarespace) → Gmail rejetait.
// Le nom affiché reste « Arti Pierre » ; Reply-To = le client.
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

// --- Anti-spam (honeypot) : un bot coche la case cachée ---
if (!empty($_POST['botcheck'])) {
    out(true); // on fait croire au bot que c'est passé
}

// --- Récupération + nettoyage ---
function clean($k) {
    return isset($_POST[$k]) ? trim(strip_tags($_POST[$k])) : '';
}
$nom       = clean('nom');
$telephone = clean('telephone');
$email     = clean('email');
$ville     = clean('ville');
$message   = clean('message');

// --- Validation ---
if ($nom === '' || $telephone === '' || $email === '' || $ville === '') {
    http_response_code(422);
    out(false, 'Merci de remplir les champs obligatoires.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    out(false, 'Adresse email invalide.');
}

// --- Pièces jointes (photos) ---
$attachments = [];
$MAX_FILES = 8;
$MAX_SIZE  = 8 * 1024 * 1024; // 8 Mo / fichier
$allowed   = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif', 'image/gif'];

if (!empty($_FILES['photos']) && is_array($_FILES['photos']['tmp_name'])) {
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $count = 0;
    foreach ($_FILES['photos']['tmp_name'] as $i => $tmp) {
        if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($count >= $MAX_FILES) break;
        if ($_FILES['photos']['size'][$i] > $MAX_SIZE) continue;
        if (!is_uploaded_file($tmp)) continue;
        $type = $finfo ? finfo_file($finfo, $tmp) : $_FILES['photos']['type'][$i];
        if (!in_array($type, $allowed, true)) continue;
        $attachments[] = [
            'name' => preg_replace('/[^A-Za-z0-9._-]/', '_', $_FILES['photos']['name'][$i]),
            'type' => $type,
            'data' => file_get_contents($tmp),
        ];
        $count++;
    }
    if ($finfo) finfo_close($finfo);
}

// --- Corps du message ---
$ip   = $_SERVER['REMOTE_ADDR'] ?? '';
$body = "Nouvelle demande de devis depuis le site Arti Pierre\n";
$body .= "------------------------------------------------------\n\n";
$body .= "Nom        : $nom\n";
$body .= "Téléphone  : $telephone\n";
$body .= "Email      : $email\n";
$body .= "Ville      : $ville\n\n";
$body .= "Projet :\n" . ($message !== '' ? $message : '(non précisé)') . "\n\n";
$body .= "------------------------------------------------------\n";
$body .= "Photos jointes : " . count($attachments) . "\n";
$body .= "IP : $ip\n";

// --- Construction de l'email (MIME multipart) ---
$subject = "Nouveau devis Arti Pierre — $ville";
$boundary = '=_' . md5(uniqid((string) mt_rand(), true));

$headers  = "From: $SITE <$FROM>\r\n";
$headers .= "Reply-To: $nom <$email>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

$enc = '=?UTF-8?B?' . base64_encode($subject) . '?=';

$msg  = "--$boundary\r\n";
$msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
$msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
$msg .= chunk_split(base64_encode($body)) . "\r\n";

foreach ($attachments as $a) {
    $msg .= "--$boundary\r\n";
    $msg .= "Content-Type: {$a['type']}; name=\"{$a['name']}\"\r\n";
    $msg .= "Content-Transfer-Encoding: base64\r\n";
    $msg .= "Content-Disposition: attachment; filename=\"{$a['name']}\"\r\n\r\n";
    $msg .= chunk_split(base64_encode($a['data'])) . "\r\n";
}
$msg .= "--$boundary--";

$sent = @mail($TO, $enc, $msg, $headers, "-f$FROM");

if ($sent) {
    out(true);
} else {
    http_response_code(500);
    out(false, "L'envoi a échoué. Appelez-nous au 07 69 88 64 54.");
}
