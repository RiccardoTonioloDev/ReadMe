<?php
$user = "NON LOGGATO";
if (session_start()) {
  if (array_key_exists("mail", $_SESSION)) {
    $user = $_SESSION["mail"];
  }
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <p><?php echo $user; ?></p>
    <audio controls>
        <source src="assets/Audio/0112_release_state.mp3" type="audio/mpeg">
        Your browser does not support the audio tag.
    </audio></body>

</html>
