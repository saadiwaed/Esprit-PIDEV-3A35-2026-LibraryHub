"""
API FastAPI pour le service de recommandation
"""
from fastapi import FastAPI, HTTPException
from app.recommender import Recommender
from app.schemas import RecommendRequest, HealthResponse

# Initialisation de l'application
app = FastAPI(
    title="LibraryHub AI Recommendation Service",
    description="Service de recommandation de clubs basé sur SVD",
    version="1.0.0"
)

# Initialisation du moteur de recommandation
try:
    recommender = Recommender()
    model_loaded = True
except Exception as e:
    print(f"⚠️ ATTENTION: {e}")
    recommender = None
    model_loaded = False

@app.get("/", response_model=HealthResponse)
@app.get("/health", response_model=HealthResponse)
async def health_check():
    """Vérifie que le service fonctionne"""
    if model_loaded:
        return HealthResponse(
            status="healthy", 
            message="Service de recommandation opérationnel"
        )
    else:
        return HealthResponse(
            status="unhealthy", 
            message="Modèle non chargé - exécutez d'abord l'entraînement"
        )

@app.post("/recommend/clubs")
async def recommend_clubs(request: RecommendRequest):
    """
    Recommande des clubs à un utilisateur
    
    - **user_id**: ID de l'utilisateur
    - **all_club_ids**: Liste de tous les clubs disponibles
    - **exclude_ids**: IDs des clubs déjà rejoints (optionnel)
    - **top_n**: Nombre de recommandations (défaut: 5)
    """
    if not model_loaded:
        raise HTTPException(
            status_code=503, 
            detail="Service non disponible - modèle non chargé"
        )
    
    try:
        recommended_ids = recommender.recommend_clubs(
            user_id=request.user_id,
            all_club_ids=request.all_club_ids,
            exclude_ids=request.exclude_ids,
            top_n=request.top_n
        )
        
        return {
            "user_id": request.user_id,
            "recommended_clubs": recommended_ids,
            "count": len(recommended_ids)
        }
    
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/info")
async def get_info():
    """Informations sur le modèle"""
    if not model_loaded:
        raise HTTPException(status_code=503, detail="Modèle non chargé")
    
    return {
        "n_users": len(recommender.model_data['user_list']),
        "n_clubs": len(recommender.model_data['club_list']),
        "model_type": "SVD",
        "n_factors": 50
    }