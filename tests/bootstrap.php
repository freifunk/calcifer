<?php

use App\Entity\Location;
use App\Entity\Tag;
use App\Kernel;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// Setup test database
$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();
$entityManager = $container->get('doctrine.orm.entity_manager');
$connection = $entityManager->getConnection();
$dbPath = $kernel->getProjectDir() . '/var/test.db';

// Remove existing database file if it exists
if (file_exists($dbPath)) {
    @unlink($dbPath);
    echo "Removed existing test database file\n";
}

// Create parent directory if it doesn't exist
if (!is_dir(dirname($dbPath))) {
    mkdir(dirname($dbPath), 0777, true);
}

// Create fresh database schema
$metadatas = $entityManager->getMetadataFactory()->getAllMetadata();
if (!empty($metadatas)) {
    // Create schema
    $schemaTool = new SchemaTool($entityManager);
    $schemaTool->createSchema($metadatas);
    echo "Test database schema created successfully\n";
    
    // Validate schema creation
    $schemaManager = $connection->createSchemaManager();
    $tables = $schemaManager->listTableNames();
    echo "Created tables: " . implode(', ', $tables) . "\n";
    
    // Add some basic test data
    $entityManager->beginTransaction();
    try {
        // Add Tags
        $tag1 = new Tag();
        $tag1->setName('work');
        $tag1->setSlug('work');
        $entityManager->persist($tag1);
        
        $tag2 = new Tag();
        $tag2->setName('personal');
        $tag2->setSlug('personal');
        $entityManager->persist($tag2);
        
        // Add Locations
        $location1 = new Location();
        $location1->setName('Office');
        $location1->setStreetaddress('123 Work Street');
        $location1->setCity('Work City');
        $location1->setSlug('office');
        $entityManager->persist($location1);
        
        $location2 = new Location();
        $location2->setName('Home');
        $location2->setStreetaddress('456 Home Avenue');
        $location2->setCity('Home City');
        $location2->setSlug('home');
        $entityManager->persist($location2);
        
        $entityManager->flush();
        $entityManager->commit();
        echo "Test data added successfully\n";
    } catch (Exception $e) {
        $entityManager->rollback();
        echo "Failed to add test data: " . $e->getMessage() . "\n";
    }
} else {
    echo "No metadata found to create schema\n";
}

$kernel->shutdown();
