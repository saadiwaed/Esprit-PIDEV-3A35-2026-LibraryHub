"""
Moteur de recommandation - Chargement du modèle et prédictions
"""
import joblib
import os
import numpy as np

MODEL_PATH = "models/svd_model.pkl"

class Recommender:
    def __init__(self):
        """Charge le modèle au démarrage"""
        if not os.path.exists(MODEL_PATH):
            raise FileNotFoundError(f"Modèle non trouvé: {MODEL_PATH}")
        
        print("📂 Chargement du modèle...")
        self.model_data = joblib.load(MODEL_PATH)
        print(f"   ✅ Modèle chargé: {len(self.model_data['user_list'])} utilisateurs, {len(self.model_data['club_list'])} clubs")
    
    def predict_rating(self, user_id: int, club_id: int) -> float:
        """Prédit la note qu'un utilisateur donnerait à un club"""
        # Gestion des nouveaux utilisateurs/clubs (cold start)
        if user_id not in self.model_data['user_index']:
            return 3.0  # Note moyenne par défaut
        
        if club_id not in self.model_data['club_index']:
            return 3.0  # Note moyenne par défaut
        
        u_idx = self.model_data['user_index'][user_id]
        c_idx = self.model_data['club_index'][club_id]
        return float(self.model_data['reconstructed'][u_idx, c_idx])
    
    def recommend_clubs(self, user_id: int, all_club_ids: list, exclude_ids: list = None, top_n: int = 5):
        """
        Retourne les top_n clubs recommandés pour un utilisateur
        
        Args:
            user_id: ID de l'utilisateur
            all_club_ids: Liste de tous les clubs disponibles
            exclude_ids: Clubs à exclure (déjà rejoints)
            top_n: Nombre de recommandations à retourner
        
        Returns:
            Liste des IDs des clubs recommandés
        """
        if exclude_ids is None:
            exclude_ids = []
        
        # Cas 1: Nouvel utilisateur (cold start) - retourner clubs populaires
        if user_id not in self.model_data['user_index']:
            print(f"   ℹ️ Nouvel utilisateur {user_id} - recommandation basée sur la popularité")
            
            # Calculer la popularité de chaque club
            club_popularity = {}
            for club_id in all_club_ids:
                if club_id in self.model_data['club_index']:
                    c_idx = self.model_data['club_index'][club_id]
                    # Popularité = moyenne des notes sur tous les utilisateurs
                    popularity = float(np.mean(self.model_data['reconstructed'][:, c_idx]))
                    club_popularity[club_id] = popularity
                else:
                    club_popularity[club_id] = 0
            
            # Trier par popularité et exclure ceux déjà rejoints
            sorted_clubs = sorted(
                [(cid, score) for cid, score in club_popularity.items() if cid not in exclude_ids],
                key=lambda x: x[1], 
                reverse=True
            )
            
            return [cid for cid, _ in sorted_clubs[:top_n]]
        
        # Cas 2: Utilisateur connu - prédictions personnalisées
        predictions = []
        for club_id in all_club_ids:
            if club_id not in exclude_ids:
                rating = self.predict_rating(user_id, club_id)
                predictions.append((club_id, rating))
        
        # Trier par note prédite
        predictions.sort(key=lambda x: x[1], reverse=True)
        
        return [cid for cid, _ in predictions[:top_n]]