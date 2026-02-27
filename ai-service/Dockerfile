# Utiliser l'image officielle Python slim
FROM python:3.11-slim

# Variables d'environnement pour Python
ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PYTHONFAULTHANDLER=1

# Installer les dépendances système nécessaires
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Créer un utilisateur non-root pour la sécurité
RUN addgroup --system --gid 1001 appuser && \
    adduser --system --uid 1001 --gid 1001 appuser

# Définir le répertoire de travail
WORKDIR /app

# Copier les fichiers de dépendances en premier (pour le cache Docker)
COPY requirements.txt .

# Installer les dépendances Python
RUN pip install --no-cache-dir --upgrade pip && \
    pip install --no-cache-dir -r requirements.txt

# Copier le reste du code
COPY --chown=appuser:appuser . .

# Créer les dossiers nécessaires avec les bonnes permissions
RUN mkdir -p /app/models /app/data && \
    chown -R appuser:appuser /app/models /app/data

# Passer à l'utilisateur non-root
USER appuser

# Exposer le port
EXPOSE 8001

# Commande pour lancer l'application
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8001", "--workers", "2"]