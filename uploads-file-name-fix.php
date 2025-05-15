<?php

// Set to true to actually perform the renaming (false for dry run)
$perform_changes = false;

// Exit if not run from command line
if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.";
    exit;
}

// Load WordPress
require_once('wp-load.php');

// Function to process a directory recursively
function process_directory($dir) {
    global $perform_changes;
    global $wpdb;
    
    $files = scandir($dir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $path = $dir . '/' . $file;
        
        if (is_dir($path)) {
            // Recursively process subdirectories
            process_directory($path);
        } else {
            // Check if filename contains _kraken_ pattern
            if (preg_match('/(.*?)_kraken_[0-9a-f]{32}$/', $file, $matches)) {
                $original_name = $matches[1];
                $new_path = $dir . '/' . $original_name;
                
                echo "Found: $path\n";
                echo "Will rename to: $new_path\n";
                
                if ($perform_changes) {
                    // Rename the file
                    if (rename($path, $new_path)) {
                        echo "Renamed successfully\n";
                        
                        // Update references in database
                        $old_url = str_replace(ABSPATH, site_url('/'), $path);
                        $new_url = str_replace(ABSPATH, site_url('/'), $new_path);
                        
                        // Update posts content
                        $wpdb->query($wpdb->prepare(
                            "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)",
                            $old_url,
                            $new_url
                        ));
                        
                        // Update attachment metadata
                        $wpdb->query($wpdb->prepare(
                            "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_key = '_wp_attached_file'",
                            $file,
                            $original_name
                        ));
                        
                        echo "Database references updated\n";
                    } else {
                        echo "Failed to rename\n";
                    }
                }
                
                echo "-------------------\n";
            }
        }
    }
}

// Main execution
echo "Starting image filename cleanup...\n";

if (!$perform_changes) {
    echo "DRY RUN MODE: No actual changes will be made\n";
    echo "Set \$perform_changes to true to make actual changes\n";
}

echo "Processing uploads directory...\n";
process_directory(WP_CONTENT_DIR . '/uploads');

echo "Process completed.\n";