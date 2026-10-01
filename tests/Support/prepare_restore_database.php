<?php

declare(strict_types=1);

// Provision only the disposable GitHub Actions MariaDB service.
if (getenv('GITHUB_ACTIONS') !== 'true' || getenv('DB_HOST') !== '127.0.0.1'
    || getenv('DB_DATABASE') !== 'lessons_test' || getenv('LESSONS_RESTORE_TEST_USERNAME') !== 'lessons_restore'
    || getenv('LESSONS_RESTORE_TEST_PASSWORD') !== 'test-only-restore-password') {
    throw new RuntimeException('Restore fixture provisioning is restricted to the configured disposable CI service.');
}
$database = new PDO('mysql:host=127.0.0.1;port=3306;dbname=lessons_test', getenv('DB_USERNAME'), getenv('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database->exec('CREATE DATABASE lessons_restore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$database->exec("CREATE USER 'lessons_restore'@'%' IDENTIFIED BY 'test-only-restore-password'");
$database->exec("GRANT ALL PRIVILEGES ON `lessons\\_restore\\_test`.* TO 'lessons_restore'@'%'");
