<?php

header('Content-Type: text/plain');

echo "PHP VERSION: " . PHP_VERSION . PHP_EOL;
echo "PHP SAPI: " . PHP_SAPI . PHP_EOL;

echo "PDO: " . (extension_loaded('pdo') ? 'YES' : 'NO') . PHP_EOL;
echo "PDO_MYSQL: " . (extension_loaded('pdo_mysql') ? 'YES' : 'NO') . PHP_EOL;
echo "MYSQLI: " . (extension_loaded('mysqli') ? 'YES' : 'NO') . PHP_EOL;

echo "PDO DRIVERS: ";
print_r(PDO::getAvailableDrivers());