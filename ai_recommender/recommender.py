from sentence_transformers import SentenceTransformer
import numpy as np
from sqlalchemy import create_engine
import pandas as pd

# ===== GLOBAL STATE =====
model = None
books_df = None
book_embeddings = None


# -------------------------------------------------
# INITIALIZATION
# -------------------------------------------------
def init_recommender():
    global model, books_df, book_embeddings

    print("Loading AI model...")
    model = SentenceTransformer('all-MiniLM-L6-v2')

    print("Connecting database...")
    engine = create_engine("mysql+pymysql://root:@127.0.0.1:3306/libreryhub")

    try:
        query = """
        SELECT 
            b.id,
            b.title,
            b.description,
            c.name as category,
            CONCAT(a.firstname, ' ', a.lastname) as author,
            b.language,
            b.cover_image
        FROM book b
        JOIN category c ON b.category_id = c.id_cat
        JOIN author a ON b.author_id = a.id
        """

        books_df = pd.read_sql(query, engine)

        # ---------- CLEAN DATASET ----------
        books_df = books_df.fillna('')

        books_df["content"] = (
            books_df["title"].astype(str) + " " +
            books_df["description"].astype(str) + " " +
            books_df["category"].astype(str) + " " +
            books_df["author"].astype(str) + " " +
            books_df["language"].astype(str)
        )

        # remove useless books (important)
        books_df["content"] = books_df["content"].str.strip()
        books_df = books_df[books_df["content"].str.len() > 15]

        if books_df.empty:
            print("WARNING: No valid books found")
            book_embeddings = None
            return

        # ---------- VECTORIZE ----------
        print("Encoding books...")
        book_embeddings = model.encode(
            books_df["content"].tolist(),
            normalize_embeddings=True,
            show_progress_bar=False
        )

        # security: remove NaN
        book_embeddings = np.nan_to_num(book_embeddings)

        print("AI recommender READY")

    except Exception as e:
        print("DATABASE ERROR:", e)
        books_df = pd.DataFrame()
        book_embeddings = None


# -------------------------------------------------
# RECOMMENDATION
# -------------------------------------------------
def recommend(prompt, top_k=5):

    global model, books_df, book_embeddings

    # safety checks
    if model is None or book_embeddings is None or books_df.empty:
        return []

    # protect prompt
    prompt = str(prompt).strip()
    if len(prompt) < 3:
        return []

    try:
        # encode query
        query_embedding = model.encode(prompt, normalize_embeddings=True)

        # cosine similarity
        similarities = np.dot(book_embeddings, query_embedding)

        # remove NaN/inf
        similarities = np.nan_to_num(similarities, nan=-1.0, posinf=-1.0, neginf=-1.0)

        # rank
        scores = list(zip(range(len(similarities)), similarities))
        scores.sort(key=lambda x: x[1], reverse=True)

        # semantic threshold (IMPORTANT)
        THRESHOLD = 0.35

        filtered = [i for i in scores if i[1] >= THRESHOLD][:top_k]

        if not filtered:
            return []

        response = []

        for idx, sim in filtered:
            row = books_df.iloc[idx]

            response.append({
                "id": int(row["id"]),
                "title": str(row["title"]),
                "author": str(row["author"]),
                "category": str(row["category"]),
                "cover_image": str(row["cover_image"]) if row["cover_image"] else "",
                "confidence": int(sim * 100)
            })

        return response

    except Exception as e:
        print("RECOMMEND ERROR:", e)
        return []