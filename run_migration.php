<?php
// Simple migration runner
require_once 'config/database.php';

echo "Running database migration...\n";

$migration_sql = file_get_contents('migrations/add_campaign_analytics.sql');

// Split the SQL into individual statements
$statements = array_filter(array_map('trim', explode(';', $migration_sql)));

$errors = [];
$success_count = 0;

foreach ($statements as $statement) {
    if (empty($statement)) continue;
    
    try {
        $result = $conexao->query($statement);
        if ($result) {
            $success_count++;
            echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
        } else {
            $errors[] = "Failed: " . $conexao->error . " - " . substr($statement, 0, 50);
        }
    } catch (Exception $e) {
        $errors[] = "Exception: " . $e->getMessage() . " - " . substr($statement, 0, 50);
    }
}

echo "\nMigration completed!\n";
echo "Successful statements: $success_count\n";

if (!empty($errors)) {
    echo "Errors encountered:\n";
    foreach ($errors as $error) {
        echo "✗ $error\n";
    }
} else {
    echo "All migrations executed successfully!\n";
}

$conexao->close();
?>