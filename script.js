/**
 * script.js - Dashboard Course Check - VERSION CORRIGÉE avec colonnes de gestion
 * Gestion des interactions et filtres du tableau
 */

$(document).ready(function() {
    console.log('Script JS chargé avec colonnes de gestion');
    
    // Variables globales
    let table;
    let activeFilters = {};
    
    // Attendre un peu avant d'initialiser DataTables
    setTimeout(function() {
        try {
            // Initialiser DataTables avec gestion d'erreur
            table = $('#coursesTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json"
                },
                "pageLength": 50,
                "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "Tous"]],
                "order": [[1, 'asc']],
                "autoWidth": false,
                "destroy": true,
                "deferRender": true,
                "columnDefs": [
                    { "orderable": false, "targets": [11, 16, 17, 18, 19, 20] },
                    { "className": "text-center", "targets": [0, 4, 5, 6, 7, 8, 10, 12, 13, 15, 16, 18, 19, 20] },
                    { "visible": false, "targets": [14] }
                ],
                "drawCallback": function() {
                    applyRowColoring();
                    updateActiveFiltersDisplay();
                }
            });
            
            console.log('DataTables initialisé avec succès');
            
        } catch (error) {
            console.error('Erreur DataTables:', error);
            // Continuer sans DataTables si nécessaire
        }
        
        // Initialiser Flatpickr
        flatpickr("#choixDate", {
            dateFormat: "d-m-Y",
            allowInput: true
        });

        // Configuration
        setupTooltips();
        setupLegend();
        setupFilters();
        setupSearch();
        setupResetFilters();
        applyRowColoring();
        
    }, 500); // Attendre 500ms 

    function setupTooltips() {
        let tooltip = $('.custom-tooltip');
        
        $('.info-icon').hover(
            function() {
                let tooltipContent = $(this).find('.custom-tooltip').html();
                tooltip.html(tooltipContent).show();
            },
            function() {
                tooltip.hide();
            }
        );
    }

    function setupLegend() {
        $('#showLegend').click(function() {
            $('#legendOverlay').css('display', 'flex');
        });
        
        $('#closeLegend, #legendOverlay').click(function(e) {
            if (e.target === this) {
                $('#legendOverlay').hide();
            }
        });
    }

    function setupFilters() {
        $('#choixDate').on('change', function() {
            let selectedDate = $(this).val();
            filterByDate(selectedDate);
        });

        $('#showOnlyWithEndDate').on('change', function() {
            filterByEndDate($(this).is(':checked'));
        });

        $('#startInDays').on('input', function() {
            let value = parseInt($(this).val());
            
            if (isNaN(value) || value < 0) {
                $(this).val(0);
                value = 0;
            } else if (value > 100) {
                $(this).val(100);
                value = 100;
            }
            
            filterByStartDays(value);
        });

        $('#categoryFilter').on('change', function() {
            filterByCategory($(this).val());
        });

        $('.yesno-filter').on('change', function() {
            filterYesNo();
        });

        $('.teams-filter').on('change', function() {
            filterTeams();
        });

        $('.activity-filter').on('change', function() {
            filterActivities();
        });

        $('.format-filter').on('change', function() {
            filterFormats();
        });

        $('.lorem-filter').on('change', function() {
            filterLorem();
        });

        $('.description-filter').on('change', function() {
            console.log('Filtre description changé');
            filterDescription();
        });

        $('.concepteur-filter').on('change', function() {
            console.log('Filtre concepteur changé');
            filterConcepteur();
        });

        $('.gestionnaire-filter').on('change', function() {
            console.log('Filtre gestionnaire changé');
            filterGestionnaire();
        });
    }

    function setupSearch() {
        $('#tableSearch, #searchButton').on('keyup click', function(e) {
            if (e.type === 'click' || e.which === 13) {
                let searchTerm = $('#tableSearch').val();
                filterBySearch(searchTerm);
            }
        });
    }

    function setupResetFilters() {
        $('#resetFilters').click(function() {
            console.log('Reset filters clicked');
            
            $('#tableSearch').val('');
            $('#choixDate').val('');
            $('#startInDays').val('0');
            $('#categoryFilter').val('');
            $('#showOnlyWithEndDate').prop('checked', false);
            $('.filter-checkbox').prop('checked', false);
            
            activeFilters = {};
            $.fn.dataTable.ext.search = [];
            table.search('').columns().search('').draw();
            applyRowColoring();
            updateActiveFiltersDisplay();
            
            console.log('Tous les filtres réinitialisés');
        });
    }

    function filterByDate(date) {
        if (date) {
            activeFilters.date = date;
            
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                return fn.toString().indexOf('filterByDateCustom') === -1;
            });
            
            $.fn.dataTable.ext.search.push(function filterByDateCustom(settings, data, dataIndex) {
                if (settings.nTable.id !== 'coursesTable') return true;
                
                let courseStartDateStr = data[4];
                
                if (!courseStartDateStr || courseStartDateStr === 'Non defini') {
                    return false;
                }
                
                let selectedDate = parseDate(date);
                let courseStartDate = parseDate(courseStartDateStr);
                
                if (!selectedDate || !courseStartDate) {
                    return false;
                }
                
                return courseStartDate >= selectedDate;
            });
            
            table.draw();
            console.log('Filtre date appliqué: cours >= ' + date);
        } else {
            delete activeFilters.date;
            
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                return fn.toString().indexOf('filterByDateCustom') === -1;
            });
            
            table.draw();
            console.log('Filtre date supprimé');
        }
    }

    function filterByEndDate(checked) {
        if (checked) {
            activeFilters.endDateOnly = 'Avec date de fin';
            table.column(5).search('^(?!Non defini)', true, false).draw();
        } else {
            delete activeFilters.endDateOnly;
            table.column(5).search('').draw();
        }
    }

    function filterByStartDays(days) {
        if (days && days > 0) {
            activeFilters.startInDays = 'Dans ' + days + ' jour(s)';
            
            let today = new Date();
            let targetDate = new Date(today.getTime() + (days * 24 * 60 * 60 * 1000));
            let targetDateStr = formatDate(targetDate);
            
            filterByDate(targetDateStr);
            
            console.log('Filtre dans X jours appliqué: ' + targetDateStr);
        } else {
            delete activeFilters.startInDays;
            
            if (!activeFilters.date) {
                $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                    return fn.toString().indexOf('filterByDateCustom') === -1;
                });
                table.draw();
            }
            
            console.log('Filtre dans X jours supprimé');
        }
    }

    function filterByCategory(category) {
        if (category) {
            activeFilters.category = category;
            
            $.ajax({
                url: 'get_courses_by_category.php',
                method: 'POST',
                data: { category_name: category },
                dataType: 'json',
                success: function(response) {
                    console.log('Réponse AJAX:', response);
                    
                    removeCategoryFilter();
                    
                    if (response.success && response.course_ids && response.course_ids.length > 0) {
                        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                            if (settings.nTable.id !== 'coursesTable') return true;
                            
                            let courseId = parseInt(data[0]);
                            return response.course_ids.includes(courseId);
                        });
                        
                        console.log('Filtre catégorie appliqué: ' + response.count + ' cours trouvés pour ' + category);
                    } else {
                        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                            if (settings.nTable.id !== 'coursesTable') return true;
                            return false;
                        });
                        
                        console.log('Aucun cours trouvé pour la catégorie ' + category);
                    }
                    table.draw();
                },
                error: function(xhr, status, error) {
                    console.log('Erreur AJAX, utilisation du filtre simple:', error);
                    
                    removeCategoryFilter();
                    table.column(3).search('^' + escapeRegex(category) + '$', true, false).draw();
                    console.log('Filtre simple appliqué pour:', category);
                }
            });
        } else {
            delete activeFilters.category;
            removeCategoryFilter();
            table.draw();
            console.log('Filtre catégorie supprimé');
        }
    }
    
    function removeCategoryFilter() {
        $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
            let fnStr = fn.toString();
            return !fnStr.includes('course_ids.includes') && !fnStr.includes('courseId');
        });
        table.column(3).search('');
    }

    function filterBySearch(searchTerm) {
        if (searchTerm) {
            activeFilters.search = searchTerm;
            table.search(searchTerm).draw();
        } else {
            delete activeFilters.search;
            table.search('').draw();
        }
    }

    function filterYesNo() {
        $('.yesno-filter').each(function() {
            let column = $(this).data('column');
            let value = $(this).val();
            let isChecked = $(this).is(':checked');
            let filterKey = 'yesno_' + column + '_' + value;
            
            if (isChecked) {
                if (column == 6) {
                    activeFilters[filterKey] = 'Suivi Achèvement : ' + value;
                } else if (column == 7) {
                    activeFilters[filterKey] = 'Achèvement de cours : ' + value;
                } else {
                    activeFilters[filterKey] = value;
                }
            } else {
                delete activeFilters[filterKey];
            }
        });
        
        [6, 7].forEach(function(column) {
            let checkedValues = [];
            $('.yesno-filter[data-column="' + column + '"]:checked').each(function() {
                checkedValues.push(escapeRegex($(this).val()));
            });
            
            if (checkedValues.length > 0) {
                table.column(column).search('^(' + checkedValues.join('|') + ')$', true, false).draw();
            } else {
                table.column(column).search('').draw();
            }
        });
    }

    function filterTeams() {
        let checkedValues = [];
        $('.teams-filter:checked').each(function() {
            let value = $(this).val();
            checkedValues.push(value);
            
            if (value === 'element_lien_ok') {
                activeFilters['teams_element_lien_ok'] = 'Teams : Element + lien OK';
            } else if (value === 'element_lien_absent') {
                activeFilters['teams_element_lien_absent'] = 'Teams : Element mais lien absent';
            } else if (value === 'aucun_element') {
                activeFilters['teams_aucun_element'] = 'Teams : Aucun element';
            }
        });
        
        $('.teams-filter:not(:checked)').each(function() {
            let value = $(this).val();
            if (value === 'element_lien_ok') {
                delete activeFilters['teams_element_lien_ok'];
            } else if (value === 'element_lien_absent') {
                delete activeFilters['teams_element_lien_absent'];
            } else if (value === 'aucun_element') {
                delete activeFilters['teams_aucun_element'];
            }
        });
        
        if (checkedValues.length > 0) {
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                return fn.toString().indexOf('filterTeamsCustom') === -1;
            });
            
            $.fn.dataTable.ext.search.push(function filterTeamsCustom(settings, data, dataIndex) {
                if (settings.nTable.id !== 'coursesTable') return true;
                
                let teamsContent = data[12];
                
                return checkedValues.some(function(value) {
                    if (value === 'element_lien_ok') {
                        return teamsContent.includes('Oui') && teamsContent.includes('[Tester]');
                    } else if (value === 'element_lien_absent') {
                        return teamsContent.includes('Non') && !teamsContent.includes('[Tester]');
                    } else if (value === 'aucun_element') {
                        return teamsContent === 'no teams element';
                    }
                    return false;
                });
            });
            
            table.draw();
            console.log('Filtre Teams appliqué:', checkedValues);
        } else {
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                return fn.toString().indexOf('filterTeamsCustom') === -1;
            });
            table.draw();
            console.log('Filtre Teams supprimé');
        }
    }

    function filterActivities() {
        let checkedValues = [];
        $('.activity-filter:checked').each(function() {
            let value = $(this).val();
            checkedValues.push(value);
            activeFilters['activities_' + value] = value;
        });
        
        $('.activity-filter:not(:checked)').each(function() {
            let value = $(this).val();
            delete activeFilters['activities_' + value];
        });
        
        if (checkedValues.length > 0) {
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                return fn.toString().indexOf('data-type') === -1;
            });
            
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                if (settings.nTable.id !== 'coursesTable') return true;
                
                let activityText = data[11];
                
                return checkedValues.some(function(value) {
                    if (value === 'Neant') {
                        return activityText === 'Neant';
                    } else {
                        return activityText.includes('data-type="' + value + '"');
                    }
                });
            });
            table.draw();
        } else {
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                return fn.toString().indexOf('data-type') === -1;
            });
            table.draw();
        }
    }

    function filterFormats() {
        let checkedValues = [];
        $('.format-filter:checked').each(function() {
            let value = $(this).val();
            checkedValues.push(escapeRegex(value));
            activeFilters['formats_' + value] = value;
        });
        
        $('.format-filter:not(:checked)').each(function() {
            let value = $(this).val();
            delete activeFilters['formats_' + value];
        });
        
        if (checkedValues.length > 0) {
            table.column(13).search('^(' + checkedValues.join('|') + ')$', true, false).draw();
        } else {
            table.column(13).search('').draw();
        }
    }

    function filterLorem() {
        let isChecked = $('.lorem-filter:checked').length > 0;
        
        if (isChecked) {
            activeFilters.lorem = 'Lorem ipsum : Oui';
            
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                return fn.toString().indexOf('Lorem ipsum') === -1;
            });
            
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                if (settings.nTable.id !== 'coursesTable') return true;
                
                let loremText = data[15];
                return loremText !== '0' && loremText.trim() !== '';
            });
            table.draw();
        } else {
            delete activeFilters.lorem;
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                return fn.toString().indexOf('Lorem ipsum') === -1;
            });
            table.draw();
        }
    }

    function filterDescription() {
        let checkedValues = [];
        $('.description-filter:checked').each(function() {
            let value = $(this).val();
            checkedValues.push(value);
            
            if (value === 'element_lien_ok') {
                activeFilters['description_element_lien_ok'] = 'Descriptif et sessions : Element + lien OK';
            } else if (value === 'element_lien_absent') {
                activeFilters['description_element_lien_absent'] = 'Descriptif et sessions : Element mais lien absent';
            } else if (value === 'aucun_element') {
                activeFilters['description_aucun_element'] = 'Descriptif et sessions : Aucun element';
            }
        });
        
        $('.description-filter:not(:checked)').each(function() {
            let value = $(this).val();
            if (value === 'element_lien_ok') {
                delete activeFilters['description_element_lien_ok'];
            } else if (value === 'element_lien_absent') {
                delete activeFilters['description_element_lien_absent'];
            } else if (value === 'aucun_element') {
                delete activeFilters['description_aucun_element'];
            }
        });
        
        if (checkedValues.length > 0) {
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                return fn.toString().indexOf('filterDescriptionCustom') === -1;
            });
            
            $.fn.dataTable.ext.search.push(function filterDescriptionCustom(settings, data, dataIndex) {
                if (settings.nTable.id !== 'coursesTable') return true;
                
                let descriptionContent = data[16];
                
                return checkedValues.some(function(value) {
                    if (value === 'element_lien_ok') {
                        return descriptionContent.includes('color: green') && descriptionContent.includes('Oui');
                    } else if (value === 'element_lien_absent') {
                        return descriptionContent.includes('element present sans hyperlien') || 
                               (descriptionContent.includes('color: red') || descriptionContent.includes('color: orange'));
                    } else if (value === 'aucun_element') {
                        return descriptionContent === 'aucun element';
                    }
                    return false;
                });
            });
            
            table.draw();
            console.log('Filtre Description appliqué:', checkedValues);
        } else {
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                return fn.toString().indexOf('filterDescriptionCustom') === -1;
            });
            table.draw();
            console.log('Filtre Description supprimé');
        }
        
        setTimeout(function() {
            applyRowColoring();
        }, 100);
    }

    function filterConcepteur() {
        let checkedValues = [];
        $('.concepteur-filter:checked').each(function() {
            let value = $(this).val();
            checkedValues.push(value);
            
            if (value === 'found') {
                activeFilters['concepteur_found'] = 'Concepteur : Trouvé';
            } else if (value === 'missing') {
                activeFilters['concepteur_missing'] = 'Concepteur : Non trouvé';
            }
        });
        
        $('.concepteur-filter:not(:checked)').each(function() {
            let value = $(this).val();
            if (value === 'found') {
                delete activeFilters['concepteur_found'];
            } else if (value === 'missing') {
                delete activeFilters['concepteur_missing'];
            }
        });
        
        if (checkedValues.length > 0) {
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                return fn.toString().indexOf('filterConcepteurCustom') === -1;
            });
            
            $.fn.dataTable.ext.search.push(function filterConcepteurCustom(settings, data, dataIndex) {
                if (settings.nTable.id !== 'coursesTable') return true;
                
                let concepteurContent = data[19];
                
                return checkedValues.some(function(value) {
                    if (value === 'found') {
                        return concepteurContent.includes('management-status-found');
                    } else if (value === 'missing') {
                        return concepteurContent.includes('management-status-missing') || 
                               concepteurContent.includes('management-status-error');
                    }
                    return false;
                });
            });
            
            table.draw();
            console.log('Filtre Concepteur appliqué:', checkedValues);
        } else {
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                return fn.toString().indexOf('filterConcepteurCustom') === -1;
            });
            table.draw();
            console.log('Filtre Concepteur supprimé');
        }
    }

    function filterGestionnaire() {
        let checkedValues = [];
        $('.gestionnaire-filter:checked').each(function() {
            let value = $(this).val();
            checkedValues.push(value);
            
            if (value === 'found') {
                activeFilters['gestionnaire_found'] = 'Gestionnaire : Trouvé';
            } else if (value === 'missing') {
                activeFilters['gestionnaire_missing'] = 'Gestionnaire : Non trouvé';
            }
        });
        
        $('.gestionnaire-filter:not(:checked)').each(function() {
            let value = $(this).val();
            if (value === 'found') {
                delete activeFilters['gestionnaire_found'];
            } else if (value === 'missing') {
                delete activeFilters['gestionnaire_missing'];
            }
        });
        
        if (checkedValues.length > 0) {
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                return fn.toString().indexOf('filterGestionnaireCustom') === -1;
            });
            
            $.fn.dataTable.ext.search.push(function filterGestionnaireCustom(settings, data, dataIndex) {
                if (settings.nTable.id !== 'coursesTable') return true;
                
                let gestionnaireContent = data[20];
                
                return checkedValues.some(function(value) {
                    if (value === 'found') {
                        return gestionnaireContent.includes('management-status-found');
                    } else if (value === 'missing') {
                        return gestionnaireContent.includes('management-status-missing') || 
                               gestionnaireContent.includes('management-status-error');
                    }
                    return false;
                });
            });
            
            table.draw();
            console.log('Filtre Gestionnaire appliqué:', checkedValues);
        } else {
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                return fn.toString().indexOf('filterGestionnaireCustom') === -1;
            });
            table.draw();
            console.log('Filtre Gestionnaire supprimé');
        }
    }

    function applyRowColoring() {
        let today = new Date();
        let threeDaysFromNow = new Date(today.getTime() + (3 * 24 * 60 * 60 * 1000));
        
        $('#coursesTable tbody tr').each(function() {
            let $row = $(this);
            let $cells = $row.find('td');
            
            $row.removeClass('row-yellow row-green row-gray');
            
            let editingTeachers = parseInt($cells.eq(8).text()) || 0;
            let emails = $cells.eq(9).text().trim();
            let participants = parseInt($cells.eq(10).text()) || 0;
            let teamsElement = $cells.eq(12).html();
            let loremIpsum = $cells.eq(15).text();
            let description = $cells.eq(16).text();
            let startDateStr = $cells.eq(4).text();
            
            let hasIssues = (
                editingTeachers === 0 ||
                emails === '' ||
                participants === 0 ||
                (teamsElement && teamsElement.includes('color: red') && teamsElement.includes('Non')) ||
                (loremIpsum !== '0' && loremIpsum.trim() !== '') ||
                (description === 'aucun element' || description.includes('element present sans hyperlien'))
            );
            
            if (hasIssues) {
                let startDate = parseDate(startDateStr);
                if (startDate && startDate <= threeDaysFromNow && startDate >= today) {
                    $row.addClass('row-gray');
                } else {
                    $row.addClass('row-yellow');
                }
            }
        });
    }

    function updateActiveFiltersDisplay() {
        let container = $('.active-filters-container');
        
        if (Object.keys(activeFilters).length === 0) {
            container.hide();
            return;
        }
        
        if (container.length === 0) {
            container = $('<div class="active-filters-container"><div class="active-filters-header"><i class="fas fa-filter"></i>Filtres actifs :</div><div class="active-filters-list"></div></div>');
            $('.hero-title-container').after(container);
        }
        
        let list = container.find('.active-filters-list');
        list.empty();
        
        Object.keys(activeFilters).forEach(function(key) {
            let value = activeFilters[key];
            let type = key.split('_')[0];
            let tag = $('<span class="filter-tag" data-filter="' + type + '">' +
                '<i class="fas fa-tag"></i>' +
                value +
                '<i class="fas fa-times remove-filter" data-filter-key="' + key + '"></i>' +
                '</span>');
            list.append(tag);
        });
        
        container.show();
        
        $('.remove-filter').click(function() {
            let filterKey = $(this).data('filter-key');
            removeFilter(filterKey);
        });
    }

    function removeFilter(filterKey) {
        console.log('Removing filter:', filterKey);
        delete activeFilters[filterKey];
        
        if (filterKey === 'search') {
            $('#tableSearch').val('');
            table.search('').draw();
        } else if (filterKey === 'date') {
            $('#choixDate').val('');
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                return fn.toString().indexOf('filterByDateCustom') === -1;
            });
            table.draw();
        } else if (filterKey === 'endDateOnly') {
            $('#showOnlyWithEndDate').prop('checked', false);
            table.column(5).search('').draw();
        } else if (filterKey === 'startInDays') {
            $('#startInDays').val('0');
            if (!activeFilters.date) {
                $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                    return fn.toString().indexOf('filterByDateCustom') === -1;
                });
            }
            table.draw();
        } else if (filterKey === 'category') {
            $('#categoryFilter').val('');
            removeCategoryFilter();
            table.draw();
        } else if (filterKey.startsWith('yesno_')) {
            let parts = filterKey.split('_');
            let column = parts[1];
            let value = parts[2];
            $('.yesno-filter[data-column="' + column + '"][value="' + value + '"]').prop('checked', false);
            filterYesNo();
        } else if (filterKey.startsWith('teams_')) {
            if (filterKey === 'teams_element_lien_ok') {
                $('.teams-filter[value="element_lien_ok"]').prop('checked', false);
            } else if (filterKey === 'teams_element_lien_absent') {
                $('.teams-filter[value="element_lien_absent"]').prop('checked', false);
            } else if (filterKey === 'teams_aucun_element') {
                $('.teams-filter[value="aucun_element"]').prop('checked', false);
            }
            filterTeams();
        } else if (filterKey.startsWith('activities_')) {
            let value = filterKey.split('_')[1];
            $('.activity-filter[value="' + value + '"]').prop('checked', false);
            filterActivities();
        } else if (filterKey.startsWith('formats_')) {
            let value = filterKey.split('_')[1];
            $('.format-filter[value="' + value + '"]').prop('checked', false);
            filterFormats();
        } else if (filterKey === 'lorem') {
            $('.lorem-filter').prop('checked', false);
            filterLorem();
        } else if (filterKey.startsWith('description_')) {
            if (filterKey === 'description_element_lien_ok') {
                $('.description-filter[value="element_lien_ok"]').prop('checked', false);
            } else if (filterKey === 'description_element_lien_absent') {
                $('.description-filter[value="element_lien_absent"]').prop('checked', false);
            } else if (filterKey === 'description_aucun_element') {
                $('.description-filter[value="aucun_element"]').prop('checked', false);
            }
            filterDescription();
        } else if (filterKey.startsWith('concepteur_')) {
            if (filterKey === 'concepteur_found') {
                $('.concepteur-filter[value="found"]').prop('checked', false);
            } else if (filterKey === 'concepteur_missing') {
                $('.concepteur-filter[value="missing"]').prop('checked', false);
            }
            filterConcepteur();
        } else if (filterKey.startsWith('gestionnaire_')) {
            if (filterKey === 'gestionnaire_found') {
                $('.gestionnaire-filter[value="found"]').prop('checked', false);
            } else if (filterKey === 'gestionnaire_missing') {
                $('.gestionnaire-filter[value="missing"]').prop('checked', false);
            }
            filterGestionnaire();
        }
        
        applyRowColoring();
        updateActiveFiltersDisplay();
    }

    function formatDate(date) {
        let day = String(date.getDate()).padStart(2, '0');
        let month = String(date.getMonth() + 1).padStart(2, '0');
        let year = date.getFullYear();
        return day + '-' + month + '-' + year;
    }

    function parseDate(dateStr) {
        if (!dateStr || dateStr === 'Non defini') return null;
        let parts = dateStr.split('-');
        if (parts.length !== 3) return null;
        return new Date(parts[2], parts[1] - 1, parts[0]);
    }

    function escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\        } else if (filterKey.startsWith('teams_')) {
            if (filterKey === 'teams_element_lien_ok')');
    }

}); // Fermeture du $(document).ready()