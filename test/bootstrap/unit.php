<?php
$_SERVER['SYMFONY'] = '/usr/share/php/symfony';

if (!isset($_SERVER['SYMFONY']))
{
  throw new RuntimeException('Could not find symfony core libraries.');
}

require_once $_SERVER['SYMFONY'].'/autoload/sfCoreAutoload.class.php';
sfCoreAutoload::register();

$configuration = new sfProjectConfiguration(getcwd());
require_once $configuration->getSymfonyLibDir().'/vendor/lime/lime.php';

require_once dirname(__FILE__).'/../../config/ccDoctrineActAsRatablePluginConfiguration.class.php';
$plugin_configuration = new ccDoctrineActAsRatablePluginConfiguration($configuration, dirname(__FILE__).'/../..');
