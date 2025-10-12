<?php
echo "PHP version: " . phpversion() . PHP_EOL;
echo "Loaded php.ini: " . php_ini_loaded_file() . PHP_EOL;

if (extension_loaded('pdo_mysql')) {
    echo "✅ L'extension pdo_mysql est bien chargée.\n";
} else {
    echo "❌ L'extension pdo_mysql n'est PAS chargée.\n";
}
