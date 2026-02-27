"""
Script d'entraînement du modèle de recommandation SVD
"""
import pandas as pd
import numpy as np
import joblib
import os
from sklearn.decomposition import TruncatedSVD
from sklearn.metrics import mean_squared_error
from sklearn.model_selection import train_test_split

DATA_PATH = "data/interactions.csv"
MODEL_PATH = "models/svd_model.pkl"

def train():
    print("=" * 50)
    print("🔧 ENTRAÎNEMENT DU MODÈLE DE RECOMMANDATION")
    print("=" * 50)
    
    # Vérifier que les données existent
    if not os.path.exists(DATA_PATH):
        print(f"❌ ERREUR: Fichier non trouvé: {DATA_PATH}")
        print("   Exportez d'abord les données depuis Symfony:")
        print("   php bin/console app:export-interactions")
        return
    
    # Charger les données
    print("\n📥 Chargement des interactions...")
    df = pd.read_csv(DATA_PATH)
    print(f"   ✅ {len(df)} interactions chargées")
    print(f"   ✅ {df['user_id'].nunique()} utilisateurs uniques")
    print(f"   ✅ {df['club_id'].nunique()} clubs uniques")
    
    # Séparer en train (80%) et test (20%)
    print("\n🔀 Séparation train/test...")
    train_data, test_data = train_test_split(df, test_size=0.2, random_state=42)
    print(f"   ✅ Train: {len(train_data)} interactions")
    print(f"   ✅ Test: {len(test_data)} interactions")
    
    # Créer la matrice utilisateur-club avec TRAIN uniquement
    print("\n⚙️ Création de la matrice utilisateur-club (train)...")
    train_matrix = train_data.pivot_table(
        index='user_id', 
        columns='club_id', 
        values='rating',
        fill_value=0
    )
    print(f"   ✅ Matrice: {train_matrix.shape[0]} utilisateurs × {train_matrix.shape[1]} clubs")
    
    # Appliquer SVD avec n_factors adapté
    print("\n🧠 Entraînement du modèle SVD...")
    n_factors = min(20, train_matrix.shape[1] - 1)  # ← CORRECTION IMPORTANTE
    print(f"   Utilisation de {n_factors} facteurs")
    
    svd = TruncatedSVD(n_components=n_factors, random_state=42)
    user_matrix = svd.fit_transform(train_matrix)
    club_matrix = svd.components_.T
    
    # Reconstruire la matrice
    reconstructed = np.dot(user_matrix, club_matrix.T)
    
    # Calculer l'erreur sur les données TEST
    print("\n📊 Calcul des performances sur TEST...")
    predictions = []
    actuals = []
    user_index = {uid: i for i, uid in enumerate(train_matrix.index)}
    club_index = {cid: i for i, cid in enumerate(train_matrix.columns)}
    
    for _, row in test_data.iterrows():
        if row['user_id'] in user_index and row['club_id'] in club_index:
            u_idx = user_index[row['user_id']]
            c_idx = club_index[row['club_id']]
            predictions.append(reconstructed[u_idx, c_idx])
            actuals.append(row['rating'])
    
    if predictions:
        rmse = np.sqrt(mean_squared_error(actuals, predictions))
        print(f"   ✅ RMSE sur TEST: {rmse:.4f}")
    else:
        print("   ⚠️ Pas assez de données pour évaluer sur test")
    
    # Sauvegarder le modèle
    print("\n💾 Sauvegarde du modèle...")
    os.makedirs("models", exist_ok=True)
    
    model_data = {
        'svd': svd,
        'user_matrix': user_matrix,
        'club_matrix': club_matrix,
        'reconstructed': reconstructed,
        'user_index': user_index,
        'club_index': club_index,
        'user_list': list(train_matrix.index),
        'club_list': list(train_matrix.columns)
    }
    
    joblib.dump(model_data, MODEL_PATH)
    print(f"   ✅ Modèle sauvegardé: {MODEL_PATH}")
    
    print("\n" + "=" * 50)
    print("✅ ENTRAÎNEMENT TERMINÉ AVEC SUCCÈS")
    print("=" * 50)

if __name__ == "__main__":
    train()