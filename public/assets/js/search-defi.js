// public/assets/js/search-defi.js
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="search"]');
    const resultsContainer = document.querySelector('.search-results-defi');
    const originalContent = resultsContainer ? resultsContainer.innerHTML : '';
    const sortSelect = document.querySelector('select[name="sort"]');
    const typeSelect = document.querySelector('select[name="type"]');
    const statutSelect = document.querySelector('select[name="statut"]');
    const difficulteSelect = document.querySelector('select[name="difficulte"]');
    const resultsCount = document.querySelector('.results-count-defi');
    
    if (!searchInput) return;
    
    // Sauvegarder le contenu original
    if (resultsContainer && !originalContent) {
        window.originalContent = resultsContainer.innerHTML;
    }
    
    let debounceTimer;
    let currentRequest = null;
    
    function performSearch() {
        const query = searchInput.value.trim();
        const sort = sortSelect ? sortSelect.value : 'date_fin_asc';
        const type = typeSelect ? typeSelect.value : '';
        const statut = statutSelect ? statutSelect.value : '';
        const difficulte = difficulteSelect ? difficulteSelect.value : '';
        
        // Annuler la requête précédente
        if (currentRequest) {
            currentRequest.abort();
        }
        
        // Si la recherche est vide, restaurer le contenu original
        if (query.length === 0) {
            if (resultsContainer && window.originalContent) {
                resultsContainer.innerHTML = window.originalContent;
            } else {
                window.location.reload();
            }
            if (resultsCount) resultsCount.style.display = 'none';
            return;
        }
        
        // Ne pas chercher si moins de 2 caractères
        if (query.length < 2) {
            if (resultsContainer) {
                resultsContainer.innerHTML = '<div class="text-center py-5"><i class="fas fa-search fa-3x text-muted mb-3"></i><p class="text-muted">Tapez au moins 2 caractères pour rechercher</p></div>';
            }
            if (resultsCount) {
                resultsCount.style.display = 'inline-block';
                resultsCount.textContent = '0 résultat';
            }
            return;
        }
        
        // Afficher chargement
        if (resultsContainer) {
            resultsContainer.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Recherche en cours...</p></div>';
        }
        
        // Construire l'URL
        const url = new URL('/defis/api/search', window.location.origin);
        url.searchParams.append('q', query);
        url.searchParams.append('sort', sort);
        if (type) url.searchParams.append('type', type);
        if (statut) url.searchParams.append('statut', statut);
        if (difficulte) url.searchParams.append('difficulte', difficulte);
        
        currentRequest = new XMLHttpRequest();
        currentRequest.open('GET', url.toString());
        currentRequest.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        currentRequest.onload = function() {
            if (currentRequest.status === 200) {
                const data = JSON.parse(currentRequest.responseText);
                displayResults(data);
            } else {
                if (resultsContainer) {
                    resultsContainer.innerHTML = '<div class="alert alert-danger">Erreur lors de la recherche</div>';
                }
            }
            currentRequest = null;
        };
        
        currentRequest.onerror = function() {
            if (resultsContainer) {
                resultsContainer.innerHTML = '<div class="alert alert-danger">Erreur de connexion</div>';
            }
            currentRequest = null;
        };
        
        currentRequest.send();
    }
    
    function displayResults(data) {
        if (!resultsContainer) return;
        
        if (data.length === 0) {
            resultsContainer.innerHTML = '<div class="text-center py-5"><i class="fas fa-search fa-3x text-muted mb-3"></i><p class="text-muted">Aucun défi trouvé</p></div>';
            if (resultsCount) {
                resultsCount.style.display = 'inline-block';
                resultsCount.textContent = '0 résultat';
            }
            return;
        }
        
        let html = '<div class="row g-4">';
        
        data.forEach(defi => {
            const statutClass = defi.statut === 'Terminé' ? 'bg-success' : 'bg-primary';
            const statutIcon = defi.statut === 'Terminé' ? 'fa-check-circle' : 'fa-hourglass-half';
            
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                stars += i <= defi.difficulte ? 
                    '<i class="fas fa-star text-warning"></i>' : 
                    '<i class="far fa-star text-warning"></i>';
            }
            
            html += `
                <div class="col-lg-6">
                    <div class="card border-0 shadow-lg h-100" style="border-radius: 20px; background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <span class="badge ${statutClass} bg-opacity-10 text-${defi.statut === 'Terminé' ? 'success' : 'primary'} px-3 py-2 mb-2">
                                        <i class="fas ${statutIcon} me-1"></i> ${defi.statut}
                                    </span>
                                    <h3 class="h4 fw-bold mb-1">${defi.titre}</h3>
                                    <p class="text-muted mb-0">${defi.description}</p>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm rounded-circle" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow">
                                        <li><a class="dropdown-item" href="/defis/${defi.id}"><i class="fas fa-eye text-primary me-2"></i>Voir détails</a></li>
                                        <li><a class="dropdown-item" href="/defis/${defi.id}/edit"><i class="fas fa-edit text-warning me-2"></i>Modifier</a></li>
                                    </ul>
                                </div>
                            </div>

                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary bg-opacity-10 rounded-3 p-2 me-3">
                                    <i class="fas fa-chart-line text-primary"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center">
                                        <span class="h2 mb-0 fw-bold text-primary">${defi.progression}/${defi.objectif}</span>
                                        <span class="ms-2 text-muted">${defi.unite}</span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <div class="progress" style="width: 200px; height: 8px;">
                                            <div class="progress-bar bg-primary" 
                                                 role="progressbar" 
                                                 style="width: ${defi.pourcentage}%;"
                                                 aria-valuenow="${defi.pourcentage}" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                            </div>
                                        </div>
                                        <span class="ms-3 fw-bold text-primary">${defi.pourcentage}%</span>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-calendar-alt text-muted me-2"></i>
                                    <span class="fw-bold">⏳ Termine le ${defi.date_fin}</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    ${stars}
                                    <span class="ms-2 text-muted">Difficulté</span>
                                </div>
                            </div>

                            ${defi.recompense ? `
                                <div class="mt-3 pt-2 border-top">
                                    <small class="text-warning">
                                        <i class="fas fa-gift me-1"></i>Récompense: ${defi.recompense}
                                    </small>
                                </div>
                            ` : ''}

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                                <a href="/defis/${defi.id}" class="btn btn-primary px-4">
                                    <i class="fas fa-eye me-2"></i>Voir détails
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        resultsContainer.innerHTML = html;
        
        if (resultsCount) {
            resultsCount.style.display = 'inline-block';
            resultsCount.textContent = data.length + ' défi' + (data.length > 1 ? 's' : '');
        }
    }
    
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(performSearch, 300);
    });
    
    if (sortSelect) sortSelect.addEventListener('change', performSearch);
    if (typeSelect) typeSelect.addEventListener('change', performSearch);
    if (statutSelect) statutSelect.addEventListener('change', performSearch);
    if (difficulteSelect) difficulteSelect.addEventListener('change', performSearch);
});