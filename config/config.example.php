<?php
// Copia este archivo como config/config.php y pon tus datos.
// config/config.php NO se sube a GitHub (está en .gitignore).
// También puedes usar variables de entorno: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS.
return [
    'db_host' => 'localhost',   // en Docker: 'db'
    'db_port' => 3306,
    'db_name' => 'rivera',
    'db_user' => 'riverauser',
    'db_pass' => 'CAMBIA_ESTA_CONTRASEÑA',
    'debug'   => false,         // true solo en tu computadora: muestra el error de conexión
];
