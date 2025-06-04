<?php
/**
 * get_users.php - Version corrigée avec la bonne base de données
 * Utilise ifenlmsdb au lieu de lmsifendb
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Charger les credentials de programmation (qui contient ifenlmsdb)
    $credentials_file = '/export/hosting/men/ifen/htdocs-lms/ifen_credentials/db_credentials_programmation.php';
    
    if (!file_exists($credentials_file)) {
        throw new Exception("Fichier de credentials non trouvé: $credentials_file");
    }
    
    $credentials = require($credentials_file);
    error_log("Credentials chargés depuis: " . $credentials_file);
    
    // Extraire les paramètres
    $host = $credentials['host'];
    $database = $credentials['db']; // ifenlmsdb
    $user = $credentials['user'];
    $pass = $credentials['pass'];
    
    error_log("Tentative de connexion: {$user}@{$host}/{$database}");
    
    // CONNEXION À ifenlmsdb (la bonne base)
    $pdo = new PDO(
        "mysql:host={$host};dbname={$database};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10
        ]
    );
    
    error_log("Connexion réussie à $database");
    
    // Vérifier la base de données actuelle
    $current_db_sql = "SELECT DATABASE() as current_db";
    $current_db_stmt = $pdo->query($current_db_sql);
    $current_db = $current_db_stmt->fetch();
    error_log("Base de données actuelle: " . $current_db['current_db']);

    // Lister toutes les tables disponibles pour debug
    $tables_sql = "SHOW TABLES";
    $tables_stmt = $pdo->query($tables_sql);
    $tables = $tables_stmt->fetchAll(PDO::FETCH_COLUMN);
    error_log("Tables disponibles dans {$database}: " . implode(', ', $tables));

    // Vérifier que foco_users existe
    if (!in_array('foco_users', $tables)) {
        throw new Exception("Table 'foco_users' non trouvée dans {$database}. Tables disponibles: " . implode(', ', $tables));
    }

    // 1. Récupérer tous les utilisateurs de foco_users
    $users_sql = "SELECT ID, name FROM foco_users ORDER BY name ASC";
    $users_stmt = $pdo->prepare($users_sql);
    $users_stmt->execute();
    $users = $users_stmt->fetchAll();
    
    error_log("Nombre d'utilisateurs trouvés dans {$database}.foco_users: " . count($users));

    // 2. Vérifier si fichier_control_upload existe
    $last_upload = null;
    
    if (in_array('fichier_control_upload', $tables)) {
        // Récupérer les informations du dernier upload
        $last_upload_sql = "
            SELECT 
                fcu.name_id,
                fcu.upload_date,
                fcu.upload_time,
                fu.name
            FROM fichier_control_upload fcu
            JOIN foco_users fu ON fcu.name_id = fu.ID
            ORDER BY fcu.upload_date DESC, fcu.upload_time DESC
            LIMIT 1
        ";
        
        $last_upload_stmt = $pdo->prepare($last_upload_sql);
        $last_upload_stmt->execute();
        $last_upload = $last_upload_stmt->fetch();
        
        if ($last_upload) {
            error_log("Dernier upload trouvé: " . $last_upload['name']);
        } else {
            error_log("Aucun upload précédent trouvé dans fichier_control_upload");
        }
    } else {
        error_log("Table 'fichier_control_upload' non trouvée dans {$database}");
    }

    // 3. Réponse JSON
    $response = [
        'success' => true,
        'users' => $users,
        'last_upload' => $last_upload,
        'debug' => [
            'database' => $database,
            'current_db' => $current_db['current_db'],
            'tables_count' => count($tables),
            'users_count' => count($users),
            'has_last_upload' => $last_upload ? true : false,
            'upload_table_exists' => in_array('fichier_control_upload', $tables),
            'tables' => $tables
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("ERREUR dans get_users.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => __FILE__,
        'line' => __LINE__,
        'attempted_database' => $credentials['db'] ?? 'unknown'
    ]);
}
?>