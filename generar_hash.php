<?php
$password = '123456';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h3>Copia y pega esta consulta SQL en phpMyAdmin:</h3>";
echo "<textarea rows='4' cols='80'>";
echo "UPDATE usuarios SET password = '$hash';";
echo "</textarea>";
?>