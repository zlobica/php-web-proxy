<?php

session_start();
$msg = $_SESSION['error'];
unset($_SESSION['error']);

?>
<html>
<head>
<title>Access Denied</title>
</head>

<body>

<h1>Access Denied</h1>

<p>You are not allowed to visit this website.</p>

<p>Reason: <?= $msg ?></p>

</body>
</html>