// public/assets/js/ia.js
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Script IA chargé');
    
    const iaButtons = document.querySelectorAll('.conseil-ia-btn, .btn-ia-analyse');
    
    iaButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const lastClick = localStorage.getItem('lastIaClick');
            const now = Date.now();
            
            if (lastClick && (now - parseInt(lastClick)) < 30000) { // 30 secondes
                e.preventDefault();
                alert('⏳ Attends 30 secondes entre chaque demande à l\'IA !');
                return false;
            }
            
            localStorage.setItem('lastIaClick', now.toString());
            
            // Désactiver le bouton temporairement
            button.classList.add('disabled');
            button.innerHTML = '⏳ Génération en cours...';
        });
    });
});