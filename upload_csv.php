<?php
/**
 * upload_csv.php - Version finale complète CORRIGÉE
 * Script PHP FINAL pour l'upload CSV - Utilise ifenlmsdb
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Activer l'affichage des erreurs pour le debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

// INITIALISATION IMMÉDIATE DES VARIABLES GLOBALES
$pdo = null;
$tempFile = null;
$transactionStarted = false;
$selectedUserId = null;

// Fonction de réponse JSON
function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'stats' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Log des événements
function logEvent($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    error_log("[$timestamp] [$type] [$clientIP] $message");
}

// Charger les credentials AU DÉBUT (CORRIGÉ - une seule base de données)
try {
    $credentials = require '/export/hosting/men/ifen/htdocs-lms/ifen_credentials/db_credentials_programmation.php';
} catch (Exception $e) {
    sendResponse(false, 'Erreur de configuration: Impossible de charger les credentials.');
}

// Vérifications préliminaires
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Méthode non autorisée. Utilisez POST.');
}

if (!isset($_POST['userSelect']) || empty($_POST['userSelect'])) {
    sendResponse(false, 'Veuillez sélectionner un utilisateur.');
}

$selectedUserId = (int)$_POST['userSelect'];

if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
    $error_messages = [
        UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille maximale autorisée.',
        UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale du formulaire.',
        UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement uploadé.',
        UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été uploadé.',
        UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant.',
        UPLOAD_ERR_CANT_WRITE => 'Erreur d\'écriture sur le disque.',
        UPLOAD_ERR_EXTENSION => 'Upload arrêté par une extension PHP.'
    ];
    
    $error_code = $_FILES['csvFile']['error'] ?? UPLOAD_ERR_NO_FILE;
    $error_message = $error_messages[$error_code] ?? 'Erreur d\'upload inconnue.';
    
    logEvent('Erreur upload fichier: ' . $error_message, 'ERROR');
    sendResponse(false, $error_message);
}

$uploadedFile = $_FILES['csvFile'];

// Vérifications de sécurité
$allowedExtensions = ['csv'];
$fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));

if (!in_array($fileExtension, $allowedExtensions)) {
    sendResponse(false, 'Type de fichier non autorisé. Seuls les fichiers CSV sont acceptés.');
}

$maxFileSize = 50 * 1024 * 1024; // 50MB
if ($uploadedFile['size'] > $maxFileSize) {
    sendResponse(false, 'Le fichier est trop volumineux. Taille maximale autorisée: 50MB.');
}

try {
    // ÉTAPE 1: Connexion à ifenlmsdb (CORRIGÉ)
    $pdo = new PDO(
        "mysql:host={$credentials['host']};dbname={$credentials['db']};charset=utf8mb4",
        $credentials['user'],
        $credentials['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    logEvent("Connexion établie à {$credentials['db']}", 'INFO');
    
    // Vérifier que l'utilisateur existe
    $user_check_sql = "SELECT name FROM foco_users WHERE ID = :user_id";
    $user_check_stmt = $pdo->prepare($user_check_sql);
    $user_check_stmt->execute(['user_id' => $selectedUserId]);
    $selected_user = $user_check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$selected_user) {
        sendResponse(false, 'Utilisateur sélectionné invalide.');
    }
    
    logEvent("Upload initié par utilisateur ID: $selectedUserId ({$selected_user['name']})", 'INFO');
    
    // Lire et préparer le fichier
    $csvContent = file_get_contents($uploadedFile['tmp_name']);
    if ($csvContent === false) {
        throw new Exception('Impossible de lire le fichier uploadé.');
    }
    
    // Détecter et convertir l'encodage
    $encoding = mb_detect_encoding($csvContent, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $csvContent = mb_convert_encoding($csvContent, 'UTF-8', $encoding);
        logEvent("Conversion d'encodage: $encoding -> UTF-8", 'INFO');
    }
    
    // Créer fichier temporaire
    $tempFile = tempnam(sys_get_temp_dir(), 'csv_upload_');
    if (!$tempFile) {
        throw new Exception('Impossible de créer le fichier temporaire.');
    }
    file_put_contents($tempFile, $csvContent);
    
    // ÉTAPE 2: Vérifier/créer la table import_csv_foco et sauvegarder les données existantes
    $backupCreated = false;
    $targetTable = 'import_csv_foco';
    
    try {
        // Vérifier si la table import_csv_foco existe
        $checkTableSql = "SHOW TABLES LIKE '$targetTable'";
        $tableExists = $pdo->query($checkTableSql)->rowCount() > 0;
        
        if (!$tableExists) {
            // Créer la table import_csv_foco basée sur elly_csv
            $pdo->exec("CREATE TABLE `$targetTable` LIKE elly_csv");
            logEvent("Table $targetTable créée", 'INFO');
        }
        
        // Vérifier s'il y a des données existantes à sauvegarder
        $stmt = $pdo->query("SELECT COUNT(*) FROM `$targetTable`");
        $currentCount = $stmt->fetchColumn();
        
        if ($currentCount > 0) {
            // Sauvegarder les données existantes dans elly_csv
            $pdo->exec("DELETE FROM elly_csv");
            $pdo->exec("INSERT INTO elly_csv SELECT * FROM `$targetTable`");
            $backupCreated = true;
            logEvent("Données existantes sauvegardées dans elly_csv ($currentCount lignes)", 'INFO');
        } else {
            logEvent("Aucune donnée existante à sauvegarder", 'INFO');
        }
    } catch (Exception $e) {
        logEvent("Erreur lors de la préparation: " . $e->getMessage(), 'WARNING');
        // En cas d'erreur, utiliser elly_csv comme fallback
        $targetTable = 'elly_csv';
        logEvent("Fallback vers elly_csv", 'INFO');
    }
    
    // ÉTAPE 3: Commencer la transaction pour l'import
    $pdo->beginTransaction();
    $transactionStarted = true;
    logEvent('Transaction d\'import démarrée', 'INFO');
    
    // Vider la table cible
    $pdo->exec("DELETE FROM `$targetTable`");
    logEvent("Table $targetTable vidée", 'INFO');
    
    // Traiter le CSV
    $handle = fopen($tempFile, 'r');
    if (!$handle) {
        throw new Exception('Impossible d\'ouvrir le fichier CSV.');
    }
    
    // Lire les en-têtes
    $headers = fgetcsv($handle, 0, ';');
    if (!$headers) {
        fclose($handle);
        throw new Exception('Fichier CSV vide ou mal formaté.');
    }
    
    logEvent('En-têtes: ' . implode(', ', array_slice($headers, 0, 5)) . '...', 'INFO');
    
    // Préparer l'insertion dans la table cible
    $sql = "INSERT INTO `$targetTable` (
        Code, Intitule, Titre, Formateurs, Groupe, Statut, Format,
        Max, Inscriptions, Confirmees, DEBUT, FIN, DUREE,
        LieuSeance, SalleSeance, Gestionnaire, GestionnaireAdmin,
        EQUIPEMENT_DESCRIPTION, INFORMATIONS, REMARQUE_INTERNE, Commentaire
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    
    // Compteurs
    $totalRows = 0;
    $successRows = 0;
    $errorRows = 0;
    $errors = [];
    
    // Traiter chaque ligne
    while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
        $totalRows++;
        
        try {
            // Nettoyer les données
            $cleanData = array_map(function($item) {
                return $item === '' ? null : trim($item);
            }, $data);
            
            // Assurer 21 colonnes
            while (count($cleanData) < 21) {
                $cleanData[] = null;
            }
            
            // Valider le code obligatoire
            if (empty($cleanData[0])) {
                throw new Exception("Code manquant");
            }
            
            // Convertir les dates avec gestion d'erreur améliorée
            $debutDate = convertDateSafe($cleanData[10] ?? null);
            $finDate = convertDateSafe($cleanData[11] ?? null);
            
            // Valider et convertir les nombres
            $maxValue = validateNumber($cleanData[7] ?? null);
            $inscriptionsValue = validateNumber($cleanData[8] ?? null);
            $confimeesValue = validateNumber($cleanData[9] ?? null);
            
            // Préparer les valeurs
            $values = [
                $cleanData[0],  // Code
                $cleanData[1],  // Intitule
                $cleanData[2],  // Titre
                $cleanData[3],  // Formateurs
                $cleanData[4],  // Groupe
                $cleanData[5],  // Statut
                $cleanData[6],  // Format
                $maxValue,      // Max
                $inscriptionsValue, // Inscriptions
                $confimeesValue,    // Confirmées
                $debutDate,     // DEBUT
                $finDate,       // FIN
                $cleanData[12], // DUREE
                $cleanData[13], // LieuSeance
                $cleanData[14], // SalleSeance
                $cleanData[15], // Gestionnaire
                $cleanData[16], // GestionnaireAdmin
                $cleanData[17], // EQUIPEMENT_DESCRIPTION
                $cleanData[18], // INFORMATIONS
                $cleanData[19], // REMARQUE_INTERNE
                $cleanData[20]  // Commentaire
            ];
            
            $stmt->execute($values);
            $successRows++;
            
        } catch (Exception $e) {
            $errorRows++;
            $errorMessage = "Ligne $totalRows (Code: " . ($cleanData[0] ?? 'N/A') . "): " . $e->getMessage();
            $errors[] = $errorMessage;
            
            if (count($errors) <= 20) {
                logEvent($errorMessage, 'WARNING');
            }
        }
        
        // Progrès
        if ($totalRows % 1000 == 0) {
            logEvent("Progrès: $totalRows lignes ($successRows OK, $errorRows erreurs)", 'INFO');
        }
    }
    
    fclose($handle);
    
    // VÉRIFIER que la transaction est toujours active
    if (!$pdo->inTransaction()) {
        throw new Exception("Transaction fermée de manière inattendue pendant l'import");
    }
    
    // Valider la transaction
    $pdo->commit();
    $transactionStarted = false;
    logEvent('Transaction validée avec succès', 'INFO');
    
    // ÉTAPE 4: Enregistrer l'upload dans fichier_control_upload
    try {
        $upload_log_sql = "
            INSERT INTO fichier_control_upload (name_id, upload_date, upload_time) 
            VALUES (:name_id, CURDATE(), CURTIME())
        ";
        $upload_log_stmt = $pdo->prepare($upload_log_sql);
        $upload_log_stmt->execute(['name_id' => $selectedUserId]);
        
        logEvent("Upload enregistré dans fichier_control_upload pour utilisateur ID: $selectedUserId", 'INFO');
    } catch (Exception $e) {
        logEvent("Erreur lors de l'enregistrement de l'upload: " . $e->getMessage(), 'WARNING');
        // Ne pas faire échouer l'import pour cette erreur
    }
    
    // Vérification finale
    $checkStmt = $pdo->query("SELECT COUNT(*) as total FROM `$targetTable`");
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
    $totalInDB = $result['total'];
    
    // Statistiques
    $stats = [
        'totalRows' => $totalRows,
        'successRows' => $successRows,
        'errorRows' => $errorRows,
        'totalInDB' => $totalInDB,
        'targetTable' => $targetTable,
        'backupCreated' => $backupCreated,
        'backupLocation' => $backupCreated ? 'elly_csv' : null
    ];
    
    if ($errorRows > 0) {
        $message = "Import terminé avec $errorRows erreur(s) sur $totalRows lignes. $successRows lignes importées dans $targetTable.";
        $stats['sampleErrors'] = array_slice($errors, 0, 10);
    } else {
        $message = "Import réussi ! $successRows lignes importées dans $targetTable.";
    }
    
    logEvent("Import terminé - En DB ($targetTable): $totalInDB", 'INFO');
    sendResponse(true, $message, $stats);
    
} catch (Exception $e) {
    // Rollback seulement si transaction active
    if ($pdo && $transactionStarted && $pdo->inTransaction()) {
        try {
            $pdo->rollback();
            logEvent('Rollback effectué', 'ERROR');
        } catch (Exception $rollbackError) {
            logEvent('Erreur rollback: ' . $rollbackError->getMessage(), 'ERROR');
        }
    }
    
    $errorMessage = "Erreur: " . $e->getMessage();
    logEvent($errorMessage, 'ERROR');
    sendResponse(false, $errorMessage);
    
} finally {
    // NETTOYAGE GARANTI
    if ($tempFile && file_exists($tempFile)) {
        unlink($tempFile);
    }
    
    // Fermer la connexion proprement
    $pdo = null;
}

/**
 * Conversion de date sécurisée
 */
function convertDateSafe($dateString) {
    if (empty($dateString) || $dateString === null) {
        return null;
    }
    
    $dateString = trim($dateString);
    
    $formats = [
        'd/m/Y H:i:s',   // 01/09/2024 12:30:45
        'd/m/Y H:i',     // 01/09/2024 12:30
        'd/m/Y',         // 01/09/2024
        'Y-m-d H:i:s',   // 2024-09-01 12:30:45
        'Y-m-d H:i',     // 2024-09-01 12:30
        'Y-m-d'          // 2024-09-01
    ];
    
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $dateString);
        if ($date !== false) {
            $errors = DateTime::getLastErrors();
            if ($errors['error_count'] === 0 && $errors['warning_count'] === 0) {
                return $date->format('Y-m-d H:i:s');
            }
        }
    }
    
    // Essayer strtotime en dernier recours
    $timestamp = strtotime($dateString);
    if ($timestamp !== false) {
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    return null;
}

/**
 * Validation de nombre sécurisée
 */
function validateNumber($value) {
    if (empty($value) || $value === null) {
        return null;
    }
    
    if (!is_numeric($value)) {
        return null;
    }
    
    $number = (int)$value;
    return $number >= 0 ? $number : null;
}
?>