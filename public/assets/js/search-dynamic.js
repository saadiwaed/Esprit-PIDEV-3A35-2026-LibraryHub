// public/assets/js/search-dynamic.js
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="search"]');
    const resultsContainer = document.querySelector('.search-results');
    const originalContent = resultsContainer ? resultsContainer.innerHTML : '';
    const sortSelect = document.querySelector('select[name="sort"]');
    const lieuSelect = document.querySelector('select[name="lieu"]');
    const noteSelect = document.querySelector('select[name="note"]');
    const resultsCount = document.querySelector('.results-count');
    
    if (!searchInput) return;
    
    // Créer un conteneur pour les résultats si nécessaire
    if (!resultsContainer) {
        const listSection = document.querySelector('.py-5 .container .row.g-4').parentNode;
        const newResultsDiv = document.createElement('div');
        newResultsDiv.className = 'search-results';
        newResultsDiv.innerHTML = listSection.innerHTML;
        listSection.parentNode.replaceChild(newResultsDiv, listSection);
    }
    
    let debounceTimer;
    let currentRequest = null;
    
    function performSearch() {
        const query = searchInput.value.trim();
        const sort = sortSelect ? sortSelect.value : 'date_desc';
        const lieu = lieuSelect ? lieuSelect.value : '';
        const note = noteSelect ? noteSelect.value : '';
        
        // Annuler la requête précédente
        if (currentRequest) {
            currentRequest.abort();
        }
        
        // Si la recherche est vide, restaurer le contenu original
        if (query.length === 0) {
            window.location.reload(); // Recharger pour revenir à la pagination
            return;
        }
        
        // Ne pas chercher si moins de 2 caractères
        if (query.length < 2) {
            document.querySelector('.search-results').innerHTML = 
                '<div class="text-center py-5"><i class="fas fa-search fa-3x text-muted mb-3"></i><p class="text-muted">Tapez au moins 2 caractères pour rechercher</p></div>';
            if (resultsCount) resultsCount.textContent = '0 résultat';
            return;
        }
        
        // Afficher chargement
        document.querySelector('.search-results').innerHTML = 
            '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Recherche en cours...</p></div>';
        
        // Construire l'URL
        const url = new URL('/journal/api/search', window.location.origin);
        url.searchParams.append('q', query);
        url.searchParams.append('sort', sort);
        if (lieu) url.searchParams.append('lieu', lieu);
        if (note) url.searchParams.append('note', note);
        
        currentRequest = new XMLHttpRequest();
        currentRequest.open('GET', url.toString());
        currentRequest.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        currentRequest.onload = function() {
            if (currentRequest.status === 200) {
                const data = JSON.parse(currentRequest.responseText);
                displayResults(data);
            } else {
                document.querySelector('.search-results').innerHTML = 
                    '<div class="alert alert-danger">Erreur lors de la recherche</div>';
            }
            currentRequest = null;
        };
        
        currentRequest.onerror = function() {
            document.querySelector('.search-results').innerHTML = 
                '<div class="alert alert-danger">Erreur de connexion</div>';
            currentRequest = null;
        };
        
        currentRequest.send();
    }
    
    function displayResults(data) {
        if (data.length === 0) {
            document.querySelector('.search-results').innerHTML = 
                '<div class="text-center py-5"><i class="fas fa-search fa-3x text-muted mb-3"></i><p class="text-muted">Aucun résultat trouvé</p></div>';
            if (resultsCount) resultsCount.textContent = '0 résultat';
            return;
        }
        
        let html = '<div class="row g-4">';
        
        data.forEach(journal => {
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                stars += i <= journal.note ? 
                    '<i class="fas fa-star text-warning"></i>' : 
                    '<i class="far fa-star text-warning"></i>';
            }
            
            html += `
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-lg h-100" style="border-radius: 20px;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2">
                                    <i class="fas fa-calendar me-1"></i>${journal.date}
                                </span>
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm rounded-circle" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow">
                                        <li><a class="dropdown-item" href="/journal/${journal.id}"><i class="fas fa-eye text-primary me-2"></i>Voir</a></li>
                                        <li><a class="dropdown-item" href="/journal/${journal.id}/edit"><i class="fas fa-edit text-warning me-2"></i>Modifier</a></li>
                                    </ul>
                                </div>
                            </div>
                            
                            <h3 class="h5 fw-bold mb-3">${journal.titre}</h3>
                            
                            <div class="d-flex justify-content-between mb-3">
                                <span class="badge bg-light text-dark p-2"><i class="fas fa-clock text-primary me-1"></i>${journal.duree} min</span>
                                <span class="badge bg-light text-dark p-2"><i class="fas fa-file-alt text-success me-1"></i>${journal.pages} pages</span>
                                <span class="badge bg-light text-dark p-2"><i class="fas fa-map-marker-alt text-danger me-1"></i>${journal.lieu || '-'}</span>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>${stars}</div>
                            </div>
                            
                            <p class="card-text text-muted small mb-3">${journal.resume}</p>
                            
                            <div class="mt-3">
                                <a href="/journal/${journal.id}" class="btn btn-outline-primary w-100">
                                    Lire la suite <i class="fas fa-arrow-right ms-2"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        document.querySelector('.search-results').innerHTML = html;
        
        if (resultsCount) {
            resultsCount.textContent = data.length + ' résultat' + (data.length > 1 ? 's' : '');
        }
    }
    
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(performSearch, 300);
    });
    
    if (sortSelect) sortSelect.addEventListener('change', performSearch);
    if (lieuSelect) lieuSelect.addEventListener('change', performSearch);
    if (noteSelect) noteSelect.addEventListener('change', performSearch);
});