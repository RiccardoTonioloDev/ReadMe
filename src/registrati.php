<?php

require_once 'components/breadcrumbs/breadcrumbItem.php';
require_once 'components/breadcrumbs/breadcrumbsBuilder.php';
require_once 'components/navbar.php';
require_once 'components/sessionEstablisher.php';
require_once 'data/database.php';
require_once 'handlers/utils.php';

set_error_handler(function ($severity, $message, $file, $line) {
  throw new \ErrorException($message, $severity, $severity, $file, $line);
});

if (!try_session()) {
  throw new ErrorException("try_session ha fallito");
}

if (is_user_signed_in()) {
  redirect('/');
}

$errori = '';
$nome = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  goto GET;
}

// ========================================================================================================================
// POST
// ========================================================================================================================

try {

  [
    "name" => $nome,
    "password" => $password,
  ] = $_POST;

  $conn = new Database();
  if ($conn->user_exists($nome)) {
    throw new Exception("Il Nome utente fornito risulta già registrato.");
  }

  if ($conn->user_sign_up($nome, $password) !== true) {
    // Questo caso non dovrebbe mai succedere
    throw new Exception("Errore del database.");
  }
  $conn->close();

  $_POST = ['name' => $nome, 'password' => $password];
  require_once('accedi.php');
  exit();
} catch (Exception $e) {
  $errori = '
    <h1>Errore</h1>
    <ul class="error">
      <li>' . (strip_tags($e->getMessage())) . '</li>
    </ul>
  ';
}

// ========================================================================================================================
GET:
// ========================================================================================================================

$content = file_get_contents("./components/registrati.html");
$content = str_replace('{{nome}}',$nome,$content);

$breadcrumbs = (new BreadcrumbsBuilder())
  ->addBreadcrumb(new BreadcrumbItem("Home"))
  ->addBreadcrumb(new BreadcrumbItem("Registrati", isCurrent: true))
  ->build()
  ->getBreadcrumbsHtml();

$page = file_get_contents("./components/layout.html");
$page = str_replace("{{title}}", "Registrati", $page);
$page = str_replace("{{description}}", "Pagina di registrazione di Orchestra", $page);
$page = str_replace("{{keywords}}", "Orchestra, musica classica, registrazione, sign up", $page);
$page = str_replace("{{menu}}", navbar(), $page);
$page = str_replace("{{breadcrumbs}}", $breadcrumbs, $page);

$page = str_replace("{{content}}", $content, $page);
$page = str_replace("{{errori}}", $errori, $page);
echo $page;
