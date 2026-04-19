<?php
return array (
  'utf_mode' => 
  array (
    'value' => true,
    'readonly' => true,
  ),
  'cache' => 
  array (
    'value' => 
    array (
      'type' => 'files',
    ),
    'readonly' => false,
  ),
  'cache_flags' => 
  array (
    'value' => 
    array (
      'config_options' => 3600,
      'site_domain' => 3600,
    ),
    'readonly' => false,
  ),
  'cookies' => 
  array (
    'value' => 
    array (
      'secure' => false,
      'http_only' => true,
    ),
    'readonly' => false,
  ),
  'exception_handling' => 
  array (
    'value' => 
    array (
      'debug' => true,
      'handled_errors_types' => 22527,
      'exception_errors_types' => 22527,
      'ignore_silence' => false,
      'assertion_throws_exception' => true,
      'assertion_error_type' => 256,
      'log' => 
      array (
        'settings' => 
        array (
          'file' => '/bitrix-error.log',
          'log_size' => 1000000,
        ),
      ),
    ),
    'readonly' => false,
  ),
  'connections' => 
  array (
    'value' => 
    array (
      'default' => 
      array (
        'className' => '\\MB\\Bitrix\\Database\\SqlLiteConnection',
        'host' => '',
        'database' => dirname(__DIR__) . '/sqlite/bitrix.sqlite',
        'login' => '',
        'password' => '',
        'options' => 2,
      ),
    ),
    'readonly' => true,
  ),
  'composer' => 
  array (
    'value' => 
    array (
      'config_path' => 'F:\\phpstorm\\mb\\composer\\mb-bitrix-support/composer.json',
    ),
    'readonly' => true,
  ),
);
