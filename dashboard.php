<?php
/**
 * dashboard.php
 * Fichier principal du dashboard Moodle avec intégration Upload CSV et suivi utilisateur
 * VERSION MISE À JOUR avec colonnes de gestion
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Inclure les fonctions
require_once('functions.php');

// Établir la connexion à la base de données
$pdo = connectDatabase();

// Récupérer les données de base des cours
$courses = getCoursesData($pdo);

// Enrichir les données avec des informations supplémentaires
$filters = enrichCoursesData($pdo, $courses);

// Extraire les filtres
$activity_types = $filters['activity_types'];
$course_formats = $filters['course_formats'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>CourseGuard Pro - Contrôle de Qualité des Cours</title>
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="styles.css">
  
  <style>
    /* NOUVEAU: Styles pour les colonnes de gestion */
    .management-section {
      border-left: 4px solid #17a2b8 !important;
      background: linear-gradient(135deg, #e8f4f8 0%, #f0f9fb 100%) !important;
    }
    
    .management-header {
      background: linear-gradient(135deg, #17a2b8 0%, #138496 100%) !important;
      color: white !important;
      position: relative;
    }
    
    .management-header::before {
      content: "📋";
      margin-right: 8px;
      font-size: 1.1em;
    }
    
    .management-cell {
      background: linear-gradient(135deg, #e8f4f8 0%, #f0f9fb 100%) !important;
      border-left: 2px solid #17a2b8 !important;
      font-weight: 500;
      color: #0c5460;
      padding: 10px 8px !important;
    }
    
    /* Indicateurs de statut pour les informations de gestion */
    .management-status-found {
      color: #155724 !important;
      background-color: #d4edda !important;
      border: 1px solid #c3e6cb !important;
      border-radius: 4px;
      padding: 2px 6px;
      font-size: 0.85em;
      font-weight: 600;
    }
    
    .management-status-missing {
      color: #721c24 !important;
      background-color: #f8d7da !important;
      border: 1px solid #f5c6cb !important;
      border-radius: 4px;
      padding: 2px 6px;
      font-size: 0.85em;
      font-weight: 600;
    }
    
    .management-status-error {
      color: #856404 !important;
      background-color: #fff3cd !important;
      border: 1px solid #ffeaa7 !important;
      border-radius: 4px;
      padding: 2px 6px;
      font-size: 0.85em;
      font-weight: 600;
    }
    
    /* Séparateur visuel entre sections */
    .section-separator {
      border-left: 3px solid #dee2e6 !important;
      position: relative;
    }
    
    .section-separator::before {
      content: "";
      position: absolute;
      left: -3px;
      top: 0;
      bottom: 0;
      width: 3px;
      background: linear-gradient(to bottom, transparent 0%, #17a2b8 50%, transparent 100%);
    }

    /* Styles pour le bouton Upload et la lightbox */
    .btn-upload {
      background: #28a745 !important;
      color: white !important;
      border-color: #28a745 !important;
    }
    
    .btn-upload:hover {
      background: #218838 !important;
      border-color: #1e7e34 !important;
    }
    
    /* Styles pour la lightbox Upload */
    .upload-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.7);
      z-index: 1001;
      opacity: 0;
      transition: opacity 0.3s ease;
    }
    
    .upload-overlay.show {
      display: flex !important;
      opacity: 1;
      justify-content: center;
      align-items: center;
    }
    
    .upload-modal {
      background: white;
      border-radius: 8px;
      width: 90%;
      max-width: 500px;
      max-height: 80vh;
      overflow-y: auto;
      position: relative;
      transform: scale(0.7);
      transition: transform 0.3s ease;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }
    
    .upload-overlay.show .upload-modal {
      transform: scale(1);
    }
    
    .upload-header {
      padding: 20px;
      border-bottom: 1px solid #eee;
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #f8f9fa;
      border-radius: 8px 8px 0 0;
    }
    
    .upload-title {
      margin: 0;
      font-size: 20px;
      color: #333;
      font-weight: 600;
    }
    
    .upload-close {
      background: none;
      border: none;
      font-size: 24px;
      cursor: pointer;
      color: #666;
      line-height: 1;
      padding: 5px;
      border-radius: 3px;
      transition: all 0.2s ease;
    }
    
    .upload-close:hover {
      color: #333;
      background: #e9ecef;
    }
    
    .upload-body {
      padding: 20px;
    }
    
    .upload-form {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    
    .upload-form-group {
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-bottom: 20px;
    }
    
    .upload-form-group label {
      font-weight: 600;
      color: #333;
      font-size: 14px;
      display: flex;
      align-items: center;
    }
    
    .upload-form-group label i {
      margin-right: 8px;
      color: #0d6efd;
    }
    
    .file-input {
      width: 100%;
      padding: 30px 20px;
      border: 2px dashed #ddd;
      border-radius: 8px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s ease;
      background: #fafafa;
    }
    
    .file-input:hover {
      border-color: #28a745;
      background: #f8fff9;
    }
    
    .file-input.dragover {
      border-color: #28a745;
      background-color: #f8fff9;
      transform: scale(1.02);
    }
    
    .file-input.file-selected {
      background-color: #d4edda;
      border-color: #28a745;
      color: #155724;
    }
    
    .file-input-text {
      display: block;
      color: #666;
      font-size: 14px;
      line-height: 1.5;
    }
    
    .file-input-text small {
      display: block;
      margin-top: 8px;
      font-size: 12px;
      color: #999;
    }
    
    .progress-container {
      display: none;
      margin-top: 15px;
    }
    
    .progress-bar-upload {
      width: 100%;
      height: 15px;
      background-color: #f0f0f0;
      border-radius: 8px;
      overflow: hidden;
      position: relative;
    }
    
    .progress-fill {
      height: 100%;
      background: linear-gradient(90deg, #28a745, #20c997);
      width: 0%;
      transition: width 0.3s ease;
      border-radius: 8px;
    }
    
    .progress-text {
      display: block;
      text-align: center;
      margin-top: 8px;
      font-size: 13px;
      color: #666;
      font-weight: 500;
    }
    
    .btn-submit {
      background: #007bff;
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 16px;
      font-weight: 600;
      transition: all 0.3s ease;
      font-family: inherit;
    }
    
    .btn-submit:hover {
      background: #0056b3;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
    }
    
    .btn-submit:disabled {
      background: #6c757d !important;
      cursor: not-allowed !important;
      transform: none !important;
      box-shadow: none !important;
      opacity: 0.7;
    }
    
    .upload-results {
      margin-top: 20px;
      padding: 20px;
      border-radius: 6px;
      display: none;
      text-align: center;
    }
    
    .upload-results.success {
      background-color: #d4edda;
      border: 1px solid #c3e6cb;
      color: #155724;
    }
    
    .upload-results.error {
      background-color: #f8d7da;
      border: 1px solid #f5c6cb;
      color: #721c24;
    }
    
    .upload-results h4 {
      margin: 0 0 10px 0;
      font-size: 18px;
      font-weight: 600;
    }
    
    .result-icon {
      font-size: 3rem;
      margin-bottom: 15px;
    }
    
    /* Informations du dernier upload */
    .last-upload-info {
      margin-bottom: 25px;
      padding: 15px;
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      border: 1px solid #dee2e6;
      border-radius: 8px;
      border-left: 4px solid #0d6efd;
      animation: fadeIn 0.3s ease-in-out;
    }
    
    .last-upload-header {
      font-weight: 600;
      color: #0d6efd;
      margin-bottom: 12px;
      font-size: 0.95rem;
      display: flex;
      align-items: center;
    }
    
    .last-upload-header i {
      margin-right: 8px;
    }
    
    .last-upload-content {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    
    .last-upload-item {
      display: flex;
      align-items: center;
      font-size: 0.9rem;
      color: #495057;
    }
    
    .last-upload-item i {
      margin-right: 10px;
      width: 16px;
      color: #6c757d;
    }
    
    .last-upload-item strong {
      margin-right: 8px;
      color: #343a40;
    }
    
    .no-upload-info {
      text-align: center;
      color: #6c757d;
      font-style: italic;
      font-size: 0.9rem;
    }
    
    .no-upload-info i {
      margin-right: 8px;
    }
    
    .loading-spinner {
      text-align: center;
      color: #6c757d;
      font-size: 0.9rem;
    }
    
    .loading-spinner i {
      margin-right: 8px;
    }
    
    /* Dropdown utilisateur */
    #userSelect {
      width: 100%;
      padding: 10px 12px;
      border: 2px solid #dee2e6;
      border-radius: 6px;
      font-size: 16px;
      background-color: white;
      transition: all 0.3s ease;
    }
    
    #userSelect:focus {
      outline: none;
      border-color: #0d6efd;
      box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
    }
    
    #userSelect.is-invalid {
      border-color: #dc3545;
      box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
    }
    
    /* Label requis */
    .required {
      color: #dc3545;
      font-weight: bold;
    }
    
    /* Feedback de validation */
    .invalid-feedback {
      display: none;
      width: 100%;
      margin-top: 0.25rem;
      font-size: 0.875rem;
      color: #dc3545;
    }
    
    #userSelect.is-invalid ~ .invalid-feedback {
      display: block;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .last-upload-info:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
    }
    
    /* Notifications */
    .notification {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 15px 20px;
      border-radius: 6px;
      color: white;
      font-weight: 600;
      z-index: 1100;
      opacity: 0;
      transform: translateX(100%);
      transition: all 0.3s ease;
      max-width: 400px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }
    
    .notification.show {
      opacity: 1;
      transform: translateX(0);
    }
    
    .notification.success {
      background: linear-gradient(135deg, #28a745, #20c997);
    }
    
    .notification.error {
      background: linear-gradient(135deg, #dc3545, #e74c3c);
    }
    
    .notification.warning {
      background: linear-gradient(135deg, #ffc107, #ff9800);
      color: #333;
    }
    
    /* Responsive pour mobile */
    @media (max-width: 768px) {
      .last-upload-content {
        gap: 6px;
      }
      
      .last-upload-item {
        font-size: 0.85rem;
      }
      
      .last-upload-item i {
        width: 14px;
        margin-right: 8px;
      }
    }
  </style>
</head>
<body>
  <div class="container-fluid">
    <div class="hero-title-container">
      <h1 class="hero-title">
        <span class="title-icon">⚡</span>
        <span class="main-title">CourseGuard</span>
        <span class="subtitle">Pro</span>
      </h1>
      <p class="hero-subtitle">Système de Contrôle et d'Audit Qualité des Cours</p>
    </div>
    
    <div class="buttons-container">
      <!-- Bouton de légende -->
      <button class="legend-btn" id="showLegend">Légende</button>
      <!-- Bouton de réinitialisation des filtres -->
      <button id="resetFilters" class="legend-btn">Réinitialiser tous les filtres</button>
      <!-- Bouton Upload CSV -->
      <button class="legend-btn btn-upload" onclick="openUploadModal()">
        <i class="fas fa-upload"></i> Upload CSV
      </button>
    </div>

    <!-- Lightbox de légende -->
    <div class="legend-overlay" id="legendOverlay">
      <div class="legend-modal">
        <span class="legend-close" id="closeLegend">&times;</span>
        <h4>Légendes</h4>
        
        <h5>Couleurs des liens dans la colonne "Activités avec achèvement d'activité"</h5>
        <ul class="legend-list">
          <li><span class="legend-item green-link">Lien vert</span> : Achèvement d'activité activé</li>
        </ul>
        
        <h5 class="mt-4">Statut Teams</h5>
        <ul class="legend-list">
          <li><span class="legend-item teams-yes">Élément + lien OK</span> : L'élément Teams contient un lien valide</li>
          <li><span class="legend-item teams-no">Élément mais lien absent</span> : L'élément Teams est présent mais ne contient pas de lien valide</li>
          <li>"Aucun élément" : Aucun élément Teams n'est présent dans le cours</li>
        </ul>
        
        <h5 class="mt-4">Informations de Gestion</h5>
        <ul class="legend-list">
          <li><span class="legend-item management-status-found">Trouvé</span> : Information disponible dans import_csv_foco</li>
          <li><span class="legend-item management-status-missing">Non trouvé</span> : Aucune correspondance trouvée</li>
          <li><span class="legend-item management-status-error">Erreur</span> : Problème de connexion ou autre erreur</li>
        </ul>
        
        <h5 class="mt-4">Couleurs des lignes</h5>
        <ul class="legend-list">
          <li><span class="legend-item row-yellow">Jaune</span> : Au moins une de ces conditions est présente:
            <ul>
              <li>Aucun enseignant (editing teacher = 0)</li>
              <li>Emails editing-teachers est vide</li>
              <li>Aucun participant (participants = 0)</li>
              <li>Teams element existe mais sans lien défini</li>
              <li>Lorem ipsum est présent</li>
              <li>Descriptif et sessions est vide</li>
            </ul>
          </li>
          <li><span class="legend-item row-gray">Rouge</span> : Mêmes conditions que jaune, mais pour les cours qui commencent dans les 3 prochains jours</li>
        </ul>
      </div>
    </div>
    
    <div class="filters-box">
      <!-- Première ligne de filtres -->
      <div class="row mb-3">
        <div class="col-md-3">
          <label for="choixDate">Choisir une date :</label>
          <div class="date-input-container">
            <input type="text" id="choixDate" class="form-control" placeholder="JJ-MM-AAAA">
            <i class="fas fa-calendar-alt" id="dateIcon"></i>
          </div>
          <!-- Section futur mise à jour -->
          <div class="start-day-filter mt-2">
            <div class="start-alert">
              <i class="fas fa-rocket future-icon"></i>
              <span class="future-text">Take me to the future :</span>
              <input type="number" id="startInDays" class="form-control form-control-sm" min="0" max="100" value="0">
              <span class="day-label">jour(s)</span>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <label for="tableSearch">Rechercher :</label>
          <div class="search-input-container">
            <input type="text" id="tableSearch" class="form-control" placeholder="Filtrer par nom de cours ou nom court...">
            <button type="button" id="searchButton" class="search-btn">
              <i class="fas fa-search"></i>
            </button>
          </div>
        </div>
        <div class="col-md-5">
          <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" id="showOnlyWithEndDate">
            <label class="form-check-label" for="showOnlyWithEndDate">
              Seulement les cours avec date de fin définie
            </label>
          </div>
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <table id="coursesTable" class="table table-striped table-bordered w-100" style="width: 100%;">
        <thead>
          <tr>
            <th>ID <span class="info-icon">
                <i class="fas fa-info-circle"></i>
                <div class="custom-tooltip">
                  <strong>Identifiant unique du cours</strong>
                  Numéro ID unique extrait directement de la table mdl_course, colonne 'id'.
                </div>
              </span></th>
            <th>Nom du cours <span class="info-icon">
                <i class="fas fa-info-circle"></i>
                <div class="custom-tooltip">
                  <strong>Nom complet du cours</strong>
                  Titre officiel du cours extrait de la table mdl_course, colonne 'fullname'.
                </div>
              </span></th>
            <th>Nom abrégé <span class="info-icon">
                <i class="fas fa-info-circle"></i>
                <div class="custom-tooltip">
                  <strong>Nom abrégé du cours</strong>
                  Acronyme ou abréviation du cours extrait de la table mdl_course, colonne 'shortname'. Lien direct vers la page du cours.
                </div>
              </span></th>
            <th>Catégorie <span class="info-icon">
                <i class="fas fa-info-circle"></i>
                <div class="custom-tooltip">
                  <strong>Catégorie du cours</strong>
                  Classification du cours extrait de la table mdl_course_categories, comparée avec mdl_course via la colonne 'category'.
                </div>
              </span><br>
              <div class="filter-container category-filter-container">
                <select id="categoryFilter" class="form-select form-select-sm">
                  <option value="">Toutes les catégories</option>
                  <?php 
                  // Extraire les catégories uniques des cours
                  $categories = array_unique(array_column($courses, 'category_name'));
                  sort($categories);
                  foreach ($categories as $category): ?>
                  <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </th>
            <th>Date de début <span class="info-icon">
                <i class="fas fa-info-circle"></i>
                <div class="custom-tooltip">
                  <strong>Date de début du cours</strong>
                  Date officielle de début du cours extraite de la table mdl_course, colonne 'startdate', convertie du format timestamp Unix.
                </div>
              </span></th>
            <th>Date de fin <span class="info-icon">
                <i class="fas fa-info-circle"></i>
                <div class="custom-tooltip">
                  <strong>Date de fin du cours</strong>
                  Date de clôture du cours (si définie) extraite de la table mdl_course, colonne 'enddate'. 'Non défini' si la valeur est NULL ou 0.
                </div>
              </span></th>
            <th>
              Suivi d'achèvement <span class="info-icon">
                <i class="fas fa-info-circle"></i>
                <div class="custom-tooltip">
                  <strong>Suivi d'achèvement activé</strong>
                  Indique si le suivi des activités est activé dans le cours. Extrait de mdl_course, colonne 'enablecompletion' (1 = Oui, 0 = Non).
                </div>
              </span><br>
              <div class="filter-container">
                <input class="form-check-input filter-checkbox yesno-filter" type="checkbox" data-column="6" value="Oui"> 
                <span class="filter-label">Oui</span>
                <input class="form-check-input filter-checkbox yesno-filter" type="checkbox" data-column="6" value="Non"> 
                <span class="filter-label">Non</span>
              </div>
            </th>
            <th>
              Achèvement de cours <span class="info-icon">
                <i class="fas fa-info-circle"></i>
                <div class="custom-tooltip">
                  <strong>Critères d'achèvement définis</strong>
                  Indique si des conditions d'achèvement du cours sont configurées. Vérification de la présence dans la table mdl_course_completion_criteria.
                </div>
              </span><br>
              <div class="filter-container">
                <input class="form-check-input filter-checkbox yesno-filter" type="checkbox" data-column="7" value="Oui"> 
                <span class="filter-label">Oui</span>
                <input class="form-check-input filter-checkbox yesno-filter" type="checkbox" data-column="7" value="Non"> 
                <span class="filter-label">Non</span>
              </div>
            </th>
            <th>editing teacher <span class="info-icon">
                <i class="fas fa-info-circle"></i>
                <div class="custom-tooltip">
                  <strong>Nombre d'enseignants</strong>
                  Total des enseignants-éditeurs avec roleid=3 dans la table mdl_role_assignments, via le contexte du cours dans mdl_context.
                </div>
              </span></th>
            <th>Emails editing-teachers <span class="info-icon">
                <i class="fas fa-info-circle"></i>
                <div class="custom-tooltip">
                  <strong>Contacts des enseignants</strong>
                  Adresses email des enseignants du cours, extraites de la table mdl_user et liées via mdl_role_assignments et mdl_context.
                </div>
              </span></th>
            <th>Participants <span class="info-icon">
                <i class="fas fa-info-circle"></i>
                <div class="custom-tooltip">
                  <strong>Nombre de participants</strong>
                  Total d'utilisateurs inscrits au cours selon les tables mdl_user_enrolments et mdl_enrol, filtrés par le courseid.
                </div>
              </span></th>
            <th class="activities-column">
              Activités avec achèvement d'activité <span class="info-icon">
                <i class="fas fa-info-circle"></i>
                <div class="custom-tooltip">
                  <strong>Activités avec achèvement d'activité</strong>
                  Modules ayant un achèvement activé (completion > 0) dans mdl_course_modules. Tous les modules affichés ont l'achèvement activé.
                </div>
              </span><br>
              <div class="filter-container activity-filter-container">
                <div class="filter-item">
                  <input class="form-check-input filter-checkbox activity-filter" type="checkbox" value="Néant">
                  <span class="filter-label">Néant</span>
                </div>
                <?php foreach ($activity_types as $type): ?>
                <div class="filter-item">
                  <input class="form-check-input filter-checkbox activity-filter" type="checkbox" value="<?= $type ?>">
                  <span class="filter-label"><?= $type ?></span>
                </div>
                <?php endforeach; ?>
              </div>
            </th>
            <th>
              Teams Element+Link <span class="info-icon">
                <i class="fas fa-info-circle"></i>
                <div class="custom-tooltip">
                  <strong>Lien Microsoft Teams</strong>
                  Détecte la présence d'un élément Teams (classe TeamsLink-BannerBackground) et vérifie si un lien Teams valide est présent.
                </div>
              </span><br>
              <div class="filter-container">
                <div class="filter-item">
                  <input class="form-check-input filter-checkbox teams-filter" type="checkbox" value="element_lien_ok"> 
                  <span class="filter-label">Élément + lien OK</span>
                </div>
                <div class="filter-item">
                  <input class="form-check-input filter-checkbox teams-filter" type="checkbox" value="element_lien_absent"> 
                  <span class="filter-label">Élément mais lien absent</span>
                </div>
                <div class="filter-item">
                  <input class="form-check-input filter-checkbox teams-filter" type="checkbox" value="aucun_element"> 
                  <span class="filter-label">Aucun élément</span>
                </div>
              </div>
            </th>
            <th>
              Format <span class="info-icon">
                <i class="fas fa-info-circle"></i>
                <div class="custom-tooltip">
                  <strong>Format du cours</strong>
                  Type de présentation du cours (grid, topics, etc.) extrait directement de la table mdl_course, colonne 'format'.
                </div>
              </span><br>
              <div class="filter-container format-filter-container">
                <?php foreach ($course_formats as $format): ?>
                <div class="filter-item">
                  <input class="form-check-input filter-checkbox format-filter" type="checkbox" value="<?= $format ?>">
                  <span class="filter-label"><?= $format ?></span>
                </div>
                <?php endforeach; ?>
              </div>
            </th>
            <th class="sections-column" style="display: none;">Sections <span class="info-icon">
                <i class="fas fa-info-circle"></i>
                <div class="custom-tooltip">
                  <strong>Nombre de sections vs paramètres</strong>
                  Pour le format grid, compare le nombre réel de sections (+1) avec le paramètre 'numsections' du cours.
                </div>
              </span></th>
            <th>
              Lorem ipsum <span class="info-icon">
                <i class="fas fa-info-circle"></i>
                <div class="custom-tooltip">
                  <strong>Texte par défaut détecté</strong>
                  Recherche du texte 'Lorem ipsum' dans toutes les activités et ressources du cours via LIKE '%Lorem ipsum%' dans les tables correspondantes.
                </div>
              </span><br>
              <div class="filter-container">
                <input class="form-check-input filter-checkbox lorem-filter" type="checkbox" value="true"> 
                <span class="filter-label">Oui</span>
              </div>
            </th>
           <th>
  Descriptif et sessions <span class="info-icon">
    <i class="fas fa-info-circle"></i>
    <div class="custom-tooltip">
      <strong>Bouton "Descriptif et sessions"</strong>
      Recherche d'un bouton avec la classe 'btn' et data-tag-id="5" contenant le texte "Descriptif et sessions", et vérifie si le lien href est valide (commence par https://).
    </div>
  </span><br>
  <div class="filter-container">
    <div class="filter-item">
      <input class="form-check-input filter-checkbox description-filter" type="checkbox" value="element_lien_ok"> 
      <span class="filter-label">Élément + lien OK</span>
    </div>
    <div class="filter-item">
      <input class="form-check-input filter-checkbox description-filter" type="checkbox" value="element_lien_absent"> 
      <span class="filter-label">Élément mais lien absent</span>
    </div>
    <div class="filter-item">
      <input class="form-check-input filter-checkbox description-filter" type="checkbox" value="aucun_element"> 
      <span class="filter-label">Aucun élément</span>
    </div>
  </div>
</th>
            
            <!-- NOUVELLES COLONNES DE GESTION -->
            <th class="section-separator"></th> <!-- Séparateur visuel -->
            <th class="management-header management-section">
              Concepteur de formation <span class="info-icon">
                <i class="fas fa-info-circle"></i>
                <div class="custom-tooltip">
                  <strong>Concepteur de formation</strong>
                  Information extraite de la table import_csv_foco, colonne 'Gestionnaire'. Recherche basée sur les 7 premiers caractères du nom abrégé du cours.
                </div>
              </span><br>
              <div class="filter-container">
                <div class="filter-item">
                  <input class="form-check-input filter-checkbox concepteur-filter" type="checkbox" value="found"> 
                  <span class="filter-label">Trouvé</span>
                </div>
                <div class="filter-item">
                  <input class="form-check-input filter-checkbox concepteur-filter" type="checkbox" value="missing"> 
                  <span class="filter-label">Non trouvé</span>
                </div>
              </div>
            </th>
            <th class="management-header management-section">
              Gestionnaire Admin <span class="info-icon">
                <i class="fas fa-info-circle"></i>
                <div class="custom-tooltip">
                  <strong>Gestionnaire Admin</strong>
                  Information extraite de la table import_csv_foco, colonne 'GestionnaireAdmin'. Recherche basée sur les 7 premiers caractères du nom abrégé du cours.
                </div>
              </span><br>
              <div class="filter-container">
                <div class="filter-item">
                  <input class="form-check-input filter-checkbox gestionnaire-filter" type="checkbox" value="found"> 
                  <span class="filter-label">Trouvé</span>
                </div>
                <div class="filter-item">
                  <input class="form-check-input filter-checkbox gestionnaire-filter" type="checkbox" value="missing"> 
                  <span class="filter-label">Non trouvé</span>
                </div>
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
       <?php foreach ($courses as $course): ?>
<tr data-start-date="<?= $course['start_date'] ?>" data-end-date="<?= $course['end_date'] ?>">
  <td><?= $course['course_id'] ?></td> <!-- 1 -->
  <td><?= $course['course_fullname'] ?></td>  <!-- 2 -->
  <td><?= $course['course_shortname'] ?></td>  <!-- 3 -->
  <td><?= $course['category_name'] ?></td> <!-- 4 -->
  <td><?= $course['start_date'] ?></td>  <!-- 5 -->
  <td><?= $course['end_date'] ?></td>  <!-- 6 -->
  <td><?= $course['completion_tracking_enabled'] ?></td>  <!-- 7 -->
  <td><?= $course['course_completion_defined'] ?></td> <!-- 8 -->
  <td><?= $course['editing_teacher_count'] ?></td> <!-- 9 -->
  <td><?= $course['editing_teacher_emails'] ?></td> <!-- 10 -->
  <td><?= $course['participant_count'] ?></td> <!-- 11 -->
  <td class="activities-column" data-activity-types="<?= isset($course['activity_types_array']) ? htmlspecialchars(json_encode($course['activity_types_array'])) : '' ?>">
    <?= $course['activity_with_completion_and_restriction'] ?>
  </td>  <!-- 12 -->
  <td><?= $course['lien_teams'] ?></td>  <!-- 13 -->
  <td><?= $course['course_format'] ?></td> <!-- 14 -->
  <td class="sections-column" style="display: none;"><?= $course['sections_comparison'] ?></td> <!-- 15 -->
  <td><?= $course['lorem_ipsum'] ?></td> <!-- 16 -->
  <td><?= $course['description_session'] ?></td> <!-- 17 AJOUTEZ CETTE LIGNE -->
  
  <!-- NOUVELLES CELLULES DE GESTION -->
  <td class="section-separator"></td> <!-- 18 -->
  <td class="management-cell" data-management-type="concepteur"> <!-- 19 -->
    <?php 
    $concepteur = $course['concepteur_formation'];
    if (in_array($concepteur, ['Non trouvé', 'Non défini'])) {
      echo '<span class="management-status-missing">' . htmlspecialchars($concepteur) . '</span>';
    } elseif (in_array($concepteur, ['Erreur', 'Non disponible'])) {
      echo '<span class="management-status-error">' . htmlspecialchars($concepteur) . '</span>';
    } else {
      echo '<span class="management-status-found">' . htmlspecialchars($concepteur) . '</span>';
    }
    ?>
  </td>
  <td class="management-cell" data-management-type="gestionnaire"> <!-- 20 -->
    <?php 
    $gestionnaire = $course['gestionnaire_admin'];
    if (in_array($gestionnaire, ['Non trouvé', 'Non défini'])) {
      echo '<span class="management-status-missing">' . htmlspecialchars($gestionnaire) . '</span>';
    } elseif (in_array($gestionnaire, ['Erreur', 'Non disponible'])) {
      echo '<span class="management-status-error">' . htmlspecialchars($gestionnaire) . '</span>';
    } else {
      echo '<span class="management-status-found">' . htmlspecialchars($gestionnaire) . '</span>';
    }
    ?>
  </td>
</tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- LIGHTBOX POUR L'UPLOAD CSV -->
  <div id="uploadOverlay" class="upload-overlay">
    <div class="upload-modal">
      <div class="upload-header">
        <h2 class="upload-title">
          <i class="fas fa-upload"></i> Upload CSV LearningSphere
        </h2>
        <button class="upload-close" onclick="closeUploadModal()">&times;</button>
      </div>
      
      <div class="upload-body">
        <!-- Informations sur le dernier upload -->
        <div id="lastUploadInfo" class="last-upload-info">
          <div class="last-upload-header">
            <i class="fas fa-history"></i> Dernière importation
          </div>
          <div id="lastUploadContent" class="last-upload-content">
            <div class="loading-spinner">
              <i class="fas fa-spinner fa-spin"></i> Chargement...
            </div>
          </div>
        </div>

        <form id="uploadForm" class="upload-form" enctype="multipart/form-data">
          <!-- Sélection utilisateur (OBLIGATOIRE) -->
          <div class="upload-form-group">
            <label for="userSelect">
              <i class="fas fa-user"></i> Sélectionner l'utilisateur <span class="required">*</span>
            </label>
            <select id="userSelect" name="userSelect" class="form-select" required>
              <option value="">Sélectionnez un nom</option>
              <!-- Options chargées dynamiquement -->
            </select>
            <div class="invalid-feedback">
              Veuillez sélectionner un utilisateur avant de continuer.
            </div>
          </div>

          <div class="upload-form-group">
            <label for="csvFile">
              <i class="fas fa-file-csv"></i> Sélectionner le fichier CSV
            </label>
            <div class="file-input" id="fileDropZone">
              <input type="file" id="csvFile" name="csvFile" accept=".csv" style="display: none;">
              <span class="file-input-text" id="fileInputText">
                📁 Cliquez ici ou glissez-déposez votre fichier CSV<br>
                <small>Format attendu: LearningSphere (.csv avec séparateur point-virgule)</small>
              </span>
            </div>
          </div>

          <div class="upload-form-group">
            <div class="alert alert-info" style="margin: 0; padding: 12px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px; color: #0c5460;">
              <i class="fas fa-info-circle"></i> 
              <strong>Mode d'importation :</strong> Les nouvelles données remplaceront automatiquement toutes les données existantes dans la table.
            </div>
            <input type="hidden" id="replaceData" name="replaceData" value="replace">
          </div>

          <div class="progress-container" id="progressContainer">
            <div class="progress-bar-upload">
              <div class="progress-fill" id="progressFill"></div>
            </div>
            <span class="progress-text" id="progressText">0%</span>
          </div>

          <button type="submit" class="btn-submit" id="submitBtn" disabled>
            <i class="fas fa-rocket"></i> Importer le fichier
          </button>

          <div id="uploadResults" class="upload-results">
            <div class="result-icon" id="resultIcon">✅</div>
            <h4 id="resultsTitle">Import réussi !</h4>
            <div id="resultsMessage">Les données ont été importées avec succès dans la table elly_csv.</div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Notification -->
  <div id="notification" class="notification"></div>
  
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js"></script>
  <script src="script.js"></script>

  <script>
    // Variables globales pour l'upload
    let selectedFile = null;
    let uploadInProgress = false;
    let usersData = null;

    // Fonction pour charger les utilisateurs et infos du dernier upload - VERSION CORRIGÉE
    async function loadUsersAndLastUpload() {
        try {
            console.log('Chargement des utilisateurs et dernier upload...');
            
            const response = await fetch('get_users.php');
            
            if (!response.ok) {
                throw new Error('Erreur HTTP: ' + response.status);
            }
            
            const data = await response.json();
            console.log('Réponse get_users.php:', data);
            
            if (data.success) {
                // Charger les utilisateurs
                if (data.users && data.users.length > 0) {
                    usersData = data.users;
                    populateUserDropdown(data.users);
                    console.log('Utilisateurs chargés:', data.users.length);
                } else {
                    console.error('Aucun utilisateur trouvé');
                    showNotification('Aucun utilisateur trouvé dans la base de données', 'warning');
                }
                
                // Afficher les infos du dernier upload (peut être null)
                displayLastUploadInfo(data.last_upload);
                
            } else {
                console.error('Erreur API:', data.error);
                showNotification('Erreur lors du chargement: ' + data.error, 'error');
                
                // Afficher un message d'erreur dans la section upload
                const lastUploadContent = document.getElementById('lastUploadContent');
                lastUploadContent.innerHTML = '<div class="no-upload-info" style="color: red;">' +
                    '<i class="fas fa-exclamation-triangle"></i>' +
                    'Erreur de connexion à la base de données' +
                    '</div>';
            }
        } catch (error) {
            console.error('Erreur réseau dans loadUsersAndLastUpload:', error);
            showNotification('Erreur de connexion au serveur', 'error');
            
            // Afficher un message d'erreur
            const lastUploadContent = document.getElementById('lastUploadContent');
            lastUploadContent.innerHTML = '<div class="no-upload-info" style="color: red;">' +
                '<i class="fas fa-exclamation-triangle"></i>' +
                'Impossible de se connecter au serveur' +
                '</div>';
        }
    }

    // Peupler le dropdown des utilisateurs
    function populateUserDropdown(users) {
        const userSelect = document.getElementById('userSelect');
        
        // Vider les options existantes (sauf la première)
        userSelect.innerHTML = '<option value="">Sélectionnez un nom</option>';
        
        // Ajouter les utilisateurs
        users.forEach(user => {
            const option = document.createElement('option');
            option.value = user.ID;
            option.textContent = user.name;
            userSelect.appendChild(option);
        });
    }

    // Afficher les infos du dernier upload - VERSION CORRIGÉE
    function displayLastUploadInfo(lastUpload) {
        const lastUploadContent = document.getElementById('lastUploadContent');
        
        // Vérifier si lastUpload existe ET a des données valides
        if (lastUpload && lastUpload.name && lastUpload.upload_date) {
            const uploadDate = new Date(lastUpload.upload_date + ' ' + lastUpload.upload_time);
            const formattedDate = uploadDate.toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
            const formattedTime = lastUpload.upload_time;
            
            lastUploadContent.innerHTML = '<div class="last-upload-item">' +
                '<i class="fas fa-user"></i>' +
                '<strong>Utilisateur:</strong> ' + lastUpload.name +
                '</div>' +
                '<div class="last-upload-item">' +
                '<i class="fas fa-calendar"></i>' +
                '<strong>Date:</strong> ' + formattedDate +
                '</div>' +
                '<div class="last-upload-item">' +
                '<i class="fas fa-clock"></i>' +
                '<strong>Heure:</strong> ' + formattedTime +
                '</div>';
        } else {
            // Aucun upload précédent trouvé
            lastUploadContent.innerHTML = '<div class="no-upload-info">' +
                '<i class="fas fa-info-circle"></i>' +
                'Aucun upload précédent trouvé dans la base de données' +
                '</div>';
        }
    }

    // Fonction pour ouvrir la lightbox (MODIFIÉE)
    function openUploadModal() {
        document.getElementById('uploadOverlay').classList.add('show');
        document.body.style.overflow = 'hidden';
        
        // Charger les utilisateurs et infos à l'ouverture
        loadUsersAndLastUpload();
    }

    function closeUploadModal() {
        if (uploadInProgress && !confirm('Import en cours. Voulez-vous vraiment fermer ?')) {
            return;
        }
        
        document.getElementById('uploadOverlay').classList.remove('show');
        document.body.style.overflow = '';
        
        setTimeout(() => resetUploadForm(), 300);
    }

    // Validation du formulaire (NOUVELLE FONCTION)
    function validateUploadForm() {
        const userSelect = document.getElementById('userSelect');
        const csvFile = document.getElementById('csvFile');
        const submitBtn = document.getElementById('submitBtn');
        
        const hasUser = userSelect.value !== '';
        const hasFile = csvFile.files.length > 0;
        
        // Activer/désactiver le bouton
        submitBtn.disabled = !(hasUser && hasFile);
        
        // Gestion visuelle de la validation
        if (userSelect.value === '') {
            userSelect.classList.add('is-invalid');
        } else {
            userSelect.classList.remove('is-invalid');
        }
        
        return hasUser && hasFile;
    }

    // Gestion de la sélection utilisateur (NOUVELLE FONCTION)
    function setupUserSelect() {
        const userSelect = document.getElementById('userSelect');
        userSelect.addEventListener('change', validateUploadForm);
    }

    // Gestion du fichier
    function setupFileInput() {
        const fileInput = document.getElementById('csvFile');
        const dropZone = document.getElementById('fileDropZone');

        dropZone.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', handleFileSelect);

        // Drag & drop
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                handleFileSelect();
            }
        });
    }

    function handleFileSelect() {
        const fileInput = document.getElementById('csvFile');
        const dropZone = document.getElementById('fileDropZone');
        const fileInputText = document.getElementById('fileInputText');
        
        if (fileInput.files.length === 0) return;
        
        selectedFile = fileInput.files[0];
        
        if (!selectedFile.name.toLowerCase().endsWith('.csv')) {
            showNotification('Veuillez sélectionner un fichier CSV', 'error');
            resetFileInput();
            return;
        }
        
        if (selectedFile.size > 50 * 1024 * 1024) {
            showNotification('Fichier trop volumineux (max 50MB)', 'error');
            resetFileInput();
            return;
        }
        
        dropZone.classList.add('file-selected');
        fileInputText.innerHTML = '✅ <strong>' + selectedFile.name + '</strong><br>' +
            '<small>Taille: ' + formatFileSize(selectedFile.size) + '</small>';
        
        // Valider le formulaire complet
        validateUploadForm();
    }

    function resetFileInput() {
        selectedFile = null;
        document.getElementById('csvFile').value = '';
        document.getElementById('fileDropZone').classList.remove('file-selected');
        document.getElementById('fileInputText').innerHTML = 
            '📁 Cliquez ici ou glissez-déposez votre fichier CSV<br>' +
            '<small>Format attendu: LearningSphere (.csv avec séparateur point-virgule)</small>';
        validateUploadForm();
    }

    // Gestion du formulaire
    function setupUploadForm() {
        document.getElementById('uploadForm').addEventListener('submit', handleFormSubmit);
    }

    async function handleFormSubmit(e) {
        e.preventDefault();
        
        if (!validateUploadForm() || uploadInProgress) {
            return;
        }

        uploadInProgress = true;
        const submitBtn = document.getElementById('submitBtn');
        const progressContainer = document.getElementById('progressContainer');
        const resultsDiv = document.getElementById('uploadResults');
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Import en cours...';
        progressContainer.style.display = 'block';
        resultsDiv.style.display = 'none';
        
        try {
            const formData = new FormData();
            formData.append('csvFile', selectedFile);
            formData.append('userSelect', document.getElementById('userSelect').value);
            formData.append('replaceData', document.getElementById('replaceData').value);
            
            updateProgress(20, 'Envoi du fichier...');
            
            const response = await fetch('upload_csv.php', {
                method: 'POST',
                body: formData
            });
            
            updateProgress(80, 'Traitement...');
            
            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status}`);
            }
            
            const result = await response.json();
            updateProgress(100, 'Terminé !');
            
            setTimeout(() => {
                displayResults(result);
                uploadInProgress = false;
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-rocket"></i> Importer le fichier';
                
                // Recharger les infos du dernier upload après succès
                if (result.success) {
                    setTimeout(() => loadUsersAndLastUpload(), 1000);
                }
            }, 500);
            
        } catch (error) {
            console.error('Erreur upload:', error);
            displayResults({
                success: false,
                message: 'Erreur: ' + error.message
            });
            
            uploadInProgress = false;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-rocket"></i> Importer le fichier';
        }
    }

    function updateProgress(percentage, message) {
        document.getElementById('progressFill').style.width = percentage + '%';
        document.getElementById('progressText').textContent = `${percentage}% - ${message}`;
    }

    function displayResults(result) {
        const resultsDiv = document.getElementById('uploadResults');
        const resultIcon = document.getElementById('resultIcon');
        const titleEl = document.getElementById('resultsTitle');
        const messageEl = document.getElementById('resultsMessage');
        
        resultsDiv.style.display = 'block';
        
        if (result.success) {
            resultsDiv.className = 'upload-results success';
            resultIcon.textContent = '✅';
            titleEl.textContent = 'Import réussi !';
            
            let message = 'Les données ont été importées avec succès dans la table elly_csv.';
            if (result.stats) {
                message += '<br><br><strong>' + result.stats.successRows + '</strong> lignes importées';
                if (result.stats.errorRows > 0) {
                    message += ' (' + result.stats.errorRows + ' erreurs)';
                }
            }
            messageEl.innerHTML = message;
            
            showNotification('✅ Import réussi !', 'success');
            
            // Fermer automatiquement après 3 secondes
            setTimeout(() => {
                if (confirm('Import réussi ! Fermer cette fenêtre ?')) {
                    closeUploadModal();
                }
            }, 3000);
            
        } else {
            resultsDiv.className = 'upload-results error';
            resultIcon.textContent = '❌';
            titleEl.textContent = 'Erreur d\'import';
            messageEl.innerHTML = result.message || 'Une erreur est survenue lors de l\'importation.';
            
            showNotification('❌ Erreur d\'import', 'error');
        }
    }

    function resetUploadForm() {
        resetFileInput();
        document.getElementById('userSelect').value = '';
        document.getElementById('userSelect').classList.remove('is-invalid');
        document.getElementById('progressContainer').style.display = 'none';
        document.getElementById('uploadResults').style.display = 'none';
        validateUploadForm();
    }

    // Utilitaires
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function showNotification(message, type = 'success') {
        const notification = document.getElementById('notification');
        notification.textContent = message;
        notification.className = `notification ${type}`;
        notification.classList.add('show');
        
        setTimeout(() => notification.classList.remove('show'), 4000);
    }

    // Event listeners pour l'upload
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const uploadOverlay = document.getElementById('uploadOverlay');
            if (uploadOverlay.classList.contains('show')) {
                closeUploadModal();
            }
        }
    });

    document.getElementById('uploadOverlay').addEventListener('click', (e) => {
        if (e.target === e.currentTarget) {
            closeUploadModal();
        }
    });

    // Initialisation de l'upload au chargement
    document.addEventListener('DOMContentLoaded', () => {
        setupFileInput();
        setupUploadForm();
        setupUserSelect();
        console.log('Dashboard avec Upload CSV et suivi utilisateur initialisé');
    });
  </script>
</body>
</html>