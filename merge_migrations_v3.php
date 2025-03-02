<?php

// Script to merge migrations by table name
$migrationsPath = __DIR__ . '/database/migrations';
$mergedPath = __DIR__ . '/database/migrations/merged';
$files = scandir($migrationsPath);

// Skip . and .. and directories
$files = array_filter($files, function($file) use ($migrationsPath) {
    $fullPath = $migrationsPath . '/' . $file;
    return $file !== '.' && $file !== '..' && !is_dir($fullPath) && pathinfo($file, PATHINFO_EXTENSION) === 'php';
});

// Create merged directory if it doesn't exist
if (!is_dir($mergedPath)) {
    mkdir($mergedPath, 0755, true);
} else {
    // Clean up existing merged migrations
    $mergedFiles = scandir($mergedPath);
    foreach ($mergedFiles as $file) {
        if ($file !== '.' && $file !== '..' && !is_dir($mergedPath . '/' . $file)) {
            unlink($mergedPath . '/' . $file);
        }
    }
}

// Function to extract column names from a migration block
function extractColumnNames($block) {
    $columns = [];
    preg_match_all('/\$table->(\w+)\([\'"](\w+)[\'"]/', $block, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $columns[] = $match[2];
    }
    
    // Also look for dropColumn calls
    preg_match_all('/\$table->dropColumn\([\'"](\w+)[\'"]/', $block, $dropMatches, PREG_SET_ORDER);
    foreach ($dropMatches as $match) {
        $columns[] = $match[1];
    }
    
    return $columns;
}

// Function to check if a line contains a column definition
function containsColumnDefinition($line, $columnName) {
    $patterns = [
        '/\$table->\w+\([\'"]' . preg_quote($columnName, '/') . '[\'"]/',
        '/\$table->dropColumn\([\'"]' . preg_quote($columnName, '/') . '[\'"]/',
        '/\$table->renameColumn\([\'"]' . preg_quote($columnName, '/') . '[\'"]/',
        '/\$table->renameColumn\([^,]+,\s*[\'"]' . preg_quote($columnName, '/') . '[\'"]/',
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $line)) {
            return true;
        }
    }
    
    return false;
}

// Group migrations by table
$tableToMigrations = [];

foreach ($files as $file) {
    $filePath = $migrationsPath . '/' . $file;
    $content = file_get_contents($filePath);
    
    // Extract table names from Schema::create or Schema::table
    preg_match_all('/Schema::(create|table)\s*\(\s*[\'"]([^\'"]*)[\'"]/', $content, $matches);
    
    if (!empty($matches[2])) {
        foreach ($matches[2] as $index => $table) {
            if (!isset($tableToMigrations[$table])) {
                $tableToMigrations[$table] = [];
            }
            $tableToMigrations[$table][] = [
                'file' => $file,
                'path' => $filePath,
                'content' => $content,
                'operation' => $matches[1][$index]
            ];
        }
    }
}

// Sort migrations by timestamp
foreach ($tableToMigrations as $table => &$migrations) {
    usort($migrations, function($a, $b) {
        return strcmp($a['file'], $b['file']);
    });
}

// Create merged migrations
$timestamp = date('Y_m_d_His');
$mergedMigrations = [];

