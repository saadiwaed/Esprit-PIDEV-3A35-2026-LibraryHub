"""
Schémas Pydantic pour la validation des requêtes API
"""
from pydantic import BaseModel
from typing import List, Optional

class RecommendRequest(BaseModel):
    """Requête de recommandation de clubs"""
    user_id: int
    all_club_ids: List[int]
    exclude_ids: Optional[List[int]] = []
    top_n: Optional[int] = 5

class HealthResponse(BaseModel):
    """Réponse de l'endpoint health"""
    status: str
    message: str