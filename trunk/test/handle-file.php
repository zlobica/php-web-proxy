<html>
<head>
<title>handle uploaded file</title>
</head>

<body>

<h1>Files Uploaded</h1>

<p>Test: <?= $_POST['test'] ?></p>

<pre>
<? print_r($_FILES); ?>
</pre>

</body>
</html>