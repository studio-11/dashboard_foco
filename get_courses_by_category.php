<?php
/**
 * get_courses_by_category.php
 * Récupère tous les cours d'une catégorie et de ses sous-catégories
 */

header('Content-Type: application/json');

// Inclure les fonctions
require_once('functions.php');

if ($_POST && isset($_POST['category_name'])) {
    try {
        // Établir la connexion à la base de données
        $pdo = connectDatabase();
        
        $categoryName = $_POST['category_name'];
        
        // Trouver l'ID de la catégorie sélectionnée
        $category_sql = "SELECT id, path FROM mdl_course_categories WHERE name = :category_name";
        $category_stmt = $pdo->prepare($category_sql);
        $category_stmt->execute(['category_name' => $categoryName]);
        $category = $category_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$category) {
            echo json_encode([
                'success' => true,
                'course_ids' => [],
                'count' => 0,
                'message' => 'Catégorie non trouvée'
            ]);
            exit;
        }
        
        $category_id = $category['id'];
        $category_path = $category['path'];
        
        // Récupérer tous les cours de cette catégorie ET de ses sous-catégories
        // en s'assurant qu'ils sont sous la catégorie principale (ID=5)
        $sql = <<<SQL
        SELECT c.id AS course_id
        FROM mdl_course c
        JOIN mdl_course_categories cc ON c.category = cc.id
        WHERE (cc.path LIKE '%/5/%' OR cc.id = 5)
          AND (cc.name = :category_name OR cc.path LIKE :category_path_pattern)
        ORDER BY c.fullname
        SQL;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'category_name' => $categoryName,
            'category_path_pattern' => '%/' . $category_id . '/%'
        ]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $course_ids = array_column($results, 'course_id');
        
        echo json_encode([
            'success' => true,
            'course_ids' => array_map('intval', $course_ids),
            'count' => count($course_ids),
            'category_name' => $categoryName,
            'category_id' => $category_id,
            'search_pattern' => '%/' . $category_id . '/%'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Nom de catégorie manquant'
    ]);
}
?>