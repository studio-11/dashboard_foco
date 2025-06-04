<?php
/**
 * functions.php
 * Contient toutes les fonctions et requêtes SQL pour le dashboard
 */

/**
 * Établit la connexion à la base de données Moodle
 * @return PDO Instance de connexion PDO
 */
function connectDatabase() {
    $credentials = require('/export/hosting/men/ifen/htdocs-lms/ifen_credentials/db_credentials_learningsphere.php');

    try {
        $pdo = new PDO(
            "mysql:host={$credentials['host']};dbname={$credentials['db']};charset=utf8mb4",
            $credentials['user'],
            $credentials['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Erreur de connexion : " . $e->getMessage());
    }
}

/**
 * Établit la connexion à la base de données pour import_csv_foco
 * @return PDO Instance de connexion PDO
 */
function connectCSVDatabase() {
    $credentials = require('/export/hosting/men/ifen/htdocs-lms/ifen_credentials/db_credentials_programmation.php');

    try {
        $pdo = new PDO(
            "mysql:host={$credentials['host']};dbname={$credentials['db']};charset=utf8mb4",
            $credentials['user'],
            $credentials['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Erreur de connexion CSV DB : " . $e->getMessage());
        return null;
    }
}

/**
 * Récupère les informations de gestion depuis import_csv_foco
 * @param string $shortname Nom abrégé du cours (on prendra les 7 premiers caractères)
 * @return array Informations de gestion ou valeurs par défaut
 */
function getManagementInfo($shortname) {
    static $csvPdo = null;
    static $cache = [];
    
    // Initialiser la connexion CSV une seule fois
    if ($csvPdo === null) {
        $csvPdo = connectCSVDatabase();
    }
    
    // Si pas de connexion, retourner des valeurs par défaut
    if (!$csvPdo) {
        return [
            'concepteur_formation' => 'Non disponible',
            'gestionnaire_admin' => 'Non disponible'
        ];
    }
    
    // Extraire les 7 premiers caractères du nom abrégé
    $searchCode = substr($shortname, 0, 7);
    
    // Vérifier le cache
    if (isset($cache[$searchCode])) {
        return $cache[$searchCode];
    }
    
    try {
        // Rechercher dans import_csv_foco avec LIKE pour plus de flexibilité
        $sql = "SELECT Gestionnaire, GestionnaireAdmin FROM import_csv_foco WHERE Code LIKE :search_code LIMIT 1";
        $stmt = $csvPdo->prepare($sql);
        $stmt->execute(['search_code' => $searchCode . '%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $data = [
                'concepteur_formation' => !empty($result['Gestionnaire']) ? $result['Gestionnaire'] : 'Non défini',
                'gestionnaire_admin' => !empty($result['GestionnaireAdmin']) ? $result['GestionnaireAdmin'] : 'Non défini'
            ];
        } else {
            $data = [
                'concepteur_formation' => 'Non trouvé',
                'gestionnaire_admin' => 'Non trouvé'
            ];
        }
        
        // Mettre en cache
        $cache[$searchCode] = $data;
        return $data;
        
    } catch (Exception $e) {
        error_log("Erreur récupération gestion pour $searchCode: " . $e->getMessage());
        return [
            'concepteur_formation' => 'Erreur',
            'gestionnaire_admin' => 'Erreur'
        ];
    }
}

/**
 * Récupère les informations de base sur les cours avec recherche récursive des catégories
 * @param PDO $pdo Instance de connexion PDO
 * @return array Liste des cours avec leurs informations de base
 */
function getCoursesData($pdo) {
    $sql = <<<SQL
    SELECT 
        c.id AS course_id,
        c.fullname AS course_fullname,
        CONCAT('<a href="https://learningsphere.ifen.lu/course/view.php?id=', c.id, '" target="_blank">', c.shortname, '</a>') AS course_shortname,
        c.shortname AS raw_shortname,
        cc.name AS category_name,
        DATE_FORMAT(FROM_UNIXTIME(c.startdate), '%d-%m-%Y') AS start_date,
        CASE 
            WHEN c.enddate IS NULL OR c.enddate = 0 THEN 'Non défini'
            ELSE DATE_FORMAT(FROM_UNIXTIME(c.enddate), '%d-%m-%Y')
        END AS end_date,
        CASE 
            WHEN c.enablecompletion = 1 THEN 'Oui'
            ELSE 'Non'
        END AS completion_tracking_enabled,
        CASE 
            WHEN EXISTS (
                SELECT 1 
                FROM mdl_course_completion_criteria 
                WHERE course = c.id
            ) THEN 'Oui'
            ELSE 'Non'
        END AS course_completion_defined,
        (SELECT COUNT(DISTINCT ra.userid)
         FROM mdl_role_assignments ra
         JOIN mdl_context ctx ON ra.contextid = ctx.id
         WHERE ctx.contextlevel = 50 AND ctx.instanceid = c.id
           AND ra.roleid = 3) AS editing_teacher_count,
        (SELECT GROUP_CONCAT(u.email SEPARATOR '<br>')
         FROM mdl_user u
         JOIN mdl_role_assignments ra ON u.id = ra.userid
         JOIN mdl_context ctx ON ra.contextid = ctx.id
         WHERE ctx.contextlevel = 50 AND ctx.instanceid = c.id AND ra.roleid = 3) AS editing_teacher_emails,
        (SELECT COUNT(DISTINCT ue.userid)
         FROM mdl_user_enrolments ue
         JOIN mdl_enrol e ON ue.enrolid = e.id
         WHERE e.courseid = c.id) AS participant_count,
        CASE 
            WHEN EXISTS (
                SELECT 1
                FROM mdl_course_modules cm
                WHERE cm.course = c.id
                  AND cm.completion > 0
            ) THEN 'Oui'
            ELSE 'Néant'
        END AS activity_with_completion_and_restriction,
        CASE
            WHEN EXISTS (
                SELECT 1
                FROM mdl_course_modules cm
                JOIN mdl_label l ON cm.instance = l.id
                WHERE cm.course = c.id
                  AND cm.module = (
                    SELECT id FROM mdl_modules WHERE name = 'label' LIMIT 1
                  )
                  AND l.intro LIKE '%https://teams.microsoft.com/l/meetup-join/%'
            ) THEN 'Oui'
            ELSE 'Non'
        END AS lien_teams,
        c.format AS course_format
    FROM mdl_course c
    JOIN mdl_course_categories cc ON c.category = cc.id
    WHERE cc.path LIKE '%/5/%' OR cc.id = 5
    ORDER BY c.fullname;
    SQL;

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère tous les cours d'une catégorie et de ses sous-catégories de manière récursive
 * @param PDO $pdo Instance de connexion PDO
 * @param string $categoryName Nom de la catégorie à rechercher
 * @return array Liste des cours filtrés
 */
function getCoursesByCategory($pdo, $categoryName) {
    // Récupérer l'ID de la catégorie par son nom
    $category_sql = "SELECT id, path FROM mdl_course_categories WHERE name = :category_name";
    $category_stmt = $pdo->prepare($category_sql);
    $category_stmt->execute(['category_name' => $categoryName]);
    $category = $category_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        return []; // Catégorie non trouvée
    }
    
    $category_id = $category['id'];
    $category_path = $category['path'];
    
    // Récupérer tous les cours de cette catégorie et de toutes ses sous-catégories
    $sql = <<<SQL
    SELECT 
        c.id AS course_id,
        c.fullname AS course_fullname,
        CONCAT('<a href="https://learningsphere.ifen.lu/course/view.php?id=', c.id, '" target="_blank">', c.shortname, '</a>') AS course_shortname,
        c.shortname AS raw_shortname,
        cc.name AS category_name,
        DATE_FORMAT(FROM_UNIXTIME(c.startdate), '%d-%m-%Y') AS start_date,
        CASE 
            WHEN c.enddate IS NULL OR c.enddate = 0 THEN 'Non défini'
            ELSE DATE_FORMAT(FROM_UNIXTIME(c.enddate), '%d-%m-%Y')
        END AS end_date,
        CASE 
            WHEN c.enablecompletion = 1 THEN 'Oui'
            ELSE 'Non'
        END AS completion_tracking_enabled,
        CASE 
            WHEN EXISTS (
                SELECT 1 
                FROM mdl_course_completion_criteria 
                WHERE course = c.id
            ) THEN 'Oui'
            ELSE 'Non'
        END AS course_completion_defined,
        (SELECT COUNT(DISTINCT ra.userid)
         FROM mdl_role_assignments ra
         JOIN mdl_context ctx ON ra.contextid = ctx.id
         WHERE ctx.contextlevel = 50 AND ctx.instanceid = c.id
           AND ra.roleid = 3) AS editing_teacher_count,
        (SELECT GROUP_CONCAT(u.email SEPARATOR '<br>')
         FROM mdl_user u
         JOIN mdl_role_assignments ra ON u.id = ra.userid
         JOIN mdl_context ctx ON ra.contextid = ctx.id
         WHERE ctx.contextlevel = 50 AND ctx.instanceid = c.id AND ra.roleid = 3) AS editing_teacher_emails,
        (SELECT COUNT(DISTINCT ue.userid)
         FROM mdl_user_enrolments ue
         JOIN mdl_enrol e ON ue.enrolid = e.id
         WHERE e.courseid = c.id) AS participant_count,
        CASE 
            WHEN EXISTS (
                SELECT 1
                FROM mdl_course_modules cm
                WHERE cm.course = c.id
                  AND cm.completion > 0
            ) THEN 'Oui'
            ELSE 'Néant'
        END AS activity_with_completion_and_restriction,
        CASE
            WHEN EXISTS (
                SELECT 1
                FROM mdl_course_modules cm
                JOIN mdl_label l ON cm.instance = l.id
                WHERE cm.course = c.id
                  AND cm.module = (
                    SELECT id FROM mdl_modules WHERE name = 'label' LIMIT 1
                  )
                  AND l.intro LIKE '%https://teams.microsoft.com/l/meetup-join/%'
            ) THEN 'Oui'
            ELSE 'Non'
        END AS lien_teams,
        c.format AS course_format
    FROM mdl_course c
    JOIN mdl_course_categories cc ON c.category = cc.id
    WHERE cc.id = :category_id OR cc.path LIKE :category_path_pattern
    ORDER BY c.fullname;
    SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'category_id' => $category_id,
        'category_path_pattern' => $category_path . '/' . $category_id . '/%'
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Vérifie la présence d'un élément Teams dans les labels d'un cours
 * @param PDO $pdo Instance de connexion PDO
 * @param int $course_id ID du cours
 * @return string Information HTML avec le résultat
 */
function checkTeamsElement($pdo, $course_id) {
    // Rechercher la classe TeamsLink-BannerBackground dans les labels
    $teams_sql = <<<SQL
    SELECT l.intro, l.id
    FROM mdl_course_modules cm
    JOIN mdl_label l ON cm.instance = l.id
    WHERE cm.course = :course_id
      AND cm.module = (SELECT id FROM mdl_modules WHERE name = 'label' LIMIT 1)
      AND l.intro LIKE '%TeamsLink-BannerBackground%'
    LIMIT 1
    SQL;
    
    $teams_stmt = $pdo->prepare($teams_sql);
    $teams_stmt->execute(['course_id' => $course_id]);
    $teams_result = $teams_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$teams_result) {
        // Pas d'élément Teams
        return "no teams element";
    } else {
        // Élément Teams trouvé, vérifier s'il y a un lien valide
        preg_match('/https:\/\/teams\.microsoft\.com\/l\/meetup-join\/[^"\']+/', $teams_result['intro'], $matches);
        
        if (!empty($matches)) {
            // Lien Teams valide trouvé
            $teams_url = htmlspecialchars($matches[0]);
            return '<span style="color: green; font-weight: bold;">Oui</span> <a href="' . $teams_url . '" target="_blank">[Tester]</a>';
        } else {
            // Élément Teams présent mais pas de lien valide
            return '<span style="color: red; font-weight: bold;">Non</span>';
        }
    }
}

/**
 * Récupère les activités avec achèvement activé pour un cours
 * @param PDO $pdo Instance de connexion PDO
 * @param int $course_id ID du cours
 * @return array Liste des activités avec liens et types
 */
function getActivitiesWithCompletion($pdo, $course_id) {
    $activities_sql = <<<SQL
    SELECT 
        cm.id AS cmid,
        m.name AS module_type,
        CASE 
            WHEN m.name = 'assign' THEN a.name
            WHEN m.name = 'quiz' THEN q.name
            WHEN m.name = 'forum' THEN f.name
            WHEN m.name = 'resource' THEN r.name
            WHEN m.name = 'url' THEN u.name
            WHEN m.name = 'page' THEN p.name
            WHEN m.name = 'label' THEN l.name
            ELSE 'Activité'
        END AS activity_name,
        CASE
            WHEN m.name = 'assign' THEN a.intro
            WHEN m.name = 'quiz' THEN q.intro
            WHEN m.name = 'forum' THEN f.intro
            WHEN m.name = 'resource' THEN r.intro
            WHEN m.name = 'url' THEN u.intro
            WHEN m.name = 'page' THEN p.content
            WHEN m.name = 'label' THEN l.intro
            ELSE ''
        END AS activity_intro,
        cm.completion AS completion_setting,
        CASE
            WHEN cm.availability IS NOT NULL THEN 1
            ELSE 0
        END AS has_restriction
    FROM mdl_course_modules cm
    JOIN mdl_modules m ON cm.module = m.id
    LEFT JOIN mdl_assign a ON m.name = 'assign' AND cm.instance = a.id
    LEFT JOIN mdl_quiz q ON m.name = 'quiz' AND cm.instance = q.id
    LEFT JOIN mdl_forum f ON m.name = 'forum' AND cm.instance = f.id
    LEFT JOIN mdl_resource r ON m.name = 'resource' AND cm.instance = r.id
    LEFT JOIN mdl_url u ON m.name = 'url' AND cm.instance = u.id
    LEFT JOIN mdl_page p ON m.name = 'page' AND cm.instance = p.id
    LEFT JOIN mdl_label l ON m.name = 'label' AND cm.instance = l.id
    WHERE cm.course = :course_id
      AND cm.completion > 0
    SQL;
    
    $activities_stmt = $pdo->prepare($activities_sql);
    $activities_stmt->execute(['course_id' => $course_id]);
    return $activities_stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère le nombre de sections et la configuration pour un cours
 * @param PDO $pdo Instance de connexion PDO
 * @param int $course_id ID du cours
 * @param string $format Format du cours
 * @return string Information sur les sections
 */
function getSectionsInfo($pdo, $course_id, $format) {
    // Si le format n'est pas grid, afficher N/A
    if ($format !== 'grid') {
        return "N/A";
    }
    
    // Récupérer le nombre de sections dans le cours (sans compter la section 0)
    $sections_sql = "SELECT COUNT(*) FROM mdl_course_sections WHERE course = :course_id AND section > 0";
    $sections_stmt = $pdo->prepare($sections_sql);
    $sections_stmt->execute(['course_id' => $course_id]);
    $actual_sections = $sections_stmt->fetchColumn();
    
    // Ajouter 1 au total comme demandé
    $actual_sections = $actual_sections + 1;
    
    // Récupérer le paramètre "numsections" depuis course_format_options
    $format_options_sql = "SELECT value FROM mdl_course_format_options 
                          WHERE courseid = :course_id 
                          AND name = 'numsections'";
    $format_options_stmt = $pdo->prepare($format_options_sql);
    $format_options_stmt->execute(['course_id' => $course_id]);
    $expected_sections = $format_options_stmt->fetchColumn();
    
    // Si le paramètre n'est pas trouvé, essayer d'autres approches
    if (!$expected_sections) {
        $alt_options_sql = "SELECT MAX(value) FROM mdl_course_format_options 
                           WHERE courseid = :course_id 
                           AND (name = 'numsections' OR name = 'coursedisplay' OR name = 'hiddensections')";
        $alt_options_stmt = $pdo->prepare($alt_options_sql);
        $alt_options_stmt->execute(['course_id' => $course_id]);
        $expected_sections = $alt_options_stmt->fetchColumn();
        
        // Si toujours pas de valeur, utiliser "?"
        if (!$expected_sections) {
            $expected_sections = "?";
        }
    }
    
    // Formater la sortie dans le format demandé
    return "Section=$actual_sections / Param=$expected_sections";
}

/**
 * Recherche "Lorem ipsum" dans les activités d'un cours
 * @param PDO $pdo Instance de connexion PDO
 * @param int $course_id ID du cours
 * @return string Information sur les occurrences trouvées
 */
function findLoremIpsum($pdo, $course_id) {
    $lorem_ipsum_sql = <<<SQL
    SELECT 
        cm.id AS cmid,
        m.name AS module_type
    FROM mdl_course_modules cm
    JOIN mdl_modules m ON cm.module = m.id
    LEFT JOIN mdl_assign a ON m.name = 'assign' AND cm.instance = a.id
    LEFT JOIN mdl_quiz q ON m.name = 'quiz' AND cm.instance = q.id
    LEFT JOIN mdl_forum f ON m.name = 'forum' AND cm.instance = f.id
    LEFT JOIN mdl_resource r ON m.name = 'resource' AND cm.instance = r.id
    LEFT JOIN mdl_url u ON m.name = 'url' AND cm.instance = u.id
    LEFT JOIN mdl_page p ON m.name = 'page' AND cm.instance = p.id
    LEFT JOIN mdl_label l ON m.name = 'label' AND cm.instance = l.id
    WHERE cm.course = :course_id
      AND (
        (m.name = 'assign' AND a.intro LIKE '%Lorem ipsum%') OR
        (m.name = 'quiz' AND q.intro LIKE '%Lorem ipsum%') OR
        (m.name = 'forum' AND f.intro LIKE '%Lorem ipsum%') OR
        (m.name = 'resource' AND r.intro LIKE '%Lorem ipsum%') OR
        (m.name = 'url' AND u.intro LIKE '%Lorem ipsum%') OR
        (m.name = 'page' AND p.content LIKE '%Lorem ipsum%') OR
        (m.name = 'label' AND l.intro LIKE '%Lorem ipsum%')
      )
    SQL;
    
    $lorem_ipsum_stmt = $pdo->prepare($lorem_ipsum_sql);
    $lorem_ipsum_stmt->execute(['course_id' => $course_id]);
    $lorem_ipsum_results = $lorem_ipsum_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($lorem_ipsum_results)) {
        $lorem_count = count($lorem_ipsum_results);
        $lorem_links = [];
        
        foreach ($lorem_ipsum_results as $result) {
            $module_type = $result['module_type'];
            $cmid = $result['cmid'];
            $lorem_links[] = '<a href="https://learningsphere.ifen.lu/mod/' . $module_type . '/view.php?id=' . $cmid . '" target="_blank">click here</a>';
        }
        
        return "Total: $lorem_count - " . implode(', ', $lorem_links);
    } else {
        return "0";
    }
}

/**
 * Recherche "Descriptif et sessions" dans les labels - VERSION CORRIGÉE
 * @param PDO $pdo Instance de connexion PDO
 * @param int $course_id ID du cours
 * @return string Information et lien si trouvé
 */
function findDescriptionEtSession($pdo, $course_id) {
    $description_session_sql = <<<SQL
    SELECT 
        cm.id AS cmid,
        l.intro AS label_content
    FROM mdl_course_modules cm
    JOIN mdl_label l ON cm.instance = l.id
    WHERE cm.course = :course_id
      AND cm.module = (SELECT id FROM mdl_modules WHERE name = 'label')
      AND l.intro LIKE '%Descriptif et sessions%'
    SQL;
    
    $description_session_stmt = $pdo->prepare($description_session_sql);
    $description_session_stmt->execute(['course_id' => $course_id]);
    $description_session_results = $description_session_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($description_session_results)) {
        foreach ($description_session_results as $result) {
            $content = $result['label_content'];
            $cmid = $result['cmid'];
            
            // Rechercher d'abord un bouton avec data-tag-id="5"
            $pattern = '/<a[^>]+href="([^"]*)"[^>]*class="[^"]*btn[^"]*"[^>]*data-tag-id="5"[^>]*>.*?Descriptif et sessions.*?<\/a>/is';
            
            if (preg_match($pattern, $content, $matches)) {
                $href = trim($matches[1]);
                
                // Vérifier si le lien est valide (commence par https://)
                if (!empty($href) && $href !== '#' && strpos($href, 'https://') === 0) {
                    // Lien valide trouvé
                    return '<a href="' . htmlspecialchars($href) . '" target="_blank" style="color: green; font-weight: bold;">Oui</a>';
                } else {
                    // Élément trouvé mais lien invalide ou vide
                    return '<span style="color: red; font-weight: bold;">élément présent sans hyperlien</span>';
                }
            }
            
            // Rechercher un bouton sans data-tag-id mais avec btn
            $pattern2 = '/<a[^>]+href="([^"]*)"[^>]*class="[^"]*btn[^"]*"[^>]*>.*?Descriptif et sessions.*?<\/a>/is';
            
            if (preg_match($pattern2, $content, $matches2)) {
                $href = trim($matches2[1]);
                
                // Vérifier si le lien est valide (commence par https://)
                if (!empty($href) && $href !== '#' && strpos($href, 'https://') === 0) {
                    // Lien valide trouvé
                    return '<a href="' . htmlspecialchars($href) . '" target="_blank" style="color: green; font-weight: bold;">Oui</a>';
                } else {
                    // Élément trouvé mais lien invalide ou vide
                    return '<span style="color: red; font-weight: bold;">élément présent sans hyperlien</span>';
                }
            }
            
            // Vérifier si le texte "Descriptif et sessions" est présent dans un lien simple
            if (preg_match('/<a[^>]+href="([^"]*)"[^>]*>.*?Descriptif et sessions.*?<\/a>/is', $content, $matches3)) {
                $href = trim($matches3[1]);
                
                if (!empty($href) && $href !== '#' && strpos($href, 'https://') === 0) {
                    return '<a href="' . htmlspecialchars($href) . '" target="_blank" style="color: green; font-weight: bold;">Oui</a>';
                } else {
                    return '<span style="color: red; font-weight: bold;">élément présent sans hyperlien</span>';
                }
            }
            
            // Si le texte est présent mais pas dans un lien
            if (strpos($content, 'Descriptif et sessions') !== false) {
                return '<span style="color: orange; font-weight: bold;">élément présent sans hyperlien</span>';
            }
        }
    }
    
    return "aucun element";
}

/**
 * Enrichit les données des cours avec des informations supplémentaires
 * @param PDO $pdo Instance de connexion PDO
 * @param array &$courses Liste des cours à enrichir
 * @return array Liste des types d'activités, formats et catégories trouvés
 */
function enrichCoursesData($pdo, &$courses) {
    $activity_types = [];
    $course_formats = [];
    $category_names = []; // Nouvelle liste pour stocker les noms des catégories
    
    foreach ($courses as &$course) {
        $course_id = $course['course_id'];
        
        // Stocker le nom de la catégorie pour le filtre
        if (!in_array($course['category_name'], $category_names)) {
            $category_names[] = $course['category_name'];
        }
        
        // 1. Vérifier les éléments Teams avec la nouvelle fonction
        $course['lien_teams'] = checkTeamsElement($pdo, $course_id);
        
        // 2. Récupérer les activités avec achèvement (modifié par rapport à la version originale)
        if ($course['activity_with_completion_and_restriction'] === 'Oui') {
            $activities = getActivitiesWithCompletion($pdo, $course_id);
            
            if (!empty($activities)) {
                $activity_links = [];
                foreach ($activities as $activity) {
                    $module_type = $activity['module_type'];
                    $activity_name = $activity['activity_name'] ?: 'Activité';
                    $cmid = $activity['cmid'];
                    $activity_intro = $activity['activity_intro'];
                    $has_restriction = $activity['has_restriction'];
                    $completion_setting = $activity['completion_setting'];
                    
                    // CORRECTION: Toutes les activités ici ont déjà achèvement > 0 (from SQL query)
                    // donc elles doivent toutes être en vert
                    $link_style = ' style="color: green; font-weight: bold;"';
                    
                    $activity_links[] = '<a href="https://learningsphere.ifen.lu/mod/' . $module_type . '/view.php?id=' . $cmid . '" target="_blank" data-type="' . $module_type . '"' . $link_style . '>' . $module_type . '</a>';
                    
                    // Ajouter ce type d'activité à notre liste pour les filtres
                    if (!in_array($module_type, $activity_types)) {
                        $activity_types[] = $module_type;
                    }
                }
                
                $course['activity_with_completion_and_restriction'] = implode(', ', $activity_links);
                $course['activity_types_array'] = array_unique(array_column($activities, 'module_type'));
            }
        }
        
        // 3. Vérifier le format du cours et compter les sections si nécessaire
        $format = $course['course_format'];
        
        // Ajouter ce format à notre liste pour les filtres
        if (!in_array($format, $course_formats)) {
            $course_formats[] = $format;
        }
        
        $course['sections_comparison'] = getSectionsInfo($pdo, $course_id, $format);
        
        // 4. Rechercher "Lorem ipsum" dans toutes les activités et ressources
        $course['lorem_ipsum'] = findLoremIpsum($pdo, $course_id);
        
        // 5. Rechercher "Descriptif et sessions" dans les labels (text&media) - VERSION CORRIGÉE
        $course['description_session'] = findDescriptionEtSession($pdo, $course_id);
        
        // 6. NOUVEAU: Récupérer les informations de gestion depuis import_csv_foco
        $managementInfo = getManagementInfo($course['raw_shortname']);
        $course['concepteur_formation'] = $managementInfo['concepteur_formation'];
        $course['gestionnaire_admin'] = $managementInfo['gestionnaire_admin'];
    }
    
    // Trier alphabétiquement les tableaux de filtres
    sort($activity_types);
    sort($course_formats);
    sort($category_names); // Trier les noms de catégories
    
    return [
        'activity_types' => $activity_types,
        'course_formats' => $course_formats,
        'category_names' => $category_names // Retourner la liste des noms de catégories
    ];
}
?>