<?php

use Pangine\Pangine;

set_include_path($_SERVER['DOCUMENT_ROOT']);
require_once 'Pangine/Pangine.php';
require_once 'include/HTMLBuilder.php';
require_once 'components/breadcrumbs.php';
require_once 'components/navbar.php';
require_once 'include/sessionEstablisher.php';
require_once 'include/database.php';
require_once 'include/utils.php';

set_error_handler(function ($severity, $message, $file, $line) {
  throw new \ErrorException($message, $severity, $severity, $file, $line);
});

if (!try_session()) {
  throw new ErrorException("try_session ha fallito");
}

if (is_user_signed_in() || is_admin_signed_in()) {
  redirect('/');
}

const logerr = 'logerr';

(new Pangine())
  ->POST_read($accedi = function () {
    $nome = '';

    try {
      [
        'nome' => $nome,
        'password' => $password,
      ] = $_POST;

      $conn = new Database();
      $res = $conn->user_with_mail_password($nome, $password);
      $conn->close();
      if (count($res) == 0) {
        throw new Exception("Nessun utente trovato. Le credenziali potrebbero essere errate.");
      }

      $_SESSION['user'] = $res[0];
      redirect(extract_from_array_else('redirection', $_SESSION, '/'));
    } catch (Exception $e) {
      $_SESSION[logerr] = ['nome' => $nome, 'val' => $e->getMessage(), 'typ' => HTMLBuilder::ERROR_P];
      redirect('accedi.php');
    }
  })
  ->POST_create(function () {
    try {

      [
        "name" => $nome,
        "password" => $password,
      ] = $_POST;

      dbcall(function ($conn) use($nome, $password) {
      if ($conn->user_exists($nome)) {
        throw new Exception("Il nome utente fornito risulta già registrato.");
      }

      if ($conn->user_sign_up($nome, $password) !== true) {
        // Questo caso non dovrebbe mai succedere
        throw new Exception("Errore del database.");
      }
      });

      $_POST = ['name' => $nome, 'password' => $password];
      require_once('accedi.php'); // TODO: cos'è sta roba?
      exit();
    } catch (Exception $e) {
      $errori = $e->getMessage();
    }
  })
  ->GET_create(function () {
    [
      'nome' => $nome,
      'risultato' => $risultato,
      'tiporisultato' => $tiporisultato,
    ] = extract_from_array_else(logerr, $_SESSION, [
      'nome' => '',
      'risultato' => '',
      'tiporisultato' => HTMLBuilder::UNSAFE,
    ]);

    echo (new HTMLBuilder('../components/layout.html'))
      ->set('title', 'Registrati')
      ->set('description', 'Pagina di registrazione di Orchestra')
      ->set('keywords', 'Orchestra, musica classica, registrazione, sign up')
      ->set('menu', navbar())
      ->set('breadcrumbs', (new BreadcrumbsBuilder())
        ->addBreadcrumb(new BreadcrumbItem("Home"))
        ->addBreadcrumb(new BreadcrumbItem("Registrati", isCurrent: true))
        ->build()
        ->getBreadcrumbsHtml())
      ->set('content', (new HTMLBuilder('../components/registrati.html'))
        ->set('nome', $nome)
        ->set('errori', $risultato, $tiporisultato)
        ->build())
      ->build();
  })
  ->GET_read(function () {
    [
      'nome' => $nome,
      'risultato' => $risultato,
      'tiporisultato' => $tiporisultato,
    ] = extract_from_array_else(logerr, $_SESSION, [
      'nome' => '',
      'risultato' => '',
      'tiporisultato' => HTMLBuilder::UNSAFE,
    ]);

    echo (new HTMLBuilder('../components/layout.html'))
      ->set('title', 'Accedi')
      ->set('description', 'Pagina di accesso di Orchestra')
      ->set('keywords', 'Orchestra, musica classica, accesso, log in, sign in')
      ->set('menu', navbar())
      ->set('breadcrumbs', (new BreadcrumbsBuilder())
        ->addBreadcrumb(new BreadcrumbItem("Home"))
        ->addBreadcrumb(new BreadcrumbItem("Accedi", isCurrent: true))
        ->build()
        ->getBreadcrumbsHtml())
      ->set('content', (new HTMLBuilder('../components/accedi.html'))
        ->set('nome', $nome)
        ->set('errori', $risultato, $tiporisultato)
        ->build())
      ->build();
  })
  ->execute();
