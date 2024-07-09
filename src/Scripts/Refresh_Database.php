<?php

    namespace DBHelix\Scripts;
    require dirname(__FILE__, 3) . '/vendor/autoload.php';

    use DBHelix\Config;
    use DBHelix\Container;
    use DBHelix\Repositories\DatabaseRepository;
    use DBHelix\Utils\Logger;

    $SourceEnvironment = 'PROD';
    $TargetEnvironment = 'DEV';
    $EnvironmentConfigFile = dirname(__FILE__, 3) . '/TESTS/TESTS.env';


    //$SourceDatabase = new DatabaseRepository(Config::fromEnv('PROD', dirname(__FILE__, 3) . '/.env'), new Logger("Refresh_SourceDB.log"));
    //$TargetDatabase = new DatabaseRepository(Config::fromEnv('DEV', dirname(__FILE__, 3) . '/.env'), new Logger("Refresh_TargetDB.log"));

    $SourceDatabase = new DatabaseRepository(Config::fromEnv($SourceEnvironment, $EnvironmentConfigFile), new Logger("Refresh_SourceDB_{$SourceEnvironment}.log"));
    $TargetDatabase = new DatabaseRepository(Config::fromEnv($TargetEnvironment, $EnvironmentConfigFile), new Logger("Refresh_TargetDB_{$TargetEnvironment}.log"));

	echo "Connections established for both {$SourceEnvironment} and {$TargetEnvironment} environments.\n";

    $SourceContainer = new Container();
    $TargetContainer = new Container();

    $SourceContainer->set('Config', function() use ($SourceDatabase) {
        return $SourceDatabase->getConfig();
    });
    $TargetContainer->set('Config', function() use ($TargetDatabase) {
        return $TargetDatabase->getConfig();
    });

    $SourceContainer->set('Logger', function() use ($SourceDatabase) {
        return $SourceDatabase->getLogger();
    });

    $TargetContainer->set('Logger', function() use ($TargetDatabase) {
        return $TargetDatabase->getLogger();
    });

    $SourceContainer->set('Database', function() use ($SourceDatabase) {
        return $SourceDatabase;
    });

    $TargetContainer->set('Database', function() use ($TargetDatabase) {
        return $TargetDatabase;
    });

    $SourceBackupFile = __DIR__ . '/SOURCE_BACKUP.sql';
    $TargetBackupFile = __DIR__ . '/TARGET_BACKUP.sql';

	Echo "Setup complete. Starting {$SourceEnvironment} backup.\n";

    $SourceDatabase->backupDatabase($SourceBackupFile);
	Echo "{$SourceEnvironment} backup complete. File: {$SourceBackupFile}\n";
	Echo "Starting {$TargetEnvironment} backup.\n";
    $TargetDatabase->backupDatabase($TargetBackupFile);
	Echo "{$TargetEnvironment} backup complete. File: {$TargetBackupFile}\n";
	Echo "Restoring {$TargetEnvironment} from {$SourceEnvironment} backup file.\n";
    $TargetDatabase->restoreFromFile($SourceBackupFile);

    echo "{$TargetEnvironment} has been successfully refreshed from {$SourceEnvironment}.\n";
