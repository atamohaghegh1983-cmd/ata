<?php
/* پیکربندی پایگاه داده‌ی آزمایشی. این پایگاه در هر اجرا پاک می‌شود؛ هرگز پایگاه داده‌ی سایت اصلی را اینجا نگذارید. */
define( 'ABSPATH', rtrim( getenv( 'WP_CORE_DIR' ) ?: '/tmp/wordpress', '/' ) . '/' );
define( 'DB_NAME', getenv( 'WP_TESTS_DB_NAME' ) ?: 'wptests' );
define( 'DB_USER', getenv( 'WP_TESTS_DB_USER' ) ?: 'wp' );
define( 'DB_PASSWORD', getenv( 'WP_TESTS_DB_PASSWORD' ) ?: 'wp' );
define( 'DB_HOST', getenv( 'WP_TESTS_DB_HOST' ) ?: '127.0.0.1' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
$table_prefix = 'wptests_';
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
define( 'WP_DEBUG', true );
