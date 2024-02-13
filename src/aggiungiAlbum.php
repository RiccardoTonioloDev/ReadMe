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

if (is_not_signed_in()) {
  $_SESSION['redirection'] = "/aggiungiAlbum.php";
  redirect('/accedi.php');
}

$risultato = '';
$artista = '';
$nome = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  goto GET;
}

// ========================================================================================================================
// POST
// ========================================================================================================================

try {
  [
    "artista" => $artista,
    "nome" => $nome,
  ] = $_POST;

  $conn = new Database();
  if ($conn->album_exists($nome, $artista)) {
    throw new Exception("L'album risulta già essere registrato");
  }

  $dir = "assets/albumPhotos";
  if ($e = file_exists($dir)) {
    if ($d = is_dir($dir) === false) {
      throw new Exception("'$dir' esiste ma non è una directory");
    }
  } else {
    if ($m = mkdir($dir, 0777, true) === false) {
      throw new Exception("Directory '$dir' mancante e non può essere creata : $dir");
    }
  };

  if ($_FILES["copertina"]["size"] > 524288) {
    throw new Exception("Copertina tropppo grande");
  }

  if (!move_uploaded_file($_FILES["copertina"]["tmp_name"], "$dir/$artista-$nome")) {
    throw new Exception("Errore nel salvataggio della copertina");
  }

  if (!$conn->album_add($nome, $artista, "$artista-$nome")) {
    throw new Exception("Errore di inserimento nel database");
  }
  $conn->close();

  $artista = '';
  $nome = '';
  $risultato = '
    <h1>Successo</h1>
    <ul class="successo">
      <li>Album ' . (strip_tags($nome)) . ' aggiunto con successo</li>
    </ul>
  ';
} catch (Exception $e) {
  $risultato = '
    <h1>Errore</h1>
    <ul class="error">
      <li>' . (strip_tags($e->getMessage())) . '</li>
    </ul>
  ';
}

// ========================================================================================================================
GET:
// ========================================================================================================================

$conn = new Database();
$artisti = implode(
  "\n",
  array_map(
    function ($coll) use ($artista) {
      ["id" => $id, "name" => $nome] = $coll;
      $nome = strip_tags($nome);
      $selection = ($id == $artista) ? 'selected' : '';
      return "<option $selection value=\"$id\">$nome</option>";
    },
    $conn->artisti()
  )
);
$conn->close();

$content = file_get_contents("./components/aggiungiAlbum.html");
$content = str_replace("{{artisti}}", $artisti, $content);
$content = str_replace("{{nome}}", $nome, $content);

$breadcrumbs = (new BreadcrumbsBuilder())
  ->addBreadcrumb(new BreadcrumbItem("Home"))
  ->addBreadcrumb(new BreadcrumbItem("Aggiungi Album", isCurrent: true))
  ->build()
  ->getBreadcrumbsHtml();

$page = file_get_contents("./components/layout.html");
$page = str_replace("{{title}}", "Aggiungi Album", $page);
$page = str_replace("{{description}}", "Pagina admin di Orchestra per aggiungere album", $page);
$page = str_replace("{{keywords}}", "", $page);
$page = str_replace("{{menu}}", navbar(), $page);
$page = str_replace("{{breadcrumbs}}", $breadcrumbs, $page);

$page = str_replace("{{content}}", $content, $page);
$page = str_replace("{{risultato}}", $risultato, $page);
echo $page;