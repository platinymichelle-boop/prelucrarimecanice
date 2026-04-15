<?php
/* ==========================
   CONFIG
========================== */
$adminEmail = "office@firma-ta.ro";
$subjectAdmin = "B2B RFQ – Mechanical Processing";
$subjectClient = "Your RFQ has been received";

$uploadDir = "uploads/";
$capabilitiesPdf = "Servicii_si_Capabilitati_B2B.pdf";

$maxTotalSize = 10 * 1024 * 1024; // 10 MB
$allowedExtensions = ['pdf', 'step', 'stp', 'dxf', 'dwg'];

/* ==========================
   SECURITY
========================== */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    exit("Invalid request.");
}

/* ==========================
   FORM DATA
========================== */
$company = htmlspecialchars($_POST["company"]);
$person  = htmlspecialchars($_POST["contact_person"]);
$email   = filter_var($_POST["email"], FILTER_SANITIZE_EMAIL);
$phone   = htmlspecialchars($_POST["phone"]);
$messageText = nl2br(htmlspecialchars($_POST["message"]));

/* ==========================
   FILE VALIDATION
========================== */
$totalSize = 0;
$filesData = [];

if (!empty($_FILES["attachments"]["name"][0])) {

    foreach ($_FILES["attachments"]["name"] as $i => $name) {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $size = $_FILES["attachments"]["size"][$i];

        if (!in_array($ext, $allowedExtensions)) {
            exit("Invalid file type: $name");
        }

        $totalSize += $size;
        if ($totalSize > $maxTotalSize) {
            exit("Uploaded files exceed 10 MB limit.");
        }

        $tmp = $_FILES["attachments"]["tmp_name"][$i];
        $newName = $uploadDir . time() . "_" . basename($name);
        move_uploaded_file($tmp, $newName);
        $filesData[] = $newName;
    }
}

/* ==========================
   EMAIL – ADMIN
========================== */
$boundary = md5(time());
$headersAdmin  = "From: $email\r\n";
$headersAdmin .= "Reply-To: $email\r\n";
$headersAdmin .= "MIME-Version: 1.0\r\n";
$headersAdmin .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

$bodyAdmin  = "--$boundary\r\n";
$bodyAdmin .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
$bodyAdmin .= "
<h2>B2B Request for Quotation</h2>
<b>Company:</b> $company<br>
<b>Contact:</b> $person<br>
<b>Email:</b> $email<br>
<b>Phone:</b> $phone<br><br>
<b>Message:</b><br>$messageText<br><br>
";

/* Attach client files */
foreach ($filesData as $file) {
    $fileContent = chunk_split(base64_encode(file_get_contents($file)));
    $fileName = basename($file);

    $bodyAdmin .= "--$boundary\r\n";
    $bodyAdmin .= "Content-Type: application/octet-stream; name=\"$fileName\"\r\n";
    $bodyAdmin .= "Content-Disposition: attachment; filename=\"$fileName\"\r\n";
    $bodyAdmin .= "Content-Transfer-Encoding: base64\r\n\r\n$fileContent\r\n";
}

/* Attach capabilities PDF */
$pdfEncoded = chunk_split(base64_encode(file_get_contents($capabilitiesPdf)));
$bodyAdmin .= "--$boundary\r\n";
$bodyAdmin .= "Content-Type: application/pdf; name=\"Capabilities.pdf\"\r\n";
$bodyAdmin .= "Content-Disposition: attachment; filename=\"Capabilities.pdf\"\r\n";
$bodyAdmin .= "Content-Transfer-Encoding: base64\r\n\r\n$pdfEncoded\r\n";

$bodyAdmin .= "--$boundary--";

/* Send to admin */
mail($adminEmail, $subjectAdmin, $bodyAdmin, $headersAdmin);

/* ==========================
   CONFIRMATION EMAIL – CLIENT
========================== */
$headersClient  = "From: $adminEmail\r\n";
$headersClient .= "MIME-Version: 1.0\r\n";
$headersClient .= "Content-Type: text/html; charset=UTF-8\r\n";

$bodyClient = "
<p>Dear $person,</p>
<p>Thank you for your request.</p>
<p>We have received your technical inquiry and will review it shortly.</p>
<p><b>Company:</b> $company</p>
<p>Our team will get back to you as soon as possible.</p>
<p>Best regards,<br>
Mechanical Processing – B2B Romania</p>
";

mail($email, $subjectClient, $bodyClient, $headersClient);

/* ==========================
   REDIRECT TO SUCCESS PAGE
========================== */
/* ==========================
   LOG REQUEST TO FILE
========================== */

$logFile = __DIR__ . "/logs/cereri-oferta.log";

$logEntry = "===============================\n";
$logEntry .= "Date: " . date("Y-m-d H:i:s") . "\n";
$logEntry .= "IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
$logEntry .= "Company: $company\n";
$logEntry .= "Contact: $person\n";
$logEntry .= "Email: $email\n";
$logEntry .= "Phone: $phone\n";
$logEntry .= "Message: " . strip_tags($_POST["message"]) . "\n";

if (!empty($filesData)) {
    $logEntry .= "Files:\n";
    foreach ($filesData as $file) {
        $logEntry .= "- " . basename($file) . "\n";
    }
}

$logEntry .= "===============================\n\n";

file_put_contents($logFile, $logEntry, FILE_APPEND);
header("Location: thank-you.html");
exit;