foreach ($tableToMigrations as $table => $migrations) {
    $className = 'Merged' . ucfirst(str_replace('_', '', ucwords($table, '_'))) . 'Table';
    $fileName = "{$timestamp}_merged_{$table}_table.php";
    $filePath = $mergedPath . '/' . $fileName;
    
    // Start building the merged migration
    $mergedContent = "<?php\n\n";
    $mergedContent .= "use Illuminate\\Database\\Migrations\\Migration;\n";
    $mergedContent .= "use Illuminate\\Database\\Schema\\Blueprint;\n";
    $mergedContent .= "use Illuminate\\Support\\Facades\\Schema;\n\n";
    $mergedContent .= "return new class extends Migration\n{\n";
    $mergedContent .= "    /**\n     * Run the migrations.\n     */\n";
    $mergedContent .= "    public function up(): void\n    {\n";
    
    // Track columns to avoid duplicates
    $existingColumns = [];
    
    // First, handle all create operations
    $createMigrations = array_filter($migrations, function($migration) {
        return $migration['operation'] === 'create';
    });
    
    if (!empty($createMigrations)) {
        $firstCreateMigration = $createMigrations[0];
        
        // Extract the Schema::create block
        preg_match('/Schema::create\s*\(\s*[\'"]' . preg_quote($table, '/') . '[\'"],\s*function\s*\(Blueprint\s*\$table\)\s*{(.*?)}\);/s', $firstCreateMigration['content'], $createMatches);
        
        if (!empty($createMatches[1])) {
            // Extract column names from the create block
            $createBlock = $createMatches[1];
            $existingColumns = extractColumnNames($createBlock);
            
            $mergedContent .= "        // Drop the table if it exists\n";
            $mergedContent .= "        Schema::dropIfExists('{$table}');\n\n";
            $mergedContent .= "        // Create the table\n";
            $mergedContent .= "        Schema::create('{$table}', function (Blueprint \$table) {\n";
            $mergedContent .= trim($createBlock) . "\n";
            $mergedContent .= "        });\n\n";
        }
    }
    
    // Then handle all table operations (for adding columns)
    $tableMigrations = array_filter($migrations, function($migration) {
        return $migration['operation'] === 'table';
    });
    
    foreach ($tableMigrations as $migration) {
        // Extract the Schema::table block
        preg_match('/Schema::table\s*\(\s*[\'"]' . preg_quote($table, '/') . '[\'"],\s*function\s*\(Blueprint\s*\$table\)\s*{(.*?)}\);/s', $migration['content'], $tableMatches);
        
        if (!empty($tableMatches[1])) {
            $tableBlock = $tableMatches[1];
            $newColumns = extractColumnNames($tableBlock);
            
            // Filter out duplicate column definitions
            $uniqueColumnBlock = '';
            $lines = explode("\n", $tableBlock);
            
            foreach ($lines as $line) {
                $shouldAdd = true;
                foreach ($existingColumns as $column) {
                    if (containsColumnDefinition($line, $column)) {
                        $shouldAdd = false;
                        break;
                    }
                }
                
                if ($shouldAdd) {
                    $uniqueColumnBlock .= $line . "\n";
                }
            }
            
            if (trim($uniqueColumnBlock) !== '') {
                $mergedContent .= "        // Add additional columns from {$migration['file']}\n";
                $mergedContent .= "        Schema::table('{$table}', function (Blueprint \$table) {\n";
                $mergedContent .= trim($uniqueColumnBlock) . "\n";
                $mergedContent .= "        });\n\n";
                
                // Add new columns to the tracking list
                $existingColumns = array_merge($existingColumns, $newColumns);
            }
        }
    }
    
    $mergedContent .= "    }\n\n";
    $mergedContent .= "    /**\n     * Reverse the migrations.\n     */\n";
    $mergedContent .= "    public function down(): void\n    {\n";
    $mergedContent .= "        Schema::dropIfExists('{$table}');\n";
    $mergedContent .= "    }\n";
    $mergedContent .= "};\n";
    
    // Write the merged migration to file
    file_put_contents($filePath, $mergedContent);
    echo "Created merged migration for {$table}: {$fileName}\n";
    
    $mergedMigrations[] = $fileName;
}

// Create a master migration that runs all merged migrations in the correct order
$masterFileName = "{$timestamp}_master_migration.php";
$masterFilePath = $mergedPath . '/' . $masterFileName;

$masterContent = "<?php\n\n";
$masterContent .= "use Illuminate\\Database\\Migrations\\Migration;\n";
$masterContent .= "use Illuminate\\Support\\Facades\\Artisan;\n\n";
$masterContent .= "return new class extends Migration\n{\n";
$masterContent .= "    /**\n     * Run the migrations.\n     */\n";
$masterContent .= "    public function up(): void\n    {\n";
$masterContent .= "        // Run all merged migrations\n";

foreach ($mergedMigrations as $migration) {
    $masterContent .= "        Artisan::call('migrate', ['--path' => 'database/migrations/merged/{$migration}', '--force' => true]);\n";
}

$masterContent .= "    }\n\n";
$masterContent .= "    /**\n     * Reverse the migrations.\n     */\n";
$masterContent .= "    public function down(): void\n    {\n";
$masterContent .= "        // This is a master migration, no specific down operation\n";
$masterContent .= "    }\n";
$masterContent .= "};\n";

file_put_contents($masterFilePath, $masterContent);
echo "Created master migration: {$masterFileName}\n";

echo "\nMigration merging completed. Run the master migration to apply all changes.\n";